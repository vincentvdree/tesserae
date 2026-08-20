<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

class SelectField extends Field
{
    public static function type(): string
    {
        return 'select';
    }

    public function defaultValue(): mixed
    {
        $default = $this->config('default');

        if ($this->isMultiple()) {
            return array_map(static fn (mixed $item): string => Arr::toString($item), Arr::wrap($default));
        }

        if (null !== $default) {
            return Arr::toString($default);
        }

        return Arr::toBool($this->config('allow_null', false)) ? '' : (string) array_key_first($this->choices() ?: ['' => '']);
    }

    public function sanitize(mixed $value): mixed
    {
        $choices = $this->choices();

        if ($this->isMultiple()) {
            $clean = [];

            foreach (Arr::wrap($value) as $item) {
                $item = Arr::toString($item);

                if (isset($choices[$item])) {
                    $clean[] = $item;
                }
            }

            return $clean;
        }

        $value = Arr::toString($value);

        return isset($choices[$value]) ? $value : '';
    }

    public function toText(mixed $value): string
    {
        return '';
    }

    public function schema(): array
    {
        return array_merge(parent::schema(), ['choices' => $this->choices(), 'multiple' => $this->isMultiple()]);
    }

    protected function isMultiple(): bool
    {
        return Arr::toBool($this->config('multiple', false));
    }

    protected function renderControl(mixed $value): string
    {
        $selected = array_map(static fn (mixed $item): string => Arr::toString($item), Arr::wrap($value));
        $options = '';

        if (Arr::toBool($this->config('allow_null', false)) && !$this->isMultiple()) {
            $options .= '<option value="">'.esc_html(Arr::toString($this->config('null_label') ?? '—')).'</option>';
        }

        foreach ($this->choices() as $key => $label) {
            // Array keys that look like integers (e.g. "2", "3", "4") get
            // silently coerced to int by PHP, so $key can't be compared
            // against $selected (always strings) with strict in_array().
            $key = (string) $key;

            $options .= \sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($key),
                \in_array($key, $selected, true) ? ' selected' : '',
                esc_html($label),
            );
        }

        return \sprintf(
            '<select class="tsr-input tsr-select" %s>%s</select>',
            $this->inputAttributes([
                'multiple' => $this->isMultiple(),
                'size' => $this->isMultiple() ? min(8, max(3, \count($this->choices()))) : null,
            ]),
            $options,
        );
    }
}
