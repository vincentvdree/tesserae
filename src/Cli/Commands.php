<?php

declare(strict_types=1);

namespace Tesserae\Cli;

use Tesserae\Plugin;

/**
 * `wp tesserae …` — the developer-facing half of a plugin with no admin screen.
 */
final class Commands
{
    public function __construct(private readonly Plugin $plugin) {}

    public static function register(Plugin $plugin): void
    {
        \WP_CLI::add_command('tesserae', new self($plugin));
    }

    /**
     * Lists every block Tesserae can find, with the files it resolved.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table, json, csv or yaml.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function blocks(array $args, array $assoc): void
    {
        do_action('after_setup_theme');

        $rows = [];

        foreach ($this->plugin->blocks->all() as $block) {
            $rows[] = [
                'type' => $block->type,
                'label' => $block->label(),
                'category' => $block->category(),
                'fields' => \count($block->fields()),
                'template' => null !== $block->templateFor('') ? '✓' : '—',
                'edit' => null !== $block->templateFor('edit') ? '✓' : '—',
                'robot' => null !== $block->templateFor('robot') ? '✓' : '—',
                'script' => null !== $block->scriptUrl() ? '✓' : '—',
                'style' => null !== $block->styleUrl() ? '✓' : '—',
            ];
        }

        foreach ($this->plugin->blocks->errors() as $error) {
            \WP_CLI::warning($error);
        }

        if ([] === $rows) {
            \WP_CLI::warning('No blocks found. Looked in: '.implode(', ', array_keys($this->plugin->blocks->sources())));

            return;
        }

        \WP_CLI\Utils\format_items(
            $assoc['format'] ?? 'table',
            $rows,
            ['type', 'label', 'category', 'fields', 'template', 'edit', 'robot', 'script', 'style'],
        );
    }

    /**
     * Prints the block document of a post.
     *
     * ## OPTIONS
     *
     * <post_id>
     * : The post to inspect.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function document(array $args, array $assoc): void
    {
        do_action('after_setup_theme');

        $document = $this->plugin->documents->load((int) ($args[0] ?? 0));

        \WP_CLI::line((string) wp_json_encode($document->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Scaffolds a new block folder in the active theme.
     *
     * ## OPTIONS
     *
     * <name>
     * : Block name, snake_case.
     *
     * [--label=<label>]
     * : Human readable label.
     *
     * [--script]
     * : Also create a JS file for this block.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function scaffold(array $args, array $assoc): void
    {
        $name = sanitize_key($args[0] ?? '');

        if ('' === $name) {
            \WP_CLI::error('A block name is required.');
        }

        $directory = get_stylesheet_directory().'/blocks/'.$name;

        if (is_dir($directory)) {
            \WP_CLI::error(\sprintf('%s already exists.', $directory));
        }

        if (!wp_mkdir_p($directory)) {
            \WP_CLI::error(\sprintf('Could not create %s.', $directory));
        }

        $label = $assoc['label'] ?? ucwords(str_replace('_', ' ', $name));

        file_put_contents($directory.'/'.$name.'.yaml', <<<YAML
            label: {$label}
            description: A new block.
            icon: ◻
            category: content

            supports:
              anchor: true
              className: true

            fields:
              - name: title
                type: text
                label: Title
                default: {$label}

            YAML);

        file_put_contents($directory.'/'.$name.'.php', <<<'PHP'
            <?php
            /** @var Tesserae\Blocks\BlockContext $block */
            ?>
            <div class="<?php echo esc_attr($block->type()); ?>">
                <h2 <?php tesserae_editable('title'); ?>><?php tesserae_the_field('title'); ?></h2>
            </div>

            PHP);

        if (isset($assoc['script'])) {
            file_put_contents($directory.'/'.$name.'.js', <<<'JS'
                // Loaded automatically whenever this block is on the page.
                //
                // Want a Stimulus controller? Register it yourself:
                //
                //   import { Controller } from '@hotwired/stimulus'
                //
                //   window.Tesserae.application.register('my-identifier', class extends Controller {
                //     connect() {
                //       // …
                //     }
                //   })

                JS);
        }

        \WP_CLI::success(\sprintf('Created %s', $directory));
    }

    /**
     * Lists every options page Tesserae can find.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table, json, csv or yaml.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function option_pages(array $args, array $assoc): void
    {
        do_action('after_setup_theme');

        $rows = [];

        foreach ($this->plugin->optionPages->all() as $page) {
            $rows[] = [
                'slug' => $page->slug,
                'label' => $page->label(),
                'capability' => $page->capability(),
                'fields' => \count($page->fields()),
            ];
        }

        foreach ($this->plugin->optionPages->errors() as $error) {
            \WP_CLI::warning($error);
        }

        if ([] === $rows) {
            \WP_CLI::warning('No options pages found. Looked in: '.implode(', ', $this->plugin->optionPages->sources()));

            return;
        }

        \WP_CLI\Utils\format_items($assoc['format'] ?? 'table', $rows, ['slug', 'label', 'capability', 'fields']);
    }

    /**
     * Prints the stored values of an options page.
     *
     * ## OPTIONS
     *
     * <page>
     * : The options page slug.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function options(array $args, array $assoc): void
    {
        do_action('after_setup_theme');

        $slug = sanitize_key($args[0] ?? '');

        if (!$this->plugin->optionPages->has($slug)) {
            \WP_CLI::error(\sprintf('Unknown options page "%s".', $slug));
        }

        \WP_CLI::line((string) wp_json_encode($this->plugin->optionsStore->raw($slug), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Scaffolds a new options page in the active theme.
     *
     * ## OPTIONS
     *
     * <name>
     * : Options page slug, snake_case.
     *
     * [--label=<label>]
     * : Human readable label.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function scaffold_options(array $args, array $assoc): void
    {
        $name = sanitize_key($args[0] ?? '');

        if ('' === $name) {
            \WP_CLI::error('An options page slug is required.');
        }

        $directory = get_stylesheet_directory().'/option-pages';
        $file = $directory.'/'.$name.'.yaml';

        if (is_file($file)) {
            \WP_CLI::error(\sprintf('%s already exists.', $file));
        }

        if (!wp_mkdir_p($directory)) {
            \WP_CLI::error(\sprintf('Could not create %s.', $directory));
        }

        $label = $assoc['label'] ?? ucwords(str_replace('_', ' ', $name));

        file_put_contents($file, <<<YAML
            label: {$label}
            description: A new options page.
            capability: manage_options

            fields:
              - name: title
                type: text
                label: Title

            YAML);

        \WP_CLI::success(\sprintf('Created %s', $file));
    }
}
