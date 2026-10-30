<?php

namespace AgileThemeTools\Form\Element;

use Laminas\Form\Element\Select;

class SectionListingTemplateSelect extends Select {

    function __construct($name = 'o:block[__blockIndex__][o:data][template]', $options = [])
    {
        parent::__construct($name, $options);
        $this->setLabel('Select a listing type');
        $this->setValueOptions(count($options) > 0 ? $options: $this->getTemplateValueOptions());
    }

    public function getTemplateValueOptions() {
        // The first entry is the block's default (SectionPageListing::form sets it on a new block), so a block saved
        // without a choice keeps it.
        return([
          'common/block-layout/item-list' => 'List - Simple',
          'common/block-layout/section-list-capsules' => 'Capsules',
          'common/block-layout/section-list-cards' => 'Cards',
          'common/block-layout/section-list-feature' => 'Feature',
          'common/block-layout/section-list-tabs' => 'List - Tabs',
          'common/block-layout/section-list-vertical' => 'List - Vertical'
        ]);
    }
}
