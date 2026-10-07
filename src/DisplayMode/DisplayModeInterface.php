<?php
namespace AgileThemeTools\DisplayMode;

use Laminas\View\Renderer\PhpRenderer;

/**
 * A display mode: an alternative presentation of a resource (item, item set, media) or a site page, for a context
 * other than its own page — a card in a list, a teaser, a row. Built the way Omeka builds resource page block
 * layouts: modes are registered in the module configuration (`display_modes`), the data is prepared in PHP, and a
 * partial renders it, which a theme overrides for markup alone.
 *
 *   $this->displayMode('card', $item, ['containerElement' => 'li'])
 *   $this->card($item)                                            // the shorthand for the card mode
 */
interface DisplayModeInterface
{
    public function getLabel(): string;

    /** Whether this mode can present the given thing (a resource representation, a site page, or the module's page array). */
    public function supports($thing): bool;

    /**
     * Everything the template needs, as a plain array: no representation calls happen in the template.
     *
     * @param mixed $thing
     */
    public function prepare(PhpRenderer $view, $thing, array $options = []): array;

    /** Renders prepared data (the partial, overridable by the theme). */
    public function render(PhpRenderer $view, array $data, array $options = []): string;
}
