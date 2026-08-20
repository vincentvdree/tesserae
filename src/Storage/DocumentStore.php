<?php

declare(strict_types=1);

namespace Tesserae\Storage;

use Tesserae\Blocks\BlockRegistry;
use Tesserae\Support\Arr;

/**
 * Reads and writes the block document for a post.
 *
 * Everything is stored in a single JSON meta value: one row per post, no
 * post_content involvement, and no `wp_postmeta` explosion of `field_x_y_0_z`
 * keys to reverse-engineer later.
 */
final class DocumentStore
{
    public const META_KEY = '_tesserae_blocks';
    public const TEXT_META_KEY = '_tesserae_text';

    /** @var array<int, Document> */
    private array $cache = [];

    public function __construct(private readonly BlockRegistry $registry) {}

    public function load(int $postId): Document
    {
        if (isset($this->cache[$postId])) {
            return $this->cache[$postId];
        }

        $raw = get_post_meta($postId, self::META_KEY, true);
        $document = Document::fromJson($raw);

        /**
         * Filter the document loaded for a post.
         */
        $filtered = apply_filters('tesserae/load_document', $document, $postId);

        return $this->cache[$postId] = $filtered instanceof Document ? $filtered : $document;
    }

    public function has(int $postId): bool
    {
        return !$this->load($postId)->isEmpty();
    }

    /**
     * Sanitises every value through its field definition before saving, so a
     * hand-crafted REST payload cannot smuggle anything past the field types.
     */
    public function save(int $postId, Document $document): Document
    {
        $blocks = [];
        $text = [];

        foreach ($document as $instance) {
            $definition = $this->registry->get($instance->type);

            if (null === $definition) {
                continue;
            }

            $values = $definition->fields()->sanitize($instance->values);
            $clean = new BlockInstance($instance->id, $instance->type, $values, $this->sanitizeSettings($instance->settings));
            $blocks[] = $clean;
            $text = array_merge($text, $definition->fields()->toText($values));
        }

        $clean = new Document($blocks);

        do_action('tesserae/before_save', $postId, $clean);

        if ($clean->isEmpty()) {
            delete_post_meta($postId, self::META_KEY);
            delete_post_meta($postId, self::TEXT_META_KEY);
        } else {
            update_post_meta($postId, self::META_KEY, wp_slash((string) wp_json_encode($clean->toArray())));
            update_post_meta($postId, self::TEXT_META_KEY, wp_slash(trim(implode("\n", $text))));
        }

        unset($this->cache[$postId]);
        $this->cache[$postId] = $clean;

        do_action('tesserae/after_save', $postId, $clean);

        return $clean;
    }

    public function flush(?int $postId = null): void
    {
        if (null === $postId) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$postId]);
    }

    /**
     * Duplicating a post has to duplicate the block ids too, otherwise the two
     * posts share anchors and the editor's undo history gets confusing.
     */
    public function copy(int $fromPostId, int $toPostId): void
    {
        $document = $this->load($fromPostId);
        $blocks = [];

        foreach ($document as $instance) {
            $blocks[] = new BlockInstance(BlockInstance::generateId(), $instance->type, $instance->values, $instance->settings);
        }

        $this->save($toPostId, new Document($blocks));
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function sanitizeSettings(array $settings): array
    {
        return [
            'anchor' => sanitize_title(Arr::toString($settings['anchor'] ?? '')),
            'class' => implode(' ', array_filter(array_map(
                'sanitize_html_class',
                preg_split('/\s+/', Arr::toString($settings['class'] ?? '')) ?: [],
            ))),
            'hidden' => Arr::toBool($settings['hidden'] ?? false),
        ];
    }
}
