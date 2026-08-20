<?php

declare(strict_types=1);

namespace Tesserae\Support;

final class Arr
{
    /**
     * @param array<array-key, mixed> $array
     */
    public static function get(array $array, string $path, mixed $default = null): mixed
    {
        $value = $array;

        foreach (explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Returns $value when it is a list, an empty list for null, and a single
     * element list for anything else. Config files are written by humans, so
     * both `post_types: page` and `post_types: [page]` have to work.
     *
     * @return list<mixed>
     */
    public static function wrap(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!\is_array($value)) {
            return [$value];
        }

        return array_values($value);
    }

    /**
     * @param mixed $value
     *
     * @return array<array-key, mixed>
     */
    public static function toArray($value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * Like {@see self::toArray()}, but drops any entries with non-string
     * keys. Used wherever a value is expected to be an associative
     * "object-shaped" array, e.g. parsed YAML/JSON config or a REST payload.
     *
     * @return array<string, mixed>
     */
    public static function toMap(mixed $value): array
    {
        $map = [];

        foreach (self::toArray($value) as $key => $item) {
            if (\is_string($key)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    public static function toString(mixed $value, string $default = ''): string
    {
        return \is_scalar($value) ? (string) $value : $default;
    }

    public static function toBool(mixed $value, bool $default = false): bool
    {
        if (null === $value) {
            return $default;
        }

        if (\is_string($value)) {
            return \in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    public static function toInt(mixed $value, ?int $default = null): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
