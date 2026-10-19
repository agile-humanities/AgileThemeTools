<?php
namespace AgileThemeTools\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * The item page's media area, without the Advanced Media module: Mirador for an image primary media, Universal
 * Viewer for a PDF primary media, otherwise each media through its own renderer (Omeka core's, or a module's —
 * OHMS Embed's viewer for an OHMS XML, for instance).
 *
 * Display policy "show only the primary media" (PRIMARY_ONLY_SETTING, global with a site override, on by default,
 * the same rule Advanced Media applies as "Only display primary and grouped media"): when an item has a designated
 * primary media, only that media renders; the other files are left to the Downloads block or the media pages.
 * Media that belong with the primary one are kept: a VTT transcript with its audio or video. Without a designated
 * primary media every media renders, in attachment order, as before.
 */
class ItemMedia implements ResourcePageBlockLayoutInterface
{
    /** Global setting (checkbox) and site setting ('' inherit, '1', '0'). */
    const PRIMARY_ONLY_SETTING = 'agilethemetools_item_media_primary_only';
    const PRIMARY_ONLY_DEFAULT = true;

    public function getLabel() : string
    {
        return 'Agile: Item Media'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        $siteUrl = $resource->siteUrl();

        /** @var ItemRepresentation $item */
        $item = $resource;

        /** @var MediaRepresentation|null $primaryMedia The designated primary media, else Omeka's fallback (the first). */
        $primaryMedia = $item->primaryMedia();
        $designated = $primaryMedia && self::isDesignatedPrimary($item, $primaryMedia);
        $primaryType = $primaryMedia ? (string) $primaryMedia->mediaType() : '';

        // Mirador for an image primary media (it shows every image of the item through the IIIF manifest), UV for a PDF.
        $useMirador = $primaryType !== '' && strpos($primaryType, 'image') > -1;
        $useUV = $primaryType !== '' && strpos($primaryType, 'pdf') > -1;

        // A primary media Omeka could not type (an embed, an external resource): render it alone.
        $usePrimary = $primaryMedia && $primaryType === '';

        $audioPrimary = $primaryType !== '' && strpos($primaryType, 'audio') > -1;
        $videoPrimary = $primaryType !== '' && strpos($primaryType, 'video') > -1;

        // VTT transcripts (Omeka often types them text/plain; the extension decides).
        $vtts = [];
        $hasVtt = false;
        foreach ($item->media() as $media) {
            if (strtolower((string) $media->extension()) === 'vtt' || stripos((string) $media->mediaType(), 'vtt') !== false) {
                $vtts[] = $media;
                $hasVtt = true;
            }
        }

        // The policy: with a designated primary media, that media (plus its transcript) and nothing else.
        $primaryOnly = $designated && $this->primaryOnly($view);
        $displayMedia = [];
        if ($primaryOnly) {
            $displayMedia[] = $primaryMedia;
            if ($audioPrimary || $videoPrimary) {
                foreach ($vtts as $vtt) {
                    $displayMedia[] = $vtt;
                }
            }
        } else {
            $displayMedia = $item->media();
        }

        return $view->partial('common/resource-page-block-layout/item-media', [
            'resource' => $resource,
            'siteUrl' => $siteUrl,
            'useMirador' => $useMirador,
            'useUV' => $useUV,
            'primaryMedia' => $primaryMedia,
            'usePrimary' => $usePrimary,
            'hasVtt' => $hasVtt,
            'audioPrimary' => $audioPrimary,
            'primaryOnly' => $primaryOnly,
            'displayMedia' => $displayMedia,
        ]);
    }

    /** Site override ('1'/'0'), else the installation's setting, else the default. */
    protected function primaryOnly(PhpRenderer $view): bool
    {
        $site = $view->siteSetting(self::PRIMARY_ONLY_SETTING, '');
        if ($site === '1' || $site === '0') {
            return $site === '1';
        }
        return (bool) $view->setting(self::PRIMARY_ONLY_SETTING, self::PRIMARY_ONLY_DEFAULT);
    }

    /**
     * Omeka's primaryMedia() falls back to the first media when none is designated; the policy only applies to a
     * deliberate designation, which the JSON-LD exposes as o:primary_media.
     */
    public static function isDesignatedPrimary(ItemRepresentation $item, MediaRepresentation $media): bool
    {
        $json = $item->jsonSerialize();
        $designated = $json['o:primary_media'] ?? null;
        if (!$designated) {
            return false;
        }
        $id = is_object($designated) && method_exists($designated, 'id') ? $designated->id() : ($designated['o:id'] ?? null);
        return $id !== null && (int) $id === $media->id();
    }
}
