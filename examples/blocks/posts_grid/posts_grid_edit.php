<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/*
 * The edit-mode variant of this block.
 *
 * "Latest posts" is a query, so what an editor sees on the page is a snapshot.
 * This version says so, and otherwise renders exactly the same markup.
 */
?>
<?php
/** @var BlockContext $block */
if ('latest' === $block->field('mode', 'latest')) { ?>
    <p class="posts-grid__note">
        <?php
        printf(
            // translators: 1: number of posts, 2: post type.
            esc_html__('Showing the %1$d most recent %2$s — this updates itself as content is published.', 'tesserae-starter'),
            Arr::toInt($block->field('count', 3), 3) ?? 3,
            esc_html(Arr::toString($block->field('post_type', 'post'))),
        );
    ?>
    </p>
<?php } ?>

<?php include __DIR__.'/posts_grid.php'; ?>
