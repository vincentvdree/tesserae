<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/** @var BlockContext $block */
$posts = [];

if ('picked' === $block->field('mode', 'latest')) {
    $posts = array_values(array_filter(
        Arr::toArray($block->field('picked', [])),
        static fn (mixed $item): bool => $item instanceof WP_Post,
    ));
} else {
    $query = new WP_Query([
        'post_type' => Arr::toString($block->field('post_type', 'post')),
        'posts_per_page' => Arr::toInt($block->field('count', 3), 3) ?? 3,
        'ignore_sticky_posts' => true,
        'post_status' => 'publish',
        'no_found_rows' => true,
    ]);

    $posts = $query->posts;
}
?>
<div class="posts-grid">
    <div class="ts-shell">
        <?php if ($block->has('heading')) { ?>
            <h2 class="posts-grid__heading" <?php tesserae_editable('heading'); ?>><?php tesserae_the_field('heading'); ?></h2>
        <?php } ?>

        <?php if ([] === $posts) { ?>
            <p class="ts-empty-note"><?php esc_html_e('No posts to show yet.', 'tesserae-starter'); ?></p>
        <?php } else { ?>
            <ul class="posts-grid__list" <?php tesserae_editable('mode'); ?>>
                <?php foreach ($posts as $item) { ?>
                    <li class="posts-grid__item">
                        <a class="posts-grid__link" href="<?php echo esc_url((string) get_permalink($item)); ?>">
                            <?php if (has_post_thumbnail($item)) { ?>
                                <span class="posts-grid__thumb"><?php echo get_the_post_thumbnail($item, 'medium_large'); ?></span>
                            <?php } ?>
                            <span class="posts-grid__meta"><?php echo esc_html(Arr::toString(get_the_date('', $item))); ?></span>
                            <span class="posts-grid__title"><?php echo esc_html(get_the_title($item)); ?></span>

                            <?php if ($block->field('show_excerpt')) { ?>
                                <span class="posts-grid__excerpt"><?php echo esc_html(wp_trim_words((string) get_the_excerpt($item), 22)); ?></span>
                            <?php } ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
</div>
