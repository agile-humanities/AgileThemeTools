<?php
namespace AgileThemeTools\Styling;

/**
 * The colour roles as the styling pages and the save messages see them (generic requirements E4, section 7.3).
 *
 * The continuum $black | $surface-dark | $surface-light | $white: each side's text colours must read on both steps
 * of their side; with impact and the two brand surfaces that makes the guaranteed pairs, the same list the library
 * checks at build time (colour_roles component). Brand colours are never enforced, only graded per step.
 */
class ColourRoles
{
    /** [foreground key, background key, label] — keep in step with the library's check-colour-roles. */
    const GUARANTEED_PAIRS = [
        ['black', 'white', 'the blackpoint on the whitepoint'],
        ['black', 'surface_light', 'black on the light surface'],
        ['on_light', 'white', 'text on white'],
        ['on_light', 'surface_light', 'text on the light surface'],
        ['white', 'black', 'the whitepoint on the blackpoint'],
        ['white', 'surface_dark', 'white on the dark surface'],
        ['on_dark', 'black', 'text on black'],
        ['on_dark', 'surface_dark', 'text on the dark surface'],
        ['on_impact', 'visual_impact', 'text on the impact colour'],
        ['on_brand', 'surface_brand', 'text on the primary brand surface'],
        ['on_brand_secondary', 'surface_brand_secondary', 'text on the secondary brand surface'],
    ];

    /** The derived roles a themer may replace with a brand-approved value, with their labels. */
    const OVERRIDABLE = [
        'brand_primary_on_light' => ['Primary brand colour as text on the light side', 'Leave empty for the automatic variant: the primary colour as it is when it reads on white and the light surface, otherwise darkened until it does. Set a brand-approved tint to use instead.'],
        'brand_primary_on_dark' => ['Primary brand colour as text on the dark side', 'Leave empty for the automatic variant (lightened until it reads on black and the dark surface), or set a brand-approved tint.'],
        'brand_secondary_on_light' => ['Secondary brand colour as text on the light side', 'Leave empty for the automatic variant, or set a brand-approved tint.'],
        'brand_secondary_on_dark' => ['Secondary brand colour as text on the dark side', 'Leave empty for the automatic variant, or set a brand-approved tint.'],
    ];

    /** Whether the configuration defines the roles at all (an older theme may not). */
    public static function present(array $config): bool
    {
        return isset($config['surface_dark'], $config['surface_light']);
    }

    /**
     * The guaranteed pairs evaluated against a configuration: each with its ratio and whether it reaches 4.5:1.
     *
     * @return array [['label' => string, 'foreground' => string, 'background' => string, 'ratio' => float, 'ok' => bool, 'fg' => css, 'bg' => css]]
     */
    public static function check(array $config): array
    {
        $e = new ColourExpression($config);
        $out = [];
        foreach (self::GUARANTEED_PAIRS as [$fgKey, $bgKey, $label]) {
            $fg = $e->resolve($fgKey);
            $bg = $e->resolve($bgKey);
            if (!$fg || !$bg) {
                continue;
            }
            $ratio = ColourExpression::contrast($fg, $bg);
            $out[] = ['label' => $label, 'foreground' => $fgKey, 'background' => $bgKey, 'ratio' => round($ratio, 2), 'ok' => $ratio >= ColourExpression::TEXT_RATIO, 'fg' => ColourExpression::toCss($fg), 'bg' => ColourExpression::toCss($bg)];
        }
        return $out;
    }

    /** The failing guaranteed pairs, as sentences for a warning. */
    public static function failures(array $config): array
    {
        $lines = [];
        foreach (self::check($config) as $pair) {
            if (!$pair['ok']) {
                $lines[] = sprintf('%s (%s on %s) is %s:1, below 4.5:1', $pair['label'], $pair['fg'], $pair['bg'], $pair['ratio']);
            }
        }
        return $lines;
    }

    /**
     * The configuration as the styling pages' script needs it: every key whose value is a colour or an expression
     * that resolves to one, as raw strings, so the script can resolve the roles against the form's live values.
     */
    public static function colourConfig(array $config): array
    {
        $e = new ColourExpression($config);
        $out = [];
        foreach ($config as $key => $value) {
            if (is_scalar($value) && trim((string) $value) !== '' && $e->resolve((string) $key)) {
                $out[$key] = trim((string) $value);
            }
        }
        return $out;
    }
}
