<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Support\Arr;

class GalleryField extends ImageField
{
    public static function type(): string
    {
        return 'gallery';
    }

    public function defaultValue(): mixed
    {
        return array_values(array_filter(array_map(
            static fn (mixed $id): int => Arr::toInt($id, 0) ?? 0,
            Arr::wrap($this->config('default')),
        )));
    }

    public function sanitize(mixed $value): mixed
    {
        $ids = [];
        $max = Arr::toInt($this->config('max'), null);

        foreach (Arr::wrap($value) as $item) {
            if (\is_array($item)) {
                $item = $item['id'] ?? null;
            }

            $id = Arr::toInt($item, 0) ?? 0;

            if ($id > 0 && 'attachment' === get_post_type($id)) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        return null !== $max ? \array_slice($ids, 0, $max) : $ids;
    }

    public function prepare(mixed $value): mixed
    {
        $size = Arr::toString($this->config('size') ?? 'large');
        $images = [];

        foreach (Arr::wrap($this->sanitize($value)) as $id) {
            $image = self::attachment(Arr::toInt($id, 0) ?? 0, $size);

            if (null !== $image) {
                $images[] = $image;
            }
        }

        return $images;
    }

    public function toText(mixed $value): string
    {
        return '';
    }

    protected function renderControl(mixed $value): string
    {
        $items = '';

        foreach (Arr::wrap($this->sanitize($value)) as $id) {
            $id = Arr::toInt($id, 0) ?? 0;

            $items .= \sprintf(
                '<figure class="tsr-media__item" data-id="%d" draggable="true"><img src="%s" alt="">'
                .'<button type="button" class="tsr-media__remove" data-action="tesserae-media#removeOne" data-id="%d" aria-label="%s">&times;</button></figure>',
                $id,
                esc_url((string) wp_get_attachment_image_url($id, 'thumbnail')),
                $id,
                esc_attr__('Remove image', 'tesserae'),
            );
        }

        return \sprintf(
            '<div class="tsr-media tsr-media--gallery" data-controller="tesserae-media" data-tesserae-media-multiple-value="true" data-tesserae-media-kind-value="gallery" data-tesserae-media-accept-value="%s">'
            .'<input type="hidden" data-tesserae-value-type="json" data-tesserae-media-target="input" value="%s" %s>'
            .'<div class="tsr-media__list tsr-media__list--grid" data-tesserae-media-target="list">%s</div>'
            .'<div class="tsr-media__actions">'
            .'<button type="button" class="tsr-btn" data-action="tesserae-media#open">%s</button>'
            .'<button type="button" class="tsr-btn tsr-btn--ghost" data-action="tesserae-media#clear">%s</button>'
            .'</div></div>',
            esc_attr($this->mimeFilter()),
            esc_attr((string) wp_json_encode($this->sanitize($value))),
            $this->inputAttributes(),
            $items ?: '<p class="tsr-media__empty">'.esc_html__('No images selected', 'tesserae').'</p>',
            esc_html__('Add images', 'tesserae'),
            esc_html__('Clear', 'tesserae'),
        );
    }
}
