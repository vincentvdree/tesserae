<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

class TextField extends Field
{
    public static function type(): string
    {
        return 'text';
    }

    public function sanitize(mixed $value): mixed
    {
        if (!\is_scalar($value)) {
            return '';
        }

        $value = (string) $value;

        return match ($this->inputType()) {
            'url' => esc_url_raw($value),
            'email' => sanitize_email($value),
            default => sanitize_text_field($value),
        };
    }

    protected function renderControl(mixed $value): string
    {
        return \sprintf(
            '<input type="%s" class="tsr-input" value="%s" %s>',
            esc_attr($this->inputType()),
            esc_attr(Arr::toString($value)),
            $this->inputAttributes([
                'maxlength' => Arr::toInt($this->config('maxlength'), null),
                'required' => $this->isRequired(),
            ]),
        );
    }

    private function inputType(): string
    {
        $configured = strtolower(Arr::toString($this->config('input_type') ?? $this->config('type') ?? 'text'));

        return \in_array($configured, ['url', 'email', 'tel', 'date', 'time', 'datetime-local', 'password'], true)
            ? $configured
            : 'text';
    }
}
