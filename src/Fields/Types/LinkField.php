<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

/**
 * A url + label + target triple, with a search box for internal content so the
 * common case (link to a page) does not mean copy-pasting URLs.
 */
class LinkField extends Field
{
    public static function type(): string
    {
        return 'link';
    }

    public function defaultValue(): mixed
    {
        $default = $this->config('default');

        return \is_array($default) ? $default : ['url' => '', 'title' => '', 'target' => ''];
    }

    public function sanitize(mixed $value): mixed
    {
        $value = Arr::toArray($value);

        return [
            'url' => esc_url_raw(Arr::toString($value['url'] ?? '')),
            'title' => sanitize_text_field(Arr::toString($value['title'] ?? '')),
            'target' => '_blank' === Arr::toString($value['target'] ?? '') ? '_blank' : '',
        ];
    }

    public function prepare(mixed $value): mixed
    {
        $value = Arr::toArray($value);
        $url = Arr::toString($value['url'] ?? '');

        if ('' === $url) {
            return null;
        }

        $target = Arr::toString($value['target'] ?? '');

        return [
            'url' => $url,
            'title' => Arr::toString($value['title'] ?? '') ?: $url,
            'target' => $target,
            'rel' => '_blank' === $target ? 'noopener noreferrer' : '',
        ];
    }

    public function toText(mixed $value): string
    {
        return sanitize_text_field(Arr::toString(Arr::toArray($value)['title'] ?? ''));
    }

    protected function renderControl(mixed $value): string
    {
        $value = Arr::toArray($value);

        return \sprintf(
            '<div class="tsr-link" data-controller="tesserae-link">'
            .'<input type="hidden" data-tesserae-value-type="json" data-tesserae-link-target="input" value="%s" %s>'
            .'<div class="tsr-link__row">'
            .'<input type="text" class="tsr-input" placeholder="%s" value="%s" data-tesserae-link-target="url" data-action="input->tesserae-link#sync focus->tesserae-link#search keyup->tesserae-link#search">'
            .'<button type="button" class="tsr-btn tsr-btn--ghost" data-action="tesserae-link#toggleSearch" title="%s">%s</button>'
            .'</div>'
            .'<div class="tsr-link__results" data-tesserae-link-target="results" hidden></div>'
            .'<div class="tsr-link__row">'
            .'<input type="text" class="tsr-input" placeholder="%s" value="%s" data-tesserae-link-target="title" data-action="input->tesserae-link#sync">'
            .'<label class="tsr-link__target"><input type="checkbox"%s data-tesserae-link-target="blank" data-action="change->tesserae-link#sync"> %s</label>'
            .'</div>'
            .'</div>',
            esc_attr((string) wp_json_encode($this->sanitize($value))),
            $this->inputAttributes(),
            esc_attr__('https:// or search…', 'tesserae'),
            esc_attr(Arr::toString($value['url'] ?? '')),
            esc_attr__('Search content', 'tesserae'),
            esc_html__('Search', 'tesserae'),
            esc_attr__('Link text', 'tesserae'),
            esc_attr(Arr::toString($value['title'] ?? '')),
            checked('_blank' === Arr::toString($value['target'] ?? ''), true, false),
            esc_html__('New tab', 'tesserae'),
        );
    }
}
