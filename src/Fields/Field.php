<?php

declare(strict_types=1);

namespace Tesserae\Fields;

use Tesserae\Support\Arr;

/**
 * Base class for every field type.
 *
 * A field is created from the array that sits under `fields:` in a block's YAML
 * file and is responsible for three things: turning submitted input into a safe
 * stored value (`sanitize`), turning a stored value into something a template
 * wants to use (`prepare`), and rendering its own control in the editor modal
 * (`renderControl`).
 */
abstract class Field
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config) {}

    /**
     * The type slug used in YAML (`type: text`).
     */
    abstract public static function type(): string;

    public function name(): string
    {
        return Arr::toString($this->config['name'] ?? '');
    }

    public function label(): string
    {
        $label = Arr::toString($this->config['label'] ?? '');

        if ('' !== $label) {
            return $label;
        }

        return ucfirst(str_replace(['_', '-'], ' ', $this->name()));
    }

    public function instructions(): string
    {
        return Arr::toString($this->config['instructions'] ?? $this->config['description'] ?? '');
    }

    public function isRequired(): bool
    {
        return Arr::toBool($this->config['required'] ?? false);
    }

    /**
     * Pseudo fields (tabs, messages) carry no value and are skipped by the
     * storage layer.
     */
    public function isPresentational(): bool
    {
        return false;
    }

    public function defaultValue(): mixed
    {
        return $this->config['default'] ?? null;
    }

    /**
     * Percentage of the modal row this field occupies.
     */
    public function width(): int
    {
        $width = Arr::toInt($this->config['width'] ?? null, 100) ?? 100;

        return max(10, min(100, $width));
    }

    public function sanitize(mixed $value): mixed
    {
        return \is_scalar($value) ? sanitize_text_field((string) $value) : null;
    }

    /**
     * Converts the stored value into the shape templates receive.
     */
    public function prepare(mixed $value): mixed
    {
        return $value;
    }

    /**
     * A plain-text rendering of the value, used for the search index and for
     * the block summaries shown in the editor's outline.
     */
    public function toText(mixed $value): string
    {
        if (\is_scalar($value)) {
            return trim(wp_strip_all_tags((string) $value));
        }

        return '';
    }

    public function render(mixed $value): string
    {
        if (null === $value) {
            $value = $this->defaultValue();
        }

        $classes = ['tsr-field', 'tsr-field--'.static::type()];
        $extra = Arr::toString($this->config['class'] ?? '');

        if ('' !== $extra) {
            $classes[] = $extra;
        }

        $attributes = [
            'class' => implode(' ', $classes),
            'data-tesserae-field' => $this->name(),
            'data-tesserae-type' => static::type(),
            'style' => \sprintf('--tsr-field-width:%d%%', $this->width()),
        ];

        $conditional = $this->conditionalRules();

        if ([] !== $conditional['rules']) {
            $attributes['data-tesserae-conditional'] = (string) wp_json_encode($conditional);
        }

        $html = '<div '.self::attributes($attributes).'>';

        if (!$this->hidesLabel()) {
            $html .= '<div class="tsr-field__head">';
            $html .= '<span class="tsr-field__label">'.esc_html($this->label());

            if ($this->isRequired()) {
                $html .= ' <span class="tsr-field__required" aria-hidden="true">*</span>';
            }

            $html .= '</span>';
            $html .= '</div>';
        }

        $html .= '<div class="tsr-field__control">'.$this->renderControl($value).'</div>';

        if ('' !== $this->instructions()) {
            $html .= '<p class="tsr-field__hint">'.esc_html($this->instructions()).'</p>';
        }

        return $html.'</div>';
    }

    /**
     * Normalised conditional logic: `{relation: and|or, rules: [{field, operator, value}]}`.
     *
     * @return array{relation: string, rules: list<array{field: string, operator: string, value: mixed}>}
     */
    public function conditionalRules(): array
    {
        $raw = $this->config['conditional'] ?? $this->config['conditions'] ?? null;

        if (null === $raw) {
            return ['relation' => 'and', 'rules' => []];
        }

        $relation = 'and';

        if (\is_array($raw) && isset($raw['rules'])) {
            $relation = 'or' === strtolower(Arr::toString($raw['relation'] ?? 'and')) ? 'or' : 'and';
            $raw = $raw['rules'];
        }

        $rules = [];

        foreach (Arr::wrap($raw) as $rule) {
            if (!\is_array($rule) || !isset($rule['field'])) {
                continue;
            }

            $rules[] = [
                'field' => Arr::toString($rule['field']),
                'operator' => self::normaliseOperator(Arr::toString($rule['operator'] ?? '==')),
                'value' => $rule['value'] ?? true,
            ];
        }

        return ['relation' => $relation, 'rules' => $rules];
    }

    /**
     * The field description handed to the editor's JavaScript.
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'type' => static::type(),
            'label' => $this->label(),
            'required' => $this->isRequired(),
            'default' => $this->defaultValue(),
            'conditional' => $this->conditionalRules(),
        ];
    }

    /**
     * The control markup shown inside the editor modal.
     */
    abstract protected function renderControl(mixed $value): string;

    protected function hidesLabel(): bool
    {
        return false;
    }

    /**
     * @param array<string, null|bool|int|string> $attributes
     */
    protected static function attributes(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if (null === $value || false === $value) {
                continue;
            }

            if (true === $value) {
                $parts[] = esc_attr($key);

                continue;
            }

            $parts[] = \sprintf('%s="%s"', esc_attr($key), esc_attr((string) $value));
        }

        return implode(' ', $parts);
    }

    /**
     * Every control that participates in serialisation carries these.
     *
     * @param array<string, null|bool|int|string> $extra
     */
    protected function inputAttributes(array $extra = []): string
    {
        return self::attributes(array_merge([
            'data-tesserae-input' => true,
            'placeholder' => Arr::toString($this->config['placeholder'] ?? '') ?: null,
        ], $extra));
    }

    /**
     * Choice lists accept either a map (`value: Label`) or a plain list.
     *
     * @return array<string, string>
     */
    protected function choices(): array
    {
        $choices = $this->config['choices'] ?? $this->config['options'] ?? [];
        $normalised = [];

        if (!\is_array($choices)) {
            return [];
        }

        foreach ($choices as $key => $value) {
            if (\is_int($key) && \is_array($value)) {
                $normalised[Arr::toString($value['value'] ?? '')] = Arr::toString($value['label'] ?? $value['value'] ?? '');

                continue;
            }

            if (\is_int($key)) {
                $normalised[Arr::toString($value)] = Arr::toString($value);

                continue;
            }

            $normalised[(string) $key] = Arr::toString($value);
        }

        return $normalised;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    private static function normaliseOperator(string $operator): string
    {
        return match (strtolower($operator)) {
            '!=', 'not', 'isnot', 'is_not', '!==' => '!=',
            '>', 'gt' => '>',
            '<', 'lt' => '<',
            'contains', 'has' => 'contains',
            'empty', 'isempty', 'is_empty' => 'empty',
            'notempty', 'not_empty', 'isnotempty' => 'not_empty',
            'in' => 'in',
            default => '==',
        };
    }
}
