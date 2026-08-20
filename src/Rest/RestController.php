<?php

declare(strict_types=1);

namespace Tesserae\Rest;

use Tesserae\Plugin;
use Tesserae\Storage\BlockInstance;
use Tesserae\Storage\Document;
use Tesserae\Support\Arr;

/**
 * The editor's entire back end: a block catalogue, a form renderer, a preview
 * renderer and a save endpoint.
 */
final class RestController
{
    public const NAMESPACE = 'tesserae/v1';

    public function __construct(private readonly Plugin $plugin) {}

    public function register(): void
    {
        $postIdArg = [
            'post_id' => [
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route(self::NAMESPACE, '/blocks', [
            'methods' => 'GET, POST',
            'callback' => [$this, 'catalogue'],
            'permission_callback' => [$this, 'canEdit'],
            'args' => $postIdArg + [
                'index' => ['type' => 'integer', 'default' => -1],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/form', [
            'methods' => 'POST',
            'callback' => [$this, 'form'],
            'permission_callback' => [$this, 'canEdit'],
            'args' => $postIdArg,
        ]);

        register_rest_route(self::NAMESPACE, '/render', [
            'methods' => 'POST',
            'callback' => [$this, 'render'],
            'permission_callback' => [$this, 'canEdit'],
            'args' => $postIdArg,
        ]);

        register_rest_route(self::NAMESPACE, '/save', [
            'methods' => 'POST',
            'callback' => [$this, 'save'],
            'permission_callback' => [$this, 'canEdit'],
            'args' => $postIdArg,
        ]);
    }

    public function canEdit(\WP_REST_Request $request): bool|\WP_Error
    {
        $postId = Arr::toInt($request->get_param('post_id'), 0) ?? 0;
        $post = get_post($postId);

        if (!$post instanceof \WP_Post) {
            return new \WP_Error('tesserae_no_post', __('Unknown post.', 'tesserae'), ['status' => 404]);
        }

        if (!$this->plugin->session->isEnabledFor($post->post_type)) {
            return new \WP_Error('tesserae_disabled', __('Tesserae is not enabled for this post type.', 'tesserae'), ['status' => 400]);
        }

        if (!current_user_can('edit_post', $postId)) {
            return new \WP_Error('tesserae_forbidden', __('You cannot edit this page.', 'tesserae'), ['status' => 403]);
        }

        return true;
    }

    public function catalogue(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = Arr::toInt($request->get_param('post_id'), 0) ?? 0;
        $index = Arr::toInt($request->get_param('index'), 0) ?? 0;
        $document = $this->documentFromRequest($request) ?? $this->plugin->documents->load($postId);

        return new \WP_REST_Response([
            'blocks' => $this->plugin->availability->catalogue($postId, $document, $index),
            'errors' => $this->plugin->blocks->errors(),
        ]);
    }

    public function form(\WP_REST_Request $request): \WP_Error|\WP_REST_Response
    {
        $instance = $this->instanceFromRequest($request);

        if ($instance instanceof \WP_Error) {
            return $instance;
        }

        $definition = $this->plugin->blocks->get($instance->type);

        if (null === $definition) {
            return new \WP_Error('tesserae_unknown_block', __('Unknown block type.', 'tesserae'), ['status' => 404]);
        }

        return $this->withPostContext(Arr::toInt($request->get_param('post_id'), 0) ?? 0, fn (): \WP_REST_Response => new \WP_REST_Response([
            'html' => $this->plugin->form->render($definition, $instance),
            'label' => $definition->label(),
            'type' => $definition->type,
            'id' => $instance->id,
        ]));
    }

    public function render(\WP_REST_Request $request): \WP_Error|\WP_REST_Response
    {
        $postId = Arr::toInt($request->get_param('post_id'), 0) ?? 0;
        $document = $this->documentFromRequest($request);

        if (null !== $document) {
            return $this->withPostContext($postId, fn (): \WP_REST_Response => new \WP_REST_Response([
                'html' => $this->plugin->renderer->renderDocument($document, $postId, true),
            ]));
        }

        $instance = $this->instanceFromRequest($request);

        if ($instance instanceof \WP_Error) {
            return $instance;
        }

        if (!$this->plugin->blocks->has($instance->type)) {
            return new \WP_Error('tesserae_unknown_block', __('Unknown block type.', 'tesserae'), ['status' => 404]);
        }

        $definition = $this->plugin->blocks->get($instance->type);
        $values = null === $definition ? $instance->values : $definition->fields()->sanitize($instance->values);
        $clean = $instance->withValues($values);

        $index = Arr::toInt($request->get_param('index'), 0) ?? 0;
        $total = Arr::toInt($request->get_param('total'), 1) ?? 1;

        return $this->withPostContext($postId, fn (): \WP_REST_Response => new \WP_REST_Response([
            'html' => $this->plugin->renderer->renderInstance($clean, $index, max($total, $index + 1), $postId, true),
            'values' => $values,
            'id' => $clean->id,
        ]));
    }

    public function save(\WP_REST_Request $request): \WP_Error|\WP_REST_Response
    {
        $postId = Arr::toInt($request->get_param('post_id'), 0) ?? 0;
        $document = $this->documentFromRequest($request);

        if (null === $document) {
            return new \WP_Error('tesserae_no_blocks', __('No blocks in the request.', 'tesserae'), ['status' => 400]);
        }

        foreach ($document as $instance) {
            if (!$this->plugin->blocks->has($instance->type)) {
                return new \WP_Error(
                    'tesserae_unknown_block',
                    \sprintf(
                        // translators: %s: block type.
                        __('Unknown block type "%s".', 'tesserae'),
                        $instance->type,
                    ),
                    ['status' => 400],
                );
            }
        }

        $saved = $this->plugin->documents->save($postId, $document);

        // Keep "last modified" honest: the page did change.
        wp_update_post(['ID' => $postId, 'post_modified' => current_time('mysql'), 'post_modified_gmt' => current_time('mysql', true)]);
        clean_post_cache($postId);

        return new \WP_REST_Response([
            'document' => $saved->toArray(),
            'count' => $saved->count(),
            'modified' => get_post_modified_time('c', true, $postId),
        ]);
    }

    private function documentFromRequest(\WP_REST_Request $request): ?Document
    {
        $blocks = $request->get_param('blocks');

        if (!\is_array($blocks)) {
            return null;
        }

        return Document::fromArray(['blocks' => $blocks]);
    }

    private function instanceFromRequest(\WP_REST_Request $request): BlockInstance|\WP_Error
    {
        $block = $request->get_param('block');

        if (!\is_array($block)) {
            $block = [
                'id' => $request->get_param('id'),
                'type' => $request->get_param('type'),
                'values' => $request->get_param('values'),
                'settings' => $request->get_param('settings'),
            ];
        }

        $instance = BlockInstance::fromArray(Arr::toMap($block));

        if (null === $instance) {
            return new \WP_Error('tesserae_bad_block', __('A block type is required.', 'tesserae'), ['status' => 400]);
        }

        return $instance;
    }

    /**
     * Templates expect to run inside the loop, so give them one.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function withPostContext(int $postId, callable $callback): mixed
    {
        global $post, $wp_query;

        $previous = $post;
        $previousQueried = $wp_query instanceof \WP_Query ? $wp_query->queried_object : null;
        $target = get_post($postId);

        if ($target instanceof \WP_Post) {
            $post = $target;
            setup_postdata($target);

            if ($wp_query instanceof \WP_Query) {
                $wp_query->queried_object = $target;
                $wp_query->queried_object_id = $postId;
            }
        }

        try {
            return $callback();
        } finally {
            $post = $previous;
            wp_reset_postdata();

            if ($wp_query instanceof \WP_Query) {
                $wp_query->queried_object = $previousQueried;
            }
        }
    }
}
