<?php

declare(strict_types=1);

/**
 * The template API. Everything a theme needs lives in this file.
 */

use Tesserae\Blocks\BlockContext;
use Tesserae\Plugin;
use Tesserae\Storage\BlockInstance;
use Tesserae\Support\Arr;

if (!function_exists('tesserae')) {
    function tesserae(): Plugin
    {
        return Plugin::instance();
    }
}

if (!function_exists('tesserae_render')) {
    /**
     * Renders the blocks of a post. This is the one call a page template needs.
     */
    function tesserae_render(?int $post_id = null): void
    {
        echo tesserae_get_render($post_id); // phpcs:ignore WordPress.Security.EscapeOutput -- block templates escape their own output.
    }
}

if (!function_exists('tesserae_get_render')) {
    function tesserae_get_render(?int $post_id = null): string
    {
        return tesserae()->renderer->renderPost($post_id);
    }
}

if (!function_exists('tesserae_has_blocks')) {
    function tesserae_has_blocks(?int $post_id = null): bool
    {
        return tesserae()->documents->has($post_id ?? (int) get_the_ID());
    }
}

if (!function_exists('tesserae_blocks')) {
    /**
     * The raw block list of a post — handy for building navigation from anchors
     * or for querying content across posts.
     *
     * @return list<BlockInstance>
     */
    function tesserae_blocks(?int $post_id = null): array
    {
        return tesserae()->documents->load($post_id ?? (int) get_the_ID())->blocks();
    }
}

if (!function_exists('tesserae_block')) {
    /**
     * The block currently being rendered, or null outside a block template.
     */
    function tesserae_block(): ?BlockContext
    {
        return tesserae()->renderer->currentContext();
    }
}

if (!function_exists('tesserae_field')) {
    /**
     * A prepared field value from the block being rendered. Dot paths work:
     * `tesserae_field('cta.link.url')`.
     */
    function tesserae_field(string $path, mixed $default = null): mixed
    {
        return tesserae_block()?->field($path, $default) ?? $default;
    }
}

if (!function_exists('tesserae_has_field')) {
    function tesserae_has_field(string $path): bool
    {
        return (bool) tesserae_block()?->has($path);
    }
}

if (!function_exists('tesserae_the_field')) {
    /**
     * Echoes a scalar field value, escaped for HTML.
     */
    function tesserae_the_field(string $path, string $default = ''): void
    {
        $value = tesserae_field($path, $default);

        echo esc_html(is_scalar($value) ? (string) $value : '');
    }
}

if (!function_exists('tesserae_is_editing')) {
    function tesserae_is_editing(): bool
    {
        return tesserae()->session->isEditing();
    }
}

if (!function_exists('tesserae_edit_url')) {
    function tesserae_edit_url(?int $post_id = null): string
    {
        return tesserae()->session->editUrl($post_id ?? (int) get_the_ID());
    }
}

if (!function_exists('tesserae_editable')) {
    /**
     * Marks an element in a block template as the entry point for one field:
     * clicking it in edit mode opens the modal with that field focused.
     *
     * <h1 <?php tesserae_editable('title'); ?>>…</h1>
     */
    function tesserae_editable(string $field): void
    {
        if (!tesserae_is_editing()) {
            return;
        }

        printf(' data-tesserae-edit-field="%s" tabindex="0" role="button"', esc_attr($field));
    }
}

if (!function_exists('tesserae_image')) {
    /**
     * Renders an <img> for a prepared image value.
     *
     * @param mixed                $image an image field value
     * @param array<string, mixed> $attrs extra HTML attributes
     */
    function tesserae_image(mixed $image, array $attrs = []): string
    {
        $image = Arr::toArray($image);
        $id = Arr::toInt($image['id'] ?? null, 0) ?? 0;

        if ($id <= 0) {
            return '';
        }

        $size = Arr::toString($attrs['size'] ?? $image['size'] ?? 'large');
        $alt = Arr::toString($attrs['alt'] ?? $image['alt'] ?? '');

        $imageAttrs = ['alt' => $alt];

        if (isset($attrs['class'])) {
            $imageAttrs['class'] = Arr::toString($attrs['class']);
        }

        if (isset($attrs['srcset'])) {
            $imageAttrs['srcset'] = Arr::toString($attrs['srcset']);
        }

        if (isset($attrs['sizes'])) {
            $imageAttrs['sizes'] = Arr::toString($attrs['sizes']);
        }

        if (isset($attrs['loading'])) {
            $imageAttrs['loading'] = Arr::toString($attrs['loading']);
        }

        if (isset($attrs['decoding'])) {
            $imageAttrs['decoding'] = Arr::toString($attrs['decoding']);
        }

        if (isset($attrs['fetchpriority'])) {
            $imageAttrs['fetchpriority'] = Arr::toString($attrs['fetchpriority']);
        }

        return (string) wp_get_attachment_image($id, $size, false, $imageAttrs);
    }
}

if (!function_exists('tesserae_the_image')) {
    /**
     * @param array<string, mixed> $attrs
     */
    function tesserae_the_image(mixed $image, array $attrs = []): void
    {
        echo tesserae_image($image, $attrs); // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_get_attachment_image().
    }
}

if (!function_exists('tesserae_link_attrs')) {
    /**
     * Attributes for an <a> built from a link field value.
     */
    function tesserae_link_attrs(mixed $link): string
    {
        $link = Arr::toArray($link);
        $url = Arr::toString($link['url'] ?? '');

        if ('' === $url) {
            return '';
        }

        $attrs = sprintf(' href="%s"', esc_url($url));
        $target = Arr::toString($link['target'] ?? '');

        if ('' !== $target) {
            $attrs .= sprintf(' target="%s" rel="%s"', esc_attr($target), esc_attr(Arr::toString($link['rel'] ?? 'noopener noreferrer')));
        }

        return $attrs;
    }
}

if (!function_exists('tesserae_the_link_attrs')) {
    function tesserae_the_link_attrs(mixed $link): void
    {
        echo tesserae_link_attrs($link); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
    }
}

if (!function_exists('tesserae_option')) {
    /**
     * A prepared value from an options page — `tesserae_option('site', 'phone')`.
     * Dot paths work, same as {@see tesserae_field()}. Omit `$path` to get the
     * whole page as an array.
     */
    function tesserae_option(string $page, string $path = '', mixed $default = null): mixed
    {
        $values = tesserae()->optionsStore->prepared($page);

        if ('' === $path) {
            return $values;
        }

        $value = Arr::get($values, $path, $default);

        return null === $value ? $default : $value;
    }
}

if (!function_exists('tesserae_has_option')) {
    function tesserae_has_option(string $page, string $path): bool
    {
        $value = tesserae_option($page, $path);

        return !(null === $value || '' === $value || [] === $value || false === $value);
    }
}

if (!function_exists('tesserae_the_option')) {
    /**
     * Echoes a scalar option value, escaped for HTML.
     */
    function tesserae_the_option(string $page, string $path, string $default = ''): void
    {
        $value = tesserae_option($page, $path, $default);

        echo esc_html(is_scalar($value) ? (string) $value : '');
    }
}
