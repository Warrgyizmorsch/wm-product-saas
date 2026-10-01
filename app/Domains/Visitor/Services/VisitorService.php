<?php

namespace App\Domains\Visitor\Services;

use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VisitorService
{
    /**
     * Resolve tenant context
     */
    public function resolveTenantContext(): array
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId = $user?->branch_id ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * Generate unique visitor pass number
     */
    public function generatePassNumber(int $tenantId): string
    {
        $date = Carbon::now()->format('Ymd');
        $random = strtoupper(Str::random(4));
        return "VP-{$date}-{$random}";
    }

    /**
     * Get live headcount of visitors currently inside premises
     */
    public function getActiveVisitorsCount(int $tenantId, ?int $branchId = null): int
    {
        $query = VisitorPass::where('tenant_id', $tenantId)
            ->where('status', 'Checked-In')
            ->whereNull('check_out_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->count();
    }

    /**
     * Get Dashboard Stats for Visitor Overview
     */
    public function getDashboardStats(int $tenantId, ?int $branchId = null): array
    {
        $baseQuery = VisitorPass::where('tenant_id', $tenantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        $totalVisitors = Visitor::where('tenant_id', $tenantId)->count();
        $totalPasses = (clone $baseQuery)->count();
        $activeInside = (clone $baseQuery)->where('status', 'Checked-In')->whereNull('check_out_at')->count();
        $todayExpected = (clone $baseQuery)->where('status', 'Expected')->count();
        $todayCheckedIn = (clone $baseQuery)->where('status', 'Checked-In')->whereDate('check_in_at', today())->count();
        $totalCheckedOut = (clone $baseQuery)->where('status', 'Checked-Out')->count();
        $todayCheckedOut = (clone $baseQuery)->where('status', 'Checked-Out')->whereDate('check_out_at', today())->count();
        $waitingApproval = (clone $baseQuery)->where('status', 'Waiting Approval')->count();
        $totalFees = (clone $baseQuery)->whereDate('created_at', today())->sum('fee_amount');

        return [
            'total_visitors'    => $totalVisitors,
            'total_passes'      => $totalPasses,
            'active_inside'     => $activeInside,
            'today_expected'    => $todayExpected,
            'today_checked_in'  => $todayCheckedIn,
            'total_checked_out' => $totalCheckedOut,
            'today_checked_out' => $todayCheckedOut,
            'waiting_approval'  => $waitingApproval,
            'today_fees'        => (float)$totalFees,
        ];
    }

    /**
     * Create or find visitor and generate visitor pass
     */
    public function createPass(array $data, int $tenantId, int $companyId, ?int $branchId = null): VisitorPass
    {
        // Find existing visitor by phone or create new
        $visitor = Visitor::firstOrNew([
            'tenant_id' => $tenantId,
            'phone'     => trim($data['phone']),
        ]);

        $visitor->company_id = $companyId;
        $visitor->branch_id = $branchId;
        $visitor->full_name = trim($data['full_name']);
        if (!empty($data['email'])) $visitor->email = trim($data['email']);
        if (!empty($data['company_name'])) $visitor->company_name = trim($data['company_name']);
        if (!empty($data['designation'])) $visitor->designation = trim($data['designation']);
        if (!empty($data['id_proof_type'])) $visitor->id_proof_type = $data['id_proof_type'];
        if (!empty($data['id_proof_number'])) $visitor->id_proof_number = trim($data['id_proof_number']);
        if (!empty($data['photo_url'])) $visitor->photo_url = $data['photo_url'];
        $visitor->save();

        $passNumber = $this->generatePassNumber($tenantId);
        $qrToken = 'QR-' . Str::uuid()->toString();
        $status = !empty($data['check_in_now']) ? 'Checked-In' : ($data['status'] ?? 'Expected');

        $pass = VisitorPass::create([
            'tenant_id'           => $tenantId,
            'company_id'          => $companyId,
            'branch_id'           => $branchId,
            'pass_number'         => $passNumber,
            'visitor_id'          => $visitor->id,
            'host_user_id'        => !empty($data['host_user_id']) ? (int)$data['host_user_id'] : null,
            'department_id'       => !empty($data['department_id']) ? (int)$data['department_id'] : null,
            'purpose'             => $data['purpose'] ?? 'Meeting',
            'entry_type'          => $data['entry_type'] ?? 'Walk-in',
            'status'              => $status,
            'expected_arrival_at' => !empty($data['expected_arrival_at']) ? Carbon::parse($data['expected_arrival_at']) : now(),
            'check_in_at'         => !empty($data['check_in_now']) ? now() : null,
            'qr_token'            => $qrToken,
            'gate_number'         => $data['gate_number'] ?? 'Gate 1',
            'fee_amount'          => (isset($data['fee_amount']) && is_numeric($data['fee_amount'])) ? (float)$data['fee_amount'] : 0.00,
            'notes'               => $data['notes'] ?? null,
        ]);

        // Save Belongings/Laptops if provided
        if (!empty($data['belongings']) && is_array($data['belongings'])) {
            foreach ($data['belongings'] as $item) {
                if (!empty($item['item_type'])) {
                    $pass->belongings()->create([
                        'tenant_id'     => $tenantId,
                        'item_type'     => $item['item_type'],
                        'brand_model'   => $item['brand_model'] ?? null,
                        'serial_number' => $item['serial_number'] ?? null,
                        'quantity'      => (int)($item['quantity'] ?? 1),
                        'remarks'       => $item['remarks'] ?? null,
                    ]);
                }
            }
        }

        return $pass->load(['visitor', 'host', 'belongings']);
    }
}
