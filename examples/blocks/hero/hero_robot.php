<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/*
 * Rendered for crawlers and other machine readers: the same information without
 * the presentation layer.
 */

/** @var BlockContext $block */
$buttons = Arr::toArray($block->field('buttons', []));
?>
<div class="ts-shell hero hero--plain">
    <h1><?php tesserae_the_field('title'); ?></h1>

    <?php if ($block->has('intro')) { ?>
        <p><?php tesserae_the_field('intro'); ?></p>
    <?php } ?>

    <?php foreach ($buttons as $button) { ?>
        <?php $button = Arr::toMap($button); ?>
        <?php $link = Arr::toMap($button['link'] ?? []); ?>
        <?php if ('' === Arr::toString($link['url'] ?? '')) {
            continue;
        } ?>
        <p><a <?php tesserae_the_link_attrs($link); ?>><?php echo esc_html(Arr::toString($link['title'] ?? '')); ?></a></p>
    <?php } ?>
</div>
