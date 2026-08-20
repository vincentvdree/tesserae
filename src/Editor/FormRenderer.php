<?php

declare(strict_types=1);

namespace Tesserae\Editor;

use Tesserae\Blocks\BlockDefinition;
use Tesserae\Storage\BlockInstance;

/**
 * Builds the HTML for the editor modal.
 *
 * The form is rendered server side so that field types stay a PHP concern:
 * a new field type ships its own markup and the editor's JavaScript never has
 * to learn about it.
 */
final class FormRenderer
{
    public function render(BlockDefinition $definition, BlockInstance $instance): string
    {
        $tabs = $definition->fields()->tabs();
        $hasSettings = $definition->supports('anchor', false) || $definition->supports('className', false);

        $html = '<div class="tsr-form" data-tesserae-form data-tesserae-block-type="'.esc_attr($definition->type).'">';

        if ([] !== $tabs || $hasSettings) {
            $html .= '<nav class="tsr-tabs" role="tablist">';

            foreach ($tabs as $position => $tab) {
                $html .= \sprintf(
                    '<button type="button" class="tsr-tab%s" role="tab" data-action="tesserae-modal#selectTab" data-tab="%s">%s</button>',
                    0 === $position ? ' is-active' : '',
                    esc_attr($tab['slug']),
                    esc_html($tab['label']),
                );
            }

            if ($hasSettings) {
                $html .= \sprintf(
                    '<button type="button" class="tsr-tab%s tsr-tab--settings" role="tab" data-action="tesserae-modal#selectTab" data-tab="__settings">%s</button>',
                    [] === $tabs ? ' is-active' : '',
                    esc_html__('Block settings', 'tesserae'),
                );
            }

            $html .= '</nav>';
        }

        $html .= '<div class="tsr-form__body" data-tesserae-scope data-tesserae-values-scope>';
        $html .= $definition->fields()->render($definition->fields()->sanitize($instance->values));
        $html .= '</div>';

        if ($hasSettings) {
            $html .= $this->renderSettings($definition, $instance, [] !== $tabs);
        }

        return $html.'</div>';
    }

    private function renderSettings(BlockDefinition $definition, BlockInstance $instance, bool $hidden): string
    {
        $html = \sprintf(
            '<div class="tsr-tab-panel" data-tesserae-tab-panel="__settings" data-tesserae-settings-scope%s>',
            $hidden ? ' hidden' : '',
        );

        if ($definition->supports('anchor', false)) {
            $html .= \sprintf(
                '<div class="tsr-field tsr-field--text"><div class="tsr-field__head"><span class="tsr-field__label">%s</span></div>'
                .'<div class="tsr-field__control"><input type="text" class="tsr-input" value="%s" data-tesserae-input data-tesserae-setting="anchor" placeholder="%s"></div>'
                .'<p class="tsr-field__hint">%s</p></div>',
                esc_html__('Anchor', 'tesserae'),
                esc_attr($instance->anchor()),
                esc_attr__('about-us', 'tesserae'),
                esc_html__('Links to this block become yourpage/#anchor.', 'tesserae'),
            );
        }

        if ($definition->supports('className', false)) {
            $html .= \sprintf(
                '<div class="tsr-field tsr-field--text"><div class="tsr-field__head"><span class="tsr-field__label">%s</span></div>'
                .'<div class="tsr-field__control"><input type="text" class="tsr-input" value="%s" data-tesserae-input data-tesserae-setting="class"></div>'
                .'<p class="tsr-field__hint">%s</p></div>',
                esc_html__('Extra CSS classes', 'tesserae'),
                esc_attr($instance->className()),
                esc_html__('Space separated, added to the block wrapper.', 'tesserae'),
            );
        }

        $html .= \sprintf(
            '<div class="tsr-field tsr-field--toggle"><div class="tsr-field__head"><span class="tsr-field__label">%s</span></div>'
            .'<div class="tsr-field__control"><label class="tsr-toggle"><input type="checkbox"%s data-tesserae-input data-tesserae-setting="hidden">'
            .'<span class="tsr-toggle__track"></span><span class="tsr-toggle__text">%s</span></label></div></div>',
            esc_html__('Visibility', 'tesserae'),
            checked($instance->isHidden(), true, false),
            esc_html__('Hide this block on the live site', 'tesserae'),
        );

        return $html.'</div>';
    }
}
