<?php declare(strict_types=1);
/**
 * Agile patch iiifserver-portable-manifest-cache (IiifServer): Portable manifest cache.
 *
 * Each variant lists the module versions it was verified on, and exact-text edits: "search" must occur once
 * in the stock file; it is replaced by "replace", which carries the marker "AGILE-PATCH iiifserver-portable-manifest-cache".
 * "legacy": text found in older, hand-applied forms of this patch (reported, never re-applied over).
 * See docs/PATCHES.md. Verify against stock releases with: php data/scripts/patches.php verify <stock-dir>
 */

return [
    'id' => 'iiifserver-portable-manifest-cache',
    'module' => 'IiifServer',
    'level' => 'required',
    'title' => 'Portable manifest cache',
    'why' => 'IiifServer caches each manifest with the absolute urls of the host that built it and serves the file verbatim, so a cache built on one host (production, a colleague\'s localhost) hands that host\'s urls to every visitor of every other environment.',
    'impact' => 'Without it, a manifest cache copied or shared between environments (backups, staging refreshes) points viewers at the wrong host.',
    'applies_from' => null,
    'fixed_in' => null,
    'when_module' => null,
    'upstream' => 'To report.',
    'legacy' => ['rebaseIiifCache'],
    'variants' => [
        [
            'versions' => ['3.6.32', '3.6.33', '3.6.34'],
            'edits' => [
                [
                    'file' => 'src/Controller/PresentationController.php',
                    'search' => <<<'AGILEPATCH'
            if (file_exists($filepath) && is_readable($filepath)) {
                $manifest = file_get_contents($filepath);
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
            if (file_exists($filepath) && is_readable($filepath)) {
                $manifest = file_get_contents($filepath);
                if ($manifest !== false) {
                    // AGILE-PATCH iiifserver-portable-manifest-cache: rebase the cached manifest onto THIS environment's host.
                    // A manifest cache is otherwise environment-bound: it stores the
                    // absolute urls of whichever host generated it, and is served
                    // verbatim, so a cache built on production hands production urls
                    // to every anonymous visitor of every other environment.
                    // See AgileThemeTools docs/PATCHES.md.
                    $manifest = $this->rebaseIiifCache($manifest);
                }
AGILEPATCH,
                ],
                [
                    'file' => 'src/Controller/PresentationController.php',
                    'search' => <<<'AGILEPATCH'
    public function genericAction()
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
    // AGILE-PATCH iiifserver-portable-manifest-cache: helpers that rebase a cached manifest onto the current host. See AgileThemeTools docs/PATCHES.md.
    /**
     * Legacy placeholder, still honoured on read so caches written by an earlier
     * revision of this patch keep working. Nothing writes it any more.
     */
    const CACHE_BASE_PLACEHOLDER = '__IIIF_CACHE_BASE__';

    /**
     * Rewrite a cached manifest's base url to that of the current environment.
     *
     * A IIIF manifest must carry absolute, dereferenceable ids — that is required by
     * the spec and relative urls would be rejected by clients — so a cached manifest
     * necessarily embeds the host that generated it, and IiifServer serves the cache
     * verbatim. That is what makes a cache built on production (or on a colleague's
     * localhost) serve production urls to everyone else.
     *
     * Rather than storing a placeholder, we derive the cached base url from the
     * manifest's own id and swap it for the current one. This keeps the file on disk
     * a valid manifest in its own right, so losing this patch degrades rather than
     * breaks. Note the json is always encoded with JSON_UNESCAPED_SLASHES above, so
     * urls appear verbatim and a plain str_replace is sufficient.
     */
    protected function rebaseIiifCache(string $json): string
    {
        $currentBase = $this->iiifCacheBaseUrl();
        if ($currentBase === '') {
            return $json;
        }

        // Caches written by the earlier revision of this patch.
        if (strpos($json, self::CACHE_BASE_PLACEHOLDER) !== false) {
            return str_replace(self::CACHE_BASE_PLACEHOLDER, $currentBase, $json);
        }

        $cachedBase = $this->iiifCachedBaseUrl($json);
        if ($cachedBase === null || $cachedBase === $currentBase) {
            return $json;
        }

        // An empty host ("http://") would match every http url: replace it with
        // the following slash only ("http:///" is unique to the missing host).
        if (preg_match('~^https?://$~', $cachedBase)) {
            return str_replace($cachedBase . '/', $currentBase . '/', $json);
        }
        return str_replace($cachedBase, $currentBase, $json);
    }

    /**
     * The base url baked into a cached manifest, taken from the first id that is
     * an IIIF url of this server (e.g. "https://example.org/iiif/3/123/manifest"
     * => "https://example.org"). Only ids under "/iiif/" are considered: the
     * first absolute id of a manifest can be a thumbnail on an external store
     * (Amazon S3), whose host must not be rewritten. The host may be empty
     * ("http:///iiif/…"): a manifest cached by a job run without a server url
     * (a cron or cli run of CacheManifests) has no host at all.
     */
    protected function iiifCachedBaseUrl(string $json): ?string
    {
        if (!preg_match('~"@?id"\s*:\s*"(https?://[^/"]*)(?:/[^"]*)?/iiif/~', $json, $matches)) {
            return null;
        }
        return $matches[1];
    }

    /**
     * Base url of the current environment, e.g. "https://example.org" or
     * "http://localhost:8080".
     */
    protected function iiifCacheBaseUrl(): string
    {
        $serverUrl = $this->viewHelpers()->get('ServerUrl');
        return rtrim((string) $serverUrl(), '/');
    }

    public function genericAction()
AGILEPATCH,
                ],
            ],
        ],
        [
            'versions' => ['3.6.35'],
            'edits' => [
                [
                    'file' => 'src/Controller/PresentationController.php',
                    'search' => <<<'AGILEPATCH'
            if (is_readable($filepath) && filemtime($filepath) > $mtime) {
                $manifest = file_get_contents($filepath);
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
            if (is_readable($filepath) && filemtime($filepath) > $mtime) {
                $manifest = file_get_contents($filepath);
                if ($manifest !== false) {
                    // AGILE-PATCH iiifserver-portable-manifest-cache: rebase the cached manifest onto THIS environment's host.
                    // A manifest cache is otherwise environment-bound: it stores the
                    // absolute urls of whichever host generated it, and is served
                    // verbatim, so a cache built on production hands production urls
                    // to every anonymous visitor of every other environment.
                    // See AgileThemeTools docs/PATCHES.md.
                    $manifest = $this->rebaseIiifCache($manifest);
                }
AGILEPATCH,
                ],
                [
                    'file' => 'src/Controller/PresentationController.php',
                    'search' => <<<'AGILEPATCH'
    public function genericAction()
AGILEPATCH,
                    'replace' => <<<'AGILEPATCH'
    // AGILE-PATCH iiifserver-portable-manifest-cache: helpers that rebase a cached manifest onto the current host. See AgileThemeTools docs/PATCHES.md.
    /**
     * Legacy placeholder, still honoured on read so caches written by an earlier
     * revision of this patch keep working. Nothing writes it any more.
     */
    const CACHE_BASE_PLACEHOLDER = '__IIIF_CACHE_BASE__';

    /**
     * Rewrite a cached manifest's base url to that of the current environment.
     *
     * A IIIF manifest must carry absolute, dereferenceable ids — that is required by
     * the spec and relative urls would be rejected by clients — so a cached manifest
     * necessarily embeds the host that generated it, and IiifServer serves the cache
     * verbatim. That is what makes a cache built on production (or on a colleague's
     * localhost) serve production urls to everyone else.
     *
     * Rather than storing a placeholder, we derive the cached base url from the
     * manifest's own id and swap it for the current one. This keeps the file on disk
     * a valid manifest in its own right, so losing this patch degrades rather than
     * breaks. Note the json is always encoded with JSON_UNESCAPED_SLASHES above, so
     * urls appear verbatim and a plain str_replace is sufficient.
     */
    protected function rebaseIiifCache(string $json): string
    {
        $currentBase = $this->iiifCacheBaseUrl();
        if ($currentBase === '') {
            return $json;
        }

        // Caches written by the earlier revision of this patch.
        if (strpos($json, self::CACHE_BASE_PLACEHOLDER) !== false) {
            return str_replace(self::CACHE_BASE_PLACEHOLDER, $currentBase, $json);
        }

        $cachedBase = $this->iiifCachedBaseUrl($json);
        if ($cachedBase === null || $cachedBase === $currentBase) {
            return $json;
        }

        // An empty host ("http://") would match every http url: replace it with
        // the following slash only ("http:///" is unique to the missing host).
        if (preg_match('~^https?://$~', $cachedBase)) {
            return str_replace($cachedBase . '/', $currentBase . '/', $json);
        }
        return str_replace($cachedBase, $currentBase, $json);
    }

    /**
     * The base url baked into a cached manifest, taken from the first id that is
     * an IIIF url of this server (e.g. "https://example.org/iiif/3/123/manifest"
     * => "https://example.org"). Only ids under "/iiif/" are considered: the
     * first absolute id of a manifest can be a thumbnail on an external store
     * (Amazon S3), whose host must not be rewritten. The host may be empty
     * ("http:///iiif/…"): a manifest cached by a job run without a server url
     * (a cron or cli run of CacheManifests) has no host at all.
     */
    protected function iiifCachedBaseUrl(string $json): ?string
    {
        if (!preg_match('~"@?id"\s*:\s*"(https?://[^/"]*)(?:/[^"]*)?/iiif/~', $json, $matches)) {
            return null;
        }
        return $matches[1];
    }

    /**
     * Base url of the current environment, e.g. "https://example.org" or
     * "http://localhost:8080".
     */
    protected function iiifCacheBaseUrl(): string
    {
        $serverUrl = $this->viewHelpers()->get('ServerUrl');
        return rtrim((string) $serverUrl(), '/');
    }

    public function genericAction()
AGILEPATCH,
                ],
            ],
        ],
    ],
];
