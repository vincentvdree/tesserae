<?php

declare(strict_types=1);

namespace Tesserae;

use Tesserae\Blocks\Availability;
use Tesserae\Blocks\BlockRegistry;
use Tesserae\Blocks\Renderer;
use Tesserae\Cli\Commands;
use Tesserae\Editor\Assets;
use Tesserae\Editor\EditSession;
use Tesserae\Editor\FormRenderer;
use Tesserae\Options\FormRenderer as OptionsFormRenderer;
use Tesserae\Options\OptionsRegistry;
use Tesserae\Options\OptionsStore;
use Tesserae\Rest\OptionsRestController;
use Tesserae\Rest\RestController;
use Tesserae\Storage\DocumentStore;
use Tesserae\Storage\Search;

/**
 * Wires Tesserae together. There is no wp-admin screen: everything Tesserae
 * knows — blocks, options pages, field definitions — comes from files in the
 * theme. Options pages are the one place actual values are read from and
 * written to the options table rather than post meta, since that data is not
 * bound to a single page; they are still edited from the front end, not a
 * settings screen.
 */
final class Plugin
{
    public const VERSION = '0.1.0';

    public readonly BlockRegistry $blocks;
    public readonly DocumentStore $documents;
    public readonly EditSession $session;
    public readonly Availability $availability;
    public readonly Renderer $renderer;
    public readonly FormRenderer $form;
    public readonly OptionsRegistry $optionPages;
    public readonly OptionsStore $optionsStore;
    public readonly OptionsFormRenderer $optionsForm;
    public readonly Assets $assets;
    private static ?self $instance = null;

    private bool $booted = false;

    private function __construct()
    {
        $this->blocks = new BlockRegistry();
        $this->documents = new DocumentStore($this->blocks);
        $this->session = new EditSession();
        $this->availability = new Availability($this->blocks);
        $this->renderer = new Renderer($this->blocks, $this->documents, $this->session);
        $this->form = new FormRenderer();
        $this->optionPages = new OptionsRegistry();
        $this->optionsStore = new OptionsStore($this->optionPages);
        $this->optionsForm = new OptionsFormRenderer();
        $this->assets = new Assets($this->blocks, $this->documents, $this->session, $this->availability, $this->optionPages, $this->optionsStore, $this->optionsForm);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Wires everything up. Called from the main plugin file on `plugins_loaded`,
     * which passes `plugins_url('', __FILE__)` as the base URL for the assets
     * this plugin serves (see {@see Assets::setUrlBase()}).
     */
    public function boot(string $pluginUrl): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $this->assets->setUrlBase($pluginUrl);

        add_action('after_setup_theme', [$this, 'registerSources'], 5);
        add_action('init', [$this, 'onInit']);
        add_action('rest_api_init', static function (): void {
            new RestController(self::instance())->register();
            new OptionsRestController(self::instance())->register();
        });

        $this->assets->register();
        new Search()->register();

        add_filter('use_block_editor_for_post_type', [$this, 'maybeDisableBlockEditor'], 100, 2);
        add_action('add_meta_boxes', [$this, 'registerEditLink']);
        add_action('template_redirect', [$this, 'guardEditMode']);

        if (\defined('WP_CLI') && WP_CLI) {
            Commands::register($this);
        }
    }

    /**
     * The active theme (and its parent) are block sources by convention.
     */
    public function registerSources(): void
    {
        $sources = [
            get_template_directory().'/blocks' => get_template_directory_uri().'/blocks',
        ];

        if (get_stylesheet_directory() !== get_template_directory()) {
            $sources[get_stylesheet_directory().'/blocks'] = get_stylesheet_directory_uri().'/blocks';
        }

        /**
         * Filter the directories scanned for blocks, as `path => url`.
         *
         * @param array<string, string> $sources
         */
        $sources = apply_filters('tesserae/block_sources', $sources);

        foreach ($sources as $path => $url) {
            $this->blocks->addSource($path, $url);
        }

        $optionSources = [get_template_directory().'/option-pages'];

        if (get_stylesheet_directory() !== get_template_directory()) {
            $optionSources[] = get_stylesheet_directory().'/option-pages';
        }

        /**
         * Filter the directories scanned for options pages.
         *
         * @param list<string> $optionSources
         */
        $optionSources = apply_filters('tesserae/option_page_sources', $optionSources);

        foreach ($optionSources as $path) {
            $this->optionPages->addSource($path);
        }
    }

    public function onInit(): void
    {
        // No bundled translations yet, so nothing to load here. If a
        // `languages/` directory shows up, load it with
        // `load_plugin_textdomain('tesserae', false, dirname(plugin_basename(__FILE__)).'/languages')`.

        foreach ($this->postTypes() as $postType) {
            register_post_meta($postType, DocumentStore::META_KEY, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static fn (bool $allowed, string $meta, int $postId): bool => current_user_can('edit_post', $postId),
            ]);
        }

        if (apply_filters('tesserae/remove_editor_support', true)) {
            foreach ($this->postTypes() as $postType) {
                remove_post_type_support($postType, 'editor');
            }
        }

        if (apply_filters('tesserae/auto_append_content', false)) {
            add_filter('the_content', [$this, 'appendBlocks'], 9);
        }
    }

    /**
     * @return list<string>
     */
    public function postTypes(): array
    {
        /** @var list<string> $types */
        $types = apply_filters('tesserae/post_types', ['page', 'post']);

        return array_values(array_filter($types, 'is_string'));
    }

    public function appendBlocks(string $content): string
    {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $postId = $this->session->postId();

        if (!$this->documents->has($postId) && !$this->session->isEditing()) {
            return $content;
        }

        return $content.$this->renderer->renderPost($postId);
    }

    /**
     * Tesserae replaces Gutenberg rather than sitting next to it: for post types
     * it manages, the block editor is switched off.
     */
    public function maybeDisableBlockEditor(bool $use, string $postType): bool
    {
        if (!\in_array($postType, $this->postTypes(), true)) {
            return $use;
        }

        return apply_filters('tesserae/disable_block_editor', true, $postType) ? false : $use;
    }

    /**
     * The only thing Tesserae adds to wp-admin: a link back to the page.
     */
    public function registerEditLink(): void
    {
        if (!apply_filters('tesserae/admin_link', true)) {
            return;
        }

        foreach ($this->postTypes() as $postType) {
            add_meta_box(
                'tesserae-edit-link',
                __('Tesserae', 'tesserae'),
                function (\WP_Post $post): void {
                    $url = $this->session->editUrl($post->ID);
                    $count = $this->documents->load($post->ID)->count();

                    printf(
                        '<p>%s</p><p><a class="button button-primary" href="%s">%s</a></p>',
                        esc_html(\sprintf(
                            // translators: %d: number of blocks.
                            _n('%d block on this page.', '%d blocks on this page.', $count, 'tesserae'),
                            $count,
                        )),
                        esc_url('publish' === $post->post_status ? $url : add_query_arg('preview', 'true', $url)),
                        esc_html__('Edit on the front end', 'tesserae'),
                    );
                },
                $postType,
                'side',
                'high',
            );
        }
    }

    /**
     * Nobody should end up on `?tesserae=edit` without the capability for it.
     */
    public function guardEditMode(): void
    {
        if ('edit' !== $this->session->mode() || is_admin()) {
            return;
        }

        $postId = $this->session->postId();

        if ($this->session->canEdit($postId)) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url((string) get_permalink($postId)));

            exit;
        }

        wp_safe_redirect($this->session->viewUrl($postId));

        exit;
    }
}
