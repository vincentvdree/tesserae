<?php

declare(strict_types=1);

namespace Tesserae\Editor;

use Tesserae\Blocks\Availability;
use Tesserae\Blocks\BlockRegistry;
use Tesserae\Plugin;
use Tesserae\Storage\DocumentStore;

/**
 * Loads the front end runtime and, when editing, the editor itself.
 *
 * Everything ships as native ES modules through WordPress' script module API,
 * so there is no build step: what is in the repository is what the browser runs.
 */
final class Assets
{
    private bool $editorPrinted = false;
    private string $urlBase = '';

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly DocumentStore $documents,
        private readonly EditSession $session,
        private readonly Availability $availability,
    ) {}

    /**
     * The URL this plugin's own directory (the one containing `assets/`) is
     * served from, i.e. `plugins_url('', __FILE__)` computed by the main
     * plugin file and passed through {@see Plugin::boot()}.
     */
    public function setUrlBase(string $url): void
    {
        $this->urlBase = rtrim($url, '/');
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer', [$this, 'printEditor'], 5);
        add_action('admin_bar_menu', [$this, 'adminBar'], 80);
    }

    public function enqueue(): void
    {
        if (!is_singular()) {
            return;
        }

        $postId = $this->session->postId();
        $editing = $this->session->isEditing();
        $document = $this->documents->load($postId);

        if ($document->isEmpty() && !$editing) {
            return;
        }

        if (!\function_exists('wp_register_script_module')) {
            _doing_it_wrong(__METHOD__, 'Tesserae needs WordPress 6.5 or newer for script modules.', '0.1.0');

            return;
        }

        wp_register_script_module('@hotwired/stimulus', $this->url('assets/js/vendor/stimulus.js'), [], '3.2.1');
        wp_register_script_module('tesserae-runtime', $this->url('assets/js/runtime.js'), [['id' => '@hotwired/stimulus']], Plugin::VERSION);
        wp_enqueue_script_module('tesserae-runtime');

        // Block styles and scripts follow usage: only what is on the page is loaded.
        $types = $editing ? array_keys($this->registry->all()) : array_keys($document->counts());

        foreach ($types as $type) {
            $block = $this->registry->get($type);

            if (null === $block) {
                continue;
            }

            if (null !== $block->styleUrl()) {
                wp_enqueue_style('tesserae-block-'.$type, $block->styleUrl(), [], $block->styleVersion());
            }

            if (null !== $block->scriptUrl()) {
                $handle = 'tesserae-block-'.$type;

                wp_register_script_module($handle, $block->scriptUrl(), [['id' => 'tesserae-runtime']], false);
                wp_enqueue_script_module($handle);
            }
        }

        if (!$editing) {
            return;
        }

        wp_enqueue_style('tesserae-editor', $this->url('assets/css/editor.css'), [], Plugin::VERSION);
    }

    /**
     * The configuration blob both runtimes read, plus the editor's chrome.
     */
    public function printEditor(): void
    {
        if ($this->editorPrinted || !is_singular()) {
            return;
        }

        $postId = $this->session->postId();
        $editing = $this->session->isEditing();
        $document = $this->documents->load($postId);

        if ($document->isEmpty() && !$editing) {
            return;
        }

        $this->editorPrinted = true;

        $config = [
            'postId' => $postId,
            'editing' => $editing,
        ];

        if ($editing) {
            $catalogue = array_map(
                static function (array $entry): array {
                    unset($entry['fields'], $entry['tabs']);

                    return $entry;
                },
                $this->availability->catalogue($postId, $document),
            );

            $config += [
                'rest' => rest_url('tesserae/v1/'),
                'restRoot' => rest_url(),
                'nonce' => wp_create_nonce('wp_rest'),
                'document' => $document->toArray(),
                'catalogue' => $catalogue,
                'viewUrl' => $this->session->viewUrl($postId),
                'errors' => $this->registry->errors(),
                'strings' => $this->strings(),
            ];
        }

        printf(
            '<script type="application/json" id="tesserae-config">%s</script>',
            wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        );

        if ($editing) {
            $this->printEditorRoot();
        }
    }

    public function adminBar(\WP_Admin_Bar $bar): void
    {
        if (is_admin() || !is_singular()) {
            return;
        }

        $postId = $this->session->postId();

        if (!$this->session->canEdit($postId)) {
            return;
        }

        $editing = $this->session->isEditing();

        $bar->add_node([
            'id' => 'tesserae-edit',
            'title' => $editing ? __('Exit Tesserae', 'tesserae') : __('Edit with Tesserae', 'tesserae'),
            'href' => $editing ? $this->session->viewUrl($postId) : $this->session->editUrl($postId),
            'meta' => ['class' => 'tesserae-admin-bar'],
        ]);
    }

    private function url(string $path = ''): string
    {
        return $this->urlBase.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function strings(): array
    {
        return [
            'save' => __('Save', 'tesserae'),
            'saving' => __('Saving…', 'tesserae'),
            'saved' => __('Saved', 'tesserae'),
            'saveFailed' => __('Could not save', 'tesserae'),
            'unsaved' => __('Unsaved changes', 'tesserae'),
            'exit' => __('Exit editor', 'tesserae'),
            'addBlock' => __('Add block', 'tesserae'),
            'editBlock' => __('Edit', 'tesserae'),
            'duplicate' => __('Duplicate', 'tesserae'),
            'remove' => __('Remove', 'tesserae'),
            'moveUp' => __('Move up', 'tesserae'),
            'moveDown' => __('Move down', 'tesserae'),
            'confirmRemove' => __('Remove this block?', 'tesserae'),
            'searchBlocks' => __('Search blocks…', 'tesserae'),
            'noResults' => __('Nothing found', 'tesserae'),
            'done' => __('Done', 'tesserae'),
            'undo' => __('Undo', 'tesserae'),
            'redo' => __('Redo', 'tesserae'),
            'leaveWarning' => __('You have unsaved changes.', 'tesserae'),
            'hiddenBlock' => __('Hidden on the live site', 'tesserae'),
            'loading' => __('Loading…', 'tesserae'),
            'blockSettings' => __('Block settings', 'tesserae'),
        ];
    }

    private function printEditorRoot(): void
    {
        $strings = $this->strings();

        ?>
        <div class="tsr-editor" data-controller="tesserae-editor" data-tesserae-editor-target="root">
            <div class="tsr-bar" role="toolbar" aria-label="<?php esc_attr_e('Tesserae editor', 'tesserae'); ?>">
                <span class="tsr-bar__brand" aria-hidden="true">◱</span>
                <span class="tsr-bar__title"><?php echo esc_html(get_the_title($this->session->postId())); ?></span>
                <span class="tsr-bar__status" data-tesserae-editor-target="status" aria-live="polite"></span>
                <span class="tsr-bar__spacer"></span>
                <button type="button" class="tsr-btn tsr-btn--ghost" data-action="tesserae-editor#undo" title="<?php echo esc_attr($strings['undo']); ?> (⌘Z)" data-tesserae-editor-target="undo" disabled>↶</button>
                <button type="button" class="tsr-btn tsr-btn--ghost" data-action="tesserae-editor#redo" title="<?php echo esc_attr($strings['redo']); ?> (⇧⌘Z)" data-tesserae-editor-target="redo" disabled>↷</button>
                <button type="button" class="tsr-btn" data-action="tesserae-editor#openPickerAtEnd"><?php echo esc_html($strings['addBlock']); ?></button>
                <button type="button" class="tsr-btn tsr-btn--primary" data-action="tesserae-editor#save" data-tesserae-editor-target="save"><?php echo esc_html($strings['save']); ?></button>
                <a class="tsr-btn tsr-btn--ghost" href="<?php echo esc_url($this->session->viewUrl($this->session->postId())); ?>" data-tesserae-editor-target="exit"><?php echo esc_html($strings['exit']); ?></a>
            </div>

            <dialog class="tsr-modal" data-tesserae-editor-target="modal" data-controller="tesserae-modal" data-action="close->tesserae-editor#modalClosed">
                <header class="tsr-modal__head">
                    <h2 class="tsr-modal__title" data-tesserae-modal-target="title"></h2>
                    <button type="button" class="tsr-modal__close" data-action="tesserae-modal#close" aria-label="<?php echo esc_attr($strings['done']); ?>">&times;</button>
                </header>
                <div class="tsr-modal__body" data-tesserae-modal-target="body" data-action="input->tesserae-modal#changed change->tesserae-modal#changed"></div>
                <footer class="tsr-modal__foot">
                    <span class="tsr-modal__hint" data-tesserae-modal-target="hint"></span>
                    <button type="button" class="tsr-btn tsr-btn--primary" data-action="tesserae-modal#close"><?php echo esc_html($strings['done']); ?></button>
                </footer>
            </dialog>

            <dialog class="tsr-picker" data-tesserae-editor-target="picker" data-controller="tesserae-picker">
                <header class="tsr-modal__head">
                    <h2 class="tsr-modal__title"><?php echo esc_html($strings['addBlock']); ?></h2>
                    <button type="button" class="tsr-modal__close" data-action="tesserae-picker#close" aria-label="<?php echo esc_attr($strings['done']); ?>">&times;</button>
                </header>
                <div class="tsr-picker__search">
                    <input type="search" class="tsr-input" placeholder="<?php echo esc_attr($strings['searchBlocks']); ?>" data-tesserae-picker-target="search" data-action="input->tesserae-picker#filter">
                </div>
                <div class="tsr-picker__list" data-tesserae-picker-target="list"></div>
            </dialog>

            <dialog class="tsr-media-modal" data-tesserae-editor-target="mediaModal" data-controller="tesserae-media-library">
                <header class="tsr-modal__head">
                    <h2 class="tsr-modal__title"><?php esc_html_e('Media', 'tesserae'); ?></h2>
                    <button type="button" class="tsr-modal__close" data-action="tesserae-media-library#close" aria-label="<?php echo esc_attr($strings['done']); ?>">&times;</button>
                </header>
                <div class="tsr-media-modal__bar">
                    <input type="search" class="tsr-input" placeholder="<?php esc_attr_e('Search media…', 'tesserae'); ?>" data-tesserae-media-library-target="search" data-action="input->tesserae-media-library#search">
                    <label class="tsr-btn tsr-btn--ghost">
                        <input type="file" multiple hidden data-tesserae-media-library-target="file" data-action="tesserae-media-library#upload">
                        <?php esc_html_e('Upload', 'tesserae'); ?>
                    </label>
                </div>
                <div class="tsr-media-modal__grid" data-tesserae-media-library-target="grid" data-action="scroll->tesserae-media-library#maybeLoadMore"></div>
                <footer class="tsr-modal__foot">
                    <span class="tsr-modal__hint" data-tesserae-media-library-target="hint"></span>
                    <button type="button" class="tsr-btn tsr-btn--primary" data-action="tesserae-media-library#confirm"><?php esc_html_e('Use selection', 'tesserae'); ?></button>
                </footer>
            </dialog>
        </div>
        <?php
    }
}
