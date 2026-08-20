<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

class ColorField extends Field
{
    public static function type(): string
    {
        return 'color';
    }

    public function sanitize(mixed $value): mixed
    {
        $value = trim(Arr::toString($value));

        if ('' === $value) {
            return '';
        }

        if (isset($this->palette()[$value])) {
            return $value;
        }

        $hex = sanitize_hex_color($value);

        return \is_string($hex) ? $hex : '';
    }

    protected function renderControl(mixed $value): string
    {
        $value = Arr::toString($this->sanitize($value));
        $palette = $this->palette();

        if ([] === $palette) {
            return \sprintf(
                '<div class="tsr-color" data-controller="tesserae-color">'
                .'<input type="color" class="tsr-color__picker" value="%s" data-tesserae-color-target="picker" data-action="input->tesserae-color#fromPicker">'
                .'<input type="text" class="tsr-input tsr-color__text" value="%s" placeholder="#000000" data-tesserae-color-target="text" data-action="input->tesserae-color#fromText" %s>'
                .'</div>',
                esc_attr('' !== $value ? $value : '#000000'),
                esc_attr($value),
                $this->inputAttributes(),
            );
        }

        $html = '<div class="tsr-swatches" data-controller="tesserae-swatches">';
        $html .= \sprintf('<input type="hidden" value="%s" data-tesserae-swatches-target="input" %s>', esc_attr($value), $this->inputAttributes());

        foreach ($palette as $key => $label) {
            $html .= \sprintf(
                '<button type="button" class="tsr-swatch%s" style="--tsr-swatch:%s" title="%s" data-value="%s" data-action="tesserae-swatches#pick"><span class="tsr-sr">%s</span></button>',
                $key === $value ? ' is-active' : '',
                esc_attr(str_starts_with($key, '#') ? $key : 'var(--'.$key.')'),
                esc_attr($label),
                esc_attr($key),
                esc_html($label),
            );
        }

        return $html.'</div>';
    }

    /**
     * A palette turns the field into a set of swatches, which is usually what a
     * design system wants; without one you get a free colour picker.
     *
     * @return array<string, string>
     */
    private function palette(): array
    {
        return $this->choices();
    }
}
