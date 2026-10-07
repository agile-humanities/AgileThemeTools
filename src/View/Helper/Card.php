<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\DisplayMode\Manager;
use Laminas\View\Helper\AbstractHelper;

/**
 * $this->card($thing, $options)  the card display mode (DisplayMode\Card) for an item, item set, media or site page.
 *
 * Options: containerElement (div), cardStyle (card), classes, slot, thumbnailType, label, title, subtitle, summary.
 * The older render($item, '<li>') form still works.
 */
class Card extends AbstractHelper
{
    protected Manager $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function __invoke($thing = null, array $options = [])
    {
        if ($thing === null) {
            return $this;
        }
        return $this->render($thing, $options);
    }

    public function render($thing, $options = []): string
    {
        if (is_string($options)) {
            $options = ['containerElement' => trim($options, '<>')]; // the old signature: render($item, '<li>')
        }
        $card = $this->manager->get('card');
        if (!$card->supports($thing)) {
            return '';
        }
        return $card->render($this->getView(), $card->prepare($this->getView(), $thing, $options), $options);
    }

    /** The prepared data without rendering, for templates that lay a card out themselves. */
    public function prepare($thing, array $options = []): array
    {
        $card = $this->manager->get('card');
        return $card->supports($thing) ? $card->prepare($this->getView(), $thing, $options) : [];
    }
}
