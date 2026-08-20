<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

class TextareaField extends Field
{
    public static function type(): string
    {
        return 'textarea';
    }

    public function sanitize(mixed $value): mixed
    {
        return \is_scalar($value) ? sanitize_textarea_field((string) $value) : '';
    }

    public function prepare(mixed $value): mixed
    {
        $text = Arr::toString($value);

        return Arr::toBool($this->config('auto_paragraphs', true)) ? wpautop($text) : $text;
    }

    public function toText(mixed $value): string
    {
        return trim(Arr::toString($value));
    }

    protected function renderControl(mixed $value): string
    {
        return \sprintf(
            '<textarea class="tsr-input tsr-textarea" rows="%d" %s>%s</textarea>',
            Arr::toInt($this->config('rows'), 4) ?? 4,
            $this->inputAttributes(['required' => $this->isRequired()]),
            esc_textarea(Arr::toString($value)),
        );
    }
}
