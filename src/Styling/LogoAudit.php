<?php
namespace AgileThemeTools\Styling;

use Doctrine\ORM\EntityManager;
use Laminas\Http\Client as HttpClient;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Theme\Theme;

/**
 * Checks the logos chosen in a site's theme settings against what the theme's header and banners are built for:
 * wide images with a transparent background.
 *
 * A setting is audited when it is an asset element of the theme's config/theme.ini whose key or name contains
 * "logo". A theme can override that per element with `options.logo_audit = true|false`.
 *
 * Findings, most serious first:
 *  - tall (warning): the image is taller than wide. The theme gives it a portrait class but headers are short.
 *  - squarish (note): wider than tall, but less than 1.5:1.
 *  - opaque (warning): a raster image without a transparent background (JPEG, or no transparent pixel on its edge),
 *    which shows as a box on any background that is not exactly its own colour.
 *  - heavy (note): a large file or pixel size for something loaded on every page.
 */
class LogoAudit
{
    const LEVEL_WARNING = 'warning';
    const LEVEL_NOTE = 'note';

    /** Width ÷ height below which a logo is reported as close to square. */
    const WIDE_RATIO = 1.5;
    const HEAVY_BYTES = 300000;
    const HEAVY_WIDTH = 2400;
    /** Pixel checks are skipped above this many pixels, to stay inside PHP's memory limit. */
    const MAX_PIXELS = 16000000;
    const MAX_DOWNLOAD_BYTES = 8000000;

    protected ApiManager $api;
    protected EntityManager $entityManager;
    protected HttpClient $httpClient;
    protected string $localBasePath;

    public function __construct(ApiManager $api, EntityManager $entityManager, HttpClient $httpClient, string $localBasePath)
    {
        $this->api = $api;
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->localBasePath = rtrim($localBasePath, '/');
    }

    /**
     * The theme's logo settings: setting name => label.
     */
    public function getLogoSettings(Theme $theme): array
    {
        $settings = [];
        foreach (($theme->getConfigSpec()['elements'] ?? []) as $key => $spec) {
            $type = (string) ($spec['type'] ?? '');
            if ($type !== 'Omeka\Form\Element\Asset' && $type !== \Omeka\Form\Element\Asset::class) {
                continue;
            }
            $name = (string) ($spec['name'] ?? $key);
            $flag = $spec['options']['logo_audit'] ?? null;
            $isLogo = $flag !== null ? filter_var($flag, FILTER_VALIDATE_BOOLEAN) : (bool) preg_match('/logo/i', $key . ' ' . $name);
            if ($isLogo) {
                $settings[$name] = (string) ($spec['options']['label'] ?? $name);
            }
        }
        return $settings;
    }

    /**
     * Audits every logo the site has set.
     *
     * @return array[] One entry per set logo: ['setting', 'label', 'name', 'url', 'width', 'height', 'mediaType',
     *   'bytes', 'findings' => [['level', 'code', 'message', 'fix'], …]]
     */
    public function audit(Theme $theme, SiteRepresentation $site): array
    {
        $logoSettings = $this->getLogoSettings($theme);
        if (!$logoSettings) {
            return [];
        }
        // Read directly: re-targeting the shared site settings service would disturb later reads on the page.
        $value = $this->entityManager->getConnection()->fetchOne(
            'SELECT value FROM site_setting WHERE site_id = ? AND id = ?',
            [$site->id(), $theme->getSettingsKey()]
        );
        $themeSettings = $value ? json_decode((string) $value, true) : [];
        $results = [];
        foreach ($logoSettings as $name => $label) {
            $assetId = is_array($themeSettings) ? ($themeSettings[$name] ?? null) : null;
            if (!$assetId || !is_numeric($assetId)) {
                continue;
            }
            try {
                $asset = $this->api->read('assets', $assetId)->getContent();
            } catch (NotFoundException $e) {
                continue;
            }
            $result = $this->auditImage($this->fetch($asset), (string) $asset->mediaType());
            $results[] = $result + [
                'setting' => $name,
                'label' => $label,
                'name' => $asset->name(),
                'url' => $asset->assetUrl(),
            ];
        }
        return $results;
    }

    /**
     * Audits image data. Public so it can be tested without an asset.
     *
     * @return array ['width', 'height', 'mediaType', 'bytes', 'findings']
     */
    public function auditImage(?string $data, string $mediaType): array
    {
        $result = ['width' => null, 'height' => null, 'mediaType' => $mediaType, 'bytes' => $data === null ? null : strlen($data), 'findings' => []];
        if ($data === null || $data === '') {
            $result['findings'][] = $this->finding(self::LEVEL_NOTE, 'unreadable', 'The image file could not be read, so it was not checked.', ''); // @translate
            return $result;
        }
        $isSvg = stripos($mediaType, 'svg') !== false || preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg\b/is', substr($data, 0, 2000));
        if ($isSvg) {
            [$width, $height] = $this->svgSize($data);
        } else {
            $size = @getimagesizefromstring($data);
            [$width, $height] = $size ? [$size[0], $size[1]] : [null, null];
            if ($size && !empty($size['mime'])) {
                $result['mediaType'] = $mediaType = $size['mime'];
            }
        }
        $result['width'] = $width;
        $result['height'] = $height;

        if ($width && $height) {
            $ratio = $width / $height;
            if ($ratio < 1) {
                $result['findings'][] = $this->finding(self::LEVEL_WARNING, 'tall',
                    sprintf('This logo is taller than it is wide (%s). Headers and banners are short, so a tall logo is shown very small or crowds the navigation.', $this->ratioLabel($width, $height)), // @translate
                    'Use a horizontal version of the logo (a wordmark or a side-by-side lockup), ideally 3:1 or wider.'); // @translate
            } elseif ($ratio < self::WIDE_RATIO) {
                $result['findings'][] = $this->finding(self::LEVEL_NOTE, 'squarish',
                    sprintf('This logo is close to square (%s), so it will appear small in a short header.', $this->ratioLabel($width, $height)), // @translate
                    'If a horizontal version of the logo exists, prefer it.'); // @translate
            }
        } else {
            $result['findings'][] = $this->finding(self::LEVEL_NOTE, 'no-size', 'The size of this image could not be determined, so its proportions were not checked.', ''); // @translate
        }

        if (!$isSvg) {
            $opaque = $this->opaqueBackground($data, $mediaType, $width, $height);
            if ($opaque !== null) {
                $result['findings'][] = $this->finding(self::LEVEL_WARNING, 'opaque',
                    $opaque === ''
                        ? 'This image has no transparent background. It will show as a box wherever the background behind it is a different colour.' // @translate
                        : sprintf('This image has no transparent background (its background is %s). It will show as a box wherever the background behind it is a different colour.', $opaque), // @translate
                    stripos($mediaType, 'jpeg') !== false
                        ? 'JPEG files cannot be transparent. Use an SVG, or a PNG or WebP exported with a transparent background.' // @translate
                        : 'Use an SVG, or re-export the image with a transparent background.'); // @translate
            }
            if ($result['bytes'] > self::HEAVY_BYTES || ($width && $width > self::HEAVY_WIDTH)) {
                $result['findings'][] = $this->finding(self::LEVEL_NOTE, 'heavy',
                    sprintf('This is a large image for a logo (%s × %s pixels, %s KB) and it is loaded on every page.', $width ?: '?', $height ?: '?', number_format($result['bytes'] / 1000)), // @translate
                    'Use an SVG, or resize to about twice the size it is displayed at.'); // @translate
            }
        }
        return $result;
    }

    protected function finding(string $level, string $code, string $message, string $fix): array
    {
        return ['level' => $level, 'code' => $code, 'message' => $message, 'fix' => $fix];
    }

    protected function ratioLabel(int|float $width, int|float $height): string
    {
        return $width >= $height
            ? rtrim(rtrim(number_format($width / $height, 1), '0'), '.') . ':1'
            : '1:' . rtrim(rtrim(number_format($height / $width, 1), '0'), '.');
    }

    /**
     * Whether a raster image lacks a transparent background: null when it has one (or cannot be checked), else the
     * background colour as hex, or '' when the edge is not one colour.
     *
     * Judged on the image's edge, where a background shows: an alpha channel that is never used, or used only
     * inside the artwork, still leaves a box.
     */
    protected function opaqueBackground(string $data, string $mediaType, ?int $width, ?int $height): ?string
    {
        if (!$width || !$height || $width * $height > self::MAX_PIXELS || !function_exists('imagecreatefromstring')) {
            // JPEG is opaque whatever its size.
            return stripos($mediaType, 'jpeg') !== false ? '' : null;
        }
        $image = @imagecreatefromstring($data);
        if (!$image) {
            return stripos($mediaType, 'jpeg') !== false ? '' : null;
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        $colours = [];
        $transparent = 0;
        $sampled = 0;
        $stepX = max(1, intdiv($width, 64));
        $stepY = max(1, intdiv($height, 64));
        $points = [];
        for ($x = 0; $x < $width; $x += $stepX) {
            $points[] = [$x, 0];
            $points[] = [$x, $height - 1];
        }
        for ($y = 0; $y < $height; $y += $stepY) {
            $points[] = [0, $y];
            $points[] = [$width - 1, $y];
        }
        foreach ($points as [$x, $y]) {
            $rgba = imagecolorat($image, $x, $y);
            $sampled++;
            // GD alpha: 0 opaque … 127 transparent.
            if ((($rgba >> 24) & 0x7F) > 12) {
                $transparent++;
            } else {
                $key = sprintf('#%06X', $rgba & 0xFFFFFF);
                $colours[$key] = ($colours[$key] ?? 0) + 1;
            }
        }
        imagedestroy($image);
        if ($transparent > 0) {
            return null;
        }
        arsort($colours);
        $dominant = (string) array_key_first($colours);
        return reset($colours) / $sampled >= 0.9 ? $dominant : '';
    }

    /** Width and height of an SVG from its viewBox, else its width and height attributes. */
    protected function svgSize(string $svg): array
    {
        if (!preg_match('/<svg\b[^>]*>/is', $svg, $m)) {
            return [null, null];
        }
        $tag = $m[0];
        if (preg_match('/\bviewBox\s*=\s*["\']\s*[-\d.eE]+[\s,]+[-\d.eE]+[\s,]+([\d.eE]+)[\s,]+([\d.eE]+)/i', $tag, $v) && $v[1] > 0 && $v[2] > 0) {
            return [(float) $v[1], (float) $v[2]];
        }
        if (preg_match('/\bwidth\s*=\s*["\']([\d.]+)(px)?["\']/i', $tag, $w) && preg_match('/\bheight\s*=\s*["\']([\d.]+)(px)?["\']/i', $tag, $h) && $w[1] > 0 && $h[1] > 0) {
            return [(float) $w[1], (float) $h[1]];
        }
        return [null, null];
    }

    /** The asset's file: from the local store when it is there, else over HTTP (e.g. an S3 store). */
    protected function fetch($asset): ?string
    {
        $local = $this->localBasePath . '/asset/' . $asset->filename();
        if (is_readable($local)) {
            return filesize($local) <= self::MAX_DOWNLOAD_BYTES ? (string) file_get_contents($local) : null;
        }
        $url = $asset->assetUrl();
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }
        try {
            $client = clone $this->httpClient;
            $response = $client->resetParameters(true)->setUri($url)->setOptions(['timeout' => 8])->send();
            $body = $response->isSuccess() ? $response->getBody() : null;
            return $body !== null && strlen($body) <= self::MAX_DOWNLOAD_BYTES ? $body : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
