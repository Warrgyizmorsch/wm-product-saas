<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use Illuminate\Support\Facades\DB;

class LeadDuplicateService
{
    /**
     * Detect and annotate duplicate leads where BOTH Email AND Phone match.
     */
    public function annotateDuplicates($leads, int $tenantId): void
    {
        if (empty($leads)) {
            return;
        }

        foreach ($leads as $lead) {
            $matchingIds = [];

            if (!empty($lead->email) && !empty($lead->phone)) {
                $e = strtolower(trim($lead->email));
                $p = preg_replace('/[^0-9]/', '', $lead->phone);

                if (strlen($p) >= 7) {
                    // Match ONLY IF BOTH Email AND Phone match
                    $matchingIds = Lead::query()
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->where('id', '!=', $lead->id)
                        ->whereRaw('LOWER(email) = ?', [$e])
                        ->whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$p}%"])
                        ->pluck('id')
                        ->toArray();
                }
            }

            if (!empty($matchingIds)) {
                $firstMatchId = min($matchingIds);
                $lead->is_duplicate = true;
                $lead->duplicate_of_id = $firstMatchId;
                $lead->duplicate_reason = 'Email & Phone';
                $lead->duplicate_count = count($matchingIds);
            } else {
                $lead->is_duplicate = false;
                $lead->duplicate_of_id = null;
                $lead->duplicate_reason = null;
                $lead->duplicate_count = 0;
            }
        }
    }

    /**
     * Check if a lead is duplicate by matching BOTH email and phone.
     */
    public function checkBothMatch(int $tenantId, ?string $email, ?string $phone, ?int $excludeLeadId = null): ?Lead
    {
        if (empty($email) || empty($phone)) {
            return null;
        }

        $e = strtolower(trim($email));
        $p = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($p) < 7) {
            return null;
        }

        return Lead::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($excludeLeadId, fn($q) => $q->where('id', '!=', $excludeLeadId))
            ->whereRaw('LOWER(email) = ?', [$e])
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$p}%"])
            ->orderBy('id', 'asc')
            ->first();
    }
}
