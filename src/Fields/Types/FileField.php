<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Support\Arr;

class FileField extends ImageField
{
    public static function type(): string
    {
        return 'file';
    }

    public function prepare(mixed $value): mixed
    {
        $id = Arr::toInt($this->sanitize($value), 0) ?? 0;

        if ($id <= 0) {
            return null;
        }

        $path = get_attached_file($id);

        return [
            'id' => $id,
            'url' => (string) wp_get_attachment_url($id),
            'title' => get_the_title($id),
            'filename' => \is_string($path) ? basename($path) : '',
            'filesize' => \is_string($path) && is_file($path) ? (int) filesize($path) : 0,
            'mime' => (string) get_post_mime_type($id),
        ];
    }

    protected function mimeFilter(): string
    {
        return Arr::toString($this->config('accept') ?? '');
    }

    protected function renderControl(mixed $value): string
    {
        $file = $this->prepare($value);

        return \sprintf(
            '<div class="tsr-media tsr-media--file" data-controller="tesserae-media" data-tesserae-media-multiple-value="false" data-tesserae-media-kind-value="file" data-tesserae-media-accept-value="%s">'
            .'<input type="hidden" data-tesserae-value-type="json" data-tesserae-media-target="input" value="%s" %s>'
            .'<div class="tsr-media__list" data-tesserae-media-target="list">%s</div>'
            .'<div class="tsr-media__actions">'
            .'<button type="button" class="tsr-btn" data-action="tesserae-media#open">%s</button>'
            .'<button type="button" class="tsr-btn tsr-btn--ghost" data-action="tesserae-media#clear">%s</button>'
            .'</div></div>',
            esc_attr($this->mimeFilter()),
            esc_attr((string) wp_json_encode($this->sanitize($value))),
            $this->inputAttributes(),
            \is_array($file)
                ? \sprintf('<p class="tsr-media__file">%s</p>', esc_html(Arr::toString($file['filename'] ?: $file['title'])))
                : '<p class="tsr-media__empty">'.esc_html__('No file selected', 'tesserae').'</p>',
            esc_html__('Choose', 'tesserae'),
            esc_html__('Remove', 'tesserae'),
        );
    }
}
