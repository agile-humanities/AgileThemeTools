<?php
namespace AgileThemeTools\Setup;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * What the site presets share: writing pages (replaced by slug, so a preset can be run again), choosing items to
 * attach, and adding pages to a site's navigation without disturbing what is already there.
 */
class PageWriter
{
    protected ServiceLocatorInterface $services;
    protected SiteRepresentation $site;

    public function __construct(ServiceLocatorInterface $services, SiteRepresentation $site)
    {
        $this->services = $services;
        $this->site = $site;
    }

    public function site(): SiteRepresentation
    {
        return $this->site;
    }

    public static function block(string $layout, array $data = [], array $attachments = [], array $layoutData = []): array
    {
        return ['o:layout' => $layout, 'o:data' => $data, 'o:attachment' => $attachments, 'o:layout_data' => $layoutData];
    }

    /**
     * Attachments for a block, from [item id, media id] pairs (media may be null).
     */
    public static function attachments(array $pairs, string $caption = ''): array
    {
        return array_map(fn ($pair) => [
            'o:item' => ['o:id' => $pair[0]],
            'o:media' => $pair[1] ? ['o:id' => $pair[1]] : null,
            'o:caption' => $caption,
        ], $pairs);
    }

    /**
     * Up to $limit of the site's public items that have an image, as [item id, media id] pairs, most recent first.
     * Falls back to any of the site's items (without a media) when fewer have images.
     */
    public function itemsWithImages(int $limit = 4): array
    {
        $api = $this->services->get('Omeka\ApiManager');
        $pairs = [];
        $others = [];
        $items = $api->search('items', ['site_id' => $this->site->id(), 'sort_by' => 'created', 'sort_order' => 'desc', 'limit' => 50])->getContent();
        foreach ($items as $item) {
            $image = null;
            foreach ($item->media() as $media) {
                if (strpos((string) $media->mediaType(), 'image/') === 0 || $media->ingester() === 'iiif') {
                    $image = $media;
                    break;
                }
            }
            if ($image) {
                $pairs[] = [$item->id(), $image->id()];
            } else {
                $others[] = [$item->id(), null];
            }
            if (count($pairs) >= $limit) {
                break;
            }
        }
        return array_slice(array_merge($pairs, $others), 0, $limit);
    }

    /** The block layouts registered in this installation, so a preset can leave out blocks of absent modules. */
    public function hasBlockLayout(string $layout): bool
    {
        return in_array($layout, $this->services->get('Omeka\BlockLayoutManager')->getRegisteredNames(), true);
    }

    /**
     * Creates the page, replacing any page of the site with the same slug (and keeping it as the homepage if it was).
     */
    public function replacePage(string $title, string $slug, array $blocks): \Omeka\Api\Representation\SitePageRepresentation
    {
        $api = $this->services->get('Omeka\ApiManager');
        $blocks = array_values(array_filter($blocks, fn ($block) => $this->hasBlockLayout($block['o:layout'])));
        $wasHomepage = false;
        foreach ($api->search('site_pages', ['site_id' => $this->site->id(), 'slug' => $slug])->getContent() as $old) {
            $site = $api->read('sites', $this->site->id())->getContent();
            if ($site->homepage() && $site->homepage()->id() === $old->id()) {
                $wasHomepage = true;
                $api->update('sites', $this->site->id(), ['o:homepage' => null], [], ['isPartial' => true]);
            }
            $api->delete('site_pages', $old->id());
        }
        $page = $api->create('site_pages', [
            'o:site' => ['o:id' => $this->site->id()],
            'o:title' => $title,
            'o:slug' => $slug,
            'o:is_public' => true,
            'o:block' => $blocks,
        ])->getContent();
        if ($wasHomepage) {
            $this->setHomepage($page->id());
        }
        $this->refreshSite();
        return $page;
    }

    public function setHomepage(int $pageId): void
    {
        $this->services->get('Omeka\ApiManager')->update('sites', $this->site->id(), ['o:homepage' => ['o:id' => $pageId]], [], ['isPartial' => true]);
        $this->refreshSite();
    }

    /**
     * Adds page links to the end of the navigation, each with optional child page ids. Pages already linked
     * anywhere in the navigation are left where they are, and links to pages that no longer exist are dropped.
     *
     * @param array $entries [[page id, [child page id, …]], …]
     */
    public function addToNavigation(array $entries, bool $first = false): void
    {
        $api = $this->services->get('Omeka\ApiManager');
        $existingIds = array_map(fn ($page) => $page->id(), $api->search('site_pages', ['site_id' => $this->site->id()])->getContent());
        $prune = function (array $links) use (&$prune, $existingIds): array {
            $kept = [];
            foreach ($links as $link) {
                if (($link['type'] ?? '') === 'page' && !in_array((int) ($link['data']['id'] ?? 0), $existingIds, true)) {
                    continue;
                }
                $link['links'] = $prune($link['links'] ?? []);
                $kept[] = $link;
            }
            return $kept;
        };
        $navigation = $prune($this->site->navigation());
        $linked = [];
        $collect = function (array $links) use (&$collect, &$linked): void {
            foreach ($links as $link) {
                if (($link['type'] ?? '') === 'page') {
                    $linked[] = (int) $link['data']['id'];
                }
                $collect($link['links'] ?? []);
            }
        };
        $collect($navigation);
        $pageLink = fn (int $id, array $links = []) => ['type' => 'page', 'data' => ['id' => $id, 'label' => ''], 'links' => $links];
        $new = [];
        foreach ($entries as [$pageId, $children]) {
            if (in_array($pageId, $linked, true)) {
                continue;
            }
            $new[] = $pageLink($pageId, array_map(fn ($childId) => $pageLink($childId), array_values(array_diff($children, $linked))));
        }
        $navigation = $first ? array_merge($new, $navigation) : array_merge($navigation, $new);
        $api->update('sites', $this->site->id(), ['o:navigation' => $navigation], [], ['isPartial' => true]);
        $this->refreshSite();
    }

    protected function refreshSite(): void
    {
        $this->site = $this->services->get('Omeka\ApiManager')->read('sites', $this->site->id())->getContent();
    }
}
