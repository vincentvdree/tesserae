<?php

declare(strict_types=1);

namespace Tesserae\Fields\Types;

use Tesserae\Fields\Field;
use Tesserae\Support\Arr;

/**
 * Hand-picked content: "select x amount of WP_Posts".
 */
class PostsField extends Field
{
    public static function type(): string
    {
        return 'posts';
    }

    /**
     * @return list<string>
     */
    public function postTypes(): array
    {
        $types = array_map(
            static fn (mixed $type): string => sanitize_key(Arr::toString($type)),
            Arr::wrap($this->config('post_type') ?? $this->config('post_types') ?? 'post'),
        );

        return array_values(array_filter($types));
    }

    public function maxItems(): ?int
    {
        return Arr::toInt($this->config('max'), null);
    }

    public function defaultValue(): mixed
    {
        return $this->isMultiple() ? [] : null;
    }

    public function sanitize(mixed $value): mixed
    {
        $ids = [];
        $allowed = $this->postTypes();

        foreach (Arr::wrap($value) as $item) {
            if (\is_array($item)) {
                $item = $item['id'] ?? null;
            }

            $id = Arr::toInt($item, 0) ?? 0;
            $type = $id > 0 ? get_post_type($id) : false;

            if (\is_string($type) && ([] === $allowed || \in_array($type, $allowed, true))) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        $max = $this->maxItems();

        if ($this->isMultiple()) {
            return null !== $max ? \array_slice($ids, 0, $max) : $ids;
        }

        return $ids[0] ?? null;
    }

    public function prepare(mixed $value): mixed
    {
        $clean = $this->sanitize($value);

        if (!$this->isMultiple()) {
            $post = null === $clean ? null : get_post(Arr::toInt($clean, 0) ?? 0);

            return $post instanceof \WP_Post ? $post : null;
        }

        $posts = [];

        foreach (Arr::wrap($clean) as $id) {
            $post = get_post(Arr::toInt($id, 0) ?? 0);

            if ($post instanceof \WP_Post) {
                $posts[] = $post;
            }
        }

        return $posts;
    }

    public function toText(mixed $value): string
    {
        return '';
    }

    public function schema(): array
    {
        return array_merge(parent::schema(), [
            'post_types' => $this->postTypes(),
            'multiple' => $this->isMultiple(),
            'max' => $this->maxItems(),
        ]);
    }

    public static function renderChip(int $id): string
    {
        $post = get_post($id);

        if (!$post instanceof \WP_Post) {
            return '';
        }

        $type = get_post_type_object($post->post_type);

        return \sprintf(
            '<span class="tsr-chip" data-id="%d" draggable="true"><span class="tsr-chip__label">%s</span>'
            .'<span class="tsr-chip__meta">%s</span>'
            .'<button type="button" class="tsr-chip__remove" data-action="tesserae-posts#remove" data-id="%d" aria-label="%s">&times;</button></span>',
            $id,
            esc_html(get_the_title($post) ?: __('(no title)', 'tesserae')),
            esc_html($type instanceof \WP_Post_Type ? Arr::toString($type->labels->singular_name) : $post->post_type),
            $id,
            esc_attr__('Remove', 'tesserae'),
        );
    }

    protected function isMultiple(): bool
    {
        return Arr::toBool($this->config('multiple', \in_array(strtolower(Arr::toString($this->config('type') ?? 'posts')), ['posts', 'relationship'], true)));
    }

    protected function renderControl(mixed $value): string
    {
        $selected = Arr::wrap($this->sanitize($value));
        $items = '';

        foreach ($selected as $id) {
            $items .= self::renderChip(Arr::toInt($id, 0) ?? 0);
        }

        return \sprintf(
            '<div class="tsr-posts" data-controller="tesserae-posts" data-tesserae-posts-multiple-value="%s" data-tesserae-posts-types-value="%s" data-tesserae-posts-max-value="%s">'
            .'<input type="hidden" data-tesserae-value-type="json" data-tesserae-posts-target="input" value="%s" %s>'
            .'<div class="tsr-posts__chosen" data-tesserae-posts-target="chosen">%s</div>'
            .'<input type="search" class="tsr-input" placeholder="%s" data-tesserae-posts-target="search" data-action="input->tesserae-posts#search focus->tesserae-posts#search">'
            .'<ul class="tsr-posts__results" data-tesserae-posts-target="results" hidden></ul>'
            .'</div>',
            $this->isMultiple() ? 'true' : 'false',
            esc_attr((string) wp_json_encode($this->postTypes())),
            esc_attr((string) ($this->maxItems() ?? 0)),
            esc_attr((string) wp_json_encode($this->sanitize($value))),
            $this->inputAttributes(),
            $items ?: '<p class="tsr-posts__empty">'.esc_html__('Nothing selected yet', 'tesserae').'</p>',
            esc_attr__('Search content…', 'tesserae'),
        );
    }
}
