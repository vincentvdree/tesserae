<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

class ImageField extends Field
{
    public static function type(): string
    {
        return 'image';
    }

    public function sanitize(mixed $value): mixed
    {
        if (\is_array($value)) {
            $value = $value['id'] ?? null;
        }

        $id = Arr::toInt($value, 0) ?? 0;

        return $id > 0 && 'attachment' === get_post_type($id) ? $id : null;
    }

    public function prepare(mixed $value): mixed
    {
        $id = Arr::toInt($value, 0) ?? 0;

        if ($id <= 0) {
            return null;
        }

        return self::attachment($id, Arr::toString($this->config('size') ?? 'large'));
    }

    /**
     * @return null|array<string, mixed>
     */
    public static function attachment(int $id, string $size = 'large'): ?array
    {
        $post = get_post($id);

        if (!$post instanceof \WP_Post || 'attachment' !== $post->post_type) {
            return null;
        }

        $source = wp_get_attachment_image_src($id, $size);
        $full = wp_get_attachment_image_src($id, 'full');

        return [
            'id' => $id,
            'url' => \is_array($source) ? $source[0] : (string) wp_get_attachment_url($id),
            'width' => \is_array($source) ? (int) $source[1] : null,
            'height' => \is_array($source) ? (int) $source[2] : null,
            'full' => \is_array($full) ? $full[0] : null,
            'alt' => Arr::toString(get_post_meta($id, '_wp_attachment_image_alt', true)),
            'title' => $post->post_title,
            'caption' => $post->post_excerpt,
            'description' => $post->post_content,
            'mime' => $post->post_mime_type,
            'srcset' => wp_get_attachment_image_srcset($id, $size) ?: null,
            'sizes' => wp_get_attachment_image_sizes($id, $size) ?: null,
            'size' => $size,
        ];
    }

    public function toText(mixed $value): string
    {
        $prepared = $this->prepare($value);

        return \is_array($prepared) ? Arr::toString($prepared['alt'] ?? '') : '';
    }

    protected function mimeFilter(): string
    {
        return Arr::toString($this->config('accept') ?? 'image');
    }

    protected function renderControl(mixed $value): string
    {
        $image = $this->prepare($value);
        $thumb = \is_array($image) ? (string) wp_get_attachment_image_url(Arr::toInt($image['id'], 0) ?? 0, 'thumbnail') : '';

        return \sprintf(
            '<div class="tsr-media" data-controller="tesserae-media" data-tesserae-media-multiple-value="false" data-tesserae-media-kind-value="image" data-tesserae-media-accept-value="%s">'
            .'<input type="hidden" data-tesserae-value-type="json" data-tesserae-media-target="input" value="%s" %s>'
            .'<div class="tsr-media__list" data-tesserae-media-target="list">%s</div>'
            .'<div class="tsr-media__actions">'
            .'<button type="button" class="tsr-btn" data-action="tesserae-media#open">%s</button>'
            .'<button type="button" class="tsr-btn tsr-btn--ghost" data-action="tesserae-media#clear"%s>%s</button>'
            .'</div></div>',
            esc_attr($this->mimeFilter()),
            esc_attr((string) wp_json_encode($this->sanitize($value))),
            $this->inputAttributes(),
            \is_array($image)
                ? \sprintf('<figure class="tsr-media__item"><img src="%s" alt=""><figcaption>%s</figcaption></figure>', esc_url($thumb), esc_html(Arr::toString($image['title'] ?? '')))
                : '<p class="tsr-media__empty">'.esc_html__('No image selected', 'tesserae').'</p>',
            esc_html__('Choose', 'tesserae'),
            \is_array($image) ? '' : ' hidden',
            esc_html__('Remove', 'tesserae'),
        );
    }
}
