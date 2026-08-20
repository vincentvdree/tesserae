<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Support\Arr;

class CheckboxField extends SelectField
{
    public static function type(): string
    {
        return 'checkbox';
    }

    protected function isMultiple(): bool
    {
        return true;
    }

    protected function renderControl(mixed $value): string
    {
        $selected = array_map(static fn (mixed $item): string => Arr::toString($item), Arr::wrap($value));
        $html = '<div class="tsr-choices'.(Arr::toBool($this->config('inline', false)) ? ' tsr-choices--inline' : '').'">';

        foreach ($this->choices() as $key => $label) {
            $html .= \sprintf(
                '<label class="tsr-choice"><input type="checkbox" value="%s"%s data-tesserae-multiple="true" %s><span>%s</span></label>',
                esc_attr($key),
                \in_array($key, $selected, true) ? ' checked' : '',
                $this->inputAttributes(),
                esc_html($label),
            );
        }

        return $html.'</div>';
    }
}
