<?php

declare(strict_types=1);

namespace Tesserae\Blocks;

use Tesserae\Storage\BlockInstance;
use Tesserae\Support\Arr;

/**
 * What a block template gets handed as `$block`.
 *
 * @implements \ArrayAccess<string, mixed>
 */
final class BlockContext implements \ArrayAccess
{
    /**
     * @param array<string, mixed> $fields prepared values, ready for output
     */
    public function __construct(
        public readonly BlockDefinition $definition,
        public readonly BlockInstance $instance,
        public readonly array $fields,
        public readonly int $index,
        public readonly int $total,
        public readonly int $postId,
        public readonly bool $editing,
        public readonly string $variant,
    ) {}

    public function type(): string
    {
        return $this->definition->type;
    }

    public function id(): string
    {
        return $this->instance->id;
    }

    /**
     * Dot paths work: `field('cta.link.url')`.
     */
    public function field(string $path, mixed $default = null): mixed
    {
        $value = Arr::get($this->fields, $path, $default);

        return null === $value ? $default : $value;
    }

    public function has(string $path): bool
    {
        $value = Arr::get($this->fields, $path);

        return !(null === $value || '' === $value || [] === $value || false === $value);
    }

    public function isFirst(): bool
    {
        return 0 === $this->index;
    }

    public function isLast(): bool
    {
        return $this->index === $this->total - 1;
    }

    public function isEditing(): bool
    {
        return $this->editing;
    }

    public function anchor(): string
    {
        return $this->instance->anchor();
    }

    /**
     * A stable id for the block element, useful for aria-* wiring inside templates.
     */
    public function uid(string $suffix = ''): string
    {
        return 'tsr-'.$this->instance->id.('' !== $suffix ? '-'.sanitize_html_class($suffix) : '');
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->field($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        _doing_it_wrong(__METHOD__, 'Block values are read-only inside templates.', '0.1.0');
    }

    public function offsetUnset(mixed $offset): void
    {
        _doing_it_wrong(__METHOD__, 'Block values are read-only inside templates.', '0.1.0');
    }
}
