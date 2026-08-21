<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/** @var BlockContext $block */
$level = in_array($block->field('level'), ['h2', 'h3'], true) ? $block->field('level') : 'h2';
?>
<div class="rich-text ts-shell <?php echo 'narrow' === $block->field('width', 'narrow') ? 'ts-shell--narrow' : ''; ?>">
    <?php if ($block->has('heading')) { ?>
        <<?php echo esc_html($level); ?> class="rich-text__heading" <?php tesserae_editable('heading'); ?>><?php tesserae_the_field('heading'); ?></<?php echo esc_html($level); ?>>
    <?php } ?>

    <div class="rich-text__body" <?php tesserae_editable('content'); ?>>
        <?php echo wp_kses_post(Arr::toString($block->field('content', ''))); ?>
    </div>
</div>
