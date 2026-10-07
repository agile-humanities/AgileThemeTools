<?php
namespace AgileThemeTools\DisplayMode;

/**
 * Automated summaries, the way Drupal makes them: plain text cut to a maximum length at the last sentence end
 * that fits, failing that at a word boundary, with an ellipsis when something was cut.
 */
class Summary
{
    const DEFAULT_LENGTH = 300;

    /** Plain text from HTML or text: tags removed, entities decoded, whitespace collapsed. */
    public static function text(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @param string|null $html The source, HTML or text.
     * @param int $max Maximum length in characters; 0 or less means no limit.
     */
    public static function summarize(?string $html, int $max = self::DEFAULT_LENGTH): string
    {
        $text = self::text($html);
        if ($max <= 0 || mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        // The last sentence end within the limit — but not so early that the summary would be a fragment.
        if (preg_match_all('/[.!?]["\')\]]?(?=\s|$)/u', $cut, $m, PREG_OFFSET_CAPTURE) && $m[0]) {
            $last = end($m[0]);
            $end = $last[1] + strlen($last[0]); // byte offsets from preg
            $sentence = trim(substr($cut, 0, $end));
            if (mb_strlen($sentence) >= $max * 0.3) {
                return $sentence;
            }
        }
        // Otherwise the last word boundary.
        $space = mb_strrpos($cut, ' ');
        $words = $space !== false && $space > $max * 0.3 ? mb_substr($cut, 0, $space) : $cut;
        return rtrim($words, " ,;:-–—") . '…';
    }
}
