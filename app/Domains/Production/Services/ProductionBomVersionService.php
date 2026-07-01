<?php

namespace App\Domains\Production\Services;

class ProductionBomVersionService
{
    /**
     * Bumps the major version, resets minor and patch to 0.
     * E.g., "1.2.3" -> "2.0.0"
     */
    public function incrementMajor(string $version): string
    {
        $parts = $this->parseVersion($version);
        $parts[0]++;
        return "{$parts[0]}.0.0";
    }

    /**
     * Bumps the minor version, resets patch to 0.
     * E.g., "1.2.3" -> "1.3.0"
     */
    public function incrementMinor(string $version): string
    {
        $parts = $this->parseVersion($version);
        $parts[1]++;
        return "{$parts[0]}.{$parts[1]}.0";
    }

    /**
     * Bumps the patch version.
     * E.g., "1.2.3" -> "1.2.4"
     */
    public function incrementPatch(string $version): string
    {
        $parts = $this->parseVersion($version);
        $parts[2]++;
        return "{$parts[0]}.{$parts[1]}.{$parts[2]}";
    }

    /**
     * Helper to parse version string into [major, minor, patch] integers.
     */
    private function parseVersion(string $version): array
    {
        $parts = explode('.', $version);
        return [
            isset($parts[0]) ? (int) $parts[0] : 1,
            isset($parts[1]) ? (int) $parts[1] : 0,
            isset($parts[2]) ? (int) $parts[2] : 0,
        ];
    }
}
