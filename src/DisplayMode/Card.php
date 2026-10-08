<?php
namespace AgileThemeTools\DisplayMode;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\ValueRepresentation;

/**
 * The card: a resource or a site page as a list entry — image, label, title, subtitle, summary, a short metadata
 * list — configured per installation and per site (CardSettings), rendered by `common/display-mode/card`.
 *
 * The representative image, in order: the resource's own thumbnail asset (Omeka's "Thumbnail" field); a media
 * linked from the configured image property (foaf:depiction by default); the primary media; the first media
 * with a thumbnail; none (the template shows a placeholder). The summary: the configured property's text, cut
 * at a sentence end (Summary). For a page: its Page summary block, else the older representative-image, excerpt
 * and byline blocks, else the first HTML block and the first attached media.
 *
 * Options (all optional): containerElement (default div), cardStyle (default card), classes, slot, thumbnailType,
 * label (overrides the computed label), heading, body.
 */
class Card implements DisplayModeInterface
{
    public function getLabel(): string
    {
        return 'Card'; // @translate
    }

    public function supports($thing): bool
    {
        return $thing instanceof AbstractResourceEntityRepresentation
            || $thing instanceof SitePageRepresentation
            || (is_array($thing) && isset($thing['o:pageRepresentation']));
    }

    public function prepare(PhpRenderer $view, $thing, array $options = []): array
    {
        $settings = CardSettings::resolve($view);
        $data = [
            'mode' => 'card',
            'type' => 'resource',
            'url' => '',
            'label' => '',
            'title' => '',
            'subtitle' => '',
            'summary' => '',
            'image' => null,          // thumbnail HTML, or null
            'imageIsPlaceholder' => false,
            'placeholder' => null,    // when there is no image: which placeholder stands in (image, text, audio, video, dataset, interactive, collection, object, page, item)
            'metadata' => [],         // [['term' => , 'label' => , 'values' => [html]]]
            'itemSets' => [],         // [['title' => , 'url' => ]]
            'rights' => null,         // ['label' => , 'value' => html]
            'byline' => '',
            'containerElement' => $options['containerElement'] ?? 'div',
            'cardStyle' => $options['cardStyle'] ?? 'card',
            'classes' => $options['classes'] ?? '',
            'slot' => $options['slot'] ?? null,
            'settings' => $settings,
        ];
        $thumbnailType = $options['thumbnailType'] ?? $settings['thumbnail_type'];

        if (is_array($thing) && isset($thing['o:pageRepresentation'])) {
            $thing = $thing['o:pageRepresentation'];
        }
        if ($thing instanceof SitePageRepresentation) {
            $this->preparePage($view, $thing, $data, $settings, $thumbnailType);
        } elseif ($thing instanceof AbstractResourceEntityRepresentation) {
            $this->prepareResource($view, $thing, $data, $settings, $thumbnailType);
        }
        foreach (['label', 'title', 'summary', 'subtitle'] as $key) {
            if (isset($options[$key]) && $options[$key] !== '') {
                $data[$key] = $options[$key];
            }
        }
        $data['resource'] = $thing;
        return $data;
    }

    public function render(PhpRenderer $view, array $data, array $options = []): string
    {
        return $view->partial('common/display-mode/card', $data);
    }

    // ------------------------------------------------------------------------------------------- resources

    protected function prepareResource(PhpRenderer $view, AbstractResourceEntityRepresentation $resource, array &$data, array $settings, string $thumbnailType): void
    {
        $translate = $view->plugin('translate');
        $escape = $view->plugin('escapeHtml');
        $valueHtml = $this->valueRenderer($view);
        $isItem = $resource instanceof ItemRepresentation;
        $isItemSet = $resource instanceof ItemSetRepresentation;
        $data['type'] = $isItem ? 'item' : ($isItemSet ? 'item_set' : ($resource instanceof MediaRepresentation ? 'media' : 'resource'));
        $data['url'] = $resource->url();

        // Label: the type value, the class label, or the kind of resource.
        if ($isItemSet) {
            $data['label'] = $translate('Collection');
        } elseif ($settings['label_source'] === 'class' && $resource->resourceClass()) {
            $data['label'] = $translate($resource->resourceClass()->label());
        } else {
            $type = $resource->value('dcterms:type');
            $data['label'] = $type ? Summary::text($valueHtml($type, false)) : ($resource->resourceClass() ? $translate($resource->resourceClass()->label()) : $translate($isItem ? 'Item' : 'Resource'));
        }
        if ($resource->resourceClass()) {
            $data['classes'] = trim($data['classes'] . ' resource-id-' . $resource->resourceClass()->id());
        }

        // Title and subtitle
        $titleTerm = $settings['title_term'] ?: (string) $view->siteSetting('browse_heading_property_term', '');
        $title = $titleTerm ? $resource->value($titleTerm) : null;
        $data['title'] = $title ? Summary::text($valueHtml($title, false)) : $resource->displayTitle($translate('[Untitled]'));
        $subtitle = $settings['subtitle_term'] ? $resource->value($settings['subtitle_term']) : null;
        $data['subtitle'] = $subtitle ? $valueHtml($subtitle, false) : '';

        // Summary: the configured property, else the site's browse body property
        $summaryTerm = $settings['summary_term'] ?: (string) $view->siteSetting('browse_body_property_term', 'dcterms:description');
        $summary = $summaryTerm ? $resource->value($summaryTerm) : null;
        $data['summary'] = $summary ? $escape(Summary::summarize($summary->asHtml(), $settings['summary_length'])) : '';

        // Image
        $data['image'] = $this->resourceImage($view, $resource, $settings['image_term'], $thumbnailType);
        $data['imageIsPlaceholder'] = $data['image'] === null;
        $data['placeholder'] = $data['imageIsPlaceholder'] ? self::placeholderType($resource) : null;

        // The short metadata list
        $values = $resource->values();
        foreach ($settings['metadata_terms'] as $term) {
            if (empty($values[$term]['values'])) {
                continue;
            }
            $property = $values[$term]['property'];
            $rendered = [];
            foreach ($values[$term]['values'] as $value) {
                if ($value->isPublic()) {
                    $rendered[] = $valueHtml($value, true);
                }
            }
            if ($rendered) {
                $data['metadata'][] = ['term' => $term, 'label' => $values[$term]['alternate_label'] ?: $translate($property->label()), 'values' => $rendered];
            }
        }

        // Collections and rights
        if ($settings['show_item_sets'] && $isItem) {
            foreach ($resource->itemSets() as $itemSet) {
                if ($itemSet->isPublic()) {
                    $data['itemSets'][$itemSet->title()] = ['title' => $itemSet->title(), 'url' => $itemSet->url()];
                }
            }
            ksort($data['itemSets']);
            $data['itemSets'] = array_values($data['itemSets']);
        }
        if ($settings['show_rights'] && $settings['rights_term']) {
            $rights = $resource->value($settings['rights_term'], ['type' => 'uri']) ?: $resource->value($settings['rights_term']);
            if ($rights) {
                $data['rights'] = ['label' => $translate('Copyright'), 'value' => $valueHtml($rights, true)];
            }
        }
    }

    /** The representative image as thumbnail HTML, or null. */
    protected function resourceImage(PhpRenderer $view, AbstractResourceEntityRepresentation $resource, string $imageTerm, string $thumbnailType): ?string
    {
        $alt = $resource->displayTitle('');
        $attribs = ['alt' => $alt, 'loading' => 'lazy'];
        if ($resource->thumbnail()) {
            return $view->thumbnail($resource, $thumbnailType, $attribs);
        }
        if ($imageTerm) {
            foreach ($resource->value($imageTerm, ['all' => true, 'type' => 'resource']) as $value) {
                $linked = $value->valueResource();
                if ($linked instanceof MediaRepresentation && $linked->hasThumbnails()) {
                    return $view->thumbnail($linked, $thumbnailType, ['alt' => $linked->altText() ?: $alt, 'loading' => 'lazy']);
                }
                if ($linked instanceof ItemRepresentation && $linked->primaryMedia() && $linked->primaryMedia()->hasThumbnails()) {
                    return $view->thumbnail($linked->primaryMedia(), $thumbnailType, $attribs);
                }
            }
        }
        $primary = $resource->primaryMedia();
        if ($primary && $primary->hasThumbnails()) {
            return $view->thumbnail($primary, $thumbnailType, ['alt' => $primary->altText() ?: $alt, 'loading' => 'lazy']);
        }
        if ($resource instanceof ItemRepresentation) {
            foreach ($resource->media() as $media) {
                if ($media->hasThumbnails()) {
                    return $view->thumbnail($media, $thumbnailType, ['alt' => $media->altText() ?: $alt, 'loading' => 'lazy']);
                }
            }
        }
        if ($resource instanceof MediaRepresentation && $resource->hasThumbnails()) {
            return $view->thumbnail($resource, $thumbnailType, $attribs);
        }
        return null;
    }

    /** Values as HTML: the module's valueDisplay helper when present (search links, formatting), else Omeka's own. */
    protected function valueRenderer(PhpRenderer $view): callable
    {
        $plugins = $view->getHelperPluginManager();
        if ($plugins->has('valueDisplay')) {
            $valueDisplay = $plugins->get('valueDisplay');
            return fn (ValueRepresentation $value, bool $link) => $link ? $valueDisplay($value) : $valueDisplay($value, ['link' => false]);
        }
        return fn (ValueRepresentation $value, bool $link) => $link ? $value->asHtml() : strip_tags($value->asHtml());
    }

    // ------------------------------------------------------------------------------------------- site pages

    protected function preparePage(PhpRenderer $view, SitePageRepresentation $page, array &$data, array $settings, string $thumbnailType): void
    {
        $translate = $view->plugin('translate');
        $escape = $view->plugin('escapeHtml');
        $data['type'] = 'page';
        $data['url'] = $page->siteUrl();
        $data['title'] = $page->title();
        $data['label'] = $translate('Page');
        $summary = null;
        $byline = '';
        $imageAttachment = null;
        $firstHtml = null;
        $firstMediaAttachment = null;

        foreach ($page->blocks() as $block) {
            $layout = $block->layout();
            if ($layout === 'pageSummary') {
                $summary = $summary ?? (trim((string) $block->dataValue('summary')) !== '' ? $block->dataValue('summary') : null);
                $byline = $byline ?: (string) $block->dataValue('byline');
                if (trim((string) $block->dataValue('label')) !== '') {
                    $data['label'] = $block->dataValue('label');
                }
                $imageAttachment = $imageAttachment ?? ($block->attachments()[0] ?? null);
            } elseif ($layout === 'representativeImage') {
                $imageAttachment = $imageAttachment ?? ($block->attachments()[0] ?? null);
            } elseif ($layout === 'excerpt') {
                $summary = $summary ?? (trim((string) $block->dataValue('html')) !== '' ? $block->dataValue('html') : null);
            } elseif ($layout === 'byline') {
                $byline = $byline ?: (string) $block->dataValue('html');
            } elseif ($layout === 'html' || $layout === 'regionalHtml') {
                $firstHtml = $firstHtml ?? $block->dataValue('html');
            }
            if (!$firstMediaAttachment) {
                foreach ($block->attachments() as $attachment) {
                    if ($attachment->media() || ($attachment->item() && $attachment->item()->primaryMedia())) {
                        $firstMediaAttachment = $attachment;
                        break;
                    }
                }
            }
        }
        $data['summary'] = $escape(Summary::summarize($summary ?? $firstHtml, $settings['summary_length']));
        $data['byline'] = Summary::text($byline);
        $attachment = $imageAttachment ?: $firstMediaAttachment;
        if ($attachment) {
            $media = $attachment->media() ?: ($attachment->item() ? $attachment->item()->primaryMedia() : null);
            if ($media && $media->hasThumbnails()) {
                $data['image'] = $view->thumbnail($media, $thumbnailType, ['alt' => $media->altText() ?: $page->title(), 'loading' => 'lazy']);
            }
        }
        $data['imageIsPlaceholder'] = $data['image'] === null;
        $data['placeholder'] = $data['imageIsPlaceholder'] ? 'page' : null;
    }

    /**
     * Which placeholder image stands in for a resource without a representative image: by the primary media's
     * type when it has one (a sound file without a thumbnail gets the audio placeholder), else by the resource
     * class, else by the kind of resource. The names are the file names of the placeholder set: the theme's
     * img/svg/thumbnails/<name>.svg, or the module's asset/img/thumbnails/<name>.svg under other themes.
     */
    public static function placeholderType(AbstractResourceEntityRepresentation $resource): string
    {
        $media = $resource instanceof MediaRepresentation ? $resource : $resource->primaryMedia();
        $mediaType = $media ? (string) $media->mediaType() : '';
        if ($mediaType !== '') {
            foreach (['image/' => 'image', 'audio/' => 'audio', 'video/' => 'video', 'text/' => 'text', 'application/pdf' => 'text', 'application/epub' => 'text', 'application/msword' => 'text', 'application/vnd.openxmlformats-officedocument.wordprocessingml' => 'text', 'application/vnd.ms-excel' => 'dataset', 'application/vnd.openxmlformats-officedocument.spreadsheetml' => 'dataset', 'application/json' => 'dataset', 'application/xml' => 'dataset'] as $prefix => $name) {
                if (str_starts_with($mediaType, $prefix)) {
                    return $name;
                }
            }
        }
        $term = $resource->resourceClass() ? (string) $resource->resourceClass()->term() : '';
        $byClass = [
            'dctype:StillImage' => 'image', 'dctype:Image' => 'image', 'bibo:Image' => 'image', 'bibo:Map' => 'image',
            'dctype:Text' => 'text', 'bibo:Document' => 'text', 'bibo:Book' => 'text', 'bibo:Article' => 'text', 'bibo:Letter' => 'text', 'bibo:Manuscript' => 'text', 'bibo:Report' => 'text', 'bibo:Thesis' => 'text', 'bibo:Periodical' => 'text',
            'dctype:Sound' => 'audio', 'bibo:AudioDocument' => 'audio',
            'dctype:MovingImage' => 'video', 'bibo:Film' => 'video', 'bibo:AudioVisualDocument' => 'video',
            'dctype:Dataset' => 'dataset', 'dctype:Software' => 'dataset',
            'dctype:InteractiveResource' => 'interactive', 'bibo:Website' => 'interactive',
            'dctype:Collection' => 'collection',
            'dctype:PhysicalObject' => 'object',
        ];
        if (isset($byClass[$term])) {
            return $byClass[$term];
        }
        // No class: a dcterms:type value such as "Text", "Still Image", "Sound" (the DCMI type vocabulary as text).
        $typeValue = $resource->value('dcterms:type');
        if ($typeValue) {
            $typeText = strtolower(trim(preg_replace('/[^a-z]+/i', ' ', (string) $typeValue)));
            $byType = ['still image' => 'image', 'image' => 'image', 'text' => 'text', 'sound' => 'audio', 'audio' => 'audio', 'moving image' => 'video', 'video' => 'video', 'dataset' => 'dataset', 'software' => 'dataset', 'interactive resource' => 'interactive', 'collection' => 'collection', 'physical object' => 'object'];
            if (isset($byType[$typeText])) {
                return $byType[$typeText];
            }
        }
        return $resource instanceof ItemSetRepresentation ? 'collection' : 'item';
    }
}
