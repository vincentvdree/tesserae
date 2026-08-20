<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

class NumberField extends Field
{
    public static function type(): string
    {
        return 'number';
    }

    public function sanitize(mixed $value): mixed
    {
        if ('' === $value || null === $value || !is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        $min = $this->config('min');
        $max = $this->config('max');

        if (is_numeric($min)) {
            $number = max((float) $min, $number);
        }

        if (is_numeric($max)) {
            $number = min((float) $max, $number);
        }

        return $number === floor($number) && !str_contains((string) $value, '.') ? (int) $number : $number;
    }

    protected function renderControl(mixed $value): string
    {
        return \sprintf(
            '<input type="number" class="tsr-input" value="%s" %s>',
            esc_attr(Arr::toString($value)),
            $this->inputAttributes([
                'min' => is_numeric($this->config('min')) ? Arr::toString($this->config('min')) : null,
                'max' => is_numeric($this->config('max')) ? Arr::toString($this->config('max')) : null,
                'step' => is_numeric($this->config('step')) ? Arr::toString($this->config('step')) : 'any',
                'required' => $this->isRequired(),
            ]),
        );
    }
}
