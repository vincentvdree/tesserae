<?php

declare(strict_types=1);

namespace Tesserae\Options;

/**
 * Builds the HTML for one options page inside the "Site Options" dialog.
 *
 * The markup mirrors {@see \Tesserae\Editor\FormRenderer}: the same tab strip
 * and `.tsr-form__body` field grid, so the block editor's CSS applies without
 * change. Tab buttons target the `tesserae-options-modal` controller instead
 * of `tesserae-modal`, since the dialog is not scoped to one block instance.
 */
final class FormRenderer
{
    /**
     * @param array<string, mixed> $values
     */
    public function render(OptionsPage $page, array $values): string
    {
        $tabs = $page->fields()->tabs();

        $html = '<div class="tsr-form" data-tesserae-options-form>';

        if ([] !== $tabs) {
            $html .= '<nav class="tsr-tabs" role="tablist">';

            foreach ($tabs as $position => $tab) {
                $html .= \sprintf(
                    '<button type="button" class="tsr-tab%s" role="tab" data-action="tesserae-options-modal#selectTab" data-tab="%s">%s</button>',
                    0 === $position ? ' is-active' : '',
                    esc_attr($tab['slug']),
                    esc_html($tab['label']),
                );
            }

            $html .= '</nav>';
        }

        $html .= '<div class="tsr-form__body" data-tesserae-scope data-tesserae-values-scope>';
        $html .= $page->fields()->render($page->fields()->sanitize($values));

        return $html.'</div></div>';
    }
}
