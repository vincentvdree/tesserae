<?php

declare(strict_types=1);

namespace Tesserae\Support;

/**
 * A small, dependency-free YAML reader covering the subset of the language that
 * block definitions need: nested mappings and sequences, flow collections,
 * quoted and block scalars, comments and the usual scalar literals.
 *
 * Anchors, aliases, tags, multi-document streams and complex keys are not
 * supported — a block definition that needs them is a block definition that has
 * outgrown a config file.
 *
 * If ext-yaml is loaded it is used instead, since it is both faster and more
 * complete.
 */
final class Yaml
{
    /**
     * @throws YamlException
     */
    public static function parseFile(string $path): mixed
    {
        $contents = @file_get_contents($path);

        if (false === $contents) {
            throw new YamlException(\sprintf('Unable to read YAML file "%s".', $path));
        }

        try {
            return self::parse($contents);
        } catch (YamlException $e) {
            throw new YamlException(\sprintf('%s (in %s)', $e->getMessage(), $path), 0, $e);
        }
    }

    /**
     * @throws YamlException
     */
    public static function parse(string $yaml): mixed
    {
        if (\function_exists('yaml_parse')) {
            $parsed = @yaml_parse($yaml);

            if (false !== $parsed) {
                return $parsed;
            }
        }

        $tokens = self::tokenize($yaml);

        if ([] === $tokens) {
            return null;
        }

        $index = 0;
        $value = self::parseNode($tokens, $index, $tokens[0]['indent']);

        if ($index < \count($tokens)) {
            throw new YamlException(\sprintf(
                'Unexpected indentation on line %d: "%s".',
                $tokens[$index]['line'],
                $tokens[$index]['text'],
            ));
        }

        return $value;
    }

    /**
     * Strips comments and blank lines and records the indentation of everything
     * that is left.
     *
     * @return list<array{indent: int, text: string, line: int}>
     *
     * @throws YamlException
     */
    private static function tokenize(string $yaml): array
    {
        $yaml = str_replace(["\r\n", "\r"], "\n", $yaml);
        $tokens = [];
        $blockIndent = null;

        foreach (explode("\n", $yaml) as $number => $raw) {
            $line = $number + 1;

            if (str_contains($raw, "\t")) {
                $expanded = str_replace("\t", '    ', $raw);

                if (ltrim($raw) !== ltrim($expanded)) {
                    // Tabs inside the value are fine; tabs used for indentation are not.
                    throw new YamlException(\sprintf('Tabs cannot be used for indentation (line %d).', $line));
                }

                $raw = $expanded;
            }

            $indent = \strlen($raw) - \strlen(ltrim($raw, ' '));
            $text = trim($raw);

            // Lines belonging to a literal block scalar are kept verbatim.
            if (null !== $blockIndent) {
                if ('' === $text || $indent >= $blockIndent) {
                    $tokens[] = ['indent' => $blockIndent, 'text' => "\0raw:".substr($raw, min($indent, $blockIndent)), 'line' => $line];

                    continue;
                }

                $blockIndent = null;
            }

            if ('' === $text || str_starts_with($text, '#')) {
                continue;
            }

            if ('---' === $text || '...' === $text) {
                continue;
            }

            $text = self::stripComment($text);

            if ('' === $text) {
                continue;
            }

            $tokens[] = ['indent' => $indent, 'text' => $text, 'line' => $line];

            if (preg_match('/(^|\s)[|>][+-]?\d*$/', $text)) {
                $blockIndent = $indent + 1;
            }
        }

        // Trailing blank lines captured for a block scalar carry no information.
        while ([] !== $tokens && str_starts_with($tokens[\count($tokens) - 1]['text'], "\0raw:")
            && '' === trim(substr($tokens[\count($tokens) - 1]['text'], 5))) {
            array_pop($tokens);
        }

        return $tokens;
    }

    /**
     * Removes a trailing `# comment`, ignoring `#` inside quotes.
     */
    private static function stripComment(string $text): string
    {
        $quote = null;
        $length = \strlen($text);

        for ($i = 0; $i < $length; ++$i) {
            $char = $text[$i];

            if (null !== $quote) {
                if ($char === $quote) {
                    $quote = null;
                } elseif ('\\' === $char && '"' === $quote) {
                    ++$i;
                }

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $quote = $char;

                continue;
            }

            if ('#' === $char && (0 === $i || ' ' === $text[$i - 1])) {
                return rtrim(substr($text, 0, $i));
            }
        }

        return $text;
    }

    /**
     * @param list<array{indent: int, text: string, line: int}> $tokens
     *
     * @throws YamlException
     */
    private static function parseNode(array $tokens, int &$index, int $indent): mixed
    {
        $token = $tokens[$index] ?? null;

        if (null === $token) {
            return null;
        }

        if ('-' === $token['text'] || str_starts_with($token['text'], '- ')) {
            return self::parseSequence($tokens, $index, $indent);
        }

        return self::parseMapping($tokens, $index, $indent);
    }

    /**
     * @param list<array{indent: int, text: string, line: int}> $tokens
     *
     * @return list<mixed>
     *
     * @throws YamlException
     */
    private static function parseSequence(array $tokens, int &$index, int $indent): array
    {
        $items = [];

        while ($index < \count($tokens)) {
            $token = $tokens[$index];

            if ($token['indent'] < $indent) {
                break;
            }

            if ($token['indent'] > $indent || ('-' !== $token['text'] && !str_starts_with($token['text'], '- '))) {
                throw new YamlException(\sprintf('Expected a list item on line %d, got "%s".', $token['line'], $token['text']));
            }

            $rest = ltrim(substr($token['text'], 1));
            ++$index;

            if ('' === $rest) {
                $items[] = self::parseChild($tokens, $index, $indent);

                continue;
            }

            // `- key: value` starts a mapping whose keys align with the value column.
            $offset = $token['indent'] + \strlen($token['text']) - \strlen($rest);
            $inline = [['indent' => $offset, 'text' => $rest, 'line' => $token['line']]];
            $merged = array_merge($inline, \array_slice($tokens, $index));
            $cursor = 0;

            if (self::isMappingLine($rest)) {
                $items[] = self::parseMapping($merged, $cursor, $offset);
            } elseif (str_starts_with($rest, '- ') || '-' === $rest) {
                $items[] = self::parseSequence($merged, $cursor, $offset);
            } else {
                $items[] = self::parseScalarOrBlock($tokens, $index, $indent, $rest, $token['line']);

                continue;
            }

            $index += $cursor - 1;
        }

        return $items;
    }

    /**
     * @param list<array{indent: int, text: string, line: int}> $tokens
     *
     * @return array<string, mixed>
     *
     * @throws YamlException
     */
    private static function parseMapping(array $tokens, int &$index, int $indent): array
    {
        $map = [];

        while ($index < \count($tokens)) {
            $token = $tokens[$index];

            if ($token['indent'] < $indent) {
                break;
            }

            if ($token['indent'] > $indent) {
                throw new YamlException(\sprintf('Unexpected indentation on line %d: "%s".', $token['line'], $token['text']));
            }

            if (!self::isMappingLine($token['text'])) {
                if (str_starts_with($token['text'], '- ') || '-' === $token['text']) {
                    break;
                }

                throw new YamlException(\sprintf('Expected "key: value" on line %d, got "%s".', $token['line'], $token['text']));
            }

            [$key, $rest] = self::splitKey($token['text']);
            ++$index;

            $map[$key] = '' === $rest
                ? self::parseChild($tokens, $index, $indent)
                : self::parseScalarOrBlock($tokens, $index, $indent, $rest, $token['line']);
        }

        return $map;
    }

    /**
     * Parses the nested node belonging to a key or list item that had no inline value.
     *
     * @param list<array{indent: int, text: string, line: int}> $tokens
     *
     * @throws YamlException
     */
    private static function parseChild(array $tokens, int &$index, int $indent): mixed
    {
        $next = $tokens[$index] ?? null;

        if (null === $next || $next['indent'] <= $indent) {
            // A sequence may be written flush with its key, YAML allows it.
            if (null !== $next && $next['indent'] === $indent && str_starts_with($next['text'], '- ')) {
                return self::parseSequence($tokens, $index, $indent);
            }

            return null;
        }

        return self::parseNode($tokens, $index, $next['indent']);
    }

    /**
     * Handles both plain inline scalars and `|` / `>` block scalars.
     *
     * @param list<array{indent: int, text: string, line: int}> $tokens
     *
     * @throws YamlException
     */
    private static function parseScalarOrBlock(array $tokens, int &$index, int $indent, string $value, int $line): mixed
    {
        if (!preg_match('/^([|>])([+-]?)$/', $value, $matches)) {
            return self::parseScalar($value, $line);
        }

        $lines = [];

        while ($index < \count($tokens) && str_starts_with($tokens[$index]['text'], "\0raw:")) {
            $lines[] = substr($tokens[$index]['text'], 5);
            ++$index;
        }

        $strip = self::dedent($lines);
        $text = '|' === $matches[1]
            ? implode("\n", $strip)
            : self::fold($strip);

        return match ($matches[2]) {
            '+' => $text."\n",
            '-' => rtrim($text, "\n"),
            default => rtrim($text, "\n")."\n",
        };
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function dedent(array $lines): array
    {
        $indent = null;

        foreach ($lines as $line) {
            if ('' === trim($line)) {
                continue;
            }

            $current = \strlen($line) - \strlen(ltrim($line, ' '));
            $indent = null === $indent ? $current : min($indent, $current);
        }

        if (null === $indent || 0 === $indent) {
            return $lines;
        }

        return array_map(static fn (string $line): string => substr($line, min($indent, \strlen($line) - \strlen(ltrim($line, ' ')))), $lines);
    }

    /**
     * @param list<string> $lines
     */
    private static function fold(array $lines): string
    {
        $out = '';

        foreach ($lines as $line) {
            if ('' === trim($line)) {
                $out = rtrim($out, ' ')."\n";

                continue;
            }

            $out .= $line.' ';
        }

        return rtrim($out, ' ');
    }

    private static function isMappingLine(string $text): bool
    {
        if (str_starts_with($text, '{') || str_starts_with($text, '[')) {
            return false;
        }

        return null !== self::findKeySeparator($text);
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws YamlException
     */
    private static function splitKey(string $text): array
    {
        $position = self::findKeySeparator($text);

        if (null === $position) {
            throw new YamlException(\sprintf('Malformed mapping entry "%s".', $text));
        }

        $key = trim(substr($text, 0, $position));
        $rest = trim(substr($text, $position + 1));

        if ((str_starts_with($key, '"') && str_ends_with($key, '"')) || (str_starts_with($key, "'") && str_ends_with($key, "'"))) {
            $key = substr($key, 1, -1);
        }

        return [$key, $rest];
    }

    /**
     * Finds the `:` that separates a key from its value, skipping quoted spans.
     */
    private static function findKeySeparator(string $text): ?int
    {
        $quote = null;
        $length = \strlen($text);

        for ($i = 0; $i < $length; ++$i) {
            $char = $text[$i];

            if (null !== $quote) {
                if ($char === $quote) {
                    $quote = null;
                } elseif ('\\' === $char && '"' === $quote) {
                    ++$i;
                }

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $quote = $char;

                continue;
            }

            if (':' === $char && ($i === $length - 1 || ' ' === $text[$i + 1])) {
                return 0 === $i ? null : $i;
            }
        }

        return null;
    }

    /**
     * @throws YamlException
     */
    private static function parseScalar(string $value, int $line): mixed
    {
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"') && \strlen($value) > 1) {
            return self::unescape(substr($value, 1, -1));
        }

        if (str_starts_with($value, "'") && str_ends_with($value, "'") && \strlen($value) > 1) {
            return str_replace("''", "'", substr($value, 1, -1));
        }

        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return array_map(
                static fn (string $item): mixed => self::parseScalar($item, $line),
                self::splitFlow(substr($value, 1, -1)),
            );
        }

        if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
            $map = [];

            foreach (self::splitFlow(substr($value, 1, -1)) as $pair) {
                if ('' === trim($pair)) {
                    continue;
                }

                [$key, $rest] = self::splitKey($pair);
                $map[$key] = self::parseScalar($rest, $line);
            }

            return $map;
        }

        return match (strtolower($value)) {
            'true', 'yes', 'on' => true,
            'false', 'no', 'off' => false,
            'null', '~' => null,
            default => self::parseNumber($value),
        };
    }

    private static function parseNumber(string $value): mixed
    {
        if (preg_match('/^[+-]?\d+$/', $value)) {
            return (int) $value;
        }

        if (preg_match('/^[+-]?(\d+\.\d*|\.\d+|\d+(\.\d+)?[eE][+-]?\d+)$/', $value)) {
            return (float) $value;
        }

        return $value;
    }

    private static function unescape(string $value): string
    {
        return preg_replace_callback(
            '/\\\(["\\\\\/nrtbf0]|u[0-9a-fA-F]{4})/',
            static function (array $matches): string {
                $escape = $matches[1];

                if (str_starts_with($escape, 'u')) {
                    return mb_convert_encoding(pack('n', (int) hexdec(substr($escape, 1))), 'UTF-8', 'UTF-16BE');
                }

                return match ($escape) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\f",
                    '0' => "\0",
                    default => $escape,
                };
            },
            $value,
        ) ?? $value;
    }

    /**
     * Splits a flow collection body on top-level commas.
     *
     * @return list<string>
     */
    private static function splitFlow(string $value): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = \strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];

            if (null !== $quote) {
                $buffer .= $char;

                if ($char === $quote) {
                    $quote = null;
                } elseif ('\\' === $char && '"' === $quote && $i + 1 < $length) {
                    $buffer .= $value[++$i];
                }

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $quote = $char;
                $buffer .= $char;

                continue;
            }

            if ('[' === $char || '{' === $char) {
                ++$depth;
            } elseif (']' === $char || '}' === $char) {
                --$depth;
            }

            if (',' === $char && 0 === $depth) {
                $parts[] = trim($buffer);
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if ('' !== trim($buffer)) {
            $parts[] = trim($buffer);
        }

        return $parts;
    }
}
