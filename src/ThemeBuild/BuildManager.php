<?php
namespace AgileThemeTools\ThemeBuild;

use AgileThemeTools\Settings\SiteThemeSettings;
use AgileThemeTools\Theme\ThemeProfile;
use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\Job;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;
use Omeka\Site\Theme\Manager as ThemeManager;
use Omeka\Site\Theme\Theme;
use Omeka\Stdlib\Cli;
use Symfony\Component\Yaml\Exception\ExceptionInterface as YamlException;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds a site's theme assets from its saved theme styling settings.
 *
 * Everything the web server writes lives under files/theme-assets/<theme>/<site slug>/, the one directory Omeka
 * guarantees the PHP user can write. The theme directory is only read. That directory holds the build's
 * config.yml, build.log and build-manifest.json, and the compiled assets in assets/ (gulp's destination, which it
 * empties itself). A build runs in a staging directory (<slug>.building), is verified, and is then swapped in, so a
 * failed build never leaves a site without assets. The previous build is kept as <slug>.previous; a failed build is
 * kept as <slug>.failed with its log.
 *
 * The site's configuration is written as YAML (config.yml) and passed to the theme's gulp build, which layers it
 * over the theme's _00_main_configuration.yml. A developer can hand the same gulp build a file from the theme's
 * alt-config directory, so the CLI workflow and the admin workflow share one format.
 *
 * build-manifest.json records what was built from what. Staleness is decided by comparing its hashes with the
 * current settings and sources, never by file timestamps: gulp copies source timestamps onto its output.
 */
class BuildManager
{
    const ROOT_DIR_NAME = 'theme-assets';
    const CONFIG_FILE = 'config.yml';
    const MANIFEST_FILE = 'build-manifest.json';
    const LOG_FILE = 'build.log';
    const LOCK_FILE = '.build.lock';
    const HOME_DIR_NAME = '.home';
    const BUILDING_SUFFIX = '.building';
    const PREVIOUS_SUFFIX = '.previous';
    const FAILED_SUFFIX = '.failed';
    const ASSETS_DIR_NAME = 'assets';
    const MANIFEST_VERSION = 1;

    /** Global setting: an explicit node executable (or its directory) when node is not on the PHP user's PATH. */
    const NODE_PATH_SETTING = 'agilethemetools_node_path';

    /** Theme setting that turns per-site styling on. */
    const CONFIGURABLE_SETTING = 'configurable_site_theme';

    /** Files gulp must have produced for a build to count as successful, relative to the assets directory. */
    const REQUIRED_ARTIFACTS = ['css/style.min.css', 'js/default.js'];

    /** Seconds a build may run before it is killed. Image optimisation of a large theme takes about a minute. */
    const BUILD_TIMEOUT = 900;

    /** The Node.js major version the theme's build tools expect (gulp-postcss 10, postcss-load-config 5). */
    const NODE_MIN_MAJOR = 18;

    /** Bytes of free space below which preflight fails. */
    const MIN_FREE_BYTES = 300 * 1024 * 1024;

    /**
     * Row in the styling table that records which base theme (theme.ini agile_base) the site's styling values were
     * saved for. The values are only used with themes of that base. See getStylingBase().
     */
    const STYLING_BASE_KEY = '_agile_base';

    /** Settings keys saved by the styling form that are not SASS configuration. */
    const NON_CONFIG_KEYS = ['slug', 'site_id', 'submit', 'reset', 'csrf', 'source_imgs', 'source_dir', self::STYLING_BASE_KEY];

    /** Per-site build options (not SASS configuration) saved by the styling form. */
    const OPTION_KEYS = ['source_imgs', 'source_dir'];

    protected ThemeManager $themes;
    protected ApiManager $api;
    protected SiteThemeSettings $siteThemeSettings;
    protected SiteSettings $siteSettings;
    protected Settings $settings;
    protected Cli $cli;
    protected EntityManager $entityManager;
    protected string $filesPath;
    protected ?string $filesUri;

    /** @var array Default configuration per theme id, parsed once. */
    protected array $defaultConfigCache = [];

    public function __construct(
        ThemeManager $themes,
        ApiManager $api,
        SiteThemeSettings $siteThemeSettings,
        SiteSettings $siteSettings,
        Settings $settings,
        Cli $cli,
        EntityManager $entityManager,
        string $filesPath,
        ?string $filesUri
    ) {
        $this->themes = $themes;
        $this->api = $api;
        $this->siteThemeSettings = $siteThemeSettings;
        $this->siteSettings = $siteSettings;
        $this->settings = $settings;
        $this->cli = $cli;
        $this->entityManager = $entityManager;
        $this->filesPath = rtrim($filesPath, '/');
        $this->filesUri = $filesUri !== null ? rtrim($filesUri, '/') : null;
    }

    /** @var \AgileThemeTools\Styling\PaletteCss|null Set by the factory; the palette stylesheet (colours and fonts without a build). */
    protected $paletteCss = null;

    public function setPaletteCss(\AgileThemeTools\Styling\PaletteCss $paletteCss): self
    {
        $this->paletteCss = $paletteCss;
        return $this;
    }

    public function getPaletteCss(): \AgileThemeTools\Styling\PaletteCss
    {
        return $this->paletteCss ?? new \AgileThemeTools\Styling\PaletteCss($this);
    }

    // ------------------------------------------------------------------ paths

    public function getRootDir(): string
    {
        return $this->filesPath . '/' . self::ROOT_DIR_NAME;
    }

    public function getThemeDir(Theme $theme): string
    {
        return $this->getRootDir() . '/' . $theme->getId();
    }

    public function getBuildDir(Theme $theme, SiteRepresentation $site): string
    {
        return $this->getThemeDir($theme) . '/' . $site->slug();
    }

    /**
     * The build directory's URL path, relative to the site root (e.g. /files/theme-assets/pitts3/digital-collections),
     * or absolute when the local file store has a base URI configured.
     */
    public function getBuildUri(Theme $theme, SiteRepresentation $site, string $basePath = ''): string
    {
        $filesUri = $this->filesUri ?? ($basePath . '/files');
        return $filesUri . '/' . self::ROOT_DIR_NAME . '/' . $theme->getId() . '/' . $site->slug();
    }

    /** The compiled assets of a build: css/, js/, img/, fonts/. */
    public function getAssetsDir(Theme $theme, SiteRepresentation $site): string
    {
        return $this->getBuildDir($theme, $site) . '/' . self::ASSETS_DIR_NAME;
    }

    public function getAssetsUri(Theme $theme, SiteRepresentation $site, string $basePath = ''): string
    {
        return $this->getBuildUri($theme, $site, $basePath) . '/' . self::ASSETS_DIR_NAME;
    }

    /** Where builds went before they moved to files/: asset-<slug> inside the theme. */
    public function getLegacyBuildDir(Theme $theme, SiteRepresentation $site): string
    {
        return $theme->getPath() . '/asset-' . $site->slug();
    }

    public function getHomeDir(): string
    {
        return $this->getRootDir() . '/' . self::HOME_DIR_NAME;
    }

    // ------------------------------------------------------------------ theme facts

    public function getSiteTheme(SiteRepresentation $site): ?Theme
    {
        $theme = $this->themes->getTheme($site->theme());
        return $theme && $theme->getState() === ThemeManager::STATE_ACTIVE ? $theme : null;
    }

    /**
     * Whether per-site styling is on in a site's theme settings. The theme.ini checkbox is on by default, so a site
     * whose theme settings were never saved, or were written without the key, counts as on; only an explicit off
     * (an unticked box saves "0") turns it off. The admin Theme page, the build manager and the siteAssetUrl helper
     * all use this rule, so a build offered on the Theme page is also the build the site serves.
     */
    public static function stylingEnabled(?array $themeSettings): bool
    {
        if (!is_array($themeSettings) || !array_key_exists(self::CONFIGURABLE_SETTING, $themeSettings)) {
            return true;
        }
        return !empty($themeSettings[self::CONFIGURABLE_SETTING]);
    }

    /**
     * Whether the site has per-site theme styling turned on in its theme settings. Read straight from the table:
     * the shared site settings service is bound to the current site, and re-targeting it here would make later
     * reads in the same request see another site's settings.
     */
    public function isSiteConfigurable(Theme $theme, SiteRepresentation $site): bool
    {
        // The theme must say it supports per-site builds (theme.ini, see Theme\ThemeProfile).
        if (!ThemeProfile::of($theme)->supportsSiteBuilds()) {
            return false;
        }
        $value = $this->entityManager->getConnection()->fetchOne(
            'SELECT value FROM site_setting WHERE site_id = ? AND id = ?',
            [$site->id(), $theme->getSettingsKey()]
        );
        $themeSettings = $value ? json_decode((string) $value, true) : null;
        return self::stylingEnabled($themeSettings);
    }

    /**
     * Sites that use the theme and have per-site styling on.
     *
     * @return SiteRepresentation[]
     */
    public function getConfigurableSites(Theme $theme): array
    {
        $sites = [];
        foreach ($this->api->search('sites', ['sort_by' => 'title'])->getContent() as $site) {
            if ($site->theme() === $theme->getId() && $this->isSiteConfigurable($theme, $site)) {
                $sites[] = $site;
            }
        }
        return $sites;
    }

    /**
     * Every site, across all active themes, that has per-site styling on.
     *
     * @return SiteRepresentation[]
     */
    public function getAllConfigurableSites(): array
    {
        $sites = [];
        foreach ($this->api->search('sites', ['sort_by' => 'title'])->getContent() as $site) {
            $theme = $this->getSiteTheme($site);
            if ($theme && $this->isSiteConfigurable($theme, $site)) {
                $sites[] = $site;
            }
        }
        return $sites;
    }

    public function getLibraryVersion(Theme $theme): ?string
    {
        $file = $theme->getPath() . '/node_modules/stop14-themesystem-legacy/package.json';
        if (!is_readable($file)) {
            return null;
        }
        $package = json_decode((string) file_get_contents($file), true);
        return is_array($package) ? ($package['version'] ?? null) : null;
    }

    // ------------------------------------------------------------------ configuration

    /**
     * The theme's default styling configuration (_00_main_configuration.yml unless the theme declares another
     * file), keyed by variable name.
     *
     * @throws \RuntimeException when the file is missing or not valid YAML.
     */
    public function getDefaultConfig(Theme $theme): array
    {
        $id = $theme->getId();
        if (!isset($this->defaultConfigCache[$id])) {
            $file = ThemeProfile::of($theme)->configFile();
            if (!is_readable($file)) {
                throw new \RuntimeException(sprintf('The theme has no readable default configuration at %s.', $file));
            }
            try {
                $parsed = Yaml::parseFile($file);
            } catch (YamlException $e) {
                throw new \RuntimeException(sprintf('The theme default configuration %s is not valid YAML: %s', $file, $e->getMessage()), 0, $e);
            }
            if (!is_array($parsed)) {
                throw new \RuntimeException(sprintf('The theme default configuration %s must be a YAML mapping.', $file));
            }
            $this->defaultConfigCache[$id] = $parsed;
        }
        return $this->defaultConfigCache[$id];
    }

    // ------------------------------------------------------------------ which base the styling belongs to

    /**
     * The base theme (theme.ini agile_base) the site's styling values were saved for. Null when the site has no
     * styling values. Values saved before this was recorded are taken to belong to the Advanced base theme, the
     * only one that had per-site styling. Read straight from the table: see isSiteConfigurable().
     */
    public function getStylingBase(SiteRepresentation $site): ?string
    {
        $connection = $this->entityManager->getConnection();
        $stored = $connection->fetchOne(
            'SELECT value FROM site_theme_setting WHERE site_id = ? AND id = ?',
            [$site->id(), self::STYLING_BASE_KEY]
        );
        if ($stored !== false) {
            $base = json_decode((string) $stored, true);
            if (is_string($base) && $base !== '') {
                return $base;
            }
        }
        $hasValues = (bool) $connection->fetchOne(
            'SELECT 1 FROM site_theme_setting WHERE site_id = ? AND id <> ? LIMIT 1',
            [$site->id(), self::STYLING_BASE_KEY]
        );
        return $hasValues ? ThemeProfile::BASE_ADVANCED : null;
    }

    /**
     * Whether the site's saved styling values are used with this theme: they were saved for the same base theme, or
     * there are none. A fork keeps its base's value, so a site that moves between a base theme and its forks keeps
     * its styling; with a theme of another base the values stay in the table, unused.
     */
    public function stylingApplies(Theme $theme, SiteRepresentation $site): bool
    {
        $base = $this->getStylingBase($site);
        return $base === null || $base === ThemeProfile::of($theme)->base();
    }

    /** Records that the site's styling values now belong to this theme's base. Called when they are saved. */
    public function setStylingBase(Theme $theme, SiteRepresentation $site): void
    {
        $base = ThemeProfile::of($theme)->base();
        if ($base === null) {
            return;
        }
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO site_theme_setting (id, site_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [self::STYLING_BASE_KEY, $site->id(), json_encode($base)]
        );
    }

    /**
     * The site's styling configuration: the theme defaults overlaid with every setting the site has saved. A setting
     * saved empty means "use the default". Only keys the theme declares are used, and only when the saved values
     * belong to this theme's base (stylingApplies()).
     */
    public function getSiteConfig(Theme $theme, SiteRepresentation $site): array
    {
        $config = $this->getDefaultConfig($theme);
        if (!$this->stylingApplies($theme, $site)) {
            return $config;
        }
        $this->siteThemeSettings->setTargetId($site->id());
        foreach (array_keys($config) as $key) {
            if (in_array($key, self::NON_CONFIG_KEYS, true)) {
                continue;
            }
            $value = $this->siteThemeSettings->get($key);
            if ($value !== null && $value !== '') {
                $config[$key] = $value;
            }
        }
        return $config;
    }

    /** Build options saved with the styling settings: alternative image and source directories. */
    public function getBuildOptions(SiteRepresentation $site): array
    {
        $options = array_fill_keys(self::OPTION_KEYS, '');
        // They are paths inside a theme of the base they were saved for: meaningless with another.
        $theme = $this->getSiteTheme($site);
        if ($theme && !$this->stylingApplies($theme, $site)) {
            return $options;
        }
        $this->siteThemeSettings->setTargetId($site->id());
        foreach (self::OPTION_KEYS as $key) {
            $value = $this->siteThemeSettings->get($key);
            $options[$key] = is_string($value) ? trim($value) : '';
        }
        return $options;
    }

    /**
     * Identifies the settings a build is made from. Changes when any styling setting or build option changes.
     * Values are compared as strings: the same setting may be stored as "1.2" by the form and as 1.2 by a reset to
     * the YAML defaults, and that difference must not count as a change.
     */
    public function getConfigHash(Theme $theme, SiteRepresentation $site): string
    {
        $normalize = fn (array $values) => array_map(fn ($v) => is_scalar($v) ? (string) $v : $v, $values);
        return sha1(json_encode([$normalize($this->getSiteConfig($theme, $site)), $normalize($this->getBuildOptions($site))]));
    }

    /**
     * Identifies the theme sources a build is made from: the newest modification time and the file count under
     * source/, plus the build files. Deploys and edits change it; it is cheap to compute and good enough to say
     * "the theme changed since this was built".
     */
    public function getSourceFingerprint(Theme $theme): string
    {
        $path = $theme->getPath();
        $profile = ThemeProfile::of($theme);
        $source = $profile->sourceDir();
        $newest = 0;
        $count = 0;
        foreach (['gulpfile.js', 'package.json', $profile->configFileName(), 'config/theme.ini', 'custom.css', 'custom.js'] as $file) {
            if (is_file("$path/$file")) {
                $newest = max($newest, (int) filemtime("$path/$file"));
                $count++;
            }
        }
        if (is_dir($source)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $newest = max($newest, (int) $file->getMTime());
                $count++;
            }
        }
        return sprintf('%d-%d-%s', $newest, $count, (string) $theme->getIni('version'));
    }

    /** Writes the site's configuration as YAML into $dir and returns the file path. */
    public function writeConfig(Theme $theme, SiteRepresentation $site, string $dir): string
    {
        $config = $this->getSiteConfig($theme, $site);
        $yaml = "# Theme styling configuration for site \"" . $site->slug() . "\" (" . $theme->getId() . "), generated from its saved settings.\n"
            . "# Layered over the theme's " . ThemeProfile::of($theme)->configFileName() . " by the theme build. Regenerated on every build.\n"
            . Yaml::dump($config, 1, 2, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
        $file = $dir . '/' . self::CONFIG_FILE;
        if (file_put_contents($file, $yaml) === false) {
            throw new \RuntimeException(sprintf('Unable to write the configuration file %s.', $file));
        }
        return $file;
    }

    // ------------------------------------------------------------------ manifest and status

    public function readManifest(string $dir): ?array
    {
        $file = $dir . '/' . self::MANIFEST_FILE;
        if (!is_readable($file)) {
            return null;
        }
        $manifest = json_decode((string) file_get_contents($file), true);
        return is_array($manifest) ? $manifest : null;
    }

    /**
     * Everything the admin needs to know about a site's build.
     */
    public function getStatus(Theme $theme, SiteRepresentation $site): array
    {
        $buildDir = $this->getBuildDir($theme, $site);
        $manifest = $this->readManifest($buildDir);
        $failed = $this->readManifest($buildDir . self::FAILED_SUFFIX);
        if ($failed && $manifest && ($failed['finishedAt'] ?? '') < ($manifest['finishedAt'] ?? '')) {
            $failed = null; // A success since the failure supersedes it.
        }

        $configHash = null;
        $configError = null;
        try {
            $configHash = $this->getConfigHash($theme, $site);
        } catch (\RuntimeException $e) {
            $configError = $e->getMessage();
        }
        $sourceFingerprint = $this->getSourceFingerprint($theme);

        $reasons = [];
        if (!$manifest) {
            $reasons[] = is_dir($this->getLegacyBuildDir($theme, $site))
                ? 'This site is served from a build in the theme folder (asset-' . $site->slug() . '). Rebuild to move it to the files directory.'
                : 'This site has never been built.';
        } else {
            if ($configHash !== null && ($manifest['configHash'] ?? null) !== $configHash) {
                $reasons[] = 'The theme styling settings have changed since the last build.';
            }
            if (($manifest['sourceFingerprint'] ?? null) !== $sourceFingerprint) {
                $reasons[] = 'The theme files have changed since the last build.';
            }
        }

        return [
            'built' => $manifest !== null,
            'legacy' => $manifest === null && is_dir($this->getLegacyBuildDir($theme, $site)),
            'manifest' => $manifest,
            'failed' => $failed,
            'stale' => $reasons !== [],
            'reasons' => $reasons,
            'configHash' => $configHash,
            'configError' => $configError,
            'sourceFingerprint' => $sourceFingerprint,
            'buildDir' => $buildDir,
            'activeJob' => $this->findActiveJob($site),
        ];
    }

    /** The running or queued build job for the site, if any. */
    public function findActiveJob(SiteRepresentation $site): ?Job
    {
        $jobs = $this->entityManager->getRepository(Job::class)->findBy(
            ['class' => [\AgileThemeTools\Job\BuildSiteTheme::class, \AgileThemeTools\Job\InstallThemeDependencies::class], 'status' => [Job::STATUS_STARTING, Job::STATUS_IN_PROGRESS]],
            ['id' => 'DESC'],
            20
        );
        $cutoff = (new \DateTime())->modify('-' . (self::BUILD_TIMEOUT + 600) . ' seconds');
        foreach ($jobs as $job) {
            // A job that never ended (PHP killed, synchronous run interrupted) must not show as running forever.
            $started = $job->getStarted();
            if ($started && $started < $cutoff) {
                continue;
            }
            $args = $job->getArgs();
            $ids = array_map('intval', (array) ($args['site_ids'] ?? []));
            if (in_array($site->id(), $ids, true)) {
                return $job;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ preflight

    /**
     * Checks whether a build can run, with a fix for each problem. Each check is
     * ['id' => …, 'label' => …, 'ok' => true|false|null (null = warning), 'detail' => …, 'fix' => …].
     *
     * @return array ['ok' => bool, 'user' => string, 'checks' => array]
     */
    public function preflight(Theme $theme, SiteRepresentation $site): array
    {
        $checks = [];
        $themePath = $theme->getPath();
        $user = $this->getProcessUser();
        $profile = ThemeProfile::of($theme);
        $configName = $profile->configFileName();
        $sourceName = $profile->sourceDirName();

        $checks[] = $this->identityCheck($profile);

        $hasBuildFiles = is_file("$themePath/gulpfile.js") && is_file("$themePath/package.json") && is_dir($profile->sourceDir());
        $checks[] = $this->check('theme_files', 'Theme build files', $hasBuildFiles, // @translate
            $hasBuildFiles ? "gulpfile.js, package.json and $sourceName/ found in $themePath." : "$themePath must contain gulpfile.js, package.json and the source directory ($sourceName).",
            'Deploy the theme with its build files. They are part of the theme repository.');

        try {
            $this->getDefaultConfig($theme);
            $checks[] = $this->check('default_config', 'Theme default configuration', true, "$themePath/$configName parses."); // @translate
        } catch (\RuntimeException $e) {
            $checks[] = $this->check('default_config', 'Theme default configuration', false, $e->getMessage(), "Fix the YAML in $configName in the theme."); // @translate
        }

        $gulp = "$themePath/node_modules/gulp/bin/gulp.js";
        $checks[] = $this->check('node_modules', 'Theme dependencies (node_modules)', is_file($gulp), // @translate
            is_file($gulp) ? "gulp found at $gulp." : "The theme's node_modules directory is missing or incomplete ($gulp not found).",
            'Use "Install theme dependencies" below (it runs npm install as a background job), or run `npm install` in ' . $themePath . ' as a user who can write there, then rebuild.');

        // npm: needed to install the theme's dependencies from the admin; not needed for a build once they are there.
        $npm = $this->findNpm();
        if ($npm === null) {
            $checks[] = $this->check('npm', 'npm', is_file($gulp) ? null : false, // @translate
                sprintf('No npm executable was found beside node or on the PATH of %s.', $user),
                'Install Node.js with npm where the web server can run it (the node path in Settings > Theme build is used to find npm too).');
        } else {
            $npmVersion = $this->runQuick([$npm, '--version'], $themePath);
            $checks[] = $this->check('npm', 'npm', $npmVersion['exitCode'] === 0 ? true : (is_file($gulp) ? null : false), // @translate
                $npmVersion['exitCode'] === 0 ? sprintf('%s (%s)', trim($npmVersion['output']), $npm) : sprintf('%s could not be run: %s', $npm, trim($npmVersion['output'])),
                'Check that npm runs as ' . $user . '.');
        }
        $themeWritable = is_writable($themePath) && (!is_dir("$themePath/node_modules") || is_writable("$themePath/node_modules"));
        $checks[] = $this->check('theme_writable', 'Theme folder writable (for npm install)', $themeWritable ? true : (is_file($gulp) ? null : false), // @translate
            $themeWritable ? sprintf('%s is writable by %s.', $themePath, $user) : sprintf('%s is not writable by %s, so npm install cannot run from the admin.', $themePath, $user),
            sprintf('Make the theme folder writable by %s (chown or group write), or run npm install there yourself.', $user));

        $node = $this->findNode();
        $nodeSetting = trim((string) $this->settings->get(self::NODE_PATH_SETTING));
        if ($node === null && $nodeSetting !== '') {
            $checks[] = $this->check('node', 'Node.js', false, // @translate
                sprintf('The Node.js path in Settings > Theme build (%s) is not an executable node, or a directory holding one, that %s may run.', $nodeSetting, $user),
                'Correct the path in Settings > Theme build, or clear it to use the node on the PATH.');
        } elseif ($node === null) {
            $checks[] = $this->check('node', 'Node.js', false, // @translate
                sprintf('No node executable was found on the PATH of the PHP user (%s).', $user),
                'Install Node.js where the web server can run it, or set its path in Settings > Theme build.');
        } else {
            $version = $this->runQuick([$node, '--version'], $themePath);
            // The theme's build tools want Node 18 or later (gulp-postcss, postcss-load-config); older runs with warnings.
            $major = (int) ltrim(trim($version['output']), 'v');
            $nodeOk = $version['exitCode'] !== 0 ? false : ($major >= self::NODE_MIN_MAJOR ? true : null);
            $checks[] = $this->check('node', 'Node.js', $nodeOk, // @translate
                $version['exitCode'] === 0 ? sprintf('%s (%s)%s', trim($version['output']), $node, $major < self::NODE_MIN_MAJOR ? sprintf(' — older than Node %d, which the theme\'s build tools expect; builds may fail.', self::NODE_MIN_MAJOR) : '') : sprintf('%s could not be run: %s', $node, trim($version['output'])),
                'Check the Node.js path in Settings > Theme build, and that the web server user may execute it.');
        }

        $optipng = "$themePath/node_modules/optipng-bin/vendor/optipng";
        if (is_file($optipng)) {
            $probe = $this->runQuick([$optipng, '--version'], $themePath);
            $checks[] = $this->check('image_tools', 'Image optimisation binaries', $probe['exitCode'] === 0 ? true : null, // @translate
                $probe['exitCode'] === 0 ? 'optipng runs.' : 'optipng in node_modules cannot run on this server (' . trim(strtok($probe['output'], "\n") ?: 'no output') . '). It was probably installed on another platform.',
                "Run `npm rebuild` (or reinstall node_modules) in $themePath on this server.");
        }

        $rootDir = $this->getThemeDir($theme);
        $rootOk = $this->ensureDir($rootDir) && is_writable($rootDir);
        $checks[] = $this->check('files_writable', 'Build directory', $rootOk, // @translate
            $rootOk ? "$rootDir is writable by $user." : "$rootDir cannot be created or written by $user.",
            sprintf('Make the directory writable by the web server user, e.g. `chown -R %s %s` or `chmod -R g+w %s`.', $user, $this->getRootDir(), $this->getRootDir()));

        foreach ([$this->getBuildDir($theme, $site), $this->getBuildDir($theme, $site) . self::BUILDING_SUFFIX, $this->getBuildDir($theme, $site) . self::PREVIOUS_SUFFIX, $this->getBuildDir($theme, $site) . self::FAILED_SUFFIX] as $dir) {
            if (is_dir($dir) && !$this->isTreeWritable($dir)) {
                $owner = $this->getPathOwner($dir);
                $checks[] = $this->check('build_dir_owner', 'Existing build ownership', false, // @translate
                    sprintf('%s contains files owned by %s that %s cannot replace.', $dir, $owner, $user),
                    sprintf('Run `chown -R %s %s`, or delete the directory. Builds started from the command line should run as %s.', $user, $dir, $user));
                break;
            }
        }

        $home = $this->getHomeDir();
        $homeOk = $this->ensureDir($home) && is_writable($home);
        $checks[] = $this->check('home', 'Writable home directory for the build tools', $homeOk, // @translate
            $homeOk ? "$home" : "$home cannot be created or written.",
            'Fix the build directory permissions above; the home directory lives inside it.');

        $free = @disk_free_space($this->filesPath);
        $checks[] = $this->check('disk_space', 'Disk space', $free === false ? null : $free > self::MIN_FREE_BYTES, // @translate
            $free === false ? 'Free space could not be determined.' : sprintf('%s free.', $this->formatBytes((int) $free)),
            'Free at least 300 MB on the volume that holds the files directory.');

        $procOpen = function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
        $checks[] = $this->check('proc_open', 'PHP can run processes', $procOpen, // @translate
            $procOpen ? 'proc_open is available.' : 'proc_open is disabled in PHP.',
            'Remove proc_open from disable_functions in php.ini.');

        if (is_dir($this->getLegacyBuildDir($theme, $site))) {
            $checks[] = $this->check('legacy_build', 'Old build in the theme folder', null, // @translate
                sprintf('%s exists. It is used until the site is built here, then it is no longer needed.', $this->getLegacyBuildDir($theme, $site)),
                'Rebuild the site, then delete the old asset-' . $site->slug() . ' directory from the theme.');
        }

        $ok = true;
        foreach ($checks as $check) {
            if ($check['ok'] === false) {
                $ok = false;
            }
        }
        return ['ok' => $ok, 'user' => $user, 'checks' => $checks];
    }

    // ------------------------------------------------------------------ build

    /**
     * Builds the site's theme assets. $log receives each line of progress and build output as it happens.
     *
     * @return array ['status' => 'success'|'error', 'message' => string, 'manifest' => ?array, 'preflight' => ?array]
     */
    public function build(Theme $theme, SiteRepresentation $site, callable $log, ?int $jobId = null): array
    {
        $startedAt = new \DateTimeImmutable();
        $start = microtime(true);

        // Every line goes to the caller and, once the staging directory exists, to its build.log as it happens, so
        // progress can be followed while the build runs (the job's own log is only saved when the job ends).
        $buffer = [];
        $logFile = null;
        $emit = function (string $line) use (&$buffer, &$logFile, $log) {
            $log($line);
            if ($logFile !== null) {
                // Checked rather than silenced: error handlers that log (the Log module's) ignore the @ operator.
                if (is_dir(dirname($logFile))) {
                    file_put_contents($logFile, $line . "\n", FILE_APPEND);
                }
            } else {
                $buffer[] = $line;
            }
        };
        $log = $emit;

        $log(sprintf('Building theme "%s" for site "%s" (%s).', $theme->getId(), $site->title(), $site->slug()));

        $preflight = $this->preflight($theme, $site);
        foreach ($preflight['checks'] as $check) {
            if ($check['ok'] !== true) {
                $log(sprintf('%s %s: %s %s', $check['ok'] === false ? 'FAIL' : 'WARN', $check['label'], $check['detail'], $check['ok'] === false ? 'Fix: ' . $check['fix'] : ''));
            }
        }
        if (!$preflight['ok']) {
            return ['status' => 'error', 'message' => 'The build cannot run until the problems above are fixed.', 'manifest' => null, 'preflight' => $preflight];
        }

        $lock = $this->acquireLock($theme);
        if ($lock === null) {
            return ['status' => 'error', 'message' => 'Another build of this theme is running. Try again when it has finished.', 'manifest' => null, 'preflight' => $preflight];
        }

        $buildDir = $this->getBuildDir($theme, $site);
        $stagingDir = $buildDir . self::BUILDING_SUFFIX;
        $output = '';
        try {
            $this->removeTree($stagingDir);
            if (!$this->ensureDir($stagingDir)) {
                throw new \RuntimeException(sprintf('Unable to create the staging directory %s.', $stagingDir));
            }
            $logFile = $stagingDir . '/' . self::LOG_FILE;
            file_put_contents($logFile, $buffer ? implode("\n", $buffer) . "\n" : '');
            $buffer = [];

            $configFile = $this->writeConfig($theme, $site, $stagingDir);
            $configHash = $this->getConfigHash($theme, $site);
            $sourceFingerprint = $this->getSourceFingerprint($theme);
            $log(sprintf('Configuration written to %s (%s).', $configFile, substr($configHash, 0, 12)));

            $stagingAssets = $stagingDir . '/' . self::ASSETS_DIR_NAME;
            $command = $this->buildCommand($theme, $site, $configFile, $stagingAssets);
            $log('Running: ' . implode(' ', array_map('escapeshellarg', $command)));
            // gulp writes a line in several chunks (timestamp, then message), so buffer until a newline arrives.
            $pending = '';
            $flush = function (bool $all) use (&$pending, $log) {
                while (($pos = strpos($pending, "\n")) !== false) {
                    $line = rtrim(substr($pending, 0, $pos), "\r");
                    $pending = substr($pending, $pos + 1);
                    if (trim($line) !== '') {
                        $log('  ' . $line);
                    }
                }
                if ($all && trim($pending) !== '') {
                    $log('  ' . rtrim($pending));
                    $pending = '';
                }
            };
            $result = $this->run($command, $theme->getPath(), self::BUILD_TIMEOUT, function (string $chunk) use (&$output, &$pending, $flush) {
                $output .= $chunk;
                $pending .= $chunk;
                $flush(false);
            });
            $flush(true);

            if ($result['timedOut']) {
                throw new \RuntimeException(sprintf('The build was stopped after %d seconds.', self::BUILD_TIMEOUT));
            }
            if ($result['exitCode'] !== 0) {
                $summary = $this->summarizeFailure($output);
                throw new \RuntimeException(sprintf('gulp exited with code %d%s See the build output for details.', $result['exitCode'], $summary !== '' ? ': ' . $summary . '.' : '.'));
            }

            $artifacts = $this->verifyArtifacts($stagingAssets);

            $finishedAt = new \DateTimeImmutable();
            $manifest = [
                'version' => self::MANIFEST_VERSION,
                'status' => 'success',
                'theme' => $theme->getId(),
                'themeVersion' => (string) $theme->getIni('version'),
                'libraryVersion' => $this->getLibraryVersion($theme),
                'site' => ['id' => $site->id(), 'slug' => $site->slug(), 'title' => $site->title()],
                'jobId' => $jobId,
                'startedAt' => $startedAt->format(\DateTimeInterface::ATOM),
                'finishedAt' => $finishedAt->format(\DateTimeInterface::ATOM),
                'durationSeconds' => (int) round(microtime(true) - $start),
                'configHash' => $configHash,
                'sourceFingerprint' => $sourceFingerprint,
                'options' => $this->getBuildOptions($site),
                'command' => $command,
                'builtBy' => $this->getProcessUser(),
                'assetsDir' => self::ASSETS_DIR_NAME,
                'artifacts' => $artifacts,
            ];
            file_put_contents($stagingDir . '/' . self::MANIFEST_FILE, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->swapIn($buildDir, $stagingDir);
            // The log moved with the staging directory; later lines belong in the live build's copy.
            $logFile = $buildDir . '/' . self::LOG_FILE;
            $this->removeTree($buildDir . self::FAILED_SUFFIX);

            $log(sprintf('Build complete in %d seconds: %s', $manifest['durationSeconds'], $buildDir));
            return ['status' => 'success', 'message' => 'Theme assets built.', 'manifest' => $manifest, 'preflight' => $preflight];
        } catch (\Throwable $e) {
            $log('ERROR: ' . $e->getMessage());
            $failedDir = $buildDir . self::FAILED_SUFFIX;
            $this->removeTree($failedDir);
            if (is_dir($stagingDir)) {
                @rename($stagingDir, $failedDir);
            } else {
                $this->ensureDir($failedDir);
            }
            if ($logFile !== null) {
                $logFile = $failedDir . '/' . self::LOG_FILE;
            }
            $manifest = [
                'version' => self::MANIFEST_VERSION,
                'status' => 'error',
                'theme' => $theme->getId(),
                'site' => ['id' => $site->id(), 'slug' => $site->slug(), 'title' => $site->title()],
                'jobId' => $jobId,
                'startedAt' => $startedAt->format(\DateTimeInterface::ATOM),
                'finishedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'durationSeconds' => (int) round(microtime(true) - $start),
                'error' => $e->getMessage(),
                'builtBy' => $this->getProcessUser(),
            ];
            if ($logFile === null) {
                @file_put_contents($failedDir . '/' . self::LOG_FILE, implode("\n", $buffer) . "\n");
            }
            @file_put_contents($failedDir . '/' . self::MANIFEST_FILE, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return ['status' => 'error', 'message' => $e->getMessage(), 'manifest' => $manifest, 'preflight' => $preflight];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /** The gulp command for a site build, as an argument list. */
    public function buildCommand(Theme $theme, SiteRepresentation $site, string $configFile, string $destination): array
    {
        $node = $this->findNode();
        $command = [$node ?? 'node', $theme->getPath() . '/node_modules/gulp/bin/gulp.js', 'build', '--config', $configFile, '--dest', $destination];
        $profile = ThemeProfile::of($theme);
        // A theme that declares its own default configuration file or source directory (theme.ini).
        if (!$profile->hasDefaultConfigFileName()) {
            array_push($command, '--mainconfig', $profile->configFileName());
        }
        $options = $this->getBuildOptions($site);
        if ($options['source_imgs'] !== '') {
            array_push($command, '--sourceimgs', $options['source_imgs']);
        }
        // The site's own source directory, if it has one, wins over the theme's.
        if ($options['source_dir'] !== '') {
            array_push($command, '--source', $options['source_dir']);
        } elseif (!$profile->hasDefaultSourceDirName()) {
            array_push($command, '--source', $profile->sourceDirName());
        }
        return $command;
    }

    /**
     * The first line of build output that names the problem (a SASS error, a missing file, a plugin error), without
     * the stack frames around it. Empty when nothing recognisable is found.
     */
    protected function summarizeFailure(string $output): string
    {
        $lines = preg_split('/\R/', $output) ?: [];
        foreach ($lines as $i => $line) {
            $clean = trim(preg_replace('/\x1b\[[0-9;]*m/', '', $line));
            if ($clean === '' || preg_match('/^(at |\[\d\d:\d\d:\d\d\] (Starting|Finished))/', $clean)) {
                continue;
            }
            if (preg_match('/^(Error|Error in plugin|.*Error:|.*errored after|Undefined variable|expected|Configuration file .* does not exist|Refusing)/i', $clean)) {
                // "Error in plugin X" is followed by the real message; prefer that when present.
                if (preg_match('/^Error in plugin/i', $clean) || preg_match('/errored after/i', $clean)) {
                    $fallback = '';
                    for ($j = $i + 1; $j < min($i + 8, count($lines)); $j++) {
                        $next = trim(preg_replace('/\x1b\[[0-9;]*m/', '', $lines[$j]));
                        if ($next === '' || preg_match('/^(at |\[\d\d:\d\d:\d\d\]|Error in plugin|formatted:|code:)/', $next)) {
                            continue;
                        }
                        $stripped = trim(preg_replace('/^(Error|Message):\s*/i', '', $next));
                        if ($stripped === '') {
                            continue;
                        }
                        if (preg_match('/^Error:/i', $next)) {
                            return mb_substr($stripped, 0, 200);
                        }
                        if ($fallback === '') {
                            $fallback = $stripped;
                        }
                    }
                    if ($fallback !== '') {
                        return mb_substr($fallback, 0, 200);
                    }
                    continue;
                }
                return mb_substr(preg_replace('/^\[\d\d:\d\d:\d\d\]\s*/', '', $clean), 0, 200);
            }
        }
        return '';
    }

    /**
     * Every required artifact must exist and be non-empty. Returns their sizes and hashes for the manifest.
     */
    protected function verifyArtifacts(string $dir): array
    {
        $artifacts = [];
        $missing = [];
        foreach (self::REQUIRED_ARTIFACTS as $relative) {
            $file = "$dir/$relative";
            if (!is_file($file) || filesize($file) === 0) {
                $missing[] = $relative;
                continue;
            }
            $artifacts[$relative] = ['size' => filesize($file), 'sha1' => sha1_file($file)];
        }
        if ($missing) {
            throw new \RuntimeException('The build finished without producing: ' . implode(', ', $missing) . '.');
        }
        return $artifacts;
    }

    /** Replaces the live build with the staged one, keeping the old build as .previous. */
    protected function swapIn(string $buildDir, string $stagingDir): void
    {
        $previous = $buildDir . self::PREVIOUS_SUFFIX;
        $this->removeTree($previous);
        if (is_dir($buildDir) && !rename($buildDir, $previous)) {
            throw new \RuntimeException(sprintf('Unable to move the current build aside (%s).', $buildDir));
        }
        if (!rename($stagingDir, $buildDir)) {
            // Put the old build back so the site keeps working.
            if (is_dir($previous)) {
                @rename($previous, $buildDir);
            }
            throw new \RuntimeException(sprintf('Unable to move the new build into place (%s).', $buildDir));
        }
    }

    // ------------------------------------------------------------------ processes

    /**
     * Runs a command with stdout and stderr merged, streaming output to $onOutput, under a controlled environment.
     *
     * @return array ['exitCode' => int, 'output' => string, 'timedOut' => bool]
     */
    public function run(array $command, string $cwd, int $timeout, ?callable $onOutput = null): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $cwd, $this->buildEnvironment());
        if (!is_resource($process)) {
            return ['exitCode' => 1, 'output' => 'The process could not be started.', 'timedOut' => false];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $timedOut = false;
        $deadline = microtime(true) + $timeout;
        $open = [$pipes[1], $pipes[2]];
        while ($open) {
            $read = $open;
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 1) === false) {
                break;
            }
            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                if ($chunk === '' || $chunk === false) {
                    if (feof($stream)) {
                        $open = array_filter($open, fn ($s) => $s !== $stream);
                    }
                    continue;
                }
                $output .= $chunk;
                if ($onOutput) {
                    $onOutput($chunk);
                }
            }
            if (microtime(true) > $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        return ['exitCode' => $timedOut ? 124 : $exitCode, 'output' => $output, 'timedOut' => $timedOut];
    }

    /** A short-lived command for preflight probes. */
    protected function runQuick(array $command, string $cwd): array
    {
        return $this->run($command, $cwd, 20);
    }

    /**
     * The environment for build processes: a PATH that includes node, a writable HOME (npm and some tools need one),
     * and no locale surprises.
     */
    protected function buildEnvironment(): array
    {
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        $node = $this->findNode();
        if ($node !== null) {
            $path = dirname($node) . PATH_SEPARATOR . $path;
        }
        $home = $this->getHomeDir();
        $this->ensureDir($home);
        return [
            'PATH' => $path,
            'HOME' => $home,
            'LANG' => getenv('LANG') ?: 'C.UTF-8',
            'LC_ALL' => getenv('LC_ALL') ?: 'C.UTF-8',
            'NO_UPDATE_NOTIFIER' => '1',
            'CI' => '1',
        ];
    }

    /**
     * The node executable: the global setting (a file or a directory holding node), else the first node on PATH,
     * else common install locations.
     */
    public function findNode(): ?string
    {
        $setting = trim((string) $this->settings->get(self::NODE_PATH_SETTING));
        if ($setting !== '') {
            if (is_dir($setting) && is_executable("$setting/node")) {
                return "$setting/node";
            }
            if (is_executable($setting) && !is_dir($setting)) {
                return $setting;
            }
            return null; // Set but wrong: don't silently use another node.
        }
        $found = $this->cli->getCommandPath('node');
        if ($found) {
            return $found;
        }
        foreach (['/usr/local/bin/node', '/usr/bin/node', '/opt/homebrew/bin/node'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * The npm executable: beside the node in use, else the first npm on PATH, else common install locations.
     */
    public function findNpm(): ?string
    {
        $node = $this->findNode();
        if ($node !== null && is_executable(dirname($node) . '/npm')) {
            return dirname($node) . '/npm';
        }
        $found = $this->cli->getCommandPath('npm');
        if ($found) {
            return $found;
        }
        foreach (['/usr/local/bin/npm', '/usr/bin/npm', '/opt/homebrew/bin/npm'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Installs the theme's build dependencies (node_modules) with npm, under the theme's build lock: `npm ci` when
     * the theme ships a package-lock.json (the exact versions it was built with), else `npm install`. Output streams
     * to $onOutput. Returns ['exitCode' => int, 'output' => string, 'timedOut' => bool].
     */
    public function installDependencies(Theme $theme, ?callable $onOutput = null): array
    {
        $themePath = $theme->getPath();
        $npm = $this->findNpm();
        if ($npm === null) {
            return ['exitCode' => 1, 'output' => 'npm was not found (see the preflight checks on the Theme page).', 'timedOut' => false];
        }
        if (!is_writable($themePath)) {
            return ['exitCode' => 1, 'output' => sprintf('%s is not writable by %s.', $themePath, $this->getProcessUser()), 'timedOut' => false];
        }
        $lock = $this->acquireLock($theme);
        if ($lock === null) {
            return ['exitCode' => 1, 'output' => 'Another build or install of this theme is running.', 'timedOut' => false];
        }
        try {
            $announce = function (array $command) use ($onOutput, $themePath): void {
                if ($onOutput) {
                    $onOutput('$ ' . implode(' ', $command) . "\n(in $themePath, as " . $this->getProcessUser() . ")\n");
                }
            };
            $install = [$npm, 'install', '--no-audit', '--no-fund', '--no-progress'];
            if (!is_file("$themePath/package-lock.json")) {
                $announce($install);
                return $this->run($install, $themePath, self::BUILD_TIMEOUT * 2, $onOutput);
            }
            $ci = [$npm, 'ci', '--no-audit', '--no-fund', '--no-progress'];
            $announce($ci);
            $result = $this->run($ci, $themePath, self::BUILD_TIMEOUT * 2, $onOutput);
            // A lock file out of step with package.json (a theme edited after its release): resolve it instead.
            if ($result['exitCode'] !== 0 && !$result['timedOut'] && stripos($result['output'], 'in sync') !== false) {
                if ($onOutput) {
                    $onOutput("\npackage-lock.json does not match package.json; running npm install instead.\n");
                }
                $announce($install);
                return $this->run($install, $themePath, self::BUILD_TIMEOUT * 2, $onOutput);
            }
            return $result;
        } finally {
            $this->releaseLock($lock);
        }
    }

    // ------------------------------------------------------------------ locking and filesystem

    /** @return resource|null */
    protected function acquireLock(Theme $theme)
    {
        $dir = $this->getThemeDir($theme);
        if (!$this->ensureDir($dir)) {
            return null;
        }
        $handle = fopen($dir . '/' . self::LOCK_FILE, 'c');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    protected function releaseLock($handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    protected function ensureDir(string $dir): bool
    {
        return is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir);
    }

    /** Deletes a directory tree we own. Never follows symlinks out of the tree. */
    public function removeTree(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            if (is_link($dir) || is_file($dir)) {
                @unlink($dir);
            }
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    protected function isTreeWritable(string $dir): bool
    {
        if (!is_writable($dir)) {
            return false;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            if (!is_writable($item->getPathname())) {
                return false;
            }
        }
        return true;
    }

    public function getProcessUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if ($info && isset($info['name'])) {
                return $info['name'];
            }
        }
        return (string) (getenv('USER') ?: get_current_user());
    }

    protected function getPathOwner(string $path): string
    {
        $uid = @fileowner($path);
        if ($uid === false) {
            return 'unknown';
        }
        if (function_exists('posix_getpwuid')) {
            $info = posix_getpwuid($uid);
            if ($info && isset($info['name'])) {
                return $info['name'];
            }
        }
        return 'uid ' . $uid;
    }

    /**
     * The theme's declaration (Theme\ThemeProfile) as a preflight check: fails when the theme is made for another
     * version of this module, warns when the declaration is missing or incomplete.
     */
    protected function identityCheck(ThemeProfile $profile): array
    {
        $findings = $profile->findings();
        $errors = array_filter($findings, fn ($f) => $f['level'] === 'error');
        $warnings = array_filter($findings, fn ($f) => $f['level'] === 'warning');
        $worst = $errors ?: $warnings;
        if ($worst) {
            $first = reset($worst);
            return $this->check('theme_identity', 'Theme declaration', $errors ? false : null, $first['message'], $first['fix']); // @translate
        }
        $detail = sprintf('Declares base theme "%s"%s%s.',
            $profile->base(),
            $profile->baseVersion() ? ' ' . $profile->baseVersion() : '',
            $profile->moduleConstraint() ? sprintf('; works with Agile Theme Tools %s (%s installed)', $profile->moduleConstraint(), ThemeProfile::moduleVersion()) : '');
        return $this->check('theme_identity', 'Theme declaration', true, $detail); // @translate
    }

    protected function check(string $id, string $label, ?bool $ok, string $detail, string $fix = ''): array
    {
        return ['id' => $id, 'label' => $label, 'ok' => $ok, 'detail' => $detail, 'fix' => $fix];
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1000 && $i < count($units) - 1) {
            $bytes /= 1000;
            $i++;
        }
        return sprintf('%.1f %s', $bytes, $units[$i]);
    }
}
