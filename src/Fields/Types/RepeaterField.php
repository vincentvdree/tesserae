<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Fields\FieldCollection;
use Tesserae\Support\Arr;

/**
 * A sortable list of rows, each row being a small form of its own. This is the
 * flexible-content workhorse most block configs lean on.
 */
class RepeaterField extends Field
{
    private ?FieldCollection $fields = null;

    public static function type(): string
    {
        return 'repeater';
    }

    public function fields(): FieldCollection
    {
        return $this->fields ??= FieldCollection::fromConfig($this->config('fields', []));
    }

    public function min(): int
    {
        return max(0, Arr::toInt($this->config('min'), 0) ?? 0);
    }

    public function max(): ?int
    {
        $max = Arr::toInt($this->config('max'), null);

        return null !== $max && $max > 0 ? $max : null;
    }

    public function defaultValue(): mixed
    {
        $rows = [];

        foreach (Arr::wrap($this->config('default')) as $row) {
            $rows[] = $this->fields()->sanitize($row);
        }

        while (\count($rows) < $this->min()) {
            $rows[] = $this->fields()->defaults();
        }

        return $rows;
    }

    public function sanitize(mixed $value): mixed
    {
        $rows = [];

        foreach (Arr::wrap($value) as $row) {
            $rows[] = $this->fields()->sanitize($row);
        }

        $max = $this->max();

        return null !== $max ? \array_slice($rows, 0, $max) : $rows;
    }

    public function prepare(mixed $value): mixed
    {
        return array_map(
            fn (mixed $row): array => $this->fields()->prepare(Arr::toMap($row)),
            Arr::wrap($this->sanitize($value)),
        );
    }

    public function toText(mixed $value): string
    {
        $text = [];

        foreach (Arr::wrap($value) as $row) {
            $text = array_merge($text, $this->fields()->toText(Arr::toMap($row)));
        }

        return implode(' ', $text);
    }

    public function schema(): array
    {
        return array_merge(parent::schema(), [
            'fields' => $this->fields()->schema(),
            'min' => $this->min(),
            'max' => $this->max(),
        ]);
    }

    protected function renderControl(mixed $value): string
    {
        $rows = Arr::wrap($this->sanitize($value));
        $rowLabel = Arr::toString($this->config('row_label') ?? __('Item', 'tesserae'));
        $html = '';

        foreach ($rows as $index => $row) {
            $html .= $this->renderRow(Arr::toMap($row), \sprintf('%s %d', $rowLabel, (int) $index + 1));
        }

        return \sprintf(
            '<div class="tsr-repeater" data-controller="tesserae-repeater" data-tesserae-repeater-min-value="%d" data-tesserae-repeater-max-value="%d" data-tesserae-repeater-label-value="%s">'
            .'<div class="tsr-repeater__rows" data-tesserae-repeater-target="rows" data-action="dragover->tesserae-repeater#dragOver drop->tesserae-repeater#drop">%s</div>'
            .'<template data-tesserae-repeater-target="template">%s</template>'
            .'<button type="button" class="tsr-btn tsr-btn--add" data-action="tesserae-repeater#add" data-tesserae-repeater-target="addButton">%s</button>'
            .'</div>',
            $this->min(),
            $this->max() ?? 0,
            esc_attr($rowLabel),
            $html ?: '',
            $this->renderRow($this->fields()->defaults(), $rowLabel),
            esc_html(Arr::toString($this->config('button_label') ?? \sprintf(
                // translators: %s: row label.
                __('Add %s', 'tesserae'),
                strtolower($rowLabel)
            ))),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function renderRow(array $values, string $label): string
    {
        return \sprintf(
            '<div class="tsr-row" data-tesserae-row>'
            .'<div class="tsr-row__bar">'
            .'<span class="tsr-row__handle" draggable="true" role="button" tabindex="0" aria-label="%s" title="%s"'
            .' data-action="dragstart->tesserae-repeater#startDrag dragend->tesserae-repeater#endDrag keydown->tesserae-repeater#handleKey">⠿</span>'
            .'<span class="tsr-row__title" data-tesserae-row-title>%s</span>'
            .'<button type="button" class="tsr-row__action" data-action="tesserae-repeater#moveUp" aria-label="%s">↑</button>'
            .'<button type="button" class="tsr-row__action" data-action="tesserae-repeater#moveDown" aria-label="%s">↓</button>'
            .'<button type="button" class="tsr-row__action" data-action="tesserae-repeater#duplicate" aria-label="%s">⧉</button>'
            .'<button type="button" class="tsr-row__action tsr-row__action--danger" data-action="tesserae-repeater#remove" aria-label="%s">&times;</button>'
            .'</div>'
            .'<div class="tsr-row__body" data-tesserae-scope>%s</div>'
            .'</div>',
            esc_attr__('Reorder row', 'tesserae'),
            esc_attr__('Drag, or use the arrow keys, to reorder', 'tesserae'),
            esc_html($label),
            esc_attr__('Move row up', 'tesserae'),
            esc_attr__('Move row down', 'tesserae'),
            esc_attr__('Duplicate row', 'tesserae'),
            esc_attr__('Remove row', 'tesserae'),
            $this->fields()->render($values),
        );
    }
}
