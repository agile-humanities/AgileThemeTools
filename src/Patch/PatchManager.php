<?php declare(strict_types=1);

namespace AgileThemeTools\Patch;

/**
 * Agile patches: small, exact fixes to third-party modules that the Agile stack needs (mostly for media on an
 * external store such as Amazon S3), shipped with this module so that they survive module updates (docs/PATCHES.md).
 *
 * A patch is a file in data/patches/<id>.php. Each of its variants lists the module versions it was verified on and
 * its edits: exact text to find ("search", once in the stock file) and its replacement ("replace", which carries the
 * marker "AGILE-PATCH <id>"). An edit is applied when its replacement is in the file.
 *
 * This class needs nothing from Omeka (paths only): the same code serves the admin audit, the Easy Admin job and the
 * command line script, and it works before a module upgrade has run, on the files as they are.
 */
class PatchManager
{
    const STATUS_APPLIED = 'applied';          // Every edit is in place.
    const STATUS_MISSING = 'missing';          // None is, and all can be applied.
    const STATUS_PARTIAL = 'partial';          // Some are; the others can be applied.
    const STATUS_CONFLICT = 'conflict';        // An edit can neither be found applied nor applied: the file changed.
    const STATUS_LEGACY = 'legacy';            // An older, hand-applied form of the patch is in the file.
    const STATUS_UNVERIFIED = 'unverified';    // No variant for this version, but one applies cleanly to its code.
    const STATUS_UNSUPPORTED = 'unsupported';  // No variant for this version, and none applies.
    const STATUS_NOT_NEEDED = 'not_needed';    // Fixed upstream in this version, or the version predates the issue.
    const STATUS_NOT_INSTALLED = 'not_installed'; // The module (or the module the patch is for) is absent.

    const MARKER = 'AGILE-PATCH';

    protected string $omekaPath;
    protected string $patchesPath;
    /** @var array[]|null */
    protected ?array $definitions = null;

    public function __construct(string $omekaPath, ?string $patchesPath = null)
    {
        $this->omekaPath = rtrim($omekaPath, '/');
        $this->patchesPath = rtrim($patchesPath ?? dirname(__DIR__, 2) . '/data/patches', '/');
    }

    /** @return array[] Patch definitions by id, in module then id order. */
    public function definitions(): array
    {
        if ($this->definitions === null) {
            $this->definitions = [];
            foreach (glob($this->patchesPath . '/*.php') ?: [] as $file) {
                $definition = require $file;
                if (is_array($definition) && !empty($definition['id'])) {
                    $this->definitions[$definition['id']] = $definition;
                }
            }
            uasort($this->definitions, fn ($a, $b) => [$a['module'], $a['id']] <=> [$b['module'], $b['id']]);
        }
        return $this->definitions;
    }

    public function definition(string $id): ?array
    {
        return $this->definitions()[$id] ?? null;
    }

    public function modulePath(string $module): string
    {
        return $this->omekaPath . '/modules/' . $module;
    }

    /** The version of the module files (config/module.ini), not the database: patches apply to the files. */
    public function moduleVersion(string $module): ?string
    {
        $ini = $this->modulePath($module) . '/config/module.ini';
        if (!is_readable($ini)) {
            return null;
        }
        $data = @parse_ini_file($ini);
        return isset($data['version']) ? (string) $data['version'] : null;
    }

    /**
     * The state of one patch on this installation.
     *
     * @return array{id: string, module: string, title: string, level: string, version: ?string, status: string,
     *   variant: ?int, edits: array[], message: string, definition: array}
     */
    public function status(string $id): array
    {
        $definition = $this->definition($id);
        if (!$definition) {
            throw new \InvalidArgumentException(sprintf('Unknown patch "%s".', $id));
        }
        $module = $definition['module'];
        $version = $this->moduleVersion($module);
        $result = [
            'id' => $id,
            'module' => $module,
            'title' => $definition['title'],
            'level' => $definition['level'] ?? 'required',
            'version' => $version,
            'status' => self::STATUS_NOT_INSTALLED,
            'variant' => null,
            'edits' => [],
            'message' => '',
            'definition' => $definition,
        ];

        if ($version === null) {
            $result['message'] = sprintf('The module %s is not installed.', $module);
            return $result;
        }
        if (!empty($definition['when_module']) && $this->moduleVersion($definition['when_module']) === null) {
            $result['message'] = sprintf('Only needed with the module %s, which is not installed.', $definition['when_module']);
            return $result;
        }
        if (!empty($definition['fixed_in']) && version_compare($version, $definition['fixed_in'], '>=')) {
            $result['status'] = self::STATUS_NOT_NEEDED;
            $result['message'] = sprintf('Fixed upstream in %s %s: not needed.', $module, $definition['fixed_in']);
            return $result;
        }
        if (!empty($definition['applies_from']) && version_compare($version, $definition['applies_from'], '<')) {
            $result['status'] = self::STATUS_NOT_NEEDED;
            $result['message'] = sprintf('The issue appeared in %s %s: not needed for %s.', $module, $definition['applies_from'], $version);
            return $result;
        }

        // The variant verified for this version.
        foreach ($definition['variants'] as $index => $variant) {
            if (in_array($version, $variant['versions'], true)) {
                return $this->variantStatus($result, $index, $variant, true);
            }
        }

        // An unknown version (usually a newer release): a variant whose edits are all found, applied or applicable,
        // probably still works, but it was not verified.
        foreach ($definition['variants'] as $index => $variant) {
            $state = $this->variantStatus($result, $index, $variant, false);
            if ($state['status'] === self::STATUS_LEGACY) {
                return $state;
            }
            if ($state['status'] !== self::STATUS_CONFLICT) {
                if ($state['status'] === self::STATUS_APPLIED) {
                    $state['message'] = sprintf('Applied, with the variant verified on %s %s (not on %s).', $module, implode(', ', $variant['versions']), $version);
                    return $state;
                }
                $state['status'] = self::STATUS_UNVERIFIED;
                $state['message'] = sprintf('Not applied. No variant was verified on %s %s, but the one verified on %s applies cleanly to its code.', $module, $version, implode(', ', $variant['versions']));
                return $state;
            }
        }
        $result['status'] = self::STATUS_UNSUPPORTED;
        $result['message'] = sprintf('No variant of this patch fits %s %s: its code changed. Check whether the issue is fixed upstream, or write a new variant.', $module, $version);
        return $result;
    }

    /** @return array[] The state of every patch, by id. */
    public function statuses(): array
    {
        $statuses = [];
        foreach (array_keys($this->definitions()) as $id) {
            $statuses[$id] = $this->status($id);
        }
        return $statuses;
    }

    protected function variantStatus(array $result, int $index, array $variant, bool $verified): array
    {
        $result['variant'] = $index;
        $states = [];
        $contents = [];
        foreach ($variant['edits'] as $i => $edit) {
            $path = $this->modulePath($result['module']) . '/' . $edit['file'];
            if (!array_key_exists($path, $contents)) {
                $contents[$path] = is_readable($path) ? (string) file_get_contents($path) : null;
            }
            $text = $contents[$path];
            if ($text === null) {
                $state = 'file_missing';
            } elseif (substr_count($text, $edit['replace']) === 1) {
                $state = 'applied';
            } elseif (substr_count($text, $edit['search']) === 1 && strpos($text, $edit['replace']) === false) {
                $state = 'pending';
            } else {
                $state = 'conflict';
            }
            $states[$i] = $state;
            $result['edits'][] = ['file' => $edit['file'], 'state' => $state];
        }
        $counts = array_count_values($states);
        $total = count($states);
        if (($counts['applied'] ?? 0) === $total) {
            $result['status'] = self::STATUS_APPLIED;
            $result['message'] = 'Applied.';
        } elseif (($counts['pending'] ?? 0) === $total) {
            $result['status'] = self::STATUS_MISSING;
            $result['message'] = 'Not applied.';
        } elseif (($counts['applied'] ?? 0) + ($counts['pending'] ?? 0) === $total) {
            $result['status'] = self::STATUS_PARTIAL;
            $result['message'] = sprintf('Partly applied (%d of %d edits).', $counts['applied'], $total);
        } else {
            $result['status'] = self::STATUS_CONFLICT;
            $files = array_unique(array_column(array_filter($result['edits'], fn ($e) => $e['state'] === 'conflict' || $e['state'] === 'file_missing'), 'file'));
            $result['message'] = sprintf('The code this patch changes was modified locally or is missing (%s): it cannot be applied as is.', implode(', ', $files));
        }
        // An older, hand-applied form of this patch (listed in "legacy") in a file that has no marker of this
        // patch: report it as such, and never apply over it (the edits would be applied twice).
        if ($result['status'] !== self::STATUS_APPLIED && !empty($result['definition']['legacy'])) {
            $marker = self::MARKER . ' ' . $result['id'];
            foreach ($contents as $text) {
                if ($text === null || strpos($text, $marker) !== false) {
                    continue;
                }
                foreach ($result['definition']['legacy'] as $signature) {
                    if (strpos($text, $signature) !== false) {
                        $result['status'] = self::STATUS_LEGACY;
                        $result['message'] = 'An older, hand-applied form of this patch is in place. It probably works, but it cannot be checked: reinstall the stock files of the module (same version), then apply this patch.';
                        break 2;
                    }
                }
            }
        }
        if (!$verified) {
            $result['message'] .= ' (variant not verified on this version)';
        }
        return $result;
    }

    /** Severity for the audit: error (required patch not working), warning, note, ok. */
    public function severity(array $status): string
    {
        $required = ($status['level'] ?? 'required') === 'required';
        switch ($status['status']) {
            case self::STATUS_APPLIED:
                return strpos($status['message'], 'not on') !== false ? 'note' : 'ok';
            case self::STATUS_MISSING:
            case self::STATUS_PARTIAL:
            case self::STATUS_CONFLICT:
                return $required ? 'error' : 'warning';
            case self::STATUS_UNVERIFIED:
            case self::STATUS_UNSUPPORTED:
            case self::STATUS_LEGACY:
                return 'warning';
            default:
                return 'ok';
        }
    }

    /**
     * What applying would need: every file to change, whether it and its directory are writable (a file is written
     * to a temporary file in the same directory, then renamed over the original), and who owns it.
     *
     * @return array{ok: bool, files: array[], user: string}
     */
    public function preflight(string $id): array
    {
        $status = $this->status($id);
        $user = $this->processUser();
        $files = [];
        $ok = true;
        if ($status['variant'] === null) {
            return ['ok' => false, 'files' => [], 'user' => $user];
        }
        $variant = $status['definition']['variants'][$status['variant']];
        foreach (array_unique(array_column($variant['edits'], 'file')) as $file) {
            $path = $this->modulePath($status['module']) . '/' . $file;
            $dir = dirname($path);
            $row = [
                'file' => $file,
                'path' => $path,
                'exists' => is_file($path),
                'file_writable' => is_writable($path),
                'dir_writable' => is_writable($dir),
                'owner' => $this->owner($path),
                'perms' => is_file($path) ? substr(sprintf('%o', fileperms($path)), -4) : '',
            ];
            $ok = $ok && $row['exists'] && $row['file_writable'] && $row['dir_writable'];
            $files[] = $row;
        }
        return ['ok' => $ok, 'files' => $files, 'user' => $user];
    }

    /**
     * Apply one patch: only its pending edits, all or nothing.
     *
     * Steps: status (only missing, partial or, with $allowUnverified, unverified patches are applied); permission
     * preflight; the new contents computed in memory; each new file linted with the php binary when one is given;
     * the originals copied to $backupDir; each file written atomically (temporary file + rename, same permissions);
     * the status checked again.
     *
     * @return array{ok: bool, id: string, message: string, files: string[], backup: ?string}
     */
    public function apply(string $id, ?string $backupDir = null, bool $dryRun = false, bool $allowUnverified = false, ?string $phpBinary = null): array
    {
        $status = $this->status($id);
        $result = ['ok' => false, 'id' => $id, 'message' => '', 'files' => [], 'backup' => null];
        $applicable = [self::STATUS_MISSING, self::STATUS_PARTIAL];
        if ($allowUnverified) {
            $applicable[] = self::STATUS_UNVERIFIED;
        }
        if ($status['status'] === self::STATUS_APPLIED || $status['status'] === self::STATUS_NOT_NEEDED || $status['status'] === self::STATUS_NOT_INSTALLED) {
            $result['ok'] = true;
            $result['message'] = 'Nothing to do: ' . lcfirst($status['message']);
            return $result;
        }
        if (!in_array($status['status'], $applicable, true)) {
            $result['message'] = $status['status'] === self::STATUS_UNVERIFIED
                ? $status['message'] . ' Apply it with the option to allow unverified variants once the module code was reviewed.'
                : $status['message'];
            return $result;
        }

        $preflight = $this->preflight($id);
        if (!$preflight['ok']) {
            $lines = [];
            foreach ($preflight['files'] as $file) {
                if (!$file['exists']) {
                    $lines[] = sprintf('%s is missing', $file['file']);
                } elseif (!$file['file_writable'] || !$file['dir_writable']) {
                    $lines[] = sprintf('%s (owner %s, mode %s) %s', $file['file'], $file['owner'], $file['perms'],
                        !$file['file_writable'] ? 'is not writable' : 'is in a directory that is not writable');
                }
            }
            $result['message'] = sprintf(
                'Cannot write the files of %s as the user "%s": %s. Run "php modules/AgileThemeTools/data/scripts/patches.php apply %s" as the owner of the module files, or make the files and their directory writable by "%s".',
                $status['module'], $preflight['user'], implode('; ', $lines), $id, $preflight['user']
            );
            return $result;
        }

        // New contents, in memory, applying only the pending edits.
        $variant = $status['definition']['variants'][$status['variant']];
        $new = [];
        foreach ($variant['edits'] as $edit) {
            $path = $this->modulePath($status['module']) . '/' . $edit['file'];
            $text = $new[$path] ?? (string) file_get_contents($path);
            if (substr_count($text, $edit['replace']) === 1) {
                $new[$path] = $text;
                continue;
            }
            if (substr_count($text, $edit['search']) !== 1) {
                $result['message'] = sprintf('The text to replace in %s was not found once: nothing was changed.', $edit['file']);
                return $result;
            }
            $new[$path] = str_replace($edit['search'], $edit['replace'], $text);
        }

        // Lint every new file before touching anything.
        if ($phpBinary) {
            foreach ($new as $path => $text) {
                $error = $this->lint($text, $phpBinary, dirname($path));
                if ($error !== null) {
                    $result['message'] = sprintf('The patched %s does not pass "php -l": %s. Nothing was changed.', basename($path), $error);
                    return $result;
                }
            }
        }

        $result['files'] = array_map(fn ($p) => substr($p, strlen($this->modulePath($status['module'])) + 1), array_keys($new));
        if ($dryRun) {
            $result['ok'] = true;
            $result['message'] = sprintf('Dry run: would change %s.', implode(', ', $result['files']));
            return $result;
        }

        // Backup of the files as they are now.
        if ($backupDir) {
            $backup = rtrim($backupDir, '/') . '/' . date('Ymd-His') . '-' . $id;
            foreach (array_keys($new) as $path) {
                $relative = $status['module'] . '/' . substr($path, strlen($this->modulePath($status['module'])) + 1);
                $dest = $backup . '/' . $relative;
                if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0775, true)) {
                    $result['message'] = sprintf('Cannot create the backup directory %s: nothing was changed.', dirname($dest));
                    return $result;
                }
                if (!@copy($path, $dest)) {
                    $result['message'] = sprintf('Cannot back up %s to %s: nothing was changed.', $relative, $dest);
                    return $result;
                }
            }
            $result['backup'] = $backup;
        }

        // Atomic writes.
        foreach ($new as $path => $text) {
            $temp = $path . '.agile-patch-' . getmypid();
            if (@file_put_contents($temp, $text) === false) {
                @unlink($temp);
                $result['message'] = sprintf('Cannot write %s.', $temp);
                return $result;
            }
            @chmod($temp, fileperms($path) & 0777);
            if (!@rename($temp, $path)) {
                @unlink($temp);
                $result['message'] = sprintf('Cannot replace %s.', $path);
                return $result;
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
        }

        $after = $this->status($id);
        $result['ok'] = $after['status'] === self::STATUS_APPLIED;
        $result['message'] = $result['ok']
            ? sprintf('Applied to %s %s (%s).', $status['module'], $status['version'], implode(', ', $result['files']))
            : sprintf('Written, but the patch is not seen as applied afterwards: %s', $after['message']);
        return $result;
    }

    /**
     * Developer check: apply every variant to the stock release of every version it lists.
     *
     * $stockPath holds one folder per release, "<Module>-<version>/<Module>/…", as unzipped from the release zips.
     *
     * @return string[] Problems; empty when every variant applies cleanly.
     */
    public function verify(string $stockPath, ?string $phpBinary = null): array
    {
        $problems = [];
        foreach ($this->definitions() as $id => $definition) {
            foreach ($definition['variants'] as $index => $variant) {
                foreach ($variant['versions'] as $version) {
                    $root = rtrim($stockPath, '/') . '/' . $definition['module'] . '-' . $version . '/' . $definition['module'];
                    if (!is_dir($root)) {
                        $problems[] = sprintf('%s: no stock release %s %s in %s.', $id, $definition['module'], $version, $stockPath);
                        continue;
                    }
                    $new = [];
                    foreach ($variant['edits'] as $edit) {
                        $path = $root . '/' . $edit['file'];
                        $text = $new[$path] ?? (is_readable($path) ? (string) file_get_contents($path) : null);
                        if ($text === null) {
                            $problems[] = sprintf('%s %s: %s is missing.', $id, $version, $edit['file']);
                            continue 2;
                        }
                        if (substr_count($text, $edit['search']) !== 1) {
                            $problems[] = sprintf('%s %s: the search text of an edit of %s is found %d times.', $id, $version, $edit['file'], substr_count($text, $edit['search']));
                            continue 2;
                        }
                        if (strpos($edit['replace'], self::MARKER . ' ' . $id) === false) {
                            $problems[] = sprintf('%s %s: an edit of %s carries no marker "%s %s".', $id, $version, $edit['file'], self::MARKER, $id);
                        }
                        $new[$path] = str_replace($edit['search'], $edit['replace'], $text);
                    }
                    foreach ($variant['edits'] as $edit) {
                        $path = $root . '/' . $edit['file'];
                        if (substr_count($new[$path], $edit['replace']) !== 1) {
                            $problems[] = sprintf('%s %s: after applying, an edit of %s is not seen as applied.', $id, $version, $edit['file']);
                        }
                    }
                    if ($phpBinary) {
                        foreach ($new as $path => $text) {
                            $error = $this->lint($text, $phpBinary, sys_get_temp_dir());
                            if ($error !== null) {
                                $problems[] = sprintf('%s %s: the patched %s does not lint: %s', $id, $version, basename($path), $error);
                            }
                        }
                    }
                }
            }
        }
        return $problems;
    }

    /** Where originals are copied before a patch is applied (with a deny-all .htaccess: they are module code). */
    public function defaultBackupDir(): string
    {
        $dir = $this->omekaPath . '/files/agile-patches';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && !file_exists($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "# Agile patches: backups of module files. Deny direct web access (Apache; nginx needs its own rule).\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
        }
        return $dir . '/backups';
    }

    /** "php -l" on a text, through a temporary file. Null when it passes or when the binary cannot be run. */
    protected function lint(string $text, string $phpBinary, string $dir): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }
        $temp = tempnam(is_writable($dir) ? $dir : sys_get_temp_dir(), 'agile-lint-');
        if ($temp === false) {
            return null;
        }
        file_put_contents($temp, $text);
        $output = [];
        $code = 0;
        @exec(escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($temp) . ' 2>&1', $output, $code);
        @unlink($temp);
        return $code === 0 ? null : trim(implode(' ', $output));
    }

    protected function processUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (!empty($info['name'])) {
                return $info['name'];
            }
        }
        return (string) (getenv('USER') ?: get_current_user());
    }

    protected function owner(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        }
        $uid = fileowner($path);
        if (function_exists('posix_getpwuid')) {
            $info = posix_getpwuid($uid);
            if (!empty($info['name'])) {
                return $info['name'];
            }
        }
        return (string) $uid;
    }
}
