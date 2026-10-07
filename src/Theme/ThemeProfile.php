<?php
namespace AgileThemeTools\Theme;

use Composer\Semver\Semver;
use Omeka\Site\Theme\Theme;

/**
 * What a theme declares about itself in the [info] section of its config/theme.ini.
 *
 * This is how the module recognises an Agile theme. It never looks at the theme's folder name or display name, so
 * a theme can be copied, renamed and edited (a "fork") and still be recognised, as long as it keeps these keys:
 *
 *     agile_base         = "agile-base"   ; the Agile base theme this theme is, or was forked from
 *     agile_base_version = "2.0.0"        ; the version of that base theme it corresponds to
 *     agile_theme_tools  = "^2.0"         ; the Agile Theme Tools versions it works with (composer constraint)
 *     agile_build        = "gulp"         ; it supports per-site SASS builds; leave out when it does not
 *
 * Optional, for themes that move the build files (defaults shown; paths are relative to the theme folder):
 *
 *     agile_build_config = "_00_main_configuration.yml"
 *     agile_build_source = "source"
 *     agile_build_fonts  = "source/sass/00_configuration/_04_typography_fonts.sass"
 *
 * Full reference: docs/THEME-IDENTITY.md.
 */
class ThemeProfile
{
    const KEY_BASE = 'agile_base';
    const KEY_BASE_VERSION = 'agile_base_version';
    const KEY_MODULE_CONSTRAINT = 'agile_theme_tools';
    const KEY_BUILD = 'agile_build';
    const KEY_BUILD_CONFIG = 'agile_build_config';
    const KEY_BUILD_SOURCE = 'agile_build_source';
    const KEY_BUILD_FONTS = 'agile_build_fonts';

    /** The only build system so far: the theme's gulpfile, run by ThemeBuild\BuildManager. */
    const BUILD_GULP = 'gulp';

    const DEFAULT_BUILD_CONFIG = '_00_main_configuration.yml';
    const DEFAULT_BUILD_SOURCE = 'source';
    /** Relative to the source directory. */
    const DEFAULT_FONTS_FILE = 'sass/00_configuration/_04_typography_fonts.sass';

    /** Known base themes. A fork keeps the value of the base it came from. */
    const BASE_ADVANCED = 'agile-base';
    const BASE_SIMPLIFIED = 'agile-base-simplified';

    /**
     * Themes made before the keys existed are recognised by the theme setting that switches per-site styling on.
     * They keep working, are reported as undeclared, and should be given the keys.
     */
    const LEGACY_SETTING = 'configurable_site_theme';

    protected Theme $theme;

    /** @var array Messages about values in theme.ini that could not be used. */
    protected array $invalid = [];

    /** @var self[] One profile per theme folder, for the length of the request. */
    protected static array $profiles = [];

    protected static ?string $moduleVersion = null;

    public static function of(Theme $theme): self
    {
        return self::$profiles[$theme->getId()] ??= new self($theme);
    }

    /** Forget cached profiles (a theme.ini was changed during the request). */
    public static function reset(): void
    {
        self::$profiles = [];
    }

    /** The installed Agile Theme Tools version, from this module's own module.ini. */
    public static function moduleVersion(): string
    {
        if (self::$moduleVersion === null) {
            $ini = @parse_ini_file(dirname(__DIR__, 2) . '/config/module.ini', true, INI_SCANNER_RAW);
            self::$moduleVersion = (string) ($ini['info']['version'] ?? '');
        }
        return self::$moduleVersion;
    }

    protected function __construct(Theme $theme)
    {
        $this->theme = $theme;
    }

    public function theme(): Theme
    {
        return $this->theme;
    }

    // ------------------------------------------------------------------ identity

    /** The theme carries the agile_base key. */
    public function isDeclared(): bool
    {
        return $this->ini(self::KEY_BASE) !== '';
    }

    /** An undeclared theme built on the Agile theme system before the keys existed. */
    public function isLegacy(): bool
    {
        if ($this->isDeclared()) {
            return false;
        }
        $spec = $this->theme->getConfigSpec();
        return isset($spec['elements'][self::LEGACY_SETTING])
            && is_file($this->theme->getPath() . '/' . self::DEFAULT_BUILD_CONFIG);
    }

    /** Declared or legacy: the module's theme features apply to this theme. */
    public function isAgile(): bool
    {
        return $this->isDeclared() || $this->isLegacy();
    }

    /** The base theme this theme is or derives from, e.g. "agile-base". Null for a theme that is not an Agile one. */
    public function base(): ?string
    {
        if ($this->isDeclared()) {
            return $this->ini(self::KEY_BASE);
        }
        return $this->isLegacy() ? self::BASE_ADVANCED : null;
    }

    /**
     * Whether the theme styles the module's own templates itself, so the module's basic stylesheets
     * (Styling\BasicStyles) are redundant for it. True for every Agile theme: the Simplified theme carries the
     * Advanced theme's compiled stylesheet, block styles included.
     */
    public function stylesModuleTemplates(): bool
    {
        return $this->isAgile();
    }

    /** The version of the base theme this theme corresponds to, as declared. Null when not declared. */
    public function baseVersion(): ?string
    {
        return $this->ini(self::KEY_BASE_VERSION) ?: null;
    }

    /** The Agile Theme Tools versions the theme says it works with (a composer constraint). Null when not declared. */
    public function moduleConstraint(): ?string
    {
        return $this->ini(self::KEY_MODULE_CONSTRAINT) ?: null;
    }

    /**
     * Whether the installed module satisfies the theme's constraint. Null when the theme declares none, or declares
     * one that cannot be read.
     */
    public function isModuleCompatible(?string $moduleVersion = null): ?bool
    {
        $constraint = $this->moduleConstraint();
        $moduleVersion = $moduleVersion ?? self::moduleVersion();
        if ($constraint === null || $moduleVersion === '') {
            return null;
        }
        try {
            return Semver::satisfies($moduleVersion, $constraint);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------ per-site builds

    /** The build system the theme declares ("gulp"), or null when it has none. Legacy themes build with gulp. */
    public function buildSystem(): ?string
    {
        if ($this->isDeclared()) {
            return strtolower($this->ini(self::KEY_BUILD)) ?: null;
        }
        return $this->isLegacy() ? self::BUILD_GULP : null;
    }

    /** The theme can be rebuilt per site from its SASS sources by this module. */
    public function supportsSiteBuilds(): bool
    {
        return $this->buildSystem() === self::BUILD_GULP;
    }

    /** Default styling configuration, relative to the theme folder. */
    public function configFileName(): string
    {
        return $this->relativePath(self::KEY_BUILD_CONFIG, self::DEFAULT_BUILD_CONFIG);
    }

    public function configFile(): string
    {
        return $this->theme->getPath() . '/' . $this->configFileName();
    }

    public function hasDefaultConfigFileName(): bool
    {
        return $this->configFileName() === self::DEFAULT_BUILD_CONFIG;
    }

    /** Source directory (SASS, scripts, fonts, images), relative to the theme folder. */
    public function sourceDirName(): string
    {
        return $this->relativePath(self::KEY_BUILD_SOURCE, self::DEFAULT_BUILD_SOURCE);
    }

    public function sourceDir(): string
    {
        return $this->theme->getPath() . '/' . $this->sourceDirName();
    }

    public function hasDefaultSourceDirName(): bool
    {
        return $this->sourceDirName() === self::DEFAULT_BUILD_SOURCE;
    }

    /** The SASS file that loads the theme's bundled fonts with +load_font, relative to the theme folder. */
    public function fontsFileName(): string
    {
        return $this->relativePath(self::KEY_BUILD_FONTS, $this->sourceDirName() . '/' . self::DEFAULT_FONTS_FILE);
    }

    public function fontsFile(): string
    {
        return $this->theme->getPath() . '/' . $this->fontsFileName();
    }

    // ------------------------------------------------------------------ audit

    /**
     * What an administrator should know about the theme's declaration.
     *
     * @return array[] ['level' => 'error'|'warning'|'note', 'message' => string, 'fix' => string]
     */
    public function findings(?string $moduleVersion = null): array
    {
        $findings = [];
        if (!$this->isAgile()) {
            return $findings;
        }
        $moduleVersion = $moduleVersion ?? self::moduleVersion();

        if ($this->isLegacy()) {
            $findings[] = $this->finding('warning',
                'This theme does not declare which Agile base theme it is built on. It was recognised by its “Use theme configuration” setting.', // @translate
                sprintf('Add %s = "%s" and the related keys to the [info] section of the theme’s config/theme.ini.', self::KEY_BASE, self::BASE_ADVANCED));
        }

        $constraint = $this->moduleConstraint();
        if ($constraint !== null) {
            $compatible = $this->isModuleCompatible($moduleVersion);
            if ($compatible === false) {
                $findings[] = $this->finding('error',
                    sprintf('This theme is made for Agile Theme Tools %1$s. Version %2$s is installed.', $constraint, $moduleVersion),
                    'Install a matching version of the module or of the theme.');
            } elseif ($compatible === null) {
                $findings[] = $this->finding('warning',
                    sprintf('The value of %1$s in theme.ini ("%2$s") is not a version constraint that can be read.', self::KEY_MODULE_CONSTRAINT, $constraint),
                    'Use a constraint such as "^2.0".');
            }
        } elseif ($this->isDeclared() && $this->buildSystem() !== null) {
            // Only worth saying for a theme that leans on the module: one without builds works without it.
            $findings[] = $this->finding('note',
                sprintf('The theme does not say which Agile Theme Tools versions it works with (%s).', self::KEY_MODULE_CONSTRAINT), '');
        }

        if ($this->isDeclared() && $this->baseVersion() === null) {
            $findings[] = $this->finding('note',
                sprintf('The theme does not say which version of its base theme it corresponds to (%s), so it cannot be compared with that version.', self::KEY_BASE_VERSION), '');
        }

        $build = $this->isDeclared() ? strtolower($this->ini(self::KEY_BUILD)) : '';
        if ($build !== '' && $build !== self::BUILD_GULP) {
            $findings[] = $this->finding('warning',
                sprintf('The theme declares a build system (%1$s = "%2$s") that this version of the module cannot run.', self::KEY_BUILD, $build),
                sprintf('Use "%s", or remove the key if the theme has no per-site builds.', self::BUILD_GULP));
        }

        // Resolve the declared paths so that unusable ones are reported.
        $this->configFileName();
        $this->sourceDirName();
        $this->fontsFileName();
        foreach ($this->invalid as $message) {
            $findings[] = $this->finding('warning', $message, 'Use a path inside the theme folder, without “..”.');
        }

        return $findings;
    }

    // ------------------------------------------------------------------ helpers

    protected function ini(string $key): string
    {
        $value = $this->theme->getIni($key);
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * A path declared in theme.ini, relative to the theme folder. Absolute paths and paths that climb out of the
     * theme are refused, and the default is used instead.
     */
    protected function relativePath(string $key, string $default): string
    {
        $value = $this->ini($key);
        if ($value === '') {
            return $default;
        }
        $value = preg_replace('#^(\./)+#', '', str_replace('\\', '/', $value));
        $value = rtrim($value, '/');
        if ($value === '' || $value[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $value) || preg_match('#^[A-Za-z]:#', $value)) {
            $this->invalid[$key] = sprintf('The value of %1$s in theme.ini ("%2$s") is not a usable path; "%3$s" is used instead.', $key, $this->ini($key), $default);
            return $default;
        }
        return $value;
    }

    protected function finding(string $level, string $message, string $fix): array
    {
        return ['level' => $level, 'message' => $message, 'fix' => $fix];
    }
}
