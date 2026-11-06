<?php

namespace App\Support;

final class LegalHtml
{
    public static function sanitize(string $html): string
    {
        $allowed = '<h2><h3><p><div><strong><b><em><i><ul><ol><li><br><a>';
        $clean = strip_tags(trim($html), $allowed);

        $clean = preg_replace_callback(
            '/<\s*(\/?)\s*(h2|h3|p|div|strong|b|em|i|ul|ol|li|br|a)\b([^>]*)>/i',
            static function (array $matches): string {
                $closing = $matches[1] === '/';
                $tag = strtolower($matches[2]);
                $tag = match ($tag) {
                    'b' => 'strong',
                    'i' => 'em',
                    'div' => 'p',
                    default => $tag,
                };

                if ($tag === 'br') {
                    return '<br>';
                }

                if ($tag === 'a') {
                    if ($closing) {
                        return '</a>';
                    }

                    preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $matches[3], $href);
                    $url = html_entity_decode($href[1] ?? $href[2] ?? $href[3] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if (! self::safeLink($url)) {
                        return '<a>';
                    }

                    return '<a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
                }

                return $closing ? "</{$tag}>" : "<{$tag}>";
            },
            $clean,
        );

        return trim((string) $clean);
    }

    public static function safeLink(string $url): bool
    {
        if (preg_match('/^mailto:([^\s]+)$/i', $url, $matches)) {
            return filter_var($matches[1], FILTER_VALIDATE_EMAIL) !== false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https'], true);
    }
}
