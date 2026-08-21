<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

/*
 * Crawlers and screen reader friendly fallback: everything expanded, no
 * JavaScript involved.
 */

/** @var BlockContext $block */
$items = Arr::toArray($block->field('items', []));
?>
<div class="accordion ts-shell ts-shell--narrow">
    <h2><?php tesserae_the_field('heading'); ?></h2>

    <dl>
        <?php foreach ($items as $item) { ?>
            <?php $item = Arr::toMap($item); ?>
            <dt><?php echo esc_html(Arr::toString($item['question'] ?? '')); ?></dt>
            <dd><?php echo wp_kses_post(Arr::toString($item['answer'] ?? '')); ?></dd>
        <?php } ?>
    </dl>
</div>
