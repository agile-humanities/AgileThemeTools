<?php

namespace AgileThemeTools\Form\Element;

use Laminas\Form\Element\Select;

class SlideViewerCompositionSelect extends Select {

    function __construct($name = 'o:block[__blockIndex__][o:data][composition]', $options = [])
    {
        parent::__construct($name, $options);
        $this->setLabel('Select a composition');
        $this->setValueOptions(count($options) > 0 ? $options: $this->getTemplateValueOptions());
    }

    public function getTemplateValueOptions() {
        return([
            'slide-viewer-with-title-default' => 'Default',
            'slide-viewer-with-title-overlay' => 'Overlay (slide viewer with container overlay)',
            'slide-viewer-with-title-grid' => 'Grid (container and slides composed in grid)'
        ]);
    }
}
