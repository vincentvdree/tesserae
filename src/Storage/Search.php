<?php

declare(strict_types=1);

namespace Tesserae\Storage;

use Tesserae\Support\Arr;

/**
 * Makes block content findable through the normal WordPress search, without
 * mirroring anything into post_content.
 */
final class Search
{
    public function register(): void
    {
        add_filter('posts_search', [$this, 'extendSearch'], 10, 2);
        add_filter('posts_distinct', [$this, 'distinct'], 10, 2);
        add_filter('posts_join', [$this, 'join'], 10, 2);
    }

    public function join(string $join, \WP_Query $query): string
    {
        $wpdb = self::wpdb();

        if (!$this->applies($query) || str_contains($join, 'tesserae_text')) {
            return $join;
        }

        return $join.$wpdb->prepare(
            ' LEFT JOIN %i AS tesserae_text ON (tesserae_text.post_id = %i.ID AND tesserae_text.meta_key = %s) ',
            $wpdb->postmeta,
            $wpdb->posts,
            DocumentStore::TEXT_META_KEY,
        );
    }

    public function distinct(string $distinct, \WP_Query $query): string
    {
        return $this->applies($query) ? 'DISTINCT' : $distinct;
    }

    /**
     * Adds `OR tesserae_text.meta_value LIKE …` to each search term group that
     * WordPress already built for post_title/post_content.
     */
    public function extendSearch(string $search, \WP_Query $query): string
    {
        $wpdb = self::wpdb();

        if (!$this->applies($query) || '' === trim($search)) {
            return $search;
        }

        $terms = $query->get('search_terms');
        $terms = \is_array($terms) && [] !== $terms ? $terms : [Arr::toString($query->get('s'))];

        foreach ($terms as $term) {
            if (!\is_string($term)) {
                continue;
            }

            $like = '%'.$wpdb->esc_like($term).'%';
            $needle = $wpdb->prepare('(%i.post_content LIKE %s)', $wpdb->posts, $like);
            $replacement = $wpdb->prepare(
                '(%i.post_content LIKE %s) OR (tesserae_text.meta_value LIKE %s)',
                $wpdb->posts,
                $like,
                $like,
            );

            if (null === $needle || null === $replacement) {
                continue;
            }

            if (str_contains($search, $needle)) {
                $search = str_replace($needle, $replacement, $search);
            }
        }

        return $search;
    }

    private function applies(\WP_Query $query): bool
    {
        return $query->is_search() && '' !== Arr::toString($query->get('s'))
            && (bool) apply_filters('tesserae/search_blocks', true, $query);
    }

    private static function wpdb(): \wpdb
    {
        global $wpdb;

        if (!$wpdb instanceof \wpdb) {
            throw new \RuntimeException('$wpdb is not available.');
        }

        return $wpdb;
    }
}
