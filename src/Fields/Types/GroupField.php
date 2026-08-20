<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Fields\FieldCollection;
use Tesserae\Support\Arr;

class GroupField extends Field
{
    private ?FieldCollection $fields = null;

    public static function type(): string
    {
        return 'group';
    }

    public function fields(): FieldCollection
    {
        return $this->fields ??= FieldCollection::fromConfig($this->config('fields', []));
    }

    public function defaultValue(): mixed
    {
        $defaults = $this->fields()->defaults();
        $configured = Arr::toArray($this->config('default'));

        return array_merge($defaults, $configured);
    }

    public function sanitize(mixed $value): mixed
    {
        return $this->fields()->sanitize($value);
    }

    public function prepare(mixed $value): mixed
    {
        return $this->fields()->prepare(Arr::toMap($value));
    }

    public function toText(mixed $value): string
    {
        return implode(' ', $this->fields()->toText(Arr::toMap($value)));
    }

    public function schema(): array
    {
        return array_merge(parent::schema(), ['fields' => $this->fields()->schema()]);
    }

    protected function renderControl(mixed $value): string
    {
        return \sprintf(
            '<div class="tsr-group%s" data-tesserae-scope>%s</div>',
            Arr::toBool($this->config('seamless', false)) ? ' tsr-group--seamless' : '',
            $this->fields()->render(Arr::toMap($value)),
        );
    }
}
