<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

/**
 * A small contenteditable editor. Deliberately not TinyMCE and definitely not
 * Gutenberg: bold, italic, links, lists and headings, stored as filtered HTML.
 */
class RichTextField extends Field
{
    public static function type(): string
    {
        return 'richtext';
    }

    public function sanitize(mixed $value): mixed
    {
        if (!\is_scalar($value)) {
            return '';
        }

        return wp_kses((string) $value, $this->allowedHtml());
    }

    public function toText(mixed $value): string
    {
        return trim(wp_strip_all_tags(Arr::toString($value)));
    }

    protected function renderControl(mixed $value): string
    {
        $toolbar = Arr::wrap($this->config('toolbar') ?? ['bold', 'italic', 'link', 'unordered_list', 'ordered_list']);

        return \sprintf(
            '<div class="tsr-richtext" data-controller="tesserae-richtext" data-tesserae-richtext-toolbar-value="%s">'
            .'<div class="tsr-richtext__bar" data-tesserae-richtext-target="bar"></div>'
            .'<div class="tsr-richtext__body" contenteditable="true" data-tesserae-richtext-target="body">%s</div>'
            .'<input type="hidden" value="%s" data-tesserae-richtext-target="input" %s>'
            .'</div>',
            esc_attr((string) wp_json_encode($toolbar)),
            wp_kses(Arr::toString($value), $this->allowedHtml()),
            esc_attr(Arr::toString($value)),
            $this->inputAttributes(),
        );
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function allowedHtml(): array
    {
        $allowed = [
            'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
            'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'code' => [], 'span' => [],
            'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'a' => ['href' => true, 'title' => true, 'target' => true, 'rel' => true],
        ];

        // Filter: tesserae/richtext_allowed_html $allowed, $config.
        $filtered = apply_filters('tesserae/richtext_allowed_html', $allowed, $this->config);

        if (!\is_array($filtered)) {
            return $allowed;
        }

        $result = [];

        foreach ($filtered as $tag => $attrs) {
            if (!\is_string($tag) || !\is_array($attrs)) {
                continue;
            }

            $tagAttrs = [];

            foreach ($attrs as $attr => $value) {
                if (\is_string($attr)) {
                    $tagAttrs[$attr] = Arr::toBool($value);
                }
            }

            $result[$tag] = $tagAttrs;
        }

        return $result;
    }
}
