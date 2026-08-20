<?php

declare(strict_types=1);

namespace Tesserae\Blocks;

use Tesserae\Editor\EditSession;
use Tesserae\Storage\BlockInstance;
use Tesserae\Storage\Document;
use Tesserae\Storage\DocumentStore;
use Tesserae\Support\Arr;

/**
 * Turns a document into HTML.
 *
 * Each block is rendered from its own PHP template. Which template depends on
 * the request: `<name>_edit.php` while editing, `<name>_robot.php` for crawlers
 * and other machine readers, `<name>.php` otherwise.
 */
final class Renderer
{
    /** @var list<BlockContext> */
    private array $stack = [];

    /** @var array<string, true> */
    private array $rendered = [];

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly DocumentStore $store,
        private readonly EditSession $session,
    ) {}

    public function currentContext(): ?BlockContext
    {
        return $this->stack[\count($this->stack) - 1] ?? null;
    }

    /**
     * Block types rendered during this request, so assets can follow usage.
     *
     * @return list<string>
     */
    public function renderedTypes(): array
    {
        return array_keys($this->rendered);
    }

    public function renderPost(?int $postId = null): string
    {
        $postId ??= $this->session->postId();

        if ($postId <= 0) {
            return '';
        }

        return $this->renderDocument($this->store->load($postId), $postId);
    }

    public function renderDocument(Document $document, int $postId, ?bool $editing = null): string
    {
        $editing ??= $this->session->isEditing() && $this->session->canEdit($postId);
        $blocks = $document->blocks();
        $html = '';

        foreach ($blocks as $index => $instance) {
            $html .= $this->renderInstance($instance, $index, \count($blocks), $postId, $editing);
        }

        if ('' === $html && $editing) {
            $html = '<div class="tsr-empty" data-tesserae-empty>'
                .'<p>'.esc_html__('This page has no blocks yet.', 'tesserae').'</p>'
                .'<button type="button" class="tsr-btn tsr-btn--primary" data-action="tesserae-editor#openPickerAtEnd">'
                .esc_html__('Add your first block', 'tesserae').'</button></div>';
        }

        return \sprintf(
            '<div class="tsr-canvas%s" data-tesserae-canvas data-tesserae-post="%d">%s</div>',
            $editing ? ' tsr-canvas--editing' : '',
            $postId,
            $html,
        );
    }

    public function renderInstance(BlockInstance $instance, int $index, int $total, int $postId, bool $editing): string
    {
        $definition = $this->registry->get($instance->type);

        if (null === $definition) {
            return $editing
                ? \sprintf(
                    '<div class="tsr-block tsr-block--missing" data-tesserae-id="%s" data-tesserae-type="%s"><p>%s</p></div>',
                    esc_attr($instance->id),
                    esc_attr($instance->type),
                    esc_html(\sprintf(
                        // translators: %s: block type.
                        __('Unknown block "%s" — its files are missing.', 'tesserae'),
                        $instance->type,
                    )),
                )
                : '';
        }

        if ($instance->isHidden() && !$editing) {
            return '';
        }

        $this->rendered[$definition->type] = true;

        $variant = $this->variantFor($definition, $editing);
        $template = $definition->templateFor($variant) ?? $definition->templateFor('');

        $context = new BlockContext(
            $definition,
            $instance,
            $definition->fields()->prepare($instance->values),
            $index,
            $total,
            $postId,
            $editing,
            $variant,
        );

        $inner = null === $template
            ? ($editing ? '<p class="tsr-block__warning">'.esc_html(\sprintf(
                // translators: %s: expected template file name.
                __('No template found — expected %s', 'tesserae'),
                $definition->type.'.php',
            )).'</p>' : '')
            : $this->renderTemplate($template, $context);

        /**
         * Filter a single block's inner HTML.
         *
         * @param string       $inner
         * @param BlockContext $context
         */
        $inner = apply_filters('tesserae/render_block', $inner, $context);

        if (!$editing && !$definition->supports('wrapper', true)) {
            return $inner;
        }

        return $this->wrap($inner, $context, $editing);
    }

    private function variantFor(BlockDefinition $definition, bool $editing): string
    {
        if ($editing && null !== $definition->templateFor('edit')) {
            return 'edit';
        }

        if (!$editing && $this->session->isRobot() && null !== $definition->templateFor('robot')) {
            return 'robot';
        }

        return '';
    }

    private function wrap(string $inner, BlockContext $context, bool $editing): string
    {
        $definition = $context->definition;
        $instance = $context->instance;

        $classes = ['tsr-block', 'tsr-block--'.str_replace('_', '-', $definition->type)];

        if ('' !== $instance->className()) {
            $classes[] = $instance->className();
        }

        if ($instance->isHidden()) {
            $classes[] = 'tsr-block--hidden';
        }

        $controllers = [];

        if (null !== $definition->controllerUrl()) {
            $controllers[] = $definition->controllerIdentifier();
        }

        if ($editing) {
            array_unshift($controllers, 'tesserae-block');
        }

        $attributes = [
            'class' => implode(' ', array_map('sanitize_html_class', $classes)),
            'data-tesserae-id' => $instance->id,
            'data-tesserae-type' => $definition->type,
        ];

        if ('' !== $instance->anchor()) {
            $attributes['id'] = $instance->anchor();
        }

        if ([] !== $controllers) {
            $attributes['data-controller'] = implode(' ', $controllers);

            // Fields declared under `controller: values:` become Stimulus values.
            foreach ($definition->controllerValues() as $name => $field) {
                $key = \sprintf('data-%s-%s-value', $definition->controllerIdentifier(), str_replace('_', '-', $name));
                $attributes[$key] = self::stringifyValue($context->field($field));
            }
        }

        if ($editing) {
            $attributes['data-tesserae-label'] = $definition->label();
            $attributes['data-tesserae-index'] = (string) $context->index;
        }

        /**
         * Filter the wrapper attributes of a rendered block.
         *
         * @param array<string, mixed> $attributes
         * @param BlockContext         $context
         */
        $attributes = apply_filters('tesserae/block_attributes', $attributes, $context);
        $rendered = '';

        foreach ($attributes as $key => $value) {
            if (null === $value || false === $value) {
                continue;
            }

            $rendered .= \sprintf(' %s="%s"', esc_attr($key), esc_attr(Arr::toString($value)));
        }

        return \sprintf('<%1$s%2$s>%3$s</%1$s>', $this->tagFor($definition), $rendered, $inner);
    }

    /**
     * Stimulus reads values as strings, so booleans and arrays are encoded the
     * way its value API expects them back.
     */
    private static function stringifyValue(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (null === $value) {
            return '';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return (string) wp_json_encode($value);
    }

    private function tagFor(BlockDefinition $definition): string
    {
        $tag = Arr::toString($definition->config()['tag'] ?? 'section', 'section');
        $tag = preg_replace('/[^a-z0-9-]/', '', strtolower($tag)) ?? 'section';

        return '' !== $tag ? $tag : 'section';
    }

    /**
     * Templates are plain PHP includes. They receive `$block` (the context),
     * `$fields` (prepared values) and `$post`.
     */
    private function renderTemplate(string $template, BlockContext $context): string
    {
        $this->stack[] = $context;

        $render = static function (string $__template, BlockContext $block): string {
            $fields = $block->fields;
            $post = get_post($block->postId);
            $editing = $block->editing;

            ob_start();

            try {
                include $__template;
            } catch (\Throwable $e) {
                ob_end_clean();

                if (\defined('WP_DEBUG') && WP_DEBUG) {
                    throw $e;
                }

                error_log(\sprintf('[Tesserae] %s in %s: %s', $block->type(), $__template, $e->getMessage()));

                return '';
            }

            return (string) ob_get_clean();
        };

        try {
            return $render($template, $context);
        } finally {
            array_pop($this->stack);
        }
    }
}
