<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class LeadDuplicateService
{
    /**
     * Compute or retrieve the cached duplicate relationship graph for the tenant.
     * Uses an O(N) inverted-index single pass lookup rather than O(N^2) pairwise comparisons.
     */
    public function getDuplicateGraph(int $tenantId): array
    {
        // v2: the cached graph holds only integer maps (no per-lead meta), and is
        // stored compressed. The old graph (~430 bytes per lead) overflowed the
        // database cache row above ~37k leads and needed 200+ MB to build.
        $cacheKey = "tenant_{$tenantId}_dup_graph_v2";

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && ($decoded = @gzuncompress(base64_decode($cached))) !== false) {
            $graph = @unserialize($decoded, ['allowed_classes' => false]);
            if (is_array($graph)) {
                return $graph;
            }
        }

        $graph = $this->buildDuplicateGraph($tenantId);
        Cache::put($cacheKey, base64_encode(gzcompress(serialize($graph), 6)), 60);

        return $graph;
    }

    private function buildDuplicateGraph(int $tenantId): array
    {
        $allLeads = Lead::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->select(['id', 'gstin', 'email', 'company_email', 'phone', 'company_phone'])
            ->toBase()
            ->lazyById(2000, 'id');

        $gstinIndex = [];
        $emailIndex = [];
        $phoneIndex = [];

        $duplicateOfMap = [];
        $originalHasDuplicates = [];

        foreach ($allLeads as $l) {
            $id = $l->id;
            $gstin = !empty($l->gstin) ? strtoupper(trim($l->gstin)) : null;
            $ce = !empty($l->email) ? strtolower(trim($l->email)) : null;
            $compE = !empty($l->company_email) ? strtolower(trim($l->company_email)) : null;
            $cp = !empty($l->phone) ? preg_replace('/[^0-9]/', '', $l->phone) : null;
            $compP = !empty($l->company_phone) ? preg_replace('/[^0-9]/', '', $l->company_phone) : null;

            $emails = array_values(array_unique(array_filter([$ce, $compE])));
            $phones = array_values(array_unique(array_filter([$cp, $compP], fn($p) => strlen($p) >= 5)));


            // Find matches from earlier leads using inverted indexes (O(1) lookups)
            $earlierMatches = [];

            if (!empty($gstin) && isset($gstinIndex[$gstin])) {
                foreach ($gstinIndex[$gstin] as $mId) {
                    $earlierMatches[$mId] = true;
                }
            }

            foreach ($emails as $em) {
                if (isset($emailIndex[$em])) {
                    foreach ($emailIndex[$em] as $mId) {
                        $earlierMatches[$mId] = true;
                    }
                }
            }

            foreach ($phones as $ph) {
                if (isset($phoneIndex[$ph])) {
                    foreach ($phoneIndex[$ph] as $mId) {
                        $earlierMatches[$mId] = true;
                    }
                }
                if (strlen($ph) >= 10) {
                    $suffix = substr($ph, -10);
                    if (isset($phoneIndex[$suffix])) {
                        foreach ($phoneIndex[$suffix] as $mId) {
                            $earlierMatches[$mId] = true;
                        }
                    }
                }
            }

            if (!empty($earlierMatches)) {
                $origId = min(array_keys($earlierMatches));
                while (isset($duplicateOfMap[$origId])) {
                    $origId = $duplicateOfMap[$origId];
                }
                $duplicateOfMap[$id] = $origId;
                $originalHasDuplicates[$origId] = true;
            }

            // Register this lead into inverted indexes for subsequent leads
            if (!empty($gstin)) {
                $gstinIndex[$gstin][] = $id;
            }
            foreach ($emails as $em) {
                $emailIndex[$em][] = $id;
            }
            foreach ($phones as $ph) {
                $phoneIndex[$ph][] = $id;
                if (strlen($ph) >= 10) {
                    $phoneIndex[substr($ph, -10)][] = $id;
                }
            }
        }

        $allDuplicateAndOriginalIds = array_values(array_unique(array_merge(
            array_keys($duplicateOfMap),
            array_keys($originalHasDuplicates)
        )));

        return [
            'duplicateOfMap' => $duplicateOfMap,
            'originalHasDuplicates' => $originalHasDuplicates,
            'allDuplicateAndOriginalIds' => $allDuplicateAndOriginalIds,
            'duplicatesCount' => count($allDuplicateAndOriginalIds),
        ];
    }

    /**
     * Normalised match keys for one lead row (model or plain object).
     */
    private function metaFor(object $l): array
    {
        $id = (int) $l->id;

        return [
            'id' => $id,
            'lead_number' => ($l->lead_number ?? null) ?: ('LD-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT)),
            'gstin' => !empty($l->gstin) ? strtoupper(trim($l->gstin)) : null,
            'contact_email' => !empty($l->email) ? strtolower(trim($l->email)) : null,
            'company_email' => !empty($l->company_email) ? strtolower(trim($l->company_email)) : null,
            'contact_phone' => !empty($l->phone) ? preg_replace('/[^0-9]/', '', $l->phone) : null,
            'company_phone' => !empty($l->company_phone) ? preg_replace('/[^0-9]/', '', $l->company_phone) : null,
        ];
    }

    /**
     * Detect and annotate duplicate leads where GSTIN, Email or Phone match.
     * Returns total duplicates count for display.
     */
    public function annotateDuplicates($leads, int $tenantId): int
    {
        $graph = $this->getDuplicateGraph($tenantId);
        $duplicateOfMap = $graph['duplicateOfMap'];
        $originalHasDuplicates = $graph['originalHasDuplicates'];

        if (empty($leads)) {
            return $graph['duplicatesCount'];
        }

        // Meta for this page's leads, plus the originals they point at.
        $leadMetaMap = [];
        $originalIds = [];
        foreach ($leads as $pageLead) {
            $leadMetaMap[(int) $pageLead->id] = $this->metaFor($pageLead);
            if (isset($duplicateOfMap[$pageLead->id])) {
                $originalIds[] = $duplicateOfMap[$pageLead->id];
            }
        }
        $missingIds = array_values(array_diff(array_unique($originalIds), array_keys($leadMetaMap)));
        if ($missingIds !== []) {
            Lead::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $missingIds)
                ->get(['id', 'lead_number', 'gstin', 'email', 'company_email', 'phone', 'company_phone'])
                ->each(function ($original) use (&$leadMetaMap) {
                    $leadMetaMap[(int) $original->id] = $this->metaFor($original);
                });
        }

        foreach ($leads as $lead) {
            $id = $lead->id;

            if (isset($duplicateOfMap[$id])) {
                $origId = $duplicateOfMap[$id];
                $origMeta = $leadMetaMap[$origId] ?? null;
                $origNumber = $origMeta['lead_number'] ?? ('LD-' . str_pad((string)$origId, 4, '0', STR_PAD_LEFT));

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
                    if (!empty($meta['gstin']) && !empty($origMeta['gstin']) && $meta['gstin'] === $origMeta['gstin']) {
                        $reasons[] = 'GSTIN';
                    }

                    if (!empty($meta['contact_email'])) {
                        $e = $meta['contact_email'];
                        if ($e === $origMeta['contact_email'] || $e === $origMeta['company_email']) {
                            $reasons[] = 'Contact Email';
                        }
                    }

                    if (!empty($meta['company_email'])) {
                        $e = $meta['company_email'];
                        if ($e === $origMeta['company_email'] || $e === $origMeta['contact_email']) {
                            $reasons[] = 'Company Email';
                        }
                    }

                    if (!empty($meta['contact_phone']) && strlen($meta['contact_phone']) >= 5) {
                        $p = $meta['contact_phone'];
                        $op1 = $origMeta['contact_phone'];
                        $op2 = $origMeta['company_phone'];
                        if (($op1 && ($p === $op1 || str_contains($p, $op1) || str_contains($op1, $p))) ||
                            ($op2 && ($p === $op2 || str_contains($p, $op2) || str_contains($op2, $p)))) {
                            $reasons[] = 'Contact Phone';
                        }
                    }

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

        return $graph['duplicatesCount'];
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
