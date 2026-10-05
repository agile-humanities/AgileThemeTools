<?php

namespace AgileThemeTools\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Media\FileRenderer\AbstractRenderer;
use AgileThemeTools\Media\FileRenderer\VttRenderer;

class TextRenderer extends AbstractRenderer
{
    const DEFAULT_OPTIONS = [
        'controls' => true,
    ];

    public function render(PhpRenderer $view, MediaRepresentation $media,
        array $options = []
    ) {
        /* Some VTT files have a text/plain MimeType. This redirects the renderer appropriately for files with vtt extensions. */
        if ($media->extension() === 'vtt') {
          $vttRenderer = new VttRenderer();
          return $vttRenderer->render($view, $media);
        } else {
          if (array_key_exists('hasVtt',$options) && $options['hasVtt'] === true) {
            $label = array_key_exists('label',$options) ? $options['label'] : 'Text';
            return $view->partial('common/media/file-renderer/download.phtml', ['label'=>$label,'mediaUrl' => $media->originalUrl(),'fileName'=>$media->filename()]);
          } else {
            $fileContents = file_get_contents($media->originalUrl());
            $convertedText = $fileContents !== false ? nl2br(htmlspecialchars($fileContents, ENT_QUOTES, 'UTF-8')) : '';
            return $view->partial('common/media/file-renderer/text.phtml', ['convertedText' => $convertedText]);
          }
        }
    }
}
