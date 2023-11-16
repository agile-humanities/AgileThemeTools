<?php
  
namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class CustomCKEditorConfig extends AbstractHelper {
    /**
     * Load a custom CKEditor library on a page.
     */
    public function __invoke()
    {
        $view = $this->getView();
        $customConfigUrl = $view->escapeJs($view->assetUrl('js/ckeditor_config.js', 'AgileThemeTools'));
        $view->headScript()->appendFile($view->assetUrl('vendor/ckeditor/ckeditor.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('vendor/ckeditor/adapters/jquery.js', 'Omeka'));
        $view->headScript()->appendScript("CKEDITOR.config.customConfig = '$customConfigUrl'");

    }
}