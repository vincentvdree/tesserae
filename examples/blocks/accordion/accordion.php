<?php

use Tesserae\Blocks\BlockContext;
use Tesserae\Support\Arr;

?>
<div class="accordion">
    <div class="ts-shell ts-shell--narrow">
        <?php
        /** @var BlockContext $block */
        if ($block->has('heading')) { ?>
            <h2 class="accordion__heading" <?php tesserae_editable('heading'); ?>><?php tesserae_the_field('heading'); ?></h2>
        <?php } ?>

        <div class="accordion__list" <?php tesserae_editable('items'); ?>>
            <?php foreach (Arr::toArray($block->field('items', [])) as $index => $item) { ?>
                <?php $item = Arr::toMap($item); ?>
                <?php $id = $block->uid('q'.$index); ?>
                <div class="accordion__item" data-accordion-target="item">
                    <h3 class="accordion__question">
                        <button type="button" class="accordion__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($id); ?>" data-action="accordion#toggle">
                            <span><?php echo esc_html(Arr::toString($item['question'] ?? '')); ?></span>
                            <span class="accordion__chevron" aria-hidden="true">＋</span>
                        </button>
                    </h3>
                    <div class="accordion__answer" id="<?php echo esc_attr($id); ?>" hidden data-accordion-target="panel">
                        <?php echo wp_kses_post(Arr::toString($item['answer'] ?? '')); ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
