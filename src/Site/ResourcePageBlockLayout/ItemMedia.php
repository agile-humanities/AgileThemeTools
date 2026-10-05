<?php
namespace AgileThemeTools\Site\ResourcePageBlockLayout;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Item;
use Omeka\Entity\Media;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class ItemMedia implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Item Media'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        $siteUrl = $resource->siteUrl();

        // Only handles Item media. Changed for clarity.
        /** @var ItemRepresentation $item */
        $item = $resource;

        /** @var MediaRepresentation $primaryMedia */
        $primaryMedia = $item->primaryMedia();

        // Use Mirador viewer if the primary media has an image MimeType.
        // Note that Mirador will display all attached images as per the associated IIIF Manifest
        // This is a shorthand logic to allow site builders some flexibility when using Mirador
        // with other media viewers.

        $useMirador = $primaryMedia && $primaryMedia->mediaType() && strpos($primaryMedia->mediaType(),'image') > -1;

        $useUV =  $primaryMedia && $primaryMedia->mediaType() && strpos($primaryMedia->mediaType(),'pdf') > -1;

        // This should probably be controlled as a setting.

        $usePrimary = false;

        //var_dump($primaryMedia->mediaType());

        if ($primaryMedia && !$primaryMedia->mediaType()) {
          $usePrimary = true;
        }

        $audioPrimary = $primaryMedia && $primaryMedia->mediaType() && strpos($primaryMedia->mediaType(),'audio') > -1;

        // Additional logic for end client. Consider implementing a listener here.

        $hasVtt = false;

        if ($resource->media()) {
          foreach ($resource->media() as $media) {

            // VTT files are identified by mimetype or extension
            if ($media->mediaType() && $primaryMedia->mediaType() && (strpos($primaryMedia->mediaType(),'vtt') > -1 || $media->extension() === 'vtt')) {
              $hasVtt = true;
            }
          }
        }

        return $view->partial('common/resource-page-block-layout/item-media', ['resource' => $resource,'siteUrl'=>$siteUrl,'useMirador'=>$useMirador,'useUV' => $useUV,'primaryMedia'=>$primaryMedia,'usePrimary'=>$usePrimary,'hasVtt'=>$hasVtt,'audioPrimary' => $audioPrimary]);
    }
}
