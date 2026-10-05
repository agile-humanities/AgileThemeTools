<?php
namespace AgileThemeTools\View\Helper;

use AgileThemeTools\Theme\ThemeProfile;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Site\Theme\Theme;

/**
 * What a theme declares about itself (Theme\ThemeProfile).
 *
 *   $this->themeIdentity($theme)            the panel shown on a site's Theme page: empty for a theme that is not
 *                                           an Agile one
 *   $this->themeIdentity()->profile($theme) the profile itself, for templates that need a fact from it
 */
class ThemeIdentity extends AbstractHelper
{
    public function __invoke(?Theme $theme = null)
    {
        if ($theme === null) {
            return $this;
        }
        $profile = ThemeProfile::of($theme);
        if (!$profile->isAgile()) {
            return '';
        }
        return $this->getView()->partial('common/theme-identity', [
            'profile' => $profile,
            'findings' => $profile->findings(),
            'moduleVersion' => ThemeProfile::moduleVersion(),
        ]);
    }

    public function profile(Theme $theme): ThemeProfile
    {
        return ThemeProfile::of($theme);
    }
}
