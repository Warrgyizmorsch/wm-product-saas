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

            $e = !empty($lead->email) ? strtolower(trim($lead->email)) : null;
            $p = !empty($lead->phone) ? preg_replace('/[^0-9]/', '', $lead->phone) : null;

            if ($e || ($p && strlen($p) >= 5)) {
                $matchingIds = Lead::query()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $lead->id)
                    ->where(function ($q) use ($e, $p) {
                        if ($e) {
                            $q->whereRaw('LOWER(email) = ?', [$e]);
                        }
                        if ($p && strlen($p) >= 5) {
                            $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$p}%"]);
                        }
                    })
                    ->pluck('id')
                    ->toArray();
            }

            if (!empty($matchingIds)) {
                $firstMatchId = min($matchingIds);
                $lead->is_duplicate = true;
                $lead->duplicate_of_id = $firstMatchId;
                $lead->duplicate_reason = ($e ? 'Email' : '') . ($e && $p ? ' / ' : '') . ($p ? 'Phone' : '');
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
