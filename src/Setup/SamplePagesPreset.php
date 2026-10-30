<?php
namespace AgileThemeTools\Setup;

use AgileThemeTools\Setup\PageWriter as W;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * The block test pages: every Omeka core block, every Agile Theme Tools block, and the blocks of the third-party
 * modules the Agile stack uses (Advanced Search, Reference, Mirador), each introduced by a heading, with sample text
 * and the site's own items. Plus a section of a parent page and three child pages, for the navigation dropdown and
 * the Section Listing block. Used to review a theme against every block, and as worked examples for editors.
 *
 * Pages are found by slug (sample-*) and replaced on a rerun; they are added at the end of the navigation. Blocks of
 * modules that are not active are left out. Attachments use up to four of the site's items with images.
 */
class SamplePagesPreset
{
    const LOREM = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent vehicula, nunc at facilisis tincidunt, lorem urna dictum nisl, a rutrum felis tortor at magna. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae. Aenean <a href="#">a sample link</a> eget tortor non ligula pulvinar lacinia.</p>';
    const LOREM2 = '<p>Integer sollicitudin, ipsum at ornare elementum, quam leo faucibus justo, nec ultricies mauris nulla non nunc. Duis porta, elit sed tempor finibus, nunc nibh porttitor lacus, sit amet facilisis nisi ex ut lorem. Suspendisse potenti.</p>';
    const LIST = '<ul><li>First list item</li><li>Second list item with <strong>bold</strong> and <em>italic</em></li><li>Third list item</li></ul>';

    const SECTION_LISTING_TEMPLATES = [
        'common/block-layout/item-list' => 'Item list (default)',
        'common/block-layout/section-list-capsules' => 'Capsules',
        'common/block-layout/section-list-cards' => 'Cards',
        'common/block-layout/section-list-feature' => 'Feature',
        'common/block-layout/section-list-tabs' => 'List - Tabs',
        'common/block-layout/section-list-vertical' => 'List - Vertical',
    ];

    protected ServiceLocatorInterface $services;
    protected SiteRepresentation $site;
    /** @var array Four [item id, media id] pairs, repeated when the site has fewer items with images. */
    protected array $items;

    /** @return string[] Lines for the job log. */
    public static function apply(ServiceLocatorInterface $services, SiteRepresentation $site): array
    {
        return (new self($services, $site))->write();
    }

    protected function __construct(ServiceLocatorInterface $services, SiteRepresentation $site)
    {
        $this->services = $services;
        $this->site = $site;
    }

    protected function write(): array
    {
        $writer = new W($this->services, $this->site);
        $items = $writer->itemsWithImages(4);
        if (!$items) {
            return [sprintf('Site "%s" has no items: add items to the site first, then rerun the test pages preset.', $this->site->slug())];
        }
        $found = count($items);
        while (count($items) < 4) {
            $items[] = $items[count($items) % $found];
        }
        $this->items = $items;

        $lines = [];
        $entries = [];
        foreach ([$this->corePage(), $this->agilePage(), $this->modulePage()] as $spec) {
            $page = $writer->replacePage($spec['title'], $spec['slug'], $spec['blocks']);
            $entries[] = [$page->id(), []];
            $lines[] = sprintf('Site "%s": page "%s" (#%d), %d blocks.', $this->site->slug(), $page->slug(), $page->id(), count($page->blocks()));
        }
        $section = $this->sectionPages();
        $parent = $writer->replacePage($section['parent']['title'], $section['parent']['slug'], $section['parent']['blocks']);
        $children = [];
        foreach ($section['children'] as $spec) {
            $children[] = $writer->replacePage($spec['title'], $spec['slug'], $spec['blocks'])->id();
        }
        $entries[] = [$parent->id(), $children];
        $lines[] = sprintf('Site "%s": section "%s" with %d child pages.', $this->site->slug(), $parent->slug(), count($children));

        $writer->addToNavigation($entries);
        $lines[] = sprintf('Site "%s": test pages added to the navigation.', $this->site->slug());
        return $lines;
    }

    protected function corePage(): array
    {
        $i = $this->items;
        $blocks = [
            W::block('pageTitle'),
            W::block('representativeImage', ['region' => 'region:default'], W::attachments([$i[1]])), // shown by link cards and listings
            W::block('html', ['html' => '<h2>Omeka core blocks</h2>' . self::LOREM . self::LIST . self::LOREM2]),
            W::block('lineBreak', ['break_type' => 'transparent']),
            W::block('media', ['thumbnail_type' => 'large', 'show_title_option' => 'item_title', 'layout' => 'horizontal', 'media_display' => 'thumbnail'], W::attachments([$i[0], $i[1]], 'A caption for the media embed'), ['alignment_block' => 'center', 'alignment_text' => 'center']),
            W::block('html', ['html' => '<h3>Composed with the media embed above</h3>' . self::LOREM]),
            W::block('browsePreview', ['resource_type' => 'items', 'query' => 'sort_by=created&sort_order=desc', 'limit' => '3', 'components' => ['resource-heading', 'resource-body', 'thumbnail'], 'heading' => 'Browse preview: recent items', 'link-text' => 'Browse all']),
            W::block('lineBreak', ['break_type' => 'opaque']),
            W::block('html', ['html' => '<h3>More core blocks</h3><p>Item with metadata, table of contents, list of pages, list of sites, page date/time, a block group of two HTML blocks, a IIIF presentation, a IIIF image and an oEmbed.</p>']),
            W::block('itemWithMetadata', [], W::attachments([$i[3]])),
            W::block('tableOfContents', ['depth' => '1']),
            W::block('listOfPages', ['pagelist' => '[]']),
            W::block('listOfSites', ['sort' => 'alpha', 'limit' => '5', 'pagination' => '0', 'summaries' => '1', 'thumbnails' => '1', 'exclude_current' => '0']),
            W::block('pageDateTime', ['display' => 'created', 'date_format' => 'medium', 'time_format' => 'none']),
            W::block('blockGroup', ['span' => '2']),
            W::block('html', ['html' => '<h4>Block group, first half</h4>' . self::LOREM2], [], ['grid_column_position' => 'auto', 'grid_column_span' => '6']),
            W::block('html', ['html' => '<h4>Block group, second half</h4>' . self::LOREM2], [], ['grid_column_position' => 'auto', 'grid_column_span' => '6']),
        ];
        [$manifest, $info] = $this->iiifUrls();
        if ($manifest) {
            $blocks[] = W::block('iiifPresentation', ['manifest_url' => $manifest, 'title' => 'IIIF presentation block', 'show_title' => '1']);
        }
        if ($info) {
            $blocks[] = W::block('iiifImage', ['image_url' => $info, 'title' => 'IIIF image block', 'show_title' => '1']);
        }
        // Core fetches the oEmbed when the page is saved; offline, the page is written without it (see oembedSafe()).
        $blocks[] = W::block('oembed', ['url' => 'https://www.youtube.com/watch?v=jNQXAC9IVRw', 'url_old' => '', 'refresh' => '0', 'oembed' => '']);
        return ['title' => 'Sample: Omeka core blocks', 'slug' => 'sample-core-blocks', 'blocks' => $this->oembedSafe($blocks)];
    }

    protected function agilePage(): array
    {
        $i = $this->items;
        $slug = $this->site->slug();
        // Three cards linking to this site's own pages: each card's image is the linked page's representative image
        // (its Page summary or Representative image block), the way the block is meant to be used.
        $cards = ['title' => 'Site link cards', 'introduction' => '<p>Three link cards to pages of this site. Each card shows the representative image of the page it links to.</p>', 'buttonText' => '', 'buttonPath' => '', 'classes' => '', 'thumbnail_type' => 'large', 'region' => 'region:default'];
        foreach ([['Omeka core blocks', 'sample-core-blocks'], ['Third-party module blocks', 'sample-module-blocks'], ['A section of pages', 'sample-section']] as $n => [$title, $pageSlug]) {
            $cards += ["itemLabel_$n" => 'PAGE', "itemTitle_$n" => $title, "itemSubTitle_$n" => 'A subtitle', "itemThumbnailUrl_$n" => '', "itemDescription_$n" => '<p>A short description of the linked page.</p>', "itemUrl_$n" => "/s/$slug/page/$pageSlug"];
        }
        $slideshow = fn (string $title, string $intro, array $pairs) => W::block('slideshow', ['title' => $title, 'introduction' => $intro, 'region' => 'region:default', 'show_title_option' => 'item_title', 'labelText' => 'LABEL', 'subtitle' => 'A subtitle', 'buttonText' => '', 'slidesperrow' => '1', 'autoplay' => '0', 'fade' => '1', 'hastitle' => '1'], W::attachments($pairs));
        $heading = fn (string $text) => W::block('html', ['html' => "<h3>$text</h3>"]);

        $blocks = [
            // The splash region, above the page title; the regional title opens the main stack.
            W::block('sectionIntroSplash', ['title' => 'Section introduction', 'introduction' => '<p>Introduction text for the section splash, in the splash region above the page.</p>', 'alignment' => 'right', 'region' => 'region:splash', 'show_title_option' => 'item_title'], W::attachments([$i[0]])),
            W::block('pageTitle'),
            W::block('regionalTitle', ['title' => 'Regional title', 'html' => '<p>Text under the regional title, first in the main content stack.</p>', 'region' => 'region:default']),
            W::block('html', ['html' => '<p><strong>These are the Agile Theme Tools blocks</strong> (all except the Homepage Introduction, which belongs on the homepage), in alphabetical order of their labels, each introduced by an HTML block.</p>' . self::LOREM]),
            // How this page presents itself in listings (read by the card display mode; hidden on the page itself).
            W::block('pageSummary', ['summary' => '<p>A hand-written summary for this sample page. It is what listings show instead of the page\'s first paragraph. A third sentence makes it long enough to be cut by a short limit.</p>', 'byline' => 'Curated by A. Sample Curator', 'label' => 'Sample', 'show' => '0'], W::attachments([$i[2]])),
            $heading('Byline'),
            W::block('byline', ['html' => '<h6>Curated by A. Sample Curator</h6><p>Autumn 2026</p>', 'region' => 'region:default'], [], ['grid_column_position' => 'auto', 'grid_column_span' => '12']),
            $heading('Callout'),
            W::block('callout', ['html' => self::LOREM . self::LOREM2, 'callout' => '<p>&ldquo;A pulled quotation set beside the running text.&rdquo;</p>', 'position' => 'right', 'region' => 'region:default', 'title' => 'Callout title']),
            $heading('Contributor list'),
            W::block('contributorList', ['html' => '<ul><li>First Contributor, Institution</li><li>Second Contributor, Institution</li><li>Third Contributor</li></ul>', 'region' => 'region:default']),
            $heading('Deck'),
            W::block('deck', ['html' => '<p>A deck: the standfirst paragraph that introduces a page in larger type.</p>', 'region' => 'region:default']),
            $heading('Excerpt'),
            W::block('excerpt', ['html' => '<p>An excerpt block: a short summary paragraph.</p>', 'region' => 'region:default']),
            $heading('Item listing with introduction'),
            W::block('itemListing', ['title' => 'Item listing', 'introduction' => '<p>Four items with an introduction.</p>', 'buttonText' => 'See more', 'buttonPath' => "/s/$slug/item", 'classes' => '', 'thumbnail_type' => 'large', 'region' => 'region:default', 'itemCount' => ''], W::attachments($i)),
            $heading('Quotation'),
            W::block('quotation', ['quotation' => '<p>&ldquo;The quotation block sets a quotation with its attribution.&rdquo;</p>', 'attribution' => 'An Attributed Author (Title of Work)']),
            $heading('Regional HTML'),
            W::block('regionalHtml', ['html' => '<p>Regional HTML placed in the primary content tier.</p>', 'region' => 'region:default']),
            $heading('Representative image'),
            W::block('representativeImage', ['region' => 'region:default'], W::attachments([$i[1]])),
        ];
        // Section listing: one block per template, each listing the pages beneath "Sample: section".
        foreach (self::SECTION_LISTING_TEMPLATES as $template => $label) {
            $blocks[] = $heading("Section listing ($label)");
            $blocks[] = W::block('sectionPageListing', ['section' => 'sample-section', 'template' => $template, 'includeCurrentPage' => '0', 'title' => "Section listing: $label", 'introduction' => '<p>The pages beneath "Sample: section", as ' . strtolower($label) . '.</p>', 'buttonText' => '', 'buttonPath' => '', 'itemCount' => '', 'classes' => '', 'cardLabel' => 'PAGE', 'region' => 'region:default']);
        }
        $blocks[] = $heading('Site link cards');
        $blocks[] = W::block('linkCards', $cards);
        $blocks[] = $heading('Slide viewer');
        $blocks[] = $slideshow('Slide viewer', '<p>Four slides.</p>', $i);
        $blocks[] = W::block('html', ['html' => '<h3>Slide viewer (many slides)</h3><p>Seven slides: above the counter threshold (5), the page markers give way to a counter at every width.</p>']);
        $blocks[] = $slideshow('Slide viewer, many slides', '<p>Six attachments plus the title slide.</p>', array_merge($i, [$i[0], $i[1]]));
        return ['title' => 'Sample: Agile Theme Tools blocks', 'slug' => 'sample-agile-blocks', 'blocks' => $blocks];
    }

    protected function modulePage(): array
    {
        $i = $this->items;
        $heading = fn (string $text) => W::block('html', ['html' => "<h3>$text</h3>"]);
        $blocks = [
            W::block('pageTitle'),
            W::block('representativeImage', ['region' => 'region:default'], W::attachments([$i[2]])), // shown by link cards and listings
            W::block('html', ['html' => '<p><strong>Blocks supplied by third-party modules</strong> the Agile stack uses: Advanced Search (search form), Reference (reference list and reference tree) and Mirador (viewer). Each is introduced by an HTML block; blocks of modules that are not installed are left out.</p>' . self::LOREM]),
        ];
        $siteSettings = $this->services->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($this->site->id());
        $searchConfig = (string) ($siteSettings->get('advancedsearch_main_config') ?: '');
        if ($searchConfig) {
            $blocks[] = $heading('Search form (Advanced Search)');
            $blocks[] = W::block('searchingForm', ['search_config' => $searchConfig, 'display_results' => false, 'properties' => [], 'autoscroll' => false, 'query' => [], 'query_filter' => [], 'link' => '']);
        }
        $reference = $this->services->get('Config')['reference']['block_settings'] ?? null;
        if ($reference) {
            $blocks[] = $heading('Reference (Reference module)');
            $blocks[] = W::block('reference', ($reference['reference'] ?? []) + ['fields' => ['dcterms:subject'], 'type' => 'properties', 'resource_name' => 'items', 'query' => [], 'languages' => [], 'sort_by' => 'alphabetic', 'sort_order' => 'asc', 'by_initial' => false, 'search_config' => '', 'link_to_single' => true, 'custom_url' => false, 'skiplinks' => true, 'headings' => true, 'total' => true, 'url_argument_reference' => false, 'thumbnail' => false, 'list_by_max' => 0, 'subject_property' => null]);
            $blocks[] = $heading('Reference tree (Reference module)');
            $blocks[] = W::block('referenceTree', ($reference['referenceTree'] ?? []) + ['fields' => ['dcterms:subject'], 'tree' => [], 'resource_name' => 'items', 'query' => [], 'query_type' => 'eq', 'search_config' => '', 'link_to_single' => true, 'custom_url' => false, 'total' => true, 'url_argument_reference' => false, 'thumbnail' => false, 'branch' => false, 'expanded' => true]);
        }
        $blocks[] = $heading('Mirador viewer (Mirador module)');
        $blocks[] = W::block('mirador', [], W::attachments([$i[0], $i[1]]));
        return ['title' => 'Sample: third-party module blocks', 'slug' => 'sample-module-blocks', 'blocks' => $blocks];
    }

    /** A parent page with three second-level pages, each carrying a Section Listing (tabs) as a subsection menu. */
    protected function sectionPages(): array
    {
        $menu = W::block('sectionPageListing', ['section' => 'sample-section', 'template' => 'common/block-layout/section-list-tabs', 'includeCurrentPage' => '0', 'title' => '', 'introduction' => '', 'buttonText' => '', 'buttonPath' => '', 'itemCount' => '', 'classes' => 'section-menu', 'cardLabel' => '', 'region' => 'region:default']);
        $child = fn (int $n, string $title) => ['title' => $title, 'slug' => "sample-section-$n", 'blocks' => [
            W::block('pageTitle'),
            $menu,
            W::block('html', ['html' => "<p><strong>Second-level sample page $n.</strong> It sits beneath \"Sample: section\" in the site navigation; the tabs above are the Section Listing block as a subsection menu.</p>" . self::LOREM]),
        ]];
        return [
            'parent' => ['title' => 'Sample: section', 'slug' => 'sample-section', 'blocks' => [
                W::block('pageTitle'),
                $menu,
                W::block('representativeImage', ['region' => 'region:default'], W::attachments([$this->items[3]])), // shown by link cards and listings
                W::block('html', ['html' => '<p><strong>A section parent page.</strong> Three second-level pages sit beneath it in the navigation, for the dropdown menu and the Section Listing block (the tabs above).</p>' . self::LOREM]),
            ]],
            'children' => [$child(1, 'Section page one'), $child(2, 'Section page two'), $child(3, 'Section page three')],
        ];
    }

    /** IIIF manifest and image info URLs for the first item, when IIIF Server provides them. */
    protected function iiifUrls(): array
    {
        $helpers = $this->services->get('ViewHelperManager');
        if (!$helpers->has('iiifUrl') || !$helpers->has('iiifMediaUrl')) {
            return [null, null];
        }
        $api = $this->services->get('Omeka\ApiManager');
        try {
            $item = $api->read('items', $this->items[0][0])->getContent();
            $manifest = $helpers->get('iiifUrl')($item, 'iiifserver/manifest');
            $info = null;
            if ($this->items[0][1]) {
                $info = $helpers->get('iiifMediaUrl')($api->read('media', $this->items[0][1])->getContent(), 'imageserver/info');
            }
            // In a job there may be no server URL to build an absolute URL from ("http:///iiif/…"): keep the path,
            // which Mirador and OpenSeadragon resolve against the page.
            // ("http:///iiif/…" is not a URL parse_url() accepts, hence the pattern.)
            $usable = fn ($url) => $url ? (preg_match('#^https?://(/.*)$#', $url, $m) ? $m[1] : $url) : null;
            return [$usable($manifest), $usable($info)];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    /** Leaves the oEmbed out when its URL cannot be fetched (no network), since core refuses to save the page. */
    protected function oembedSafe(array $blocks): array
    {
        try {
            $this->services->get('Omeka\HttpClient')->setUri('https://www.youtube.com/oembed?format=json&url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DjNQXAC9IVRw')->setOptions(['timeout' => 5])->send();
            return $blocks;
        } catch (\Throwable $e) {
            return array_values(array_filter($blocks, fn ($block) => $block['o:layout'] !== 'oembed'));
        }
    }
}
