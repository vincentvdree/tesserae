<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/** @var BlockContext $block */
$link = $block->field('link');
?>
<div class="cta cta--<?php echo esc_attr(Arr::toString($block->field('tone', 'ink'))); ?> cta--<?php echo esc_attr(Arr::toString($block->field('align', 'center'))); ?>">
    <div class="ts-shell cta__inner">
        <div class="cta__text">
            <h2 class="cta__title" <?php tesserae_editable('title'); ?>><?php tesserae_the_field('title'); ?></h2>

            <?php if ($block->has('text')) { ?>
                <p class="cta__body" <?php tesserae_editable('text'); ?>><?php tesserae_the_field('text'); ?></p>
            <?php } ?>
        </div>

        <?php if (is_array($link)) { ?>
            <a class="ts-button cta__button" <?php tesserae_the_link_attrs($link); ?>><?php echo esc_html(Arr::toString($link['title'] ?? '')); ?></a>
        <?php } ?>
    </div>
</div>
