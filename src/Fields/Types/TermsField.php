<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

class TermsField extends Field
{
    public static function type(): string
    {
        return 'terms';
    }

    public function defaultValue(): mixed
    {
        return $this->isMultiple() ? [] : null;
    }

    public function sanitize(mixed $value): mixed
    {
        $ids = [];

        foreach (Arr::wrap($value) as $item) {
            $id = Arr::toInt($item, 0) ?? 0;
            $term = $id > 0 ? get_term($id, $this->taxonomy()) : null;

            if ($term instanceof \WP_Term) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        return $this->isMultiple() ? $ids : ($ids[0] ?? null);
    }

    public function prepare(mixed $value): mixed
    {
        $terms = [];

        foreach (Arr::wrap($this->sanitize($value)) as $id) {
            $term = get_term(Arr::toInt($id, 0) ?? 0, $this->taxonomy());

            if ($term instanceof \WP_Term) {
                $terms[] = $term;
            }
        }

        return $this->isMultiple() ? $terms : ($terms[0] ?? null);
    }

    public function toText(mixed $value): string
    {
        return '';
    }

    protected function renderControl(mixed $value): string
    {
        $selected = array_map(static fn (mixed $id): int => Arr::toInt($id, 0) ?? 0, Arr::wrap($this->sanitize($value)));
        $terms = get_terms([
            'taxonomy' => $this->taxonomy(),
            'hide_empty' => Arr::toBool($this->config('hide_empty', false)),
            'number' => 200,
        ]);

        if (is_wp_error($terms)) {
            return '<p class="tsr-field__error">'.esc_html($terms->get_error_message()).'</p>';
        }

        $options = $this->isMultiple() ? '' : '<option value="">'.esc_html__('—', 'tesserae').'</option>';

        foreach ($terms as $term) {
            $options .= \sprintf(
                '<option value="%d"%s>%s</option>',
                $term->term_id,
                \in_array($term->term_id, $selected, true) ? ' selected' : '',
                esc_html($term->name),
            );
        }

        return \sprintf(
            '<select class="tsr-input tsr-select" %s>%s</select>',
            $this->inputAttributes([
                'multiple' => $this->isMultiple(),
                'size' => $this->isMultiple() ? 6 : null,
                'data-tesserae-cast' => 'int',
            ]),
            $options,
        );
    }

    private function taxonomy(): string
    {
        return sanitize_key(Arr::toString($this->config('taxonomy') ?? 'category'));
    }

    private function isMultiple(): bool
    {
        return Arr::toBool($this->config('multiple', true));
    }
}
