<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/** @var BlockContext $block */
$classes = [
    'media-text',
    'media-text--'.Arr::toString($block->field('layout', 'left')),
    'media-text--ratio-'.Arr::toString($block->field('ratio', '4-3')),
];

if ($block->field('tone')) {
    $classes[] = 'media-text--tone';
}

$link = $block->field('link');
?>
<div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <div class="ts-shell media-text__inner">
        <figure class="media-text__media" <?php tesserae_editable('image'); ?>>
            <?php tesserae_the_image($block->field('image'), ['class' => 'media-text__image']); ?>
        </figure>

        <div class="media-text__body">
            <?php if ($block->has('heading')) { ?>
                <h2 <?php tesserae_editable('heading'); ?>><?php tesserae_the_field('heading'); ?></h2>
            <?php } ?>

            <div <?php tesserae_editable('content'); ?>><?php echo wp_kses_post(Arr::toString($block->field('content', ''))); ?></div>

            <?php if (is_array($link)) { ?>
                <a class="ts-button" <?php tesserae_the_link_attrs($link); ?>><?php echo esc_html(Arr::toString($link['title'] ?? '')); ?></a>
            <?php } ?>
        </div>
    </div>
</div>
