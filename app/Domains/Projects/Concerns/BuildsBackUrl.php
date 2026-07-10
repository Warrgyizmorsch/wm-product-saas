<?php

namespace App\Domains\Projects\Concerns;

trait BuildsBackUrl
{
    /**
     * Build a "back" URL from the previous request, overriding the given query parameters.
     * Useful for resetting pagination (e.g. ['member_page' => 1]) after a create action.
     */
    protected function backUrlWithQuery(string $fallbackUrl, array $overrides): string
    {
        $previous = url()->previous($fallbackUrl);
        $parts = parse_url($previous);

        parse_str($parts['query'] ?? '', $query);
        $query = array_merge($query, $overrides);

        $url = ($parts['path'] ?? $fallbackUrl) . '?' . http_build_query($query);

        return isset($parts['fragment']) ? $url . '#' . $parts['fragment'] : $url;
    }
}
