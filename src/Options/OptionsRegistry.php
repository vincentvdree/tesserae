<?php

declare(strict_types=1);

namespace Tesserae\Options;

use Tesserae\Support\YamlException;

/**
 * Finds option page files and keeps the parsed definitions around.
 *
 * Sources are plain directories, registered by the active theme (and its
 * parent) by default — dropping `option-pages/site.yaml` into a theme is all
 * it takes, the same way a `blocks/hero/` folder is all a block takes.
 */
final class OptionsRegistry
{
    /** @var list<string> */
    private array $sources = [];

    /** @var null|array<string, OptionsPage> */
    private ?array $pages = null;

    /** @var list<string> */
    private array $errors = [];

    public function addSource(string $path): void
    {
        $path = rtrim($path, '/');

        if ('' === $path || \in_array($path, $this->sources, true)) {
            return;
        }

        $this->sources[] = $path;
        $this->pages = null;
    }

    /**
     * @return list<string>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    /**
     * @return array<string, OptionsPage>
     */
    public function all(): array
    {
        if (null !== $this->pages) {
            return $this->pages;
        }

        $pages = [];
        $this->errors = [];

        foreach ($this->sources as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = array_merge(glob($path.'/*.yaml') ?: [], glob($path.'/*.yml') ?: []);
            sort($files);

            foreach ($files as $file) {
                try {
                    $page = OptionsPage::fromFile($file);
                } catch (YamlException $e) {
                    $this->errors[] = $e->getMessage();

                    continue;
                }

                if (null === $page) {
                    continue;
                }

                // Later sources win, which lets a child theme override a page.
                $pages[$page->slug] = $page;
            }
        }

        /**
         * Filter the registered options pages.
         *
         * @param array<string, OptionsPage> $pages
         * @param self                       $registry
         */
        $filtered = apply_filters('tesserae/option_pages', $pages, $this);

        return $this->pages = $filtered;
    }

    public function get(string $slug): ?OptionsPage
    {
        return $this->all()[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        return null !== $this->get($slug);
    }

    /**
     * Config files that failed to parse.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        $this->all();

        return $this->errors;
    }

    public function flush(): void
    {
        $this->pages = null;
    }
}
