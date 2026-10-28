<?php
namespace AgileThemeTools\Setup;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * The standard homepage: a page "home" made the site's homepage and put first in its navigation.
 *
 *   Homepage introduction (homepageSplash): the site title over a slide viewer of up to five items with images.
 *   An about paragraph (regionalHtml), to be rewritten by the site's editors.
 *   Item listing: the four most recent items, with a link to browse them all.
 *
 * Rerunning replaces the page (it is found by its slug). Any previous homepage is kept as an ordinary page.
 */
class HomepagePreset
{
    const SLUG = 'home';

    /** @return string[] Lines for the job log. */
    public static function apply(ServiceLocatorInterface $services, SiteRepresentation $site): array
    {
        $writer = new PageWriter($services, $site);
        $slides = $writer->itemsWithImages(5);
        $recent = $writer->itemsWithImages(4);
        $browse = sprintf('/s/%s/item', $site->slug());

        $blocks = [
            PageWriter::block('homepageSplash', [
                'title' => $site->title(),
                'labelText' => '',
                'subtitle' => '',
                'introduction' => '<p>' . htmlspecialchars((string) ($site->summary() ?: 'Search the collection, or browse the highlights below.')) . '</p>',
                'buttonText' => '',
                'composition' => 'slide-viewer-with-title-overlay',
                'region' => 'region:splash',
                'show_title_option' => 'item_title',
                'slidesperrow' => '1',
                'slidestoshow' => '1',
                'autoplay' => '0',
                'fade' => '0',
            ], PageWriter::attachments($slides), ['grid_column_position' => 'auto', 'grid_column_span' => '12']),
            PageWriter::block('regionalHtml', [
                'html' => '<h2>About the collection</h2><p>Introduce the collection here: what it holds, who gathered it and how to use this site. Edit this block on the page "Home".</p>',
                'region' => 'region:default',
            ]),
            PageWriter::block('itemListing', [
                'title' => 'Recent additions',
                'introduction' => '',
                'buttonText' => 'Browse all items',
                'buttonPath' => $browse,
                'classes' => '',
                'thumbnail_type' => 'large',
                'region' => 'region:default',
                'itemCount' => '',
            ], PageWriter::attachments($recent)),
        ];

        $page = $writer->replacePage('Home', self::SLUG, $blocks);
        $writer->setHomepage($page->id());
        $writer->addToNavigation([[$page->id(), []]], true);

        return [
            sprintf('Site "%s": homepage "%s" (#%d) written and set as the homepage, with %d slides and %d recent items.',
                $site->slug(), self::SLUG, $page->id(), count($slides), count($recent)),
        ];
    }
}
