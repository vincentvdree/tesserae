<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/**
 * @var BlockContext         $block
 * @var array<string, mixed> $fields
 */
$image = $block->field('image');
$classes = [
    'hero',
    'hero--'.Arr::toString($block->field('align', 'center')),
    'hero--'.Arr::toString($block->field('height', 'medium')),
];

if (is_array($image)) {
    $classes[] = 'hero--has-image';
}

if ($block->field('overlay') && is_array($image)) {
    $classes[] = 'hero--overlay';
}
?>
<div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <?php if (is_array($image)) { ?>
        <div class="hero__media" aria-hidden="true" data-hero-target="media">
            <?php tesserae_the_image($image, ['class' => 'hero__image', 'loading' => 'eager', 'fetchpriority' => 'high']); ?>
        </div>
    <?php } ?>

    <div class="ts-shell hero__inner">
        <?php if ($block->has('eyebrow')) { ?>
            <span class="ts-eyebrow hero__eyebrow" <?php tesserae_editable('eyebrow'); ?>><?php tesserae_the_field('eyebrow'); ?></span>
        <?php } ?>

        <h1 class="hero__title" <?php tesserae_editable('title'); ?>><?php tesserae_the_field('title'); ?></h1>

        <?php if ($block->has('intro')) { ?>
            <p class="hero__intro ts-lede" <?php tesserae_editable('intro'); ?>><?php tesserae_the_field('intro'); ?></p>
        <?php } ?>

        <?php if ($block->has('buttons')) { ?>
            <div class="hero__buttons" <?php tesserae_editable('buttons'); ?>>
                <?php foreach (Arr::toArray($block->field('buttons', [])) as $button) { ?>
                    <?php $button = Arr::toMap($button); ?>
                    <?php $link = Arr::toMap($button['link'] ?? []); ?>
                    <?php if ('' === Arr::toString($link['url'] ?? '')) {
                        continue;
                    } ?>
                    <a class="ts-button<?php echo 'ghost' === ($button['style'] ?? '') ? ' ts-button--ghost' : ''; ?>" <?php tesserae_the_link_attrs($link); ?>>
                        <?php echo esc_html(Arr::toString($link['title'] ?? '')); ?>
                    </a>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
