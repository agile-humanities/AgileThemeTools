<?php
/**
 * Builds site themes from the command line, through the same path as the admin's Build button (ThemeBuild\BuildManager):
 * preflight, staged build, verification, swap, manifest. One site after another; a failure does not stop the rest.
 *
 *   php modules/AgileThemeTools/data/scripts/build-site-themes.php --all            every site with per-site styling on
 *   php modules/AgileThemeTools/data/scripts/build-site-themes.php --site slug      one site (repeat --site for several)
 *   php modules/AgileThemeTools/data/scripts/build-site-themes.php --theme Name     every configurable site on one theme
 *   php modules/AgileThemeTools/data/scripts/build-site-themes.php --all --check    only the preflight checks and build status; builds nothing
 *   php modules/AgileThemeTools/data/scripts/build-site-themes.php --list           the sites that would be built
 *
 * Run it from the Omeka root as the web server's user (the builds are written to files/theme-assets), e.g.
 *   sudo -u www-data php modules/AgileThemeTools/data/scripts/build-site-themes.php --all
 * Exit code: 0 when every site built (or passed its checks), 1 otherwise, 2 for a usage error. --quiet keeps only
 * the summary and failures.
 */

$options = getopt('', ['all', 'site:', 'theme:', 'check', 'list', 'quiet', 'help']);
if ($options === false || isset($options['help']) || (!isset($options['all']) && !isset($options['site']) && !isset($options['theme']) && !isset($options['list']))) {
    $usage = substr(file_get_contents(__FILE__), 0, strpos(file_get_contents(__FILE__), '*/'));
    fwrite(STDERR, trim(preg_replace('/^\s*(<\?php|\/\*\*|\*)\s?/m', '', $usage)) . "\n");
    exit(isset($options['help']) ? 0 : 2);
}
$quiet = isset($options['quiet']);

$root = dirname(__DIR__, 4);
if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Omeka was not found at $root. Run this script from an Omeka installation's modules/AgileThemeTools/data/scripts.\n");
    exit(2);
}
chdir($root);
require 'bootstrap.php';
$application = Omeka\Mvc\Application::init(require 'application/config/application.config.php');
$services = $application->getServiceManager();
$api = $services->get('Omeka\ApiManager');
/** @var \AgileThemeTools\ThemeBuild\BuildManager $builder */
$builder = $services->get(AgileThemeTools\ThemeBuild\BuildManager::class);

// The sites to build.
$sites = [];
if (isset($options['site'])) {
    foreach ((array) $options['site'] as $slug) {
        $found = $api->search('sites', ['slug' => $slug])->getContent();
        if (!$found) {
            fwrite(STDERR, "No site has the slug \"$slug\".\n");
            exit(2);
        }
        $sites[] = $found[0];
    }
} else {
    $sites = $builder->getAllConfigurableSites();
    if (isset($options['theme'])) {
        $sites = array_values(array_filter($sites, fn ($site) => $site->theme() === $options['theme']));
    }
}

if (isset($options['list'])) {
    foreach ($sites as $site) {
        $theme = $builder->getSiteTheme($site);
        $status = $theme ? $builder->getStatus($theme, $site) : null;
        printf("%-30s %-28s %s\n", $site->slug(), $theme ? $theme->getId() : '(no active theme)', $status ? ($status['built'] ? ($status['stale'] ? 'rebuild needed' : 'up to date') : 'never built') : '');
    }
    printf("%d site(s).\n", count($sites));
    exit(0);
}
if (!$sites) {
    echo "No sites to build: none has \"Use theme configuration\" switched on in its theme settings.\n";
    exit(0);
}

$check = isset($options['check']);
$results = [];
foreach ($sites as $site) {
    $theme = $builder->getSiteTheme($site);
    $label = sprintf('%s (%s)', $site->slug(), $theme ? $theme->getId() : 'no active theme');
    if (!$quiet) {
        echo "\n==== $label ====\n";
    }
    if (!$theme) {
        $results[] = ['label' => $label, 'ok' => false, 'detail' => 'no active theme', 'seconds' => 0];
        continue;
    }
    if (!$builder->isSiteConfigurable($theme, $site)) {
        $results[] = ['label' => $label, 'ok' => false, 'detail' => 'per-site styling is not switched on for this site, or its theme has no build system', 'seconds' => 0];
        continue;
    }
    $started = microtime(true);
    if ($check) {
        $preflight = $builder->preflight($theme, $site);
        foreach ($preflight['checks'] as $c) {
            if (!$quiet || $c['ok'] === false) {
                printf("  [%s] %s: %s%s\n", $c['ok'] === true ? 'ok' : ($c['ok'] === false ? 'FAIL' : 'warn'), $c['label'], $c['detail'], $c['ok'] === false && $c['fix'] ? ' Fix: ' . $c['fix'] : '');
            }
        }
        $status = $builder->getStatus($theme, $site);
        $state = $status['built'] ? ($status['stale'] ? 'rebuild needed (' . implode(' ', $status['reasons']) . ')' : 'up to date') : ($status['legacy'] ? 'old build in the theme folder' : 'never built');
        $results[] = ['label' => $label, 'ok' => $preflight['ok'], 'detail' => ($preflight['ok'] ? 'checks pass' : 'checks FAIL') . '; build: ' . $state, 'seconds' => round(microtime(true) - $started)];
        continue;
    }
    $result = $builder->build($theme, $site, function (string $line) use ($quiet) {
        if (!$quiet) {
            echo "  ", $line, "\n";
        }
    });
    $results[] = ['label' => $label, 'ok' => $result['status'] === 'success', 'detail' => $result['message'] ?? $result['status'], 'seconds' => round(microtime(true) - $started)];
}

echo "\n==== Summary ====\n";
$failed = 0;
foreach ($results as $r) {
    $failed += $r['ok'] ? 0 : 1;
    printf("  %-5s %-40s %4ds  %s\n", $r['ok'] ? 'ok' : 'FAIL', $r['label'], $r['seconds'], $r['detail']);
}
printf("%d of %d site(s) %s.\n", count($results) - $failed, count($results), $check ? 'pass their checks' : 'built');
exit($failed ? 1 : 0);
