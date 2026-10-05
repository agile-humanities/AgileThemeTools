<?php

namespace AgileThemeTools\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Media\FileRenderer\AbstractRenderer;
use AgileThemeTools\Media\FileRenderer\CircularAudioRenderer;
use AgileThemeTools\AgileVttParser as TextParser;

class VttRenderer extends AbstractRenderer
{
    const DEFAULT_OPTIONS = [
        'controls' => true,
    ];

    public function render(PhpRenderer $view, MediaRepresentation $media,
        array $options = []
    ) {

      $convertedVtt = '<p>';

      $parser = new TextParser();
      if ($parser->getFileContents($media->originalUrl())) {
        $subtitles = $parser->parse();

        foreach ($subtitles as $subtitle) {
          $start_time = $subtitle['time']['start'];
          $end_time = $subtitle['time']['end'];
          $start_cue = $subtitle['time']['start_cue']; // For audio synchronization
          $end_cue = $subtitle['time']['end_cue']; // For audio synchronization
          $timestamp = $subtitle['time']['timestamp'];

          $text = implode(' ', $subtitle['text']);

          $convertedVtt .= sprintf("<span class='vtt-segment' data-timestamp='%s' data-start-time='%s' data-end-time='%s' data-start-cue='%s' data-end-cue='%s'>%s</span> ",$timestamp,$start_time,$end_time,$start_cue,$end_cue,$text);
        }
      }

      $convertedVtt .= '</p>';


      $cAudio = new CircularAudioRenderer();

      /** @var string $vttID Used to match CTT text display with an audio player element */
      $vttID = $cAudio->generateMediaFileID($media);

      return $view->partial('common/media/file-renderer/vtt.phtml', ['convertedVtt'=>$convertedVtt,'vttID'=>$vttID]);

    }
}
