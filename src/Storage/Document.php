<?php

declare(strict_types=1);

namespace Tesserae\Storage;

use Tesserae\Support\Arr;

/**
 * The ordered list of blocks that makes up a page.
 *
 * @implements \IteratorAggregate<int, BlockInstance>
 */
final class Document implements \IteratorAggregate, \Countable, \JsonSerializable
{
    public const VERSION = 1;

    /** @var list<BlockInstance> */
    private array $blocks;

    /**
     * @param list<BlockInstance> $blocks
     */
    public function __construct(array $blocks = [])
    {
        $this->blocks = $blocks;
    }

    public static function fromJson(mixed $json): self
    {
        if (\is_string($json)) {
            $json = json_decode($json, true);
        }

        return self::fromArray(Arr::toMap($json));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $blocks = [];
        $seen = [];

        foreach (Arr::wrap($data['blocks'] ?? []) as $raw) {
            if (!\is_array($raw)) {
                continue;
            }

            $block = BlockInstance::fromArray(Arr::toMap($raw));

            if (null === $block) {
                continue;
            }

            // Duplicate ids would break every DOM lookup in the editor.
            if (isset($seen[$block->id])) {
                $block = new BlockInstance(BlockInstance::generateId(), $block->type, $block->values, $block->settings);
            }

            $seen[$block->id] = true;
            $blocks[] = $block;
        }

        return new self($blocks);
    }

    /**
     * @return list<BlockInstance>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    public function get(string $id): ?BlockInstance
    {
        foreach ($this->blocks as $block) {
            if ($block->id === $id) {
                return $block;
            }
        }

        return null;
    }

    /**
     * @return array<string, int> block type => number of placements
     */
    public function counts(): array
    {
        $counts = [];

        foreach ($this->blocks as $block) {
            $counts[$block->type] = ($counts[$block->type] ?? 0) + 1;
        }

        return $counts;
    }

    public function isEmpty(): bool
    {
        return [] === $this->blocks;
    }

    public function count(): int
    {
        return \count($this->blocks);
    }

    /**
     * @return \ArrayIterator<int, BlockInstance>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->blocks);
    }

    /**
     * @return array{version: int, blocks: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'blocks' => array_map(static fn (BlockInstance $block): array => $block->toArray(), $this->blocks),
        ];
    }

    /**
     * @return array{version: int, blocks: list<array<string, mixed>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
