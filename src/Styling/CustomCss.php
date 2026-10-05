<?php
namespace AgileThemeTools\Styling;

use AgileThemeTools\ThemeBuild\BuildManager;
use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Settings\Settings;

/**
 * Custom CSS written by administrators, in two layers loaded after the theme's own stylesheets:
 *
 * 1. Global CSS (Settings > Theme styling): every site of the installation.
 * 2. Site CSS (Site settings > Theme styling): one site.
 *
 * Each layer is served as a static file under files/theme-assets/custom/, named after a hash of its content, so a
 * change produces a new URL and browsers never keep a stale copy. The file is written the first time it is needed
 * (no save hook required) and older versions of the same layer are removed. When the directory cannot be written,
 * the CSS is inlined in the page instead, so it always applies.
 *
 * The variables reference lists the CSS custom properties the site's compiled stylesheet declares on :root
 * (--colour--*, --breakpoint-*, --space-*, --type-*, --font-*, …), so the custom CSS can reuse them.
 */
class CustomCss
{
    const GLOBAL_SETTING = 'agilethemetools_global_css';
    const SITE_SETTING = 'agilethemetools_site_css';
    const DIR_NAME = 'custom';

    protected Settings $settings;
    protected ApiManager $api;
    protected BuildManager $builder;
    protected EntityManager $entityManager;
    protected string $filesPath;
    protected ?string $filesUri;

    public function __construct(Settings $settings, ApiManager $api, BuildManager $builder, EntityManager $entityManager, string $filesPath, ?string $filesUri)
    {
        $this->settings = $settings;
        $this->api = $api;
        $this->builder = $builder;
        $this->entityManager = $entityManager;
        $this->filesPath = rtrim($filesPath, '/');
        $this->filesUri = $filesUri !== null ? rtrim($filesUri, '/') : null;
    }

    public function getGlobalCss(): string
    {
        return $this->sanitize((string) $this->settings->get(self::GLOBAL_SETTING, ''));
    }

    /**
     * Read straight from the table: the shared site settings service is bound to the current site, and this may
     * be asked about another one (the variables reference on the global settings page).
     */
    public function getSiteCss(SiteRepresentation $site): string
    {
        $value = $this->entityManager->getConnection()->fetchOne(
            'SELECT value FROM site_setting WHERE site_id = ? AND id = ?',
            [$site->id(), self::SITE_SETTING]
        );
        return $this->sanitize($value ? (string) json_decode((string) $value, true) : '');
    }

    /** CSS is trusted admin content; only a closing style tag could break the inline fallback out of its element. */
    public function sanitize(string $css): string
    {
        return trim(preg_replace('~</style~i', '<\\/style', $css));
    }

    /**
     * The custom stylesheets to load for a page, in order (global, then site). Each entry is either
     * ['url' => …] or, when the file could not be written, ['inline' => css].
     */
    public function getStylesheets(?SiteRepresentation $site, string $basePath = ''): array
    {
        $sheets = [];
        $global = $this->getGlobalCss();
        if ($global !== '') {
            $sheets[] = $this->serve('global', $global, $basePath);
        }
        if ($site) {
            $siteCss = $this->getSiteCss($site);
            if ($siteCss !== '') {
                $sheets[] = $this->serve('site-' . $site->slug(), $siteCss, $basePath);
            }
        }
        return $sheets;
    }

    protected function serve(string $name, string $css, string $basePath): array
    {
        $hash = substr(sha1($css), 0, 10);
        $file = sprintf('%s-%s.css', $name, $hash);
        $dir = $this->getDir();
        $path = "$dir/$file";

        if (!is_file($path)) {
            if (!(is_dir($dir) || @mkdir($dir, 0775, true)) || @file_put_contents($path, $css) === false) {
                return ['inline' => $css];
            }
            // Older versions of this layer are no longer referenced by any page.
            foreach (glob("$dir/$name-*.css") ?: [] as $old) {
                if ($old !== $path) {
                    @unlink($old);
                }
            }
        }
        $uri = $this->filesUri ?? ($basePath . '/files');
        return ['url' => $uri . '/' . BuildManager::ROOT_DIR_NAME . '/' . self::DIR_NAME . '/' . $file];
    }

    public function getDir(): string
    {
        return $this->filesPath . '/' . BuildManager::ROOT_DIR_NAME . '/' . self::DIR_NAME;
    }

    // ------------------------------------------------------------------ variables reference

    /**
     * The CSS custom properties declared on :root by the site's compiled stylesheet, grouped for display:
     * ['label' => …, 'variables' => [name => value]]. Without a site, the default site (else the first) is used.
     */
    public function getVariables(?SiteRepresentation $site = null): array
    {
        $site = $site ?? $this->getReferenceSite();
        $file = $site ? $this->getStylesheetPath($site) : null;
        if (!$file) {
            return [];
        }
        $variables = $this->parseRootVariables((string) file_get_contents($file));
        if (!$variables) {
            return [];
        }

        $groups = [
            'colour' => ['label' => 'Colours', 'variables' => []], // @translate
            'sizing' => ['label' => 'Spacing and type', 'variables' => []], // @translate
            'font' => ['label' => 'Fonts', 'variables' => []], // @translate
            'layout' => ['label' => 'Layout', 'variables' => []], // @translate
            'breakpoint' => ['label' => 'Breakpoints (pixels, for use in scripts; media queries cannot use variables)', 'variables' => []], // @translate
            'other' => ['label' => 'Other', 'variables' => []], // @translate
        ];
        foreach ($variables as $name => $value) {
            if (str_starts_with($name, '--colour')) {
                $group = 'colour';
            } elseif (str_starts_with($name, '--space') || str_starts_with($name, '--type') || $name === '--scale' || $name === '--line-height') {
                $group = 'sizing';
            } elseif (str_starts_with($name, '--font')) {
                $group = 'font';
            } elseif (str_starts_with($name, '--breakpoint')) {
                $group = 'breakpoint';
            } elseif (in_array($name, ['--max-width', '--legible-width', '--masthead-height', '--ui-height', '--ui-radius'], true)) {
                $group = 'layout';
            } else {
                $group = 'other';
            }
            $groups[$group]['variables'][$name] = $value;
        }
        return array_filter($groups, fn ($group) => $group['variables'] !== []);
    }

    /** Whether a value can be shown as a colour swatch. */
    public function isColourValue(string $value): bool
    {
        return (bool) preg_match('/^(#[0-9a-f]{3,8}|rgba?\(|hsla?\(|[a-z]+$)/i', trim($value));
    }

    /** The compiled stylesheet the site is actually served: files build, else old in-theme build, else theme asset. */
    public function getStylesheetPath(SiteRepresentation $site): ?string
    {
        $theme = $this->builder->getSiteTheme($site);
        if (!$theme) {
            return null;
        }
        $candidates = [];
        if ($this->builder->isSiteConfigurable($theme, $site)) {
            $candidates[] = $this->builder->getAssetsDir($theme, $site) . '/css/style.min.css';
            $candidates[] = $this->builder->getLegacyBuildDir($theme, $site) . '/css/style.min.css';
        }
        $candidates[] = $theme->getPath() . '/asset/css/style.min.css';
        $candidates[] = $theme->getPath() . '/asset/css/style.css';
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /** All `--name: value` declarations inside :root blocks; a later declaration overrides an earlier one. */
    public function parseRootVariables(string $css): array
    {
        $variables = [];
        if (!preg_match_all('/:root\s*\{([^}]*)\}/', $css, $blocks)) {
            return $variables;
        }
        foreach ($blocks[1] as $block) {
            foreach (explode(';', $block) as $declaration) {
                if (preg_match('/^\s*(--[A-Za-z0-9_-]+)\s*:\s*(.+?)\s*$/s', $declaration, $m)) {
                    $variables[$m[1]] = $m[2];
                }
            }
        }
        return $variables;
    }

    protected function getReferenceSite(): ?SiteRepresentation
    {
        $defaultId = (int) $this->settings->get('default_site');
        if ($defaultId) {
            try {
                return $this->api->read('sites', $defaultId)->getContent();
            } catch (\Exception $e) {
                // Fall through to the first site.
            }
        }
        $sites = $this->api->search('sites', ['limit' => 1, 'sort_by' => 'id'])->getContent();
        return $sites[0] ?? null;
    }
}
