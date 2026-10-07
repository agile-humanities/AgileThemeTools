<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\DisplayMode\Manager;
use Laminas\View\Helper\AbstractHelper;

/**
 * $this->displayMode('card', $thing, $options)  renders a resource or site page in a display mode.
 * $this->displayMode()->prepare('card', $thing)  the prepared data alone, for templates that lay it out themselves.
 */
class DisplayMode extends AbstractHelper
{
    protected Manager $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function __invoke(?string $mode = null, $thing = null, array $options = [])
    {
        if ($mode === null) {
            return $this;
        }
        return $this->render($mode, $thing, $options);
    }

    public function has(string $mode): bool
    {
        return $this->manager->has($mode);
    }

    public function prepare(string $mode, $thing, array $options = []): array
    {
        $display = $this->manager->get($mode);
        if (!$display->supports($thing)) {
            return [];
        }
        return $display->prepare($this->getView(), $thing, $options);
    }

    public function render(string $mode, $thing, array $options = []): string
    {
        $display = $this->manager->get($mode);
        if (!$display->supports($thing)) {
            return '';
        }
        $data = $display->prepare($this->getView(), $thing, $options);
        return $display->render($this->getView(), $data, $options);
    }
}
