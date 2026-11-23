<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for the rich-text fields the Quill editor produces
 * (quotation / order terms, invoice notes). Anything outside the allowlist is
 * dropped: <script>, <style>, event handlers, style attributes, javascript:
 * URLs, iframes, and dompdf's <script type="text/php">.
 */
class HtmlSanitizer
{
    /** Tags kept as-is. Unknown tags are unwrapped (their text is kept). */
    private const ALLOWED_TAGS = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ol', 'ul', 'li', 'a', 'span', 'blockquote', 'pre', 'code', 'sub', 'sup',
    ];

    /** Tags removed together with everything inside them. */
    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'frame', 'frameset', 'applet',
        'meta', 'link', 'base', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'svg', 'math', 'template', 'noscript', 'title', 'head', 'img', 'video', 'audio', 'source',
    ];

    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        if (trim($html) === '') {
            return $html;
        }

        // Plain text: nothing to strip, and no reason to re-encode it.
        if (! str_contains($html, '<') && ! str_contains($html, '&')) {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__sanitize_root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('__sanitize_root');
        if (! $root) {
            return e(strip_tags($html));
        }

        self::cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    private static function cleanChildren(DOMNode $node): void
    {
        // Copy first: the loop removes and replaces nodes.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Comments, processing instructions (e.g. a smuggled PHP open tag), CDATA.
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }

            self::cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            $keep = false;

            if ($name === 'class') {
                // Quill's own classes only (ql-indent-1, ql-align-center, ...).
                $classes = array_filter(preg_split('/\s+/', $value) ?: [], fn ($c) => preg_match('/^ql-[a-z0-9-]+$/', $c));
                if ($classes) {
                    $el->setAttribute('class', implode(' ', $classes));
                    continue;
                }
            } elseif ($name === 'data-list' && $tag === 'li') {
                $keep = in_array($value, ['bullet', 'ordered', 'checked', 'unchecked'], true);
            } elseif ($tag === 'a' && $name === 'href') {
                $keep = self::isSafeUrl($value);
            } elseif ($tag === 'a' && $name === 'target') {
                $keep = in_array($value, ['_blank', '_self'], true);
            }

            if (! $keep) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        // Strip control characters and whitespace browsers ignore inside schemes.
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url)) ?? '');

        if ($normalized === '' || str_starts_with($normalized, '#') || str_starts_with($normalized, '/')) {
            return $normalized !== '' && ! str_starts_with($normalized, '//');
        }

        $scheme = parse_url($normalized, PHP_URL_SCHEME);

        return is_string($scheme) && in_array($scheme, self::ALLOWED_URL_SCHEMES, true);
    }
}
