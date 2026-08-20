<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

/**
 * A note for whoever is editing the page. Renders in the modal, stores nothing.
 */
class MessageField extends Field
{
    public static function type(): string
    {
        return 'message';
    }

    public function isPresentational(): bool
    {
        return true;
    }

    protected function hidesLabel(): bool
    {
        return '' === Arr::toString($this->config('label') ?? '');
    }

    protected function renderControl(mixed $value): string
    {
        $tone = Arr::toString($this->config('tone') ?? 'info');
        $message = Arr::toString($this->config('message') ?? $this->config('text') ?? '');

        return \sprintf(
            '<div class="tsr-message tsr-message--%s">%s</div>',
            esc_attr(sanitize_html_class($tone)),
            wp_kses_post($message),
        );
    }
}
