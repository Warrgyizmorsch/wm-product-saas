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

        // Fetch all active tenant leads to build duplicate graph
        $allLeads = Lead::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->select(['id', 'lead_number', 'gstin', 'email', 'company_email', 'phone', 'company_phone'])
            ->orderBy('id', 'asc')
            ->get();

        // Lead lookup map
        $leadMetaMap = [];
        foreach ($allLeads as $l) {
            $gstin = !empty($l->gstin) ? strtoupper(trim($l->gstin)) : null;
            $ce = !empty($l->email) ? strtolower(trim($l->email)) : null;
            $compE = !empty($l->company_email) ? strtolower(trim($l->company_email)) : null;
            $cp = !empty($l->phone) ? preg_replace('/[^0-9]/', '', $l->phone) : null;
            $compP = !empty($l->company_phone) ? preg_replace('/[^0-9]/', '', $l->company_phone) : null;

            $emails = array_values(array_unique(array_filter([$ce, $compE])));
            $phones = array_values(array_unique(array_filter([$cp, $compP], fn($p) => strlen($p) >= 5)));

            $num = $l->lead_number ?: ('LD-' . str_pad($l->id, 4, '0', STR_PAD_LEFT));

            $leadMetaMap[$l->id] = [
                'id' => $l->id,
                'lead_number' => $num,
                'gstin' => $gstin,
                'contact_email' => $ce,
                'company_email' => $compE,
                'contact_phone' => $cp,
                'company_phone' => $compP,
                'emails' => $emails,
                'phones' => $phones,
            ];
        }

        // Determine original vs duplicate relationships
        $duplicateOfMap = [];        // child_id -> parent_original_id
        $originalHasDuplicates = []; // parent_original_id -> true

        foreach ($leadMetaMap as $id => $meta) {
            if (empty($meta['gstin']) && empty($meta['emails']) && empty($meta['phones'])) {
                continue;
            }

            // Find all matching lead IDs
            $matchingIds = [];
            foreach ($leadMetaMap as $otherId => $otherMeta) {
                if ($id === $otherId) {
                    continue;
                }

                $gstinMatched = !empty($meta['gstin']) && !empty($otherMeta['gstin']) && ($meta['gstin'] === $otherMeta['gstin']);
                $emailMatched = !empty(array_intersect($meta['emails'], $otherMeta['emails']));

                $phoneMatched = false;
                if (!empty($meta['phones']) && !empty($otherMeta['phones'])) {
                    foreach ($meta['phones'] as $p1) {
                        foreach ($otherMeta['phones'] as $p2) {
                            if ($p1 === $p2 || (strlen($p1) >= 5 && strlen($p2) >= 5 && (str_contains($p1, $p2) || str_contains($p2, $p1)))) {
                                $phoneMatched = true;
                                break 2;
                            }
                        }
                    }
                }

                if ($gstinMatched || $emailMatched || $phoneMatched) {
                    $matchingIds[] = $otherId;
                }
            }

            if (!empty($matchingIds)) {
                // Find matching leads created BEFORE this one (smaller ID)
                $earlierMatches = array_filter($matchingIds, fn($mId) => $mId < $id);

                if (!empty($earlierMatches)) {
                    // This lead was created AFTER an existing match -> it is DUPLICATE of the oldest match!
                    $origId = min($earlierMatches);
                    while (isset($duplicateOfMap[$origId])) {
                        $origId = $duplicateOfMap[$origId];
                    }
                    $duplicateOfMap[$id] = $origId;
                    $originalHasDuplicates[$origId] = true;
                }
            }
        }

        // Annotate passed leads collection
        foreach ($leads as $lead) {
            $id = $lead->id;

            if (isset($duplicateOfMap[$id])) {
                $origId = $duplicateOfMap[$id];
                $origMeta = $leadMetaMap[$origId] ?? null;
                $origNumber = $origMeta['lead_number'] ?? ('LD-' . str_pad($origId, 4, '0', STR_PAD_LEFT));

                $lead->is_duplicate = true;
                $lead->is_original = false;
                $lead->duplicate_of_id = $origId;
                $lead->duplicate_of_number = $origNumber;

                // Build granular reason string
                $meta = $leadMetaMap[$id] ?? [
                    'gstin' => !empty($lead->gstin) ? strtoupper(trim($lead->gstin)) : null,
                    'contact_email' => !empty($lead->email) ? strtolower(trim($lead->email)) : null,
                    'company_email' => !empty($lead->company_email) ? strtolower(trim($lead->company_email)) : null,
                    'contact_phone' => !empty($lead->phone) ? preg_replace('/[^0-9]/', '', $lead->phone) : null,
                    'company_phone' => !empty($lead->company_phone) ? preg_replace('/[^0-9]/', '', $lead->company_phone) : null,
                ];

                $reasons = [];

                if ($origMeta) {
                    // GSTIN
                    if (!empty($meta['gstin']) && !empty($origMeta['gstin']) && $meta['gstin'] === $origMeta['gstin']) {
                        $reasons[] = 'GSTIN';
                    }

                    // Contact Email
                    if (!empty($meta['contact_email'])) {
                        $e = $meta['contact_email'];
                        if ($e === $origMeta['contact_email'] || $e === $origMeta['company_email']) {
                            $reasons[] = 'Contact Email';
                        }
                    }

                    // Company Email
                    if (!empty($meta['company_email'])) {
                        $e = $meta['company_email'];
                        if ($e === $origMeta['company_email'] || $e === $origMeta['contact_email']) {
                            $reasons[] = 'Company Email';
                        }
                    }

                    // Contact Phone
                    if (!empty($meta['contact_phone']) && strlen($meta['contact_phone']) >= 5) {
                        $p = $meta['contact_phone'];
                        $op1 = $origMeta['contact_phone'];
                        $op2 = $origMeta['company_phone'];
                        if (($op1 && ($p === $op1 || str_contains($p, $op1) || str_contains($op1, $p))) ||
                            ($op2 && ($p === $op2 || str_contains($p, $op2) || str_contains($op2, $p)))) {
                            $reasons[] = 'Contact Phone';
                        }
                    }

                    // Company Phone
                    if (!empty($meta['company_phone']) && strlen($meta['company_phone']) >= 5) {
                        $p = $meta['company_phone'];
                        $op1 = $origMeta['company_phone'];
                        $op2 = $origMeta['contact_phone'];
                        if (($op1 && ($p === $op1 || str_contains($p, $op1) || str_contains($op1, $p))) ||
                            ($op2 && ($p === $op2 || str_contains($p, $op2) || str_contains($op2, $p)))) {
                            $reasons[] = 'Company Phone';
                        }
                    }
                }

                $lead->duplicate_reason = !empty($reasons) ? implode(', ', array_unique($reasons)) : 'Email / Phone';
            } else {
                $lead->is_duplicate = false;
                $lead->is_original = !empty($originalHasDuplicates[$id]);
                $lead->duplicate_of_id = null;
                $lead->duplicate_of_number = null;
                $lead->duplicate_reason = null;
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
