<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/** @var BlockContext $block */
$images = Arr::toArray($block->field('images', []));
?>
<div class="gallery<?php echo $block->field('crop') ? ' gallery--crop' : ''; ?>" style="--gallery-columns: <?php echo esc_attr(Arr::toString($block->field('columns', '3'))); ?>">
    <div class="ts-shell">
        <div class="gallery__grid" <?php tesserae_editable('images'); ?>>
            <?php foreach ($images as $index => $image) { ?>
                <?php $image = Arr::toMap($image); ?>
                <button type="button" class="gallery__item" data-action="gallery#open" data-index="<?php echo esc_attr((string) $index); ?>" data-full="<?php echo esc_url(Arr::toString($image['full'] ?? $image['url'] ?? '')); ?>">
                    <?php tesserae_the_image($image, ['class' => 'gallery__image', 'loading' => 'lazy']); ?>
                </button>
            <?php } ?>
        </div>

        <?php if ($block->has('caption')) { ?>
            <p class="gallery__caption" <?php tesserae_editable('caption'); ?>><?php tesserae_the_field('caption'); ?></p>
        <?php } ?>
    </div>

    <dialog class="gallery__lightbox" data-gallery-target="lightbox" data-action="click->gallery#close">
        <img alt="" data-gallery-target="lightboxImage">
    </dialog>
</div>
