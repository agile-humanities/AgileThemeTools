<?php
namespace AgileThemeTools\Dependency;

use Composer\Semver\Semver;
use Doctrine\DBAL\Connection;
use Omeka\Module\Manager as ModuleManager;

/**
 * Compares the modules that are installed with config/dependencies.php, the one list of what the Agile theme stack
 * needs and which versions it is tested with.
 *
 * Omeka itself does not enforce dependencies between modules, so this class is what does: Module::install() refuses
 * to install while a required module is missing, and the admin shows the audit on this module's configuration
 * page, on the dashboard and on the Modules page (when something is wrong), and as an Easy Admin check.
 */
class DependencyAudit
{
    const LEVEL_REQUIRED = 'required';
    const LEVEL_RECOMMENDED = 'recommended';
    const LEVEL_OPTIONAL = 'optional';

    const STATUS_OK = 'ok';
    const STATUS_MISSING = 'missing';             // not in the modules folder
    const STATUS_NOT_INSTALLED = 'not_installed'; // in the folder, never installed
    const STATUS_INACTIVE = 'inactive';
    const STATUS_NEEDS_UPGRADE = 'needs_upgrade'; // files newer than the database
    const STATUS_INVALID = 'invalid';             // Omeka cannot load it
    const STATUS_TOO_OLD = 'too_old';             // below "minimum"
    const STATUS_KNOWN_BAD = 'known_bad';
    const STATUS_OLDER = 'older';                 // below "pinned", no known problem
    const STATUS_NEWER = 'newer';                 // above "pinned"
    const STATUS_UNTESTED = 'untested';           // no pinned version to compare with

    const SEVERITY_ERROR = 'error';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_NOTE = 'note';
    const SEVERITY_OK = 'ok';

    const CORE = 'omeka';

    protected ModuleManager $modules;
    protected Connection $connection;
    protected array $manifest;
    protected ?array $rows = null;
    protected array $conditions = [];

    public function __construct(ModuleManager $modules, Connection $connection, array $manifest)
    {
        $this->modules = $modules;
        $this->connection = $connection;
        $this->manifest = $manifest;
    }

    public static function manifestFile(): string
    {
        return dirname(__DIR__, 2) . '/config/dependencies.php';
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    /**
     * One row per entry of the manifest, in its order.
     *
     * @return array[] id, label, level (as declared), required (bool: required right now), when, status, severity,
     *   installed (version or null), pinned, minimum, message, fix, for, source, download
     */
    public function rows(): array
    {
        if ($this->rows === null) {
            $this->rows = [];
            foreach ($this->manifest as $id => $entry) {
                $this->rows[$id] = $this->audit((string) $id, $entry);
            }
        }
        return $this->rows;
    }

    /** Rows an administrator has to act on or look at: errors and warnings. */
    public function problems(): array
    {
        return array_filter($this->rows(), fn ($row) => in_array($row['severity'], [self::SEVERITY_ERROR, self::SEVERITY_WARNING], true));
    }

    public function errors(): array
    {
        return array_filter($this->rows(), fn ($row) => $row['severity'] === self::SEVERITY_ERROR);
    }

    /** @return int[] Count of rows per severity: error, warning, note, ok. */
    public function summary(): array
    {
        $summary = [self::SEVERITY_ERROR => 0, self::SEVERITY_WARNING => 0, self::SEVERITY_NOTE => 0, self::SEVERITY_OK => 0];
        foreach ($this->rows() as $row) {
            $summary[$row['severity']]++;
        }
        return $summary;
    }

    /**
     * Module ids for the "dependencies" line of config/module.ini: required without condition. (Omeka ignores the
     * line; Easy Admin's installer and the module catalogues read it.)
     */
    public function iniDependencies(): array
    {
        $ids = [];
        foreach ($this->manifest as $id => $entry) {
            if ($id !== self::CORE && ($entry['level'] ?? '') === self::LEVEL_REQUIRED && empty($entry['when'])) {
                $ids[] = (string) $id;
            }
        }
        sort($ids);
        return $ids;
    }

    /** Null when module.ini agrees with the manifest; otherwise what differs. For developers. */
    public function iniMismatch(): ?string
    {
        $ini = @parse_ini_file(dirname(__DIR__, 2) . '/config/module.ini', true, INI_SCANNER_RAW);
        $declared = preg_split('/[\s,]+/', trim((string) ($ini['info']['dependencies'] ?? ''), " \"'"), -1, PREG_SPLIT_NO_EMPTY);
        sort($declared);
        $expected = $this->iniDependencies();
        if ($declared === $expected) {
            return null;
        }
        return sprintf('config/module.ini lists "%s" as dependencies; config/dependencies.php requires "%s".', implode(' ', $declared), implode(' ', $expected));
    }

    // ------------------------------------------------------------------ one entry

    protected function audit(string $id, array $entry): array
    {
        $level = $entry['level'] ?? self::LEVEL_OPTIONAL;
        $when = $entry['when'] ?? null;
        $required = $level === self::LEVEL_REQUIRED && ($when === null || $this->conditionHolds($when));
        $pinned = isset($entry['pinned']) ? (string) $entry['pinned'] : null;
        $minimum = isset($entry['minimum']) ? (string) $entry['minimum'] : null;
        $label = (string) ($entry['label'] ?? $id);

        $row = [
            'id' => $id,
            'label' => $label,
            'level' => $level,
            'required' => $required,
            'when' => $when,
            'installed' => null,
            'pinned' => $pinned,
            'minimum' => $minimum,
            'for' => (string) ($entry['for'] ?? ''),
            'source' => $entry['source'] ?? null,
            'download' => !empty($entry['download']) && $pinned ? str_replace('{version}', $pinned, $entry['download']) : null,
            'fix' => '',
        ];

        // Presence.
        if ($id === self::CORE) {
            $row['installed'] = \Omeka\Module::VERSION;
        } else {
            $module = $this->modules->getModule($id);
            $state = $module ? $module->getState() : ModuleManager::STATE_NOT_FOUND;
            $version = $module ? (string) ($module->getIni('version') ?? '') : '';
            $row['installed'] = $version !== '' ? $version : null;
            $absent = [
                ModuleManager::STATE_NOT_FOUND => [self::STATUS_MISSING, 'Not in the modules folder.', sprintf('Install %s%s.', $label, $pinned ? ' ' . $pinned : '')],
                ModuleManager::STATE_NOT_INSTALLED => [self::STATUS_NOT_INSTALLED, 'In the modules folder but not installed.', 'Install it on the Modules page.'],
                ModuleManager::STATE_NOT_ACTIVE => [self::STATUS_INACTIVE, 'Installed but not active.', 'Activate it on the Modules page.'],
            ];
            if (isset($absent[$state])) {
                [$status, $message, $fix] = $absent[$state];
                if ($required) {
                    return ['fix' => $fix] + $row + ['status' => $status, 'severity' => self::SEVERITY_ERROR, 'message' => $message];
                }
                $suffix = $level === self::LEVEL_RECOMMENDED ? ' Recommended.' : ($level === self::LEVEL_REQUIRED ? ' Not needed with the current search engine.' : ' Optional.');
                return ['fix' => ''] + $row + ['status' => $status, 'severity' => $level === self::LEVEL_RECOMMENDED ? self::SEVERITY_NOTE : self::SEVERITY_OK, 'message' => $message . $suffix];
            }
            if ($state === ModuleManager::STATE_NEEDS_UPGRADE) {
                return ['fix' => 'Finish the upgrade on the Modules page.'] + $row + ['status' => self::STATUS_NEEDS_UPGRADE, 'severity' => self::SEVERITY_WARNING,
                    'message' => sprintf('The files are version %1$s but the database is still at %2$s.', $version, (string) $module->getDb('version'))];
            }
            if ($state !== ModuleManager::STATE_ACTIVE) {
                return ['fix' => 'See the Modules page.'] + $row + ['status' => self::STATUS_INVALID, 'severity' => $required ? self::SEVERITY_ERROR : self::SEVERITY_WARNING,
                    'message' => sprintf('Omeka cannot load this module (%s).', $state)];
            }
        }

        // Version.
        $installed = (string) $row['installed'];
        foreach ((array) ($entry['known_bad'] ?? []) as $constraint => $reason) {
            if ($this->satisfies($installed, (string) $constraint)) {
                return ['fix' => $pinned ? sprintf('Use version %s.', $pinned) : ''] + $row + ['status' => self::STATUS_KNOWN_BAD, 'severity' => $required ? self::SEVERITY_ERROR : self::SEVERITY_WARNING, 'message' => (string) $reason];
            }
        }
        if ($minimum !== null && version_compare($installed, $minimum, '<')) {
            return ['fix' => sprintf('Update to version %s.', $pinned ?? $minimum)] + $row + ['status' => self::STATUS_TOO_OLD, 'severity' => $required ? self::SEVERITY_ERROR : self::SEVERITY_WARNING,
                'message' => sprintf('Version %1$s is older than %2$s, the oldest version that works.', $installed, $minimum)];
        }
        if ($pinned === null) {
            return $row + ['status' => self::STATUS_UNTESTED, 'severity' => self::SEVERITY_NOTE, 'message' => 'Not tested with a specific version yet.'];
        }
        $compare = version_compare($installed, $pinned);
        if ($compare < 0) {
            return ['fix' => sprintf('Update to version %s.', $pinned)] + $row + ['status' => self::STATUS_OLDER, 'severity' => self::SEVERITY_WARNING,
                'message' => sprintf('Version %1$s is older than the tested version %2$s.', $installed, $pinned)];
        }
        if ($compare > 0) {
            $volatile = !empty($entry['volatile']);
            return ['fix' => $volatile ? 'Check the pages that use it before relying on this version.' : ''] + $row + ['status' => self::STATUS_NEWER, 'severity' => $volatile ? self::SEVERITY_WARNING : self::SEVERITY_NOTE,
                'message' => sprintf('Version %1$s is newer than the tested version %2$s.', $installed, $pinned) . ($volatile ? ' New releases of this module often change templates or settings.' : '')];
        }
        return $row + ['status' => self::STATUS_OK, 'severity' => self::SEVERITY_OK, 'message' => 'The tested version.'];
    }

    // ------------------------------------------------------------------ conditions

    protected function conditionHolds(string $when): bool
    {
        if (!isset($this->conditions[$when])) {
            switch ($when) {
                case 'internal-search':
                    $this->conditions[$when] = $this->usesInternalSearch();
                    break;
                case 'solr-search':
                    $this->conditions[$when] = $this->usesSolrSearch();
                    break;
                default:
                    $this->conditions[$when] = true;
            }
        }
        return $this->conditions[$when];
    }

    /**
     * Whether any search engine runs on Solr (SearchSolr's "solarium" adapter). False when it cannot be told (no
     * Advanced Search tables yet): nothing uses Solr before an engine is created.
     */
    protected function usesSolrSearch(): bool
    {
        try {
            return (bool) $this->connection->fetchOne('SELECT 1 FROM search_engine WHERE adapter = ? LIMIT 1', ['solarium']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Whether a main search page (the installation's, or any site's) runs on Advanced Search's internal engine.
     * True when it cannot be told (Advanced Search not installed yet): the internal engine is its default.
     */
    protected function usesInternalSearch(): bool
    {
        try {
            $ids = [];
            $global = $this->connection->fetchOne("SELECT value FROM setting WHERE id = 'advancedsearch_main_config'");
            if ($global !== false) {
                $ids[] = (int) json_decode((string) $global, true);
            }
            foreach ($this->connection->fetchFirstColumn("SELECT value FROM site_setting WHERE id = 'advancedsearch_main_config'") as $value) {
                $ids[] = (int) json_decode((string) $value, true);
            }
            $ids = array_values(array_unique(array_filter($ids)));
            if (!$ids) {
                return true;
            }
            return (bool) $this->connection->fetchOne(
                'SELECT 1 FROM search_config c INNER JOIN search_engine e ON e.id = c.engine_id WHERE e.adapter = ? AND c.id IN (' . implode(',', $ids) . ') LIMIT 1',
                ['internal']
            );
        } catch (\Throwable $e) {
            return true;
        }
    }

    protected function satisfies(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies($version, $constraint);
        } catch (\Throwable $e) {
            return $version === $constraint;
        }
    }
}
