<?php
namespace AgileThemeTools\DisplayMode;

use Laminas\View\Renderer\PhpRenderer;

/**
 * What a card shows: installation-wide defaults (Settings > Agile Theme Tools > Cards) with per-site overrides
 * (Site settings > Cards), each site value empty meaning "use the installation's". The theme's legacy `card_terms`
 * theme setting is honoured for the metadata terms where a site has not set them.
 *
 * Keys and defaults:
 *   title_term      '' (the site's browse heading property, else the resource's own title)
 *   subtitle_term   dcterms:creator
 *   label_source    'type' (dcterms:type value) | 'class' (resource class label)
 *   summary_term    dcterms:description — the field summarised; dcterms:abstract is the usual alternative
 *   summary_length  300 characters, cut at a sentence end (Summary)
 *   metadata_terms  dcterms:created,dcterms:date — the short metadata list under the summary
 *   image_term      foaf:depiction — the property whose linked media is the representative image
 *   thumbnail_type  large
 *   show_item_sets  1 — the "Collection" line
 *   show_rights     1 — the rights line
 *   rights_term     dcterms:rights
 */
class CardSettings
{
    const PREFIX = 'agilethemetools_card_';

    const DEFAULTS = [
        'title_term' => '',
        'subtitle_term' => 'dcterms:creator',
        'label_source' => 'type',
        'summary_term' => 'dcterms:description',
        'summary_length' => 300,
        'metadata_terms' => 'dcterms:created,dcterms:date',
        'image_term' => 'foaf:depiction',
        'thumbnail_type' => 'large',
        'show_item_sets' => 1,
        'show_rights' => 1,
        'rights_term' => 'dcterms:rights',
    ];

    const BOOLEANS = ['show_item_sets', 'show_rights'];

    public static function settingName(string $key): string
    {
        return self::PREFIX . $key;
    }

    /** The effective values for the current site, through the view's setting helpers. */
    public static function resolve(PhpRenderer $view): array
    {
        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $global = $view->setting(self::settingName($key));
            $site = $view->siteSetting(self::settingName($key));
            $value = self::usable($site) ? $site : (self::usable($global) ? $global : $default);
            if ($key === 'metadata_terms' && !self::usable($site)) {
                // The theme's own card terms (theme setting) still win over the installation default.
                $legacy = (string) $view->themeSetting('card_terms', '');
                if (trim($legacy) !== '') {
                    $value = $legacy;
                }
            }
            $values[$key] = in_array($key, self::BOOLEANS, true) ? (bool) (int) $value : $value;
        }
        $values['summary_length'] = (int) $values['summary_length'];
        $values['metadata_terms'] = array_values(array_filter(array_map('trim', explode(',', (string) $values['metadata_terms']))));
        return $values;
    }

    /** A saved value that counts: not null, not an empty string, not the "inherit" marker. */
    public static function usable($value): bool
    {
        return $value !== null && $value !== '' && $value !== 'inherit';
    }
}
