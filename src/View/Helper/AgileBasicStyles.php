<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\Styling\BasicStyles;
use Laminas\View\Helper\AbstractHelper;

/**
 * $this->agileBasicStyles() — the basic-styles service (Styling\BasicStyles), for admin templates that need to
 * know whether the module's basic stylesheets are on and whether they are redundant for a theme.
 */
class AgileBasicStyles extends AbstractHelper
{
    private $basicStyles;

    public function __construct(BasicStyles $basicStyles)
    {
        $this->basicStyles = $basicStyles;
    }

    public function __invoke(): BasicStyles
    {
        return $this->basicStyles;
    }
}
