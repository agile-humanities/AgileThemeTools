<?php

namespace AgileThemeTools\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Media\FileRenderer\AbstractRenderer;

class CircularAudioRenderer extends AbstractRenderer
{
    const DEFAULT_OPTIONS = [
        'controls' => true,
    ];

    public function render(PhpRenderer $view, MediaRepresentation $media,
        array $options = []
    ) {
        $plugins = $view->getHelperPluginManager();


        $options = array_merge(self::DEFAULT_OPTIONS, $options);
        $assetUrl = $plugins->get('assetUrl');

        $attrs[] = sprintf('src="%s"', $view->escapeHtml($media->originalUrl()));

        if (isset($options['autoplay']) && $options['autoplay']) {
            $attrs[] = 'autoplay';
        }
        if (isset($options['controls']) && $options['controls']) {
            $attrs[] = 'controls';
        }
        if (isset($options['loop']) && $options['loop']) {
            $attrs[] = 'loop';
        }
        if (isset($options['muted']) && $options['muted']) {
            $attrs[] = 'muted';
        }

        //$audioSrc =  $view->hyperlink($media->filename(), $media->originalUrl());

        $audioSrc = $media->originalUrl();
        $transcriptSrc = '';

        $vttID = null;

        foreach($media->item()->media() as $siblingMedia) {
          $mediaUrl = $siblingMedia->originalUrl();

          // Find a VTT file in the item’s media stack.
          // @todo: currently only supports 1 VTT file per item. In future, it should provide a
          // method to match VTT files to audio files, say by filename.

          if ($mediaUrl && $siblingMedia->extension() === "vtt") {
            $transcriptSrc = $mediaUrl;

            // vttID will be used by the Circular Audio JS to match audio with transcripts.
            $vttID = $this->generateMediaFileID($siblingMedia);
          }
        }

        $view->headScript()->appendFile($assetUrl('js/circularAudio.js','AgileThemeTools'));
        $view->headLink()->appendStylesheet($assetUrl('css/circularAudio.css','AgileThemeTools'));

        return $view->partial('common/media/file-renderer/circular-audio.phtml',['attrs' => implode(' ', $attrs),'audioSrc'=>$audioSrc,'transcriptSrc'=>$transcriptSrc,'vttID'=>$vttID]);
    }

    /*
     * Creates a hash from a media file url to use as an identifier.
     * @todo: move to a more general place.
     */

    public function generateMediaFileID(MediaRepresentation $media) {
      return hash('sha256', $media->originalUrl());
    }
}
