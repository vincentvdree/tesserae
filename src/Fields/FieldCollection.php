<?php

declare(strict_types=1);

namespace Tesserae\Fields;

use Tesserae\Support\Arr;

/**
 * An ordered set of fields — the body of a block, a group or a repeater row.
 *
 * @implements \IteratorAggregate<int, Field>
 */
final class FieldCollection implements \IteratorAggregate, \Countable
{
    /** Slug of the implicit tab holding the fields declared before the first `type: tab`. */
    public const MAIN_TAB = '__main';

    /** @var list<Field> */
    private array $fields;

    /**
     * @param list<Field> $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    /**
     * @param mixed $config the raw `fields:` value from YAML
     */
    public static function fromConfig(mixed $config): self
    {
        $fields = [];

        if (!\is_array($config)) {
            return new self();
        }

        foreach ($config as $key => $definition) {
            if (!\is_array($definition)) {
                continue;
            }

            $definition = Arr::toMap($definition);

            // Both `- name: title` lists and `title: {type: text}` maps are accepted.
            if (\is_string($key) && !isset($definition['name'])) {
                $definition['name'] = $key;
            }

            $field = FieldRegistry::make($definition);

            if (null === $field) {
                continue;
            }

            if ('' === $field->name() && !$field->isPresentational()) {
                continue;
            }

            $fields[] = $field;
        }

        return new self($fields);
    }

    /**
     * @return \ArrayIterator<int, Field>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->fields);
    }

    public function count(): int
    {
        return \count($this->fields);
    }

    /**
     * @return list<Field>
     */
    public function all(): array
    {
        return $this->fields;
    }

    public function get(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $values = [];

        foreach ($this->fields as $field) {
            if ($field->isPresentational()) {
                continue;
            }

            $values[$field->name()] = $field->defaultValue();
        }

        return $values;
    }

    /**
     * @param mixed $values
     *
     * @return array<string, mixed>
     */
    public function sanitize($values): array
    {
        $values = \is_array($values) ? $values : [];
        $clean = [];

        foreach ($this->fields as $field) {
            if ($field->isPresentational()) {
                continue;
            }

            $name = $field->name();
            $clean[$name] = \array_key_exists($name, $values)
                ? $field->sanitize($values[$name])
                : $field->sanitize($field->defaultValue());
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function prepare(array $values): array
    {
        $prepared = [];

        foreach ($this->fields as $field) {
            if ($field->isPresentational()) {
                continue;
            }

            $name = $field->name();
            $prepared[$name] = $field->prepare($values[$name] ?? $field->defaultValue());
        }

        return $prepared;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function render(array $values): string
    {
        $tabbed = [] !== $this->tabFields();
        $html = '';
        $open = false;

        // With tabs in play, the fields before the first tab become a tab of
        // their own so that switching tabs always swaps the whole panel.
        if ($tabbed && $this->hasLeadingFields()) {
            $html .= '<div class="tsr-tab-panel" data-tesserae-tab-panel="'.esc_attr(self::MAIN_TAB).'">';
            $open = true;
        }

        foreach ($this->fields as $field) {
            if ($field instanceof Types\TabField) {
                $html .= ($open ? '</div>' : '').$field->render(null);
                $html .= '<div class="tsr-tab-panel" data-tesserae-tab-panel="'.esc_attr($field->slug()).'">';
                $open = true;

                continue;
            }

            $html .= $field->render($values[$field->name()] ?? null);
        }

        return $html.($open ? '</div>' : '');
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    public function toText(array $values): array
    {
        $text = [];

        foreach ($this->fields as $field) {
            if ($field->isPresentational()) {
                continue;
            }

            $value = $field->toText($values[$field->name()] ?? null);

            if ('' !== $value) {
                $text[] = $value;
            }
        }

        return $text;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function schema(): array
    {
        return array_map(static fn (Field $field): array => $field->schema(), $this->fields);
    }

    /**
     * @return list<array{slug: string, label: string}>
     */
    public function tabs(): array
    {
        $tabs = [];

        foreach ($this->fields as $field) {
            if ($field instanceof Types\TabField) {
                $tabs[] = ['slug' => $field->slug(), 'label' => $field->label()];
            }
        }

        if ([] !== $tabs && $this->hasLeadingFields()) {
            /**
             * Filter the label of the implicit tab holding the fields declared
             * before the first `type: tab`.
             *
             * @param string $label
             */
            $label = apply_filters('tesserae/main_tab_label', __('Content', 'tesserae'));

            array_unshift($tabs, [
                'slug' => self::MAIN_TAB,
                'label' => $label,
            ]);
        }

        return $tabs;
    }

    /**
     * @return list<Types\TabField>
     */
    private function tabFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (Field $field): bool => $field instanceof Types\TabField,
        ));
    }

    private function hasLeadingFields(): bool
    {
        foreach ($this->fields as $field) {
            if ($field instanceof Types\TabField) {
                return false;
            }

            return true;
        }

        return false;
    }
}
