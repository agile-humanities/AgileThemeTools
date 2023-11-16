<?php

namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\View;

class Card extends AbstractHelper
{
    /**
     * Load a custom CKEditor library on a page.
     */
    public function __invoke() {

    }

    public function render($item,$containerElement = '<div>') {
    /** @var View $view */
    $view = $this->getView();
        return $view->partial('agile-item/card', [
            'item' => $item,
            'containerElement' => trim($containerElement,'<>'),
        ]);
    }
}