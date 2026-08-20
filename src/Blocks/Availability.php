<?php

declare(strict_types=1);

namespace Tesserae\Blocks;

use Tesserae\Storage\Document;
use Tesserae\Support\Arr;

/**
 * Decides which blocks may be placed where.
 *
 * Rules live under `rules:` in a block's YAML file and are evaluated against
 * the post being edited plus the blocks already on it.
 */
final class Availability
{
    public function __construct(private readonly BlockRegistry $registry) {}

    /**
     * @return array{allowed: bool, reason: string}
     */
    public function check(BlockDefinition $block, int $postId, Document $document, int $index = -1): array
    {
        $rules = $block->rules();
        $post = get_post($postId);

        if (Arr::toBool($rules['hidden'] ?? false)) {
            return self::deny(__('Not available in the block picker.', 'tesserae'));
        }

        $postTypes = self::slugs($rules['post_types'] ?? $rules['post_type'] ?? []);

        if ([] !== $postTypes && (!$post instanceof \WP_Post || !\in_array($post->post_type, $postTypes, true))) {
            return self::deny(\sprintf(
                // translators: %s: comma separated list of post types.
                __('Only available on: %s', 'tesserae'),
                implode(', ', $postTypes),
            ));
        }

        $excluded = self::slugs($rules['not_post_types'] ?? []);

        if ($post instanceof \WP_Post && \in_array($post->post_type, $excluded, true)) {
            return self::deny(__('Not available on this post type.', 'tesserae'));
        }

        $templates = array_map(static fn (mixed $t): string => Arr::toString($t), Arr::wrap($rules['templates'] ?? $rules['template'] ?? []));

        if ([] !== $templates) {
            $current = $post instanceof \WP_Post ? (string) get_page_template_slug($post) : '';
            $current = '' !== $current ? $current : 'default';

            if (!\in_array($current, $templates, true)) {
                return self::deny(__('Not available on this page template.', 'tesserae'));
            }
        }

        $capability = Arr::toString($rules['capability'] ?? '');

        if ('' !== $capability && !current_user_can($capability)) {
            return self::deny(__('You do not have permission to use this block.', 'tesserae'));
        }

        $max = Arr::toInt($rules['max'] ?? $rules['max_instances'] ?? null, null);

        if (null !== $max && $max > 0) {
            $used = $document->counts()[$block->type] ?? 0;

            if ($used >= $max) {
                return self::deny(\sprintf(
                    // translators: %d: maximum number of allowed placements.
                    _n('Already used the maximum of %d time.', 'Already used the maximum of %d times.', $max, 'tesserae'),
                    $max,
                ));
            }
        }

        $position = strtolower(Arr::toString($rules['position'] ?? 'any'));

        if ('first' === $position && $index > 0) {
            return self::deny(__('Only allowed as the first block.', 'tesserae'));
        }

        if ('last' === $position && -1 !== $index && $index < $document->count()) {
            return self::deny(__('Only allowed as the last block.', 'tesserae'));
        }

        $requires = self::slugs($rules['requires'] ?? []);

        foreach ($requires as $required) {
            if (!isset($document->counts()[$required])) {
                $label = $this->registry->get($required)?->label() ?? $required;

                return self::deny(\sprintf(
                    // translators: %s: block label.
                    __('Needs a "%s" block on the page first.', 'tesserae'),
                    $label,
                ));
            }
        }

        foreach (self::slugs($rules['not_with'] ?? []) as $conflict) {
            if (isset($document->counts()[$conflict])) {
                $label = $this->registry->get($conflict)?->label() ?? $conflict;

                return self::deny(\sprintf(
                    // translators: %s: block label.
                    __('Cannot be combined with "%s".', 'tesserae'),
                    $label,
                ));
            }
        }

        /**
         * Final say on whether a block may be placed.
         *
         * @param array{allowed: bool, reason: string} $result
         * @param BlockDefinition                      $block
         * @param int                                  $postId
         * @param Document                             $document
         * @param int                                  $index
         */
        $result = apply_filters('tesserae/block_available', ['allowed' => true, 'reason' => ''], $block, $postId, $document, $index);

        return ['allowed' => $result['allowed'], 'reason' => $result['reason']];
    }

    /**
     * The block catalogue for one post, as the editor's picker shows it.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(int $postId, Document $document, int $index = -1): array
    {
        $catalogue = [];

        foreach ($this->registry->all() as $block) {
            $check = $this->check($block, $postId, $document, $index);

            if (Arr::toBool($block->rules()['hidden'] ?? false)) {
                continue;
            }

            $catalogue[] = array_merge($block->toArray(), [
                'allowed' => $check['allowed'],
                'reason' => $check['reason'],
            ]);
        }

        return $catalogue;
    }

    /**
     * @return array{allowed: bool, reason: string}
     */
    private static function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }

    /**
     * @return list<string>
     */
    private static function slugs(mixed $value): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $item): string => sanitize_key(Arr::toString($item)),
            Arr::wrap($value),
        )));
    }
}
