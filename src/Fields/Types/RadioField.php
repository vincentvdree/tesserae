<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Support\Arr;

class RadioField extends SelectField
{
    public static function type(): string
    {
        return 'radio';
    }

    protected function isMultiple(): bool
    {
        return false;
    }

    protected function renderControl(mixed $value): string
    {
        $value = Arr::toString($value);
        $name = 'tsr-'.wp_generate_uuid4();
        $html = '<div class="tsr-choices'.(Arr::toBool($this->config('inline', true)) ? ' tsr-choices--inline' : '').'">';

        foreach ($this->choices() as $key => $label) {
            $html .= \sprintf(
                '<label class="tsr-choice"><input type="radio" name="%s" value="%s"%s %s><span>%s</span></label>',
                esc_attr($name),
                esc_attr($key),
                checked($key, $value, false),
                $this->inputAttributes(),
                esc_html($label),
            );
        }

        return $html.'</div>';
    }
}
