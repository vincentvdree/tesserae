<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

/**
 * Splits the fields that follow it into a tab. Carries no value of its own.
 */
class TabField extends Field
{
    public static function type(): string
    {
        return 'tab';
    }

    public function isPresentational(): bool
    {
        return true;
    }

    public function slug(): string
    {
        $name = Arr::toString($this->config('name') ?? '');

        return sanitize_title('' !== $name ? $name : $this->label());
    }

    public function label(): string
    {
        return Arr::toString($this->config('label') ?? $this->config('name') ?? __('Tab', 'tesserae'));
    }

    public function render(mixed $value): string
    {
        return \sprintf('<div class="tsr-tab-marker" data-tesserae-tab="%s" data-tesserae-tab-label="%s" hidden></div>', esc_attr($this->slug()), esc_attr($this->label()));
    }

    protected function renderControl(mixed $value): string
    {
        return '';
    }
}
