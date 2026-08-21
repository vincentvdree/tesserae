<?php

declare(strict_types=1);

namespace Tesserae\Rest;

use Tesserae\Options\OptionsPage;
use Tesserae\Plugin;
use Tesserae\Support\Arr;

/**
 * The single route the "Site Options" dialog needs: saving a page. The
 * dialog's HTML is rendered up front, alongside the block editor's own
 * chrome, so there is no matching `/options/form` — only a value to persist.
 */
final class OptionsRestController
{
    public function __construct(private readonly Plugin $plugin) {}

    public function register(): void
    {
        register_rest_route(RestController::NAMESPACE, '/options/save', [
            'methods' => 'POST',
            'callback' => [$this, 'save'],
            'permission_callback' => [$this, 'canSave'],
            'args' => [
                'page' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    public function canSave(\WP_REST_Request $request): bool|\WP_Error
    {
        $page = $this->pageFromRequest($request);

        if ($page instanceof \WP_Error) {
            return $page;
        }

        if (!current_user_can($page->capability())) {
            return new \WP_Error('tesserae_forbidden', __('You cannot edit this options page.', 'tesserae'), ['status' => 403]);
        }

        return true;
    }

    public function save(\WP_REST_Request $request): \WP_Error|\WP_REST_Response
    {
        $page = $this->pageFromRequest($request);

        if ($page instanceof \WP_Error) {
            return $page;
        }

        $values = Arr::toMap($request->get_param('values'));
        $saved = $this->plugin->optionsStore->save($page->slug, $values);

        return new \WP_REST_Response(['values' => $saved]);
    }

    private function pageFromRequest(\WP_REST_Request $request): OptionsPage|\WP_Error
    {
        $slug = sanitize_key(Arr::toString($request->get_param('page')));
        $page = $this->plugin->optionPages->get($slug);

        if (null === $page) {
            return new \WP_Error('tesserae_unknown_options_page', __('Unknown options page.', 'tesserae'), ['status' => 404]);
        }

        return $page;
    }
}
