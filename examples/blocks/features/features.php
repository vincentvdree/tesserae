<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/** @var BlockContext $block */
$columns = Arr::toString($block->field('columns', '3'));
?>
<div class="features">
    <div class="ts-shell">
        <?php if ($block->has('heading') || $block->has('intro')) { ?>
            <header class="features__head">
                <?php if ($block->has('heading')) { ?>
                    <h2 <?php tesserae_editable('heading'); ?>><?php tesserae_the_field('heading'); ?></h2>
                <?php } ?>

                <?php if ($block->has('intro')) { ?>
                    <p class="ts-lede" <?php tesserae_editable('intro'); ?>><?php tesserae_the_field('intro'); ?></p>
                <?php } ?>
            </header>
        <?php } ?>

        <ul class="features__grid" style="--features-columns: <?php echo esc_attr($columns); ?>" <?php tesserae_editable('items'); ?>>
            <?php foreach (Arr::toArray($block->field('items', [])) as $item) { ?>
                <?php $item = Arr::toMap($item); ?>
                <li class="features__item">
                    <?php if (!empty($item['icon'])) { ?>
                        <span class="features__icon" aria-hidden="true"><?php echo esc_html(Arr::toString($item['icon'])); ?></span>
                    <?php } ?>
                    <h3 class="features__title"><?php echo esc_html(Arr::toString($item['title'] ?? '')); ?></h3>
                    <p class="features__text"><?php echo esc_html(Arr::toString($item['text'] ?? '')); ?></p>
                </li>
            <?php } ?>
        </ul>
    </div>
</div>
