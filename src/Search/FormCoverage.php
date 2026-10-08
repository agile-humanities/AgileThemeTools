<?php
namespace AgileThemeTools\Search;

use AgileThemeTools\View\Helper\FormLayout;
use Laminas\Form\Element\Collection;
use Laminas\Form\ElementInterface;
use Laminas\Form\FieldsetInterface;

/**
 * Which element classes each Advanced Search configuration's form uses, and which of them the form layout
 * (View\Helper\FormLayout) does not know: the fixture check for the pinned Advanced Search version (generic
 * requirements G2). Shown on the module's configuration page with the dependency audit.
 */
class FormCoverage
{
    /**
     * @param iterable $searchConfigs \AdvancedSearch\Api\Representation\SearchConfigRepresentation[]
     * @return array [['id', 'name', 'adapter', 'classes' => [class => count], 'unknown' => [class => element name], 'error' => ?string]]
     */
    public static function report(iterable $searchConfigs, FormLayout $layout): array
    {
        $out = [];
        foreach ($searchConfigs as $config) {
            $row = ['id' => $config->id(), 'name' => $config->name(), 'adapter' => $config->formAdapterName(), 'classes' => [], 'unknown' => [], 'error' => null];
            try {
                $form = $config->form();
                if ($form) {
                    self::walk($form, $layout, $row);
                }
            } catch (\Throwable $e) {
                $row['error'] = $e->getMessage();
            }
            ksort($row['classes']);
            $out[] = $row;
        }
        return $out;
    }

    protected static function walk($container, FormLayout $layout, array &$row): void
    {
        foreach ($container->getIterator() as $element) {
            self::note($element, $layout, $row);
            if ($element instanceof Collection && $element->getTargetElement()) {
                self::note($element->getTargetElement(), $layout, $row);
                if ($element->getTargetElement() instanceof FieldsetInterface) {
                    self::walk($element->getTargetElement(), $layout, $row);
                }
            }
            if ($element instanceof FieldsetInterface) {
                self::walk($element, $layout, $row);
            }
        }
    }

    protected static function note(ElementInterface $element, FormLayout $layout, array &$row): void
    {
        $class = get_class($element);
        $row['classes'][$class] = ($row['classes'][$class] ?? 0) + 1;
        if ($layout->typeOf($element) === 'unknown') {
            $row['unknown'][$class] = (string) $element->getName();
        }
    }
}
