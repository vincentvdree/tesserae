<?php

declare(strict_types=1);

namespace Tesserae\Options;

use Tesserae\Support\Arr;

/**
 * Reads and writes an options page's values.
 *
 * Each page is one autoloaded `wp_options` row — global data read on most
 * requests belongs in the options table, not scattered across post meta the
 * way a page's own blocks are.
 */
final class OptionsStore
{
    public const OPTION_PREFIX = 'tesserae_options_';

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(private readonly OptionsRegistry $registry) {}

    /**
     * Stored values, defaulted for any field never saved. Sanitised already
     * (it went through {@see self::save()} on the way in), so this is safe to
     * feed straight back into the form.
     *
     * @return array<string, mixed>
     */
    public function raw(string $slug): array
    {
        if (isset($this->cache[$slug])) {
            return $this->cache[$slug];
        }

        $page = $this->registry->get($slug);

        if (null === $page) {
            return [];
        }

        $stored = Arr::toMap(get_option(self::OPTION_PREFIX.$slug, []));
        $values = array_merge($page->fields()->defaults(), Arr::toMap($stored['values'] ?? $stored));

        /**
         * Filter the raw values loaded for an options page.
         *
         * @param array<string, mixed> $values
         * @param string               $slug
         */
        $filtered = apply_filters('tesserae/load_options', $values, $slug);

        return $this->cache[$slug] = $filtered;
    }

    /**
     * The shape a template gets: images resolved, posts and terms hydrated,
     * same as a block's `$fields`.
     *
     * @return array<string, mixed>
     */
    public function prepared(string $slug): array
    {
        $page = $this->registry->get($slug);

        return null === $page ? [] : $page->fields()->prepare($this->raw($slug));
    }

    /**
     * Sanitises every value through its field definition before saving, so a
     * hand-crafted REST payload cannot smuggle anything past the field types.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function save(string $slug, array $values): array
    {
        $page = $this->registry->get($slug);

        if (null === $page) {
            return [];
        }

        $clean = $page->fields()->sanitize($values);

        do_action('tesserae/options_before_save', $slug, $clean);

        update_option(self::OPTION_PREFIX.$slug, ['version' => 1, 'values' => $clean], true);

        unset($this->cache[$slug]);
        $this->cache[$slug] = $clean;

        do_action('tesserae/options_after_save', $slug, $clean);

        return $clean;
    }

    public function flush(?string $slug = null): void
    {
        if (null === $slug) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$slug]);
    }
}
