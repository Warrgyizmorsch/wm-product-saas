<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Repositories\RoutingRepositoryInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Q1: Routing Number Service
 * Generates unique routing numbers in format: RTG-YYYY-NNNNNN
 * Example: RTG-2026-000001
 *
 * Mirrors ProductionBomNumberService pattern.
 */
class RoutingNumberService
{
    /** Pattern: RTG-YYYY-NNNNNN */
    private const PATTERN = '/^RTG-\d{4}-\d{6}$/';

    public function __construct(
        private readonly RoutingRepositoryInterface $routingRepository
    ) {}

    /**
     * Generate the next available routing number for a tenant.
     */
    public function generateNextNumber(int $tenantId): string
    {
        $year     = Carbon::now()->year;
        $prefix   = config('production.routing_number_prefix', 'RTG');
        $last     = $this->routingRepository->getLastSequenceNumber($tenantId);
        $next     = $last + 1;

        return "{$prefix}-{$year}-" . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validate routing number format.
     */
    public function validateNumber(string $number): bool
    {
        // Accept custom alphanumeric numbers (not just auto-generated format)
        return (bool) preg_match('/^[A-Z0-9\-\/]{3,50}$/i', $number);
    }

    /**
     * Check if a routing number already exists for this tenant.
     */
    public function isDuplicate(string $number, int $tenantId, ?int $ignoreId = null): bool
    {
        return $this->routingRepository->findByRoutingNumber($number, $tenantId, $ignoreId) !== null;
    }
}
