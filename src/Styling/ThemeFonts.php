<?php
namespace AgileThemeTools\Styling;

use AgileThemeTools\Theme\ThemeProfile;
use AgileThemeTools\ThemeBuild\BuildManager;
use Doctrine\ORM\EntityManager;
use Laminas\Http\Client as HttpClient;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Settings\Settings;
use Omeka\Site\Theme\Theme;

/**
 * Fonts for the theme's font roles (main text, headings, alternative, compact).
 *
 * Each role's styling setting (basefont, headingfont, altfont, compactfont) stays the CSS font-family declaration the
 * SASS build expects, e.g. `"Open Sans", sans-serif` or `$headingfont`. A companion setting (<role>_source) records
 * where the family comes from: `google` (loaded from Google Fonts by this class), `bundled` (shipped with the theme
 * and declared in source/sass/00_configuration/_04_typography_fonts.sass), `system` (a system font or one loaded
 * some other way, e.g. the theme's "Other font embed code"), or `alias` (the same font as another role).
 *
 * For Google families the <link> tags are generated on every page, so nothing has to be pasted, and on save the
 * families are checked against Google's stylesheet endpoint: an unknown family is refused (HTTP 400) and weights
 * a family lacks are silently dropped, so both are reported. Changing a role's font still needs a theme build,
 * because font-family is compiled into the stylesheet.
 */
class ThemeFonts
{
    const SOURCE_SUFFIX = '_source';
    const SOURCE_GOOGLE = 'google';
    const SOURCE_BUNDLED = 'bundled';
    const SOURCE_SYSTEM = 'system';
    const SOURCE_ALIAS = 'alias';

    /** Global setting: cache of Google Fonts probes, family => ['ok' => bool, 'weights' => string[], 'checkedAt' => …]. */
    const PROBE_CACHE_SETTING = 'agilethemetools_google_fonts_probes';

    /** Weights and styles requested for every Google family: regular and bold, upright and italic. */
    const GOOGLE_AXES = 'ital,wght@0,400;0,700;1,400;1,700';
    const GOOGLE_CSS_ENDPOINT = 'https://fonts.googleapis.com/css2';

    const ROLES = [
        'basefont' => ['label' => 'Main text font', 'description' => 'Body text and most of the site.'], // @translate
        'headingfont' => ['label' => 'Heading font', 'description' => 'Headings and titles.'], // @translate
        'altfont' => ['label' => 'Alternative font', 'description' => 'Special-use text such as quotations and decks.'], // @translate
        'compactfont' => ['label' => 'Compact font', 'description' => 'Labels, buttons, menus and other interface text set small; a face that reads well at small sizes. Follows the main text font unless set.'], // @translate
    ];

    const GENERICS = ['sans-serif', 'serif', 'monospace', 'system-ui', 'cursive'];

    /** Popular Google families with their category, as suggestions. */
    const GOOGLE_SUGGESTIONS = [
        'Open Sans' => 'sans-serif', 'Roboto' => 'sans-serif', 'Lato' => 'sans-serif', 'Source Sans 3' => 'sans-serif',
        'Noto Sans' => 'sans-serif', 'Inter' => 'sans-serif', 'Work Sans' => 'sans-serif', 'Nunito Sans' => 'sans-serif',
        'PT Sans' => 'sans-serif', 'Raleway' => 'sans-serif', 'Montserrat' => 'sans-serif', 'Fira Sans' => 'sans-serif',
        'IBM Plex Sans' => 'sans-serif', 'Libre Franklin' => 'sans-serif', 'Barlow' => 'sans-serif', 'Cabin' => 'sans-serif',
        'Merriweather' => 'serif', 'Lora' => 'serif', 'Playfair Display' => 'serif', 'Spectral' => 'serif',
        'Source Serif 4' => 'serif', 'Noto Serif' => 'serif', 'PT Serif' => 'serif', 'Libre Baskerville' => 'serif',
        'EB Garamond' => 'serif', 'Crimson Pro' => 'serif', 'Cormorant Garamond' => 'serif', 'Literata' => 'serif',
        'IBM Plex Serif' => 'serif', 'Alegreya' => 'serif', 'Vollkorn' => 'serif', 'Domine' => 'serif',
        'Oswald' => 'sans-serif', 'Bebas Neue' => 'sans-serif', 'Archivo Narrow' => 'sans-serif', 'Roboto Condensed' => 'sans-serif',
        'Fira Code' => 'monospace', 'IBM Plex Mono' => 'monospace', 'Source Code Pro' => 'monospace', 'JetBrains Mono' => 'monospace',
    ];

    /** System font stacks that need no loading at all. */
    const SYSTEM_STACKS = [
        'System UI' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'Helvetica / Arial' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
        'Georgia' => 'Georgia, "Times New Roman", Times, serif',
        'Times' => '"Times New Roman", Times, serif',
        'Verdana' => 'Verdana, Geneva, sans-serif',
        'Monospace' => 'Menlo, Consolas, "Liberation Mono", monospace',
    ];

    protected Settings $settings;
    protected BuildManager $builder;
    protected EntityManager $entityManager;
    protected HttpClient $httpClient;

    /** @var array Bundled families per theme id, parsed once. */
    protected array $bundledCache = [];

    public function __construct(Settings $settings, BuildManager $builder, EntityManager $entityManager, HttpClient $httpClient)
    {
        $this->settings = $settings;
        $this->builder = $builder;
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
    }

    // ------------------------------------------------------------------ declarations

    /**
     * Splits a CSS font-family declaration into its first family, the generic fallback and, for `$otherrole`, the
     * role it aliases. `"Open Sans", sans-serif` → family "Open Sans", generic "sans-serif".
     */
    public function parseDeclaration(string $declaration): array
    {
        $declaration = trim($declaration);
        $result = ['family' => '', 'generic' => '', 'alias' => null, 'declaration' => $declaration];
        if (preg_match('/^\$([a-z]+font)$/i', $declaration, $m) && isset(self::ROLES[$m[1]])) {
            $result['alias'] = $m[1];
            return $result;
        }
        $parts = array_values(array_filter(array_map(fn ($p) => trim($p, " \t\"'"), preg_split('/\s*,\s*/', $declaration)), 'strlen'));
        foreach ($parts as $i => $part) {
            if (in_array(strtolower($part), self::GENERICS, true)) {
                $result['generic'] = strtolower($part);
                unset($parts[$i]);
            }
        }
        $result['family'] = array_values($parts)[0] ?? '';
        return $result;
    }

    /** `Open Sans` + `sans-serif` → `"Open Sans", sans-serif`. */
    public function buildDeclaration(string $family, string $generic = ''): string
    {
        $family = trim($family);
        if ($family === '') {
            return $generic;
        }
        $quoted = preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $family) ? $family : '"' . str_replace('"', '', $family) . '"';
        return $generic !== '' && strtolower($family) !== $generic ? $quoted . ', ' . $generic : $quoted;
    }

    // ------------------------------------------------------------------ sources

    /**
     * Families the theme ships and declares with +load_font in its font configuration, keyed by family name with
     * the weights found in source/fonts. Falls back to the font directories when the SASS file cannot be read.
     */
    public function getBundledFamilies(Theme $theme): array
    {
        $id = $theme->getId();
        if (isset($this->bundledCache[$id])) {
            return $this->bundledCache[$id];
        }
        $families = [];
        $profile = ThemeProfile::of($theme);
        $sass = $profile->fontsFile();
        if (is_readable($sass) && preg_match_all("/^\s*\+load_font\(\s*'([^']+)'\s*,\s*'([^']+)'/m", (string) file_get_contents($sass), $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $families[$match[1]] = ['basename' => $match[2], 'weights' => []];
            }
        }
        foreach (glob($profile->sourceDir() . '/fonts/*-[0-9][0-9][0-9]', GLOB_ONLYDIR) ?: [] as $dir) {
            [$base, $weight] = [substr(basename($dir), 0, -4), substr(basename($dir), -3)];
            $family = null;
            foreach ($families as $name => $info) {
                if ($info['basename'] === $base) {
                    $family = $name;
                }
            }
            if ($family === null && !$families) {
                $family = ucfirst($base);
                $families[$family] = ['basename' => $base, 'weights' => []];
            }
            if ($family !== null) {
                $families[$family]['weights'][] = (int) $weight;
            }
        }
        foreach ($families as &$info) {
            sort($info['weights']);
        }
        return $this->bundledCache[$id] = $families;
    }

    /**
     * The roles of a site: [role => ['declaration' => …, 'source' => …, 'family' => …, 'generic' => …, 'alias' => …]].
     * Read straight from the styling settings table so the shared services are not re-targeted.
     */
    public function getRoles(SiteRepresentation $site): array
    {
        $theme = $this->builder->getSiteTheme($site);
        $defaults = $theme ? $this->builder->getDefaultConfig($theme) : [];
        $bundled = $theme ? $this->getBundledFamilies($theme) : [];
        // Saved font choices are styling values: only used with a theme of the base they were saved for.
        $rows = ($theme && !$this->builder->stylingApplies($theme, $site)) ? [] : $this->entityManager->getConnection()->fetchAllKeyValue(
            'SELECT id, value FROM site_theme_setting WHERE site_id = ?',
            [$site->id()]
        );
        $get = function (string $key) use ($rows) {
            if (!array_key_exists($key, $rows)) {
                return null;
            }
            $value = json_decode((string) $rows[$key], true);
            return is_scalar($value) ? (string) $value : null;
        };

        $roles = [];
        foreach (array_keys(self::ROLES) as $role) {
            $declaration = $get($role);
            if ($declaration === null || $declaration === '') {
                $declaration = (string) ($defaults[$role] ?? '');
            }
            $parsed = $this->parseDeclaration($declaration);
            $source = $get($role . self::SOURCE_SUFFIX) ?: $this->inferSource($parsed, $bundled);
            $roles[$role] = $parsed + ['source' => $source];
        }
        return $roles;
    }

    /** Best guess for a role saved before sources existed. */
    public function inferSource(array $parsed, array $bundledFamilies): string
    {
        if ($parsed['alias']) {
            return self::SOURCE_ALIAS;
        }
        if ($parsed['family'] !== '' && isset($bundledFamilies[$parsed['family']])) {
            return self::SOURCE_BUNDLED;
        }
        return self::SOURCE_SYSTEM;
    }

    /** Google families the site loads, resolved through aliases, deduplicated. */
    public function getGoogleFamilies(SiteRepresentation $site): array
    {
        $roles = $this->getRoles($site);
        $families = [];
        foreach ($roles as $role => $info) {
            $seen = [];
            while ($info['source'] === self::SOURCE_ALIAS && $info['alias'] && !isset($seen[$info['alias']]) && isset($roles[$info['alias']])) {
                $seen[$info['alias']] = true;
                $info = $roles[$info['alias']];
            }
            if ($info['source'] === self::SOURCE_GOOGLE && $info['family'] !== '') {
                $families[$info['family']] = true;
            }
        }
        return array_keys($families);
    }

    // ------------------------------------------------------------------ Google Fonts

    public function getGoogleCssUrl(array $families): string
    {
        $query = [];
        foreach ($families as $family) {
            $query[] = 'family=' . str_replace('%20', '+', rawurlencode($family)) . ':' . self::GOOGLE_AXES;
        }
        $query[] = 'display=swap';
        return self::GOOGLE_CSS_ENDPOINT . '?' . implode('&', $query);
    }

    /**
     * The tags a page needs for a site's Google fonts: [['tag' => 'link', 'attributes' => [...]], …].
     */
    public function getHeadTags(SiteRepresentation $site): array
    {
        $families = $this->getGoogleFamilies($site);
        if (!$families) {
            return [];
        }
        return [
            ['rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com'],
            ['rel' => 'preconnect', 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'],
            ['rel' => 'stylesheet', 'href' => $this->getGoogleCssUrl($families)],
        ];
    }

    /**
     * Asks Google Fonts for a family. Returns ['ok' => bool, 'message' => string, 'weights' => string[]] where
     * weights are the "style weight" pairs Google served (e.g. "normal 400"). Results are cached for a day.
     */
    public function probeGoogleFamily(string $family, bool $useCache = true): array
    {
        $cache = $this->settings->get(self::PROBE_CACHE_SETTING, []) ?: [];
        if ($useCache && isset($cache[$family]) && ($cache[$family]['checkedAt'] ?? 0) > time() - 86400) {
            return $cache[$family];
        }

        $result = ['ok' => false, 'message' => '', 'weights' => [], 'checkedAt' => time()];
        try {
            $client = clone $this->httpClient;
            $client->setUri($this->getGoogleCssUrl([$family]));
            $client->setHeaders(['User-Agent' => 'Mozilla/5.0 (Omeka S theme font check)']);
            $client->setOptions(['timeout' => 10]);
            $response = $client->send();
            if ($response->getStatusCode() === 400) {
                $result['message'] = sprintf('Google Fonts has no family named "%s". Check the spelling on fonts.google.com.', $family);
            } elseif (!$response->isSuccess()) {
                $result['message'] = sprintf('Google Fonts answered %d for "%s".', $response->getStatusCode(), $family);
            } else {
                preg_match_all('/font-style:\s*(\w+);\s*font-weight:\s*(\d+)/', $response->getBody(), $m, PREG_SET_ORDER);
                $weights = array_values(array_unique(array_map(fn ($x) => $x[1] . ' ' . $x[2], $m)));
                $result['ok'] = true;
                $result['weights'] = $weights;
                $missing = array_diff(['normal 400', 'normal 700', 'italic 400', 'italic 700'], $weights);
                if ($missing) {
                    $result['message'] = sprintf('"%s" is available, but Google Fonts has no %s for it; the browser will synthesise %s.', $family, implode(', ', $missing), count($missing) > 1 ? 'them' : 'it');
                } else {
                    $result['message'] = sprintf('"%s" is available from Google Fonts in regular, bold and italics.', $family);
                }
            }
        } catch (\Exception $e) {
            $result['message'] = sprintf('Could not reach Google Fonts to check "%s" (%s). The font will be loaded anyway.', $family, $e->getMessage());
            return $result; // Not cached: a network failure is not a verdict.
        }

        $cache[$family] = $result;
        $this->settings->set(self::PROBE_CACHE_SETTING, $cache);
        return $result;
    }

    /**
     * @font-face rules for the theme's bundled families, pointing at the fonts of the site's build (else the theme's
     * own asset folder), so the admin — which does not load the theme's stylesheet — can preview them and report
     * truthfully whether they load. woff2 only: this is for the administrator's current browser.
     */
    public function getBundledFontFaceCss(Theme $theme, ?SiteRepresentation $site, string $basePath = ''): string
    {
        $fontsUri = null;
        if ($site && is_dir($this->builder->getAssetsDir($theme, $site) . '/fonts')) {
            $fontsUri = $this->builder->getAssetsUri($theme, $site, $basePath) . '/fonts';
        } elseif (is_dir($theme->getPath() . '/asset/fonts')) {
            $fontsUri = $basePath . '/themes/' . $theme->getId() . '/asset/fonts';
        }
        if ($fontsUri === null) {
            return '';
        }
        $css = '';
        foreach ($this->getBundledFamilies($theme) as $family => $info) {
            foreach ($info['weights'] as $weight) {
                foreach (['normal', 'italic'] as $style) {
                    $relative = sprintf('%1$s-%2$d/%1$s-%3$s-%2$d.woff2', $info['basename'], $weight, $style);
                    if (!is_file(ThemeProfile::of($theme)->sourceDir() . '/fonts/' . $relative)) {
                        continue;
                    }
                    $css .= sprintf("@font-face{font-family:\"%s\";font-style:%s;font-weight:%d;font-display:swap;src:url(\"%s/%s\") format(\"woff2\")}\n", $family, $style, $weight, $fontsUri, $relative);
                }
            }
        }
        return $css;
    }

    /** For the form's suggestion lists. */
    public function getSuggestions(?Theme $theme): array
    {
        return [
            self::SOURCE_GOOGLE => self::GOOGLE_SUGGESTIONS,
            self::SOURCE_BUNDLED => $theme ? $this->getBundledFamilies($theme) : [],
            self::SOURCE_SYSTEM => self::SYSTEM_STACKS,
        ];
    }
}
