<?php

namespace App\Domains\Visitor\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use App\Domains\Visitor\Services\VisitorService;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function __construct(
        protected readonly VisitorService $visitorService
    ) {}

    /**
     * Visitor Dashboard & Logs Overview
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Visitor::class);
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        $stats = $this->visitorService->getDashboardStats($tenantId, $branchId);

        $search = $request->input('search');
        $status = $request->input('status');
        $visitorType = $request->input('visitor_type');
        $purpose = $request->input('purpose');
        $hostUserId = $request->input('host_user_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $passesQuery = VisitorPass::where('tenant_id', $tenantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($visitorType, fn($q) => $q->where('visitor_type', $visitorType))
            ->when($purpose, fn($q) => $q->where('purpose', $purpose))
            ->when($hostUserId, fn($q) => $q->where('host_user_id', $hostUserId))
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo));

        // Handle Status Filtering (including Overstayed, Active inside, Denied)
        if ($status === 'Checked-In') {
            $passesQuery->whereIn('status', ['Checked-In', 'Meeting in Progress'])->whereNull('check_out_at');
        } elseif ($status === 'Overstayed') {
            $passesQuery->whereIn('status', ['Checked-In', 'Meeting in Progress', 'Overstayed'])
                ->whereNull('check_out_at')
                ->whereRaw("TIMESTAMPDIFF(MINUTE, check_in_at, NOW()) > COALESCE(expected_duration_minutes, 60)");
        } elseif ($status === 'Denied' || $status === 'Rejected') {
            $passesQuery->whereIn('status', ['Denied', 'Rejected']);
        } elseif (!empty($status) && $status !== 'all') {
            $passesQuery->where('status', $status);
        }

        // Search Filter
        if ($search) {
            $passesQuery->where(function ($sub) use ($search) {
                $sub->where('pass_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('gate_number', 'like', "%{$search}%")
                    ->orWhere('vehicle_number', 'like', "%{$search}%")
                    ->orWhere('badge_number', 'like', "%{$search}%")
                    ->orWhereHas('visitor', function ($vQ) use ($search) {
                        $vQ->where('full_name', 'like', "%{$search}%")
                           ->orWhere('phone', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%")
                           ->orWhere('company_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('host', function ($hQ) use ($search) {
                        $hQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $passes = $passesQuery->with(['visitor', 'host', 'belongings', 'linkedLead'])->latest()->paginate(15)->withQueryString();
        $hosts = \App\Models\User::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);
        $products = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->with(['primaryImage', 'images'])->orderBy('name')->get();

        return view('modules.visitor.index', compact(
            'stats',
            'passes',
            'hosts',
            'products',
            'search',
            'status',
            'visitorType',
            'purpose',
            'hostUserId',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Store a new visitor & generate pass
     */
    public function store(Request $request)
    {
        $this->authorize('create', Visitor::class);
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        $validated = $request->validate([
            'full_name'                 => 'required|string|max:255',
            'phone'                     => 'required|string|max:30',
            'email'                     => 'nullable|email|max:255',
            'company_name'              => 'nullable|string|max:255',
            'designation'               => 'nullable|string|max:100',
            'visitor_type'              => 'nullable|string|in:Client,Vendor,Candidate,Service,Guest',
            'id_proof_type'             => 'nullable|string|max:50',
            'id_proof_number'           => 'nullable|string|max:100',
            'id_verification_status'    => 'nullable|string|in:Pending,Verified,Exempted,Failed',
            'photo_url'                 => 'nullable|string',
            'host_user_id'              => 'nullable|exists:users,id',
            'department_id'             => 'nullable|integer',
            'purpose'                   => 'required|string|max:100',
            'entry_type'                => 'nullable|string|max:50',
            'gate_number'               => 'nullable|string|max:50',
            'fee_amount'                => 'nullable|numeric|min:0',
            'expected_duration_minutes' => 'nullable|integer|min:5|max:1440',
            'accompanying_count'        => 'nullable|integer|min:0|max:50',
            'accompanying_names'        => 'nullable|string|max:1000',
            'vehicle_type'              => 'nullable|string|max:50',
            'vehicle_number'            => 'nullable|string|max:50',
            'parking_slot'              => 'nullable|string|max:50',
            'badge_number'              => 'nullable|string|max:50',
            'badge_printed'             => 'nullable|boolean',
            'nda_safety_acknowledged'   => 'nullable|boolean',
            'restricted_area_access'    => 'nullable|boolean',
            'gate_pass_reference'       => 'nullable|string|max:100',
            'item_type'                 => 'nullable|string|max:100',
            'serial_number'             => 'nullable|string|max:100',
            'is_returnable'             => 'nullable|boolean',
            'belonging_gate_pass'       => 'nullable|string|max:100',
            'expected_arrival_at'       => 'nullable|date',
            'check_in_now'              => 'nullable|boolean',
            'status'                    => 'nullable|string|in:Expected,Arrived,Waiting Approval,Approved,Checked-In',
            'product_ids'               => 'nullable|array',
            'product_ids.*'             => 'integer',
            'product_items'             => 'nullable|array',
            'product_items.*.product_id' => 'nullable|integer',
            'product_items.*.quantity'  => 'nullable|numeric|min:0.01',
            'inquiry_notes'             => 'nullable|string|max:1000',
            'create_crm_lead'           => 'nullable|boolean',
            'notes'                     => 'nullable|string|max:1000',
        ]);

        if (!empty($validated['item_type'])) {
            $validated['belongings'] = [
                [
                    'item_type'        => $validated['item_type'],
                    'serial_number'    => $validated['serial_number'] ?? null,
                    'is_returnable'    => isset($validated['is_returnable']) ? (bool)$validated['is_returnable'] : true,
                    'gate_pass_number' => $validated['belonging_gate_pass'] ?? null,
                ]
            ];
        }

        $pass = $this->visitorService->createPass($validated, $tenantId, $companyId, $branchId);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('visitor.pass_created_success'),
                'data'    => $pass,
            ]);
        }

        return redirect()->route('visitor.index')->with('success', __('visitor.pass_created_success'));
    }

    /**
     * Check in visitor pass
     */
    public function checkIn(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        if ($pass->status === 'Waiting Approval') {
            $msg = __('visitor.approval_required_checkin_blocked', [], null) ?? 'Host approval is pending. You cannot check-in until the host approves this pass.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('error', $msg);
        }

        if (in_array($pass->status, ['Rejected', 'Denied'])) {
            $msg = 'This visitor pass was denied/rejected and cannot be checked-in.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('error', $msg);
        }

        $badgeNumber = $request->input('badge_number', $pass->badge_number);
        $idVerificationStatus = $request->input('id_verification_status', 'Verified');

        $pass->update([
            'status'                 => 'Checked-In',
            'check_in_at'            => now(),
            'badge_number'           => $badgeNumber,
            'id_verification_status' => $idVerificationStatus,
            'id_verified_by'         => auth()->id(),
        ]);

        // Notify Host that visitor checked in and is arriving
        $this->visitorService->notifyHost($pass, 'arrived');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => __('visitor.checked_in_success')]);
        }

        return redirect()->back()->with('success', __('visitor.checked_in_success'));
    }

    /**
     * Check out visitor pass
     */
    public function checkOut(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->with('belongings')->findOrFail($id);

        $badgeReturned = $request->boolean('badge_returned', true);
        $gatePassRef = $request->input('gate_pass_reference', $pass->gate_pass_reference);

        $pass->update([
            'status'              => 'Checked-Out',
            'check_out_at'        => now(),
            'badge_returned'      => $badgeReturned,
            'badge_returned_at'   => $badgeReturned ? now() : null,
            'gate_pass_reference' => $gatePassRef,
        ]);

        // Verify belongings on exit
        foreach ($pass->belongings as $belonging) {
            $belonging->update([
                'is_verified_on_exit' => true,
                'exit_verified_by'    => auth()->id(),
                'exit_verified_at'    => now(),
            ]);
        }

        // Notify host that visitor checked out
        $this->visitorService->notifyHost($pass, 'checked_out');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => __('visitor.checked_out_success')]);
        }

        return redirect()->back()->with('success', __('visitor.checked_out_success'));
    }

    /**
     * Mark visitor as physically arrived at gate
     */
    public function markArrived(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        $newStatus = $pass->host_user_id ? 'Waiting Approval' : 'Arrived';
        $pass->update([
            'status'     => $newStatus,
            'arrived_at' => now(),
        ]);

        if ($pass->host_user_id) {
            $this->visitorService->notifyHost($pass, 'approval_request');
        }

        $msg = "Visitor arrival logged successfully. Host notified for entry clearance.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Mark meeting in progress
     */
    public function startMeeting(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        $pass->update([
            'status'             => 'Meeting in Progress',
            'meeting_started_at' => now(),
        ]);

        $this->visitorService->notifyHost($pass, 'meeting_started');

        $msg = "Meeting status set to In Progress.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Re-notify or ping host manually
     */
    public function notifyHostManual(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->with('host')->findOrFail($id);

        if (!$pass->host_user_id) {
            return response()->json(['success' => false, 'message' => 'No host assigned to this pass.'], 422);
        }

        $this->visitorService->notifyHost($pass, 'host_ping');

        $msg = "Reminder notification sent to Host ({$pass->host?->name}).";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Deny Entry at Gate Desk
     */
    public function denyEntry(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->with('visitor')->findOrFail($id);

        $validated = $request->validate([
            'denied_reason'     => 'required|string|max:500',
            'blacklist_visitor' => 'nullable|boolean',
        ]);

        $pass->update([
            'status'        => 'Denied',
            'denied_reason' => $validated['denied_reason'],
        ]);

        if (!empty($validated['blacklist_visitor']) && $pass->visitor) {
            $pass->visitor->update([
                'is_blacklisted'   => true,
                'blacklist_reason' => $validated['denied_reason'],
                'status'           => 'Blocked',
            ]);
        }

        $msg = "Visitor entry has been denied.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Toggle Blacklist status for a visitor
     */
    public function toggleBlacklist(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $visitor = Visitor::where('tenant_id', $tenantId)->findOrFail($id);

        $isCurrentlyBlacklisted = (bool)$visitor->is_blacklisted;
        $reason = $request->input('reason', 'Security / Front Desk Action');

        $visitor->update([
            'is_blacklisted'   => !$isCurrentlyBlacklisted,
            'blacklist_reason' => !$isCurrentlyBlacklisted ? $reason : null,
            'status'           => !$isCurrentlyBlacklisted ? 'Blocked' : 'Active',
        ]);

        $msg = !$isCurrentlyBlacklisted 
            ? "Visitor '{$visitor->full_name}' has been BLACKLISTED. Future entries will be blocked."
            : "Visitor '{$visitor->full_name}' has been UNBLOCKED and removed from Blacklist.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg, 'is_blacklisted' => !$isCurrentlyBlacklisted]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Extend Visit Duration
     */
    public function extendVisit(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'extend_minutes' => 'required|integer|min:15|max:480',
            'extend_notes'   => 'nullable|string|max:500',
        ]);

        $newDuration = ($pass->expected_duration_minutes ?: 60) + (int)$validated['extend_minutes'];
        $notes = $pass->notes;
        if (!empty($validated['extend_notes'])) {
            $notes = ($notes ? $notes . " | " : "") . "Extended by {$validated['extend_minutes']} mins: " . $validated['extend_notes'];
        }

        $pass->update([
            'expected_duration_minutes' => $newDuration,
            'notes'                     => $notes,
        ]);

        $this->visitorService->notifyHost($pass, 'visit_extended');

        $msg = "Visit extended by {$validated['extend_minutes']} minutes.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg, 'new_duration' => $newDuration]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Report Security Incident
     */
    public function reportIncident(Request $request, int $id)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $pass = VisitorPass::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'incident_details' => 'required|string|max:1000',
        ]);

        $pass->update([
            'incident_reported' => true,
            'incident_details'  => $validated['incident_details'],
        ]);

        $this->visitorService->notifyHost($pass, 'incident_reported');

        $msg = "Security incident recorded successfully.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Look up existing visitor details by phone, email, or company for fast auto-fill
     */
    public function lookup(Request $request)
    {
        [$tenantId] = $this->visitorService->resolveTenantContext();
        $phone = trim($request->input('phone', ''));
        $email = trim($request->input('email', ''));
        $company = trim($request->input('company', ''));

        if (empty($phone) && empty($email) && empty($company)) {
            return response()->json(['found' => false]);
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        $visitor = Visitor::where('tenant_id', $tenantId)
            ->where(function ($q) use ($phone, $cleanPhone, $email, $company) {
                if (!empty($phone) && strlen($phone) >= 4) {
                    $q->where('phone', $phone)
                      ->orWhere('phone', 'like', "{$phone}%");
                    if (!empty($cleanPhone)) {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["{$cleanPhone}%"]);
                    }
                }
                if (!empty($email) && strlen($email) >= 4) {
                    $q->orWhere('email', $email);
                }
                if (!empty($company) && strlen($company) >= 4) {
                    $q->orWhere('company_name', 'like', "%{$company}%");
                }
            })
            ->withCount('passes')
            ->first();

        if (!$visitor) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'   => true,
            'visitor' => [
                'id'              => $visitor->id,
                'full_name'       => $visitor->full_name,
                'phone'           => $visitor->phone,
                'email'           => $visitor->email,
                'company_name'    => $visitor->company_name,
                'designation'     => $visitor->designation,
                'visitor_type'    => $visitor->visitor_type ?? 'Client',
                'id_proof_type'   => $visitor->id_proof_type,
                'id_proof_number' => $visitor->id_proof_number,
                'photo_url'       => $visitor->photo_url,
                'total_visits'    => $visitor->passes_count,
                'is_blacklisted'  => (bool)$visitor->is_blacklisted,
                'blacklist_reason'=> $visitor->blacklist_reason,
            ],
        ]);
    }

    /**
     * Export Visitor Passes to Excel / CSV
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Visitor::class);
        [$tenantId] = $this->visitorService->resolveTenantContext();

        $filters = $request->only([
            'search', 'status', 'visitor_type', 'purpose', 'host_user_id', 'date_from', 'date_to', 'columns'
        ]);

        $fileName = 'visitor_passes_' . now()->format('Ymd_His') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\VisitorPassExport($tenantId, $filters),
            $fileName
        );
    }

    /**
     * Download Sample Visitor Pass Import Template
     */
    public function downloadSample()
    {
        $this->authorize('viewAny', Visitor::class);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\VisitorPassTemplateExport(),
            'visitor_pass_import_sample.xlsx'
        );
    }

    /**
     * Parse uploaded spreadsheet file for smart column mapping
     */
    public function parseImportFile(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Visitor::class);
        [$tenantId] = $this->visitorService->resolveTenantContext();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ]);

        try {
            $service = app(\App\Domains\Visitor\Services\VisitorImportMapperService::class);
            $result = $service->parseFile($request->file('file'), $tenantId);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Process mapped visitor pass rows (Dry run or Live commit)
     */
    public function processMappedImport(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Visitor::class);
        [$tenantId, $companyId, $branchId] = $this->visitorService->resolveTenantContext();

        $request->validate([
            'file_token' => 'required|string',
            'mapping'    => 'required|array',
            'options'    => 'nullable|array',
        ]);

        $options = (array)$request->input('options', []);
        $isDryRun = !empty($options['is_dry_run']);

        try {
            $service = app(\App\Domains\Visitor\Services\VisitorImportMapperService::class);
            $result = $service->processImport(
                fileToken: (string)$request->input('file_token'),
                mapping: (array)$request->input('mapping', []),
                options: $options,
                tenantId: $tenantId,
                companyId: $companyId,
                branchId: $branchId,
                isDryRun: $isDryRun
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Import Visitor Passes from Excel / CSV (Direct fallback)
     */
    public function import(Request $request)
    {
        $this->authorize('create', Visitor::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        try {
            $import = new \App\Imports\VisitorPassImport($this->visitorService);
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

            $message = "Import completed! Successfully created {$import->successCount} visitor passes.";
            if ($import->failedCount > 0) {
                $message .= " ({$import->failedCount} rows skipped due to invalid data).";
            }

            return redirect()->route('visitor.index')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('visitor.index')->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
        }
    }
}
