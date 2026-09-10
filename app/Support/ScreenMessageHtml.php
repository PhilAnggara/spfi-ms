<?php

namespace App\Support;

final class ScreenMessageHtml
{
    /**
     * @var list<string>
     */
    public const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'];

    public static function isEmpty(?string $html): bool
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;

        return trim($text) === '';
    }

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            return $html;
        }

        $wrapped = '<div id="sm-root">'.$html.'</div>';

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">'.$wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return trim(strip_tags($html));
        }

        $root = $document->getElementById('sm-root');
        if (! $root) {
            return trim(strip_tags($html));
        }

        self::sanitizeNode($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Safe HTML for Blade / overlay display (re-sanitized).
     */
    public static function forDisplay(?string $html): string
    {
        $html = (string) $html;

        if ($html === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            return nl2br(e($html), false);
        }

        return self::sanitize($html);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (! $child instanceof \DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap disallowed elements: keep children, drop the tag.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            // Strip all attributes (no href/style/on*).
            while ($child->attributes->length > 0) {
                $child->removeAttribute($child->attributes->item(0)?->name ?? '');
            }

            self::sanitizeNode($child);
        }
    }
}
