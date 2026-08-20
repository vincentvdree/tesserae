<?php

declare(strict_types=1);

namespace Tesserae\Blocks;

use Tesserae\Support\YamlException;

/**
 * Finds block directories and keeps the parsed definitions around.
 *
 * Sources are registered as `path => url` pairs. The active theme (and its
 * parent) are registered automatically, so dropping a folder into
 * `blocks/my_block/` is genuinely all it takes.
 */
final class BlockRegistry
{
    /** @var array<string, string> */
    private array $sources = [];

    /** @var null|array<string, BlockDefinition> */
    private ?array $blocks = null;

    /** @var list<string> */
    private array $errors = [];

    public function addSource(string $path, string $url): void
    {
        $path = rtrim($path, '/');

        if ('' === $path || isset($this->sources[$path])) {
            return;
        }

        $this->sources[$path] = rtrim($url, '/');
        $this->blocks = null;
    }

    /**
     * @return array<string, string>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    /**
     * @return array<string, BlockDefinition>
     */
    public function all(): array
    {
        if (null !== $this->blocks) {
            return $this->blocks;
        }

        $blocks = [];
        $this->errors = [];

        foreach ($this->sources as $path => $url) {
            if (!is_dir($path)) {
                continue;
            }

            $directories = glob($path.'/*', GLOB_ONLYDIR) ?: [];
            sort($directories);

            foreach ($directories as $directory) {
                try {
                    $block = BlockDefinition::fromDirectory($directory, $url.'/'.basename($directory));
                } catch (YamlException $e) {
                    $this->errors[] = $e->getMessage();

                    continue;
                }

                if (null === $block) {
                    continue;
                }

                // Later sources win, which lets a child theme override a block.
                $blocks[$block->type] = $block;
            }
        }

        /**
         * Filter the registered blocks.
         *
         * @param array<string, BlockDefinition> $blocks
         * @param self                           $registry
         */
        $filtered = apply_filters('tesserae/blocks', $blocks, $this);

        return $this->blocks = $filtered;
    }

    public function get(string $type): ?BlockDefinition
    {
        return $this->all()[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return null !== $this->get($type);
    }

    /**
     * Config files that failed to parse, so the editor can say so out loud
     * instead of silently dropping a block.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        $this->all();

        return $this->errors;
    }

    /**
     * @return array<string, list<BlockDefinition>>
     */
    public function byCategory(): array
    {
        $categories = [];

        foreach ($this->all() as $block) {
            $categories[$block->category()][] = $block;
        }

        return $categories;
    }

    public function flush(): void
    {
        $this->blocks = null;
    }
}
