<?php

declare(strict_types=1);

namespace Tesserae\Storage;

use Tesserae\Support\Arr;

/**
 * One placed block: a type, the values the editor stored for it, and the
 * per-instance settings the editor's "Block" tab exposes.
 */
final class BlockInstance implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $values = [],
        public readonly array $settings = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $type = sanitize_key(Arr::toString($data['type'] ?? ''));

        if ('' === $type) {
            return null;
        }

        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', Arr::toString($data['id'] ?? '')) ?? '';

        return new self(
            '' !== $id ? substr($id, 0, 40) : self::generateId(),
            $type,
            Arr::toMap($data['values'] ?? []),
            Arr::toMap($data['settings'] ?? []),
        );
    }

    public static function generateId(): string
    {
        return 'b'.substr(str_replace('-', '', wp_generate_uuid4()), 0, 12);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function withValues(array $values): self
    {
        return new self($this->id, $this->type, $values, $this->settings);
    }

    public function anchor(): string
    {
        return sanitize_title(Arr::toString($this->settings['anchor'] ?? ''));
    }

    public function className(): string
    {
        $classes = array_filter(array_map(
            'sanitize_html_class',
            preg_split('/\s+/', Arr::toString($this->settings['class'] ?? '')) ?: [],
        ));

        return implode(' ', $classes);
    }

    public function isHidden(): bool
    {
        return Arr::toBool($this->settings['hidden'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'values' => $this->values,
            'settings' => $this->settings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
