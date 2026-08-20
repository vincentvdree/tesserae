<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

class ToggleField extends Field
{
    public static function type(): string
    {
        return 'toggle';
    }

    public function sanitize(mixed $value): mixed
    {
        return Arr::toBool($value);
    }

    public function defaultValue(): mixed
    {
        return Arr::toBool($this->config('default', false));
    }

    public function toText(mixed $value): string
    {
        return '';
    }

    protected function renderControl(mixed $value): string
    {
        return \sprintf(
            '<label class="tsr-toggle"><input type="checkbox" %s %s><span class="tsr-toggle__track"></span><span class="tsr-toggle__text">%s</span></label>',
            checked(Arr::toBool($value), true, false),
            $this->inputAttributes(),
            esc_html(Arr::toString($this->config('text') ?? $this->config('on_label') ?? '')),
        );
    }
}
