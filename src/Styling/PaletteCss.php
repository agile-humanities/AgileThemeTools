<?php
namespace AgileThemeTools\Styling;

use AgileThemeTools\Theme\ThemeProfile;
use AgileThemeTools\ThemeBuild\BuildManager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Theme\Theme;

/**
 * The stylesheet that applies a site's saved colours and fonts without a build.
 *
 * The theme's compiled stylesheet reads its palette and fonts from CSS variables (see the theme's
 * tools/css-variables.js), so a few :root declarations loaded after it restyle the site. This class turns the
 * site's saved styling values into those declarations: the inputs — the palette colours and the font roles —
 * where the saved value is a plain colour or font list that differs from the theme's default, and, when a palette
 * colour changed, the derived colour roles recomputed from the new palette (derivedRoles(), ColourExpression).
 * Other values written as theme-system expressions ($clr2, neutral(40)) follow the palette through color-mix()
 * in the stylesheet itself.
 *
 * The block sits inside @supports for color-mix(): browsers that cannot mix colours in CSS keep the built palette
 * throughout, rather than the inputs with the derived colours left behind.
 */
class PaletteCss
{
    const SUPPORTS = '(color: color-mix(in srgb, red, blue))';

    /** Left in a stylesheet by the theme's tools/css-variables.js: it reads its palette and fonts from variables. */
    const MARKER = '--agile-css-variables';

    /** @var array<string, bool> Per served stylesheet (path + mtime), for the request. */
    protected static array $variableDriven = [];

    /** Font roles of the theme system and the variables the theme exports for them (same table as the tool). */
    const FONT_VARIABLES = [
        'basefont' => '--font-base',
        'headingfont' => '--font-heading',
        'altfont' => '--font-alt',
        'compactfont' => '--font-compact',
        'listfont' => '--font-list',
        'codefont' => '--font-code',
    ];

    protected BuildManager $builder;

    public function __construct(BuildManager $builder)
    {
        $this->builder = $builder;
    }

    /** The variable a configuration key is exported as: clr3 → --colour--3, white → --colour--white. */
    public static function variableFor(string $key): ?string
    {
        if (isset(self::FONT_VARIABLES[$key])) {
            return self::FONT_VARIABLES[$key];
        }
        return preg_match('/^clr(\d+)$/', $key, $m) ? '--colour--' . $m[1] : '--colour--' . str_replace('_', '-', $key);
    }

    public static function isColour(string $value): bool
    {
        $value = trim($value);
        return (bool) preg_match('/^(#[0-9a-f]{3}|#[0-9a-f]{6}|#[0-9a-f]{8})$/i', $value)
            || (bool) preg_match('/^[a-z]{3,20}$/i', $value) && !in_array(strtolower($value), ['inherit', 'initial', 'unset', 'none', 'null'], true);
    }

    /** A font list the browser can use as it is: no theme-system expressions, no stray characters. */
    public static function isFontList(string $value): bool
    {
        $value = trim($value, " \"'");
        return $value !== '' && strpos($value, '$') === false && (bool) preg_match('/^[A-Za-z0-9 ,\'"._-]+$/', $value);
    }

    /**
     * The configuration keys this layer can apply at run time, in display order: the palette colours and the
     * font roles that the theme's default configuration gives as plain values.
     *
     * @return array [key => ['kind' => 'colour'|'font', 'variable' => string, 'default' => string]]
     */
    public function inputs(Theme $theme): array
    {
        $defaults = $this->builder->getDefaultConfig($theme);
        $inputs = [];
        foreach ($defaults as $key => $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            if (preg_match('/^clr\d+$/', $key) && self::isColour($value)) {
                $inputs[$key] = ['kind' => 'colour', 'variable' => self::variableFor($key), 'default' => $value];
            } elseif (isset(self::FONT_VARIABLES[$key]) && self::isFontList($value)) {
                $inputs[$key] = ['kind' => 'font', 'variable' => self::variableFor($key), 'default' => trim($value, " \"'")];
            }
        }
        return $inputs;
    }

    /**
     * The derived colour roles: configuration keys whose default value is an expression the build evaluates
     * non-linearly (on-colour, ensure-contrast). They cannot be written as color-mix() of the palette, so when a
     * site's palette changes without a build they are recomputed here (Styling\ColourExpression).
     *
     * @return array [key => ['variable' => string, 'expression' => string]]
     */
    public function derivedRoles(Theme $theme): array
    {
        $roles = [];
        foreach ($this->builder->getDefaultConfig($theme) as $key => $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '' && ColourExpression::isDerived($value)) {
                $roles[$key] = ['variable' => self::variableFor($key), 'expression' => $value];
            }
        }
        return $roles;
    }

    /**
     * Brand-approved replacements a site has saved for derived roles (ColourRoles::OVERRIDABLE): plain colours only.
     *
     * @return array [key => colour]
     */
    public function overrides(Theme $theme, SiteRepresentation $site): array
    {
        if (!$this->builder->stylingApplies($theme, $site)) {
            return [];
        }
        $saved = $this->builder->getSiteConfig($theme, $site);
        $out = [];
        foreach (array_keys(ColourRoles::OVERRIDABLE) as $key) {
            $value = isset($saved[$key]) && is_scalar($saved[$key]) ? trim((string) $saved[$key]) : '';
            if (self::isColour($value)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * The site's saved values for those inputs, as they can be applied: plain values only, defaults where the saved
     * value is empty or an expression.
     *
     * @return array [key => value]
     */
    public function values(Theme $theme, SiteRepresentation $site): array
    {
        $saved = $this->builder->stylingApplies($theme, $site) ? $this->builder->getSiteConfig($theme, $site) : $this->builder->getDefaultConfig($theme);
        $values = [];
        foreach ($this->inputs($theme) as $key => $input) {
            $value = isset($saved[$key]) && is_scalar($saved[$key]) ? trim((string) $saved[$key]) : '';
            $usable = $input['kind'] === 'colour' ? self::isColour($value) : self::isFontList($value);
            $values[$key] = $usable ? trim($value, " \"'") : $input['default'];
        }
        return $values;
    }

    /**
     * The stylesheet the site is actually served: its own build, else an old in-theme build, else the theme's asset.
     * Same order as the theme's siteAssetUrl helper.
     */
    public function servedStylesheet(SiteRepresentation $site): ?string
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

    /**
     * Whether the stylesheet the site is served reads its palette and fonts from CSS variables — the Simplified
     * theme always, the Advanced theme when built with `gulp build --variables`. Only then can colours and fonts
     * be applied without a build.
     */
    public function isVariableDriven(SiteRepresentation $site): bool
    {
        $file = $this->servedStylesheet($site);
        if ($file === null) {
            return false;
        }
        $key = $file . '@' . (string) @filemtime($file);
        if (!array_key_exists($key, self::$variableDriven)) {
            // The marker is in the generated block at the end of the file.
            $size = (int) @filesize($file);
            $tail = $size > 65536 ? (string) @file_get_contents($file, false, null, $size - 65536) : (string) @file_get_contents($file);
            self::$variableDriven[$key] = strpos($tail, self::MARKER) !== false;
        }
        return self::$variableDriven[$key];
    }

    /**
     * The override stylesheet for the site, or an empty string when nothing differs from the theme's defaults or the
     * served stylesheet cannot take variables.
     */
    public function getCss(SiteRepresentation $site): string
    {
        $theme = $this->builder->getSiteTheme($site);
        if (!$theme || !ThemeProfile::of($theme)->isAgile() || !$this->isVariableDriven($site)) {
            return '';
        }
        try {
            $inputs = $this->inputs($theme);
        } catch (\RuntimeException $e) {
            return ''; // No default configuration to compare with (a theme without a build system).
        }
        $lines = [];
        $values = $this->values($theme, $site);
        $overrides = $this->overrides($theme, $site);
        $paletteChanged = (bool) $overrides;
        foreach ($values as $key => $value) {
            if (strcasecmp($value, $inputs[$key]['default']) !== 0) {
                $lines[] = sprintf('    %s: %s;', $inputs[$key]['variable'], $value);
                $paletteChanged = $paletteChanged || $inputs[$key]['kind'] === 'colour';
            }
        }
        if (!$lines && !$overrides) {
            return '';
        }
        // The derived roles follow the palette by rule, not by proportion: recompute every one of them from the
        // palette as it now is (the defaults with the saved colours laid over), with the build's own maths.
        if ($paletteChanged) {
            $config = $this->builder->getDefaultConfig($theme);
            foreach ($values as $key => $value) {
                if ($inputs[$key]['kind'] === 'colour') {
                    $config[$key] = $value;
                }
            }
            foreach ($overrides as $key => $value) {
                $config[$key] = $value; // a brand-approved tint in place of the derivation
            }
            $evaluator = new ColourExpression($config);
            foreach ($this->derivedRoles($theme) as $key => $role) {
                $colour = $evaluator->resolve($key);
                if ($colour) {
                    $lines[] = sprintf('    %s: %s;', $role['variable'], ColourExpression::toCss($colour));
                }
            }
        }
        return "/* Colours and fonts saved for this site (Agile Theme Tools). */\n@supports " . self::SUPPORTS . " {\n  :root {\n" . implode("\n", $lines) . "\n  }\n}\n";
    }
}
