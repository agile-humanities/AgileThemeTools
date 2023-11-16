<?php

namespace AgileThemeTools\Form\Element;

use Laminas\Form\Element\Select;

class PositionSelect extends Select {

    function __construct($name = 'o:block[__blockIndex__][o:data][position]', $options = [])
    {
        parent::__construct($name, $options);
        $this->setLabel('Position');
        $this->setValueOptions(count($options) > 0 ? $options: $this->getTemplateValueOptions());
    }

    public function getTemplateValueOptions() {
        return([
            'left' => 'Left',
            'right' => 'Right',
            'centre' => 'Centre',
        ]);
    }
}
