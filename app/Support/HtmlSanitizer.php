<?php

namespace App\Support;

/**
 * Robust HTML Sanitizer to prevent Stored XSS and HTML Injection
 * in user-supplied rich text (Quotation terms, Sales & Purchase Order notes, etc.)
 */
class HtmlSanitizer
{
    /**
     * Whitelist of safe formatting HTML tags.
     * All executable tags (<script>, <style>, <iframe>, <object>, <embed>, <form>, <svg>, <meta>) are stripped.
     */
    private const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><s><strike><ul><ol><li><span><h1><h2><h3><h4><h5><h6><table><thead><tbody><tfoot><tr><th><td><blockquote><hr><a>';

    /**
     * Clean and sanitize untrusted HTML string.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // 1. Strip all disallowed tags
        $cleaned = strip_tags($html, self::ALLOWED_TAGS);

        // 2. Remove all 'on*' event handler attributes (onload, onerror, onclick, onmouseover, etc.)
        $cleaned = preg_replace('/\s+on[a-zA-Z0-9_\-]+\s*=\s*(["\']).*?\1/is', '', $cleaned);
        $cleaned = preg_replace('/\s+on[a-zA-Z0-9_\-]+\s*=\s*[^>\s]+/is', '', $cleaned);

        // 3. Remove dangerous URI schemes (javascript:, vbscript:, data:, file:) in href/src
        $cleaned = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data|file):.*?\2/is', '$1="#"', $cleaned);
        $cleaned = preg_replace('/(href|src)\s*=\s*(javascript|vbscript|data|file):[^\s>]+/is', '$1="#"', $cleaned);

        // 4. Remove dangerous CSS expressions and scripts in style attributes
        $cleaned = preg_replace('/style\s*=\s*(["\']).*?(expression|behavior|javascript|-moz-binding).*?\1/is', '', $cleaned);

        return trim($cleaned);
    }
}
