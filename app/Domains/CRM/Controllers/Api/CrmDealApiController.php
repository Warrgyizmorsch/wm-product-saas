<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\DealStatus;
use App\Domains\CRM\Services\DealHealthService;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CrmDealApiController extends Controller
{
    public function __construct(
        private readonly DealHealthService $healthService
    ) {}

    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/crm/deals/meta
     * Master dropdown metadata for Deals UI (Stages, Sources, Accounts, Contacts, Owners, Products).
     */
    public function meta(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmDeal::class);
        [$tenantId] = $this->resolveTenantContext();

        $statuses = DealStatus::getOrderedStatuses($tenantId)->map(fn($s) => [
            'id'          => $s->id,
            'name'        => $s->name,
            'color'       => $s->color ?? 'bg-primary',
            'probability' => $s->probability ?? 50,
            'sort_order'  => (int)$s->sort_order,
            'is_protected'=> (bool)$s->is_protected,
        ]);

        $accounts = CrmAccount::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'account_number', 'email', 'phone', 'gstin']);

        $contacts = CrmContact::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'crm_account_id', 'name', 'designation', 'email', 'phone', 'is_primary']);

        $users = User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar']);

        $products = Product::where('tenant_id', $tenantId)
            ->sellable()
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'selling_price', 'cost_price']);

        return response()->json([
            'success' => true,
            'data'    => [
                'stages'         => $statuses,
                'sources'        => ['Website', 'Referral', 'Cold Call', 'WhatsApp', 'Exhibition', 'Partner', 'Inbound', 'Other'],
                'close_reasons_won'  => ['Best Pricing', 'Superior Product Quality', 'Fast Delivery', 'Existing Relationship', 'Other'],
                'close_reasons_lost' => ['Competitor Won', 'High Price', 'Budget Constraints', 'Feature Gap', 'Client Postponed', 'Other'],
                'accounts'       => $accounts,
                'contacts'       => $contacts,
                'users'          => $users,
                'products'       => $products,
            ],
        ]);
    }

    /**
     * GET /api/crm/deals
     * List deals with filtering, search, pagination, and pipeline stage metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmDeal::class);
        [$tenantId] = $this->resolveTenantContext();

        $query = CrmDeal::query()
            ->where('tenant_id', $tenantId)
            ->with(['account', 'contact', 'owner', 'quotations', 'lead']);

        // 1. Search keyword
        if ($request->filled('search')) {
            $search = '%' . trim((string)$request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('deal_number', 'like', $search)
                  ->orWhereHas('account', fn($aq) => $aq->where('name', 'like', $search))
                  ->orWhereHas('contact', fn($cq) => $cq->where('name', 'like', $search));
            });
        }

        // 2. Stage Filter
        if ($request->filled('stage')) {
            $stage = trim((string)$request->input('stage'));
            if (in_array(strtolower($stage), ['won', 'closed won'], true)) {
                $query->whereIn('stage', ['Won', 'Closed Won']);
            } elseif (in_array(strtolower($stage), ['lost', 'closed lost'], true)) {
                $query->whereIn('stage', ['Lost', 'Closed Lost']);
            } else {
                $query->where('stage', $stage);
            }
        }

        // 3. Account / Contact / Owner Filters
        if ($request->filled('crm_account_id')) {
            $query->where('crm_account_id', (int)$request->input('crm_account_id'));
        }
        if ($request->filled('crm_contact_id')) {
            $query->where('crm_contact_id', (int)$request->input('crm_contact_id'));
        }
        if ($request->filled('owner_id')) {
            $query->where('owner_id', (int)$request->input('owner_id'));
        }
        if ($request->filled('lead_source')) {
            $query->where('lead_source', trim((string)$request->input('lead_source')));
        }

        // 4. Date Range
        if ($request->filled('from_date')) {
            try { $query->whereDate('created_at', '>=', Carbon::parse($request->input('from_date'))); } catch (\Throwable $e) {}
        }
        if ($request->filled('to_date')) {
            try { $query->whereDate('created_at', '<=', Carbon::parse($request->input('to_date'))); } catch (\Throwable $e) {}
        }

        // 5. Sorting
        $sortBy = in_array($request->input('sort_by'), ['created_at', 'id', 'deal_number', 'title', 'estimated_value', 'closing_date', 'probability', 'stage'], true)
            ? $request->input('sort_by')
            : 'id';
        $sortOrder = strtolower((string)$request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // 6. Pagination
        $perPage = max(1, min((int)($request->input('per_page') ?: $request->input('limit') ?: 15), 100));
        $paginated = $query->paginate($perPage);

        // 7. Stage Counts Summary
        $stageCounts = CrmDeal::where('tenant_id', $tenantId)
            ->select('stage', DB::raw('count(*) as count'), DB::raw('sum(estimated_value) as total_value'))
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $deals = collect($paginated->items())->map(function (CrmDeal $deal) {
            return [
                'id'              => $deal->id,
                'deal_number'     => $deal->deal_number,
                'title'           => $deal->title,
                'estimated_value' => (float)($deal->estimated_value ?? 0),
                'actual_value'    => (float)$deal->actual_value,
                'stage'           => $deal->stage,
                'probability'     => (int)($deal->probability ?? 50),
                'closing_date'    => $deal->closing_date?->format('Y-m-d'),
                'lead_source'     => $deal->lead_source,
                'account'         => $deal->account ? [
                    'id'   => $deal->account->id,
                    'name' => $deal->account->name,
                ] : null,
                'contact'         => $deal->contact ? [
                    'id'    => $deal->contact->id,
                    'name'  => $deal->contact->name,
                    'email' => $deal->contact->email,
                    'phone' => $deal->contact->phone,
                ] : null,
                'owner'           => $deal->owner ? [
                    'id'     => $deal->owner->id,
                    'name'   => $deal->owner->name,
                    'email'  => $deal->owner->email,
                    'avatar' => $deal->owner->avatar,
                ] : null,
                'lead_id'         => $deal->lead?->id,
                'quotations_count'=> $deal->quotations->count(),
                'health'          => [
                    'score'            => $deal->health_score,
                    'risk_level'       => $deal->risk_level,
                    'sentiment'        => $deal->sentiment_score,
                    'next_best_action' => $deal->next_best_action,
                ],
                'created_at'      => $deal->created_at?->toIso8601String(),
                'updated_at'      => $deal->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $deals,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'total_pages'  => $paginated->lastPage(),
            ],
            'summary' => [
                'stage_metrics' => $stageCounts,
                'total_deals'   => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/crm/deals/{id}
     * Single deal 360-degree view (Account, Contact, Quotations, Followups, Products, Health).
     */
    public function show(CrmDeal $deal): JsonResponse
    {
        $this->authorize('view', $deal);

        $deal->load(['account', 'contact', 'owner', 'quotations.items', 'followups.taggedUser', 'lead']);

        $resolvedProducts = [];
        if (!empty($deal->product_ids) && is_array($deal->product_ids)) {
            $resolvedProducts = Product::whereIn('id', $deal->product_ids)->get(['id', 'name', 'sku', 'selling_price', 'cost_price']);
        }

        $quotations = $deal->quotations->map(fn($q) => [
            'id'               => $q->id,
            'quotation_number' => $q->quotation_number,
            'quotation_date'   => $q->quotation_date?->format('Y-m-d'),
            'total_amount'     => (float)($q->total_amount ?? $q->grand_total ?? 0),
            'status'           => $q->status,
            'items_count'      => $q->items->count(),
        ]);

        $followups = $deal->followups->map(fn($f) => [
            'id'               => $f->id,
            'type'             => $f->type ?? 'Call',
            'title'            => $f->title,
            'status'           => $f->status ?? 'Pending',
            'followup_date'    => $f->followup_date?->toIso8601String(),
            'notes'            => $f->notes,
            'is_google_meet'   => (bool)$f->is_google_meet,
            'google_meet_link' => $f->google_meet_link,
            'tagged_user'      => $f->taggedUser ? ['id' => $f->taggedUser->id, 'name' => $f->taggedUser->name] : null,
            'created_at'       => $f->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $deal->id,
                'deal_number'     => $deal->deal_number,
                'title'           => $deal->title,
                'estimated_value' => (float)($deal->estimated_value ?? 0),
                'actual_value'    => (float)$deal->actual_value,
                'stage'           => $deal->stage,
                'close_reason'    => $deal->close_reason,
                'closing_date'    => $deal->closing_date?->format('Y-m-d'),
                'lead_source'     => $deal->lead_source,
                'probability'     => (int)($deal->probability ?? 50),
                'notes'           => $deal->notes,

                'account' => $deal->account ? [
                    'id'             => $deal->account->id,
                    'name'           => $deal->account->name,
                    'account_number' => $deal->account->account_number,
                    'email'          => $deal->account->email,
                    'phone'          => $deal->account->phone,
                    'gstin'          => $deal->account->gstin,
                ] : null,

                'contact' => $deal->contact ? [
                    'id'          => $deal->contact->id,
                    'name'        => $deal->contact->name,
                    'designation' => $deal->contact->designation,
                    'email'       => $deal->contact->email,
                    'phone'       => $deal->contact->phone,
                ] : null,

                'owner' => $deal->owner ? [
                    'id'     => $deal->owner->id,
                    'name'   => $deal->owner->name,
                    'email'  => $deal->owner->email,
                    'avatar' => $deal->owner->avatar,
                ] : null,

                'lead_id'         => $deal->lead?->id,
                'lead_number'     => $deal->lead?->lead_number,

                'products' => [
                    'resolved_items' => $resolvedProducts,
                    'product_ids'    => $deal->product_ids ?: [],
                    'product_items'  => $deal->product_items ?: [],
                ],

                'health' => [
                    'score'            => $deal->health_score,
                    'risk_level'       => $deal->risk_level,
                    'sentiment'        => $deal->sentiment_score,
                    'next_best_action' => $deal->next_best_action,
                    'health_synced_at' => $deal->health_synced_at?->toIso8601String(),
                ],

                'quotations' => $quotations,
                'followups'  => $followups,
                'created_at' => $deal->created_at?->toIso8601String(),
                'updated_at' => $deal->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/crm/deals
     * Create a new deal.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CrmDeal::class);
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'title'           => 'required|string|max:255',
            'crm_account_id'  => 'nullable|integer|exists:crm_accounts,id',
            'crm_contact_id'  => 'nullable|integer|exists:crm_contacts,id',
            'estimated_value' => 'nullable|numeric|min:0',
            'stage'           => 'nullable|string|max:100',
            'closing_date'    => 'nullable|date',
            'lead_source'     => 'nullable|string|max:255',
            'probability'     => 'nullable|integer|min:0|max:100',
            'owner_id'        => 'nullable|integer|exists:users,id',
            'notes'           => 'nullable|string',
            'product_ids'     => 'nullable|array',
            'product_ids.*'   => 'integer',
            'product_items'   => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $deal = CrmDeal::create([
            'tenant_id'       => $tenantId,
            'company_id'      => $companyId,
            'branch_id'       => $branchId,
            'title'           => $validated['title'],
            'crm_account_id'  => $validated['crm_account_id'] ?? null,
            'crm_contact_id'  => $validated['crm_contact_id'] ?? null,
            'estimated_value' => $validated['estimated_value'] ?? 0.00,
            'stage'           => $validated['stage'] ?? 'Qualification',
            'closing_date'    => !empty($validated['closing_date']) ? Carbon::parse($validated['closing_date']) : null,
            'lead_source'     => $validated['lead_source'] ?? null,
            'probability'     => $validated['probability'] ?? 50,
            'owner_id'        => $validated['owner_id'] ?? (auth()->id() ?: 1),
            'notes'           => $validated['notes'] ?? null,
            'product_ids'     => $validated['product_ids'] ?? null,
            'product_items'   => $validated['product_items'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deal created successfully.',
            'data'    => [
                'id'              => $deal->id,
                'deal_number'     => $deal->deal_number,
                'title'           => $deal->title,
                'stage'           => $deal->stage,
                'estimated_value' => (float)$deal->estimated_value,
                'created_at'      => $deal->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT / PATCH /api/crm/deals/{id}
     * Update deal details.
     */
    public function update(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $validator = Validator::make($request->all(), [
            'title'           => 'nullable|string|max:255',
            'crm_account_id'  => 'nullable|integer|exists:crm_accounts,id',
            'crm_contact_id'  => 'nullable|integer|exists:crm_contacts,id',
            'estimated_value' => 'nullable|numeric|min:0',
            'stage'           => 'nullable|string|max:100',
            'closing_date'    => 'nullable|date',
            'lead_source'     => 'nullable|string|max:255',
            'probability'     => 'nullable|integer|min:0|max:100',
            'owner_id'        => 'nullable|integer|exists:users,id',
            'notes'           => 'nullable|string',
            'close_reason'    => 'nullable|string|max:255',
            'product_ids'     => 'nullable|array',
            'product_items'   => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (isset($validated['closing_date']) && !empty($validated['closing_date'])) {
            $validated['closing_date'] = Carbon::parse($validated['closing_date']);
        }

        $deal->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Deal updated successfully.',
            'data'    => [
                'id'              => $deal->id,
                'deal_number'     => $deal->deal_number,
                'title'           => $deal->title,
                'stage'           => $deal->stage,
                'estimated_value' => (float)$deal->estimated_value,
                'updated_at'      => $deal->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/crm/deals/{id}
     * Soft delete deal.
     */
    public function destroy(CrmDeal $deal): JsonResponse
    {
        $this->authorize('delete', $deal);
        $deal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deal deleted successfully.',
        ]);
    }

    /**
     * POST /api/crm/deals/{id}/restore
     * Restore soft-deleted deal.
     */
    public function restore(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $deal = CrmDeal::withTrashed()->where('tenant_id', $tenantId)->find($id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Deal not found',
            ], 404);
        }

        $this->authorize('update', $deal);
        $deal->restore();

        return response()->json([
            'success' => true,
            'message' => 'Deal restored successfully.',
            'data'    => ['id' => $deal->id, 'deal_number' => $deal->deal_number],
        ]);
    }

    /**
     * PATCH /api/crm/deals/{id}/stage
     * Change pipeline stage / drag-drop Kanban move.
     */
    public function updateStage(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $validator = Validator::make($request->all(), [
            'stage'        => 'required|string|max:100',
            'close_reason' => 'nullable|string|max:255',
            'probability'  => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $newStage = $validated['stage'];

        $deal->stage = $newStage;
        if (!empty($validated['close_reason'])) {
            $deal->close_reason = $validated['close_reason'];
        }
        if (isset($validated['probability'])) {
            $deal->probability = (int)$validated['probability'];
        } elseif (in_array(strtolower($newStage), ['won', 'closed won'], true)) {
            $deal->probability = 100;
        } elseif (in_array(strtolower($newStage), ['lost', 'closed lost'], true)) {
            $deal->probability = 0;
        }

        $deal->save();

        return response()->json([
            'success' => true,
            'message' => "Deal stage updated to {$deal->stage}.",
            'data'    => [
                'id'          => $deal->id,
                'stage'       => $deal->stage,
                'probability' => $deal->probability,
                'close_reason'=> $deal->close_reason,
            ],
        ]);
    }

    /**
     * POST /api/crm/deals/{id}/won
     * Mark deal as Won directly.
     */
    public function markWon(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $validated = $request->validate([
            'close_reason' => 'nullable|string|max:255',
        ]);

        $deal->stage = 'Won';
        $deal->probability = 100;
        $deal->close_reason = $validated['close_reason'] ?? 'Lowest Price & Quality';
        $deal->save();

        return response()->json([
            'success' => true,
            'message' => "Deal #{$deal->deal_number} successfully marked as Won!",
            'data'    => [
                'id'    => $deal->id,
                'stage' => 'Won',
            ],
        ]);
    }

    /**
     * POST /api/crm/deals/{id}/lost
     * Mark deal as Lost with loss reason.
     */
    public function markLost(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $validator = Validator::make($request->all(), [
            'close_reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Close reason is required when marking deal as Lost.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $deal->stage = 'Lost';
        $deal->probability = 0;
        $deal->close_reason = $validated['close_reason'];
        $deal->save();

        return response()->json([
            'success' => true,
            'message' => "Deal #{$deal->deal_number} marked as Lost.",
            'data'    => [
                'id'           => $deal->id,
                'stage'        => 'Lost',
                'close_reason' => $deal->close_reason,
            ],
        ]);
    }

    /**
     * GET /api/crm/deals/kanban
     */
    public function kanban(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);
        $allDeals = CrmDeal::with(['account', 'contact', 'owner'])
            ->where('tenant_id', $tenantId)
            ->orderBy('updated_at', 'desc')
            ->get();

        $kanbanData = [];
        foreach ($dealStatuses as $st) {
            $filteredDeals = $allDeals->filter(fn($d) => $d->stage === $st->name)->values();
            $kanbanData[$st->name] = [
                'stage'       => $st->name,
                'color'       => $st->color ?? 'primary',
                'probability' => $st->probability ?? 50,
                'count'       => $filteredDeals->count(),
                'total_value' => (float) $filteredDeals->sum('estimated_value'),
                'deals'       => $filteredDeals,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $kanbanData,
        ]);
    }

    /**
     * PATCH /api/crm/deals/{deal}/requirement
     */
    public function updateRequirement(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $deal->notes = $request->input('notes') ?: ($request->input('requirement') ?: $deal->notes);
        $deal->save();

        return response()->json([
            'success' => true,
            'message' => 'Deal requirement updated.',
            'data'    => $deal,
        ]);
    }

    /**
     * POST /api/crm/deals/{deal}/sync-health
     */
    public function syncHealth(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $eval = $this->healthService->syncDealHealth($deal);

        return response()->json([
            'success' => true,
            'message' => $eval['message'] ?? 'Deal health evaluated successfully.',
            'data'    => $eval,
        ]);
    }

    /**
     * POST /api/crm/deals/{deal}/convert-to-customer
     * Converts a won Deal to a Customer & Account record.
     */
    public function convertToCustomer(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $mode = $request->input('conversion_mode', 'create_new');
        $existingCustomerId = $request->input('existing_customer_id');

        $deal->load(['account', 'contact', 'lead']);
        $account = $deal->account;
        $leadObj = $deal->lead_id ? Lead::find($deal->lead_id) : Lead::where('crm_deal_id', $deal->id)->first();

        if ($mode === 'existing' && $existingCustomerId) {
            $customer = Customer::where('tenant_id', $tenantId)->find($existingCustomerId);
            if (!$customer) {
                return response()->json(['success' => false, 'message' => 'Selected existing customer not found.'], 404);
            }

            $targetAccount = CrmAccount::where('tenant_id', $tenantId)->where('customer_id', $customer->id)->first();
            if (!$targetAccount) {
                $targetAccount = CrmAccount::create([
                    'tenant_id'   => $tenantId,
                    'company_id'  => $companyId,
                    'branch_id'   => $branchId,
                    'customer_id' => $customer->id,
                    'name'        => $customer->name,
                    'email'       => $customer->email,
                    'phone'       => $customer->phone,
                    'gstin'       => $customer->gstin,
                    'status'      => 'active',
                    'owner_id'    => auth()->id() ?: 1,
                ]);
            }

            $deal->update([
                'crm_account_id' => $targetAccount->id,
                'stage'          => 'Won',
                'probability'    => 100,
                'closing_date'   => now(),
            ]);

            if ($leadObj) {
                $leadObj->update([
                    'status'         => 'Won',
                    'crm_account_id' => $targetAccount->id,
                    'is_customer'    => true,
                    'converted_at'   => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Deal #{$deal->deal_number} linked to existing customer {$customer->name} successfully.",
                'data'    => [
                    'deal'     => $deal->fresh(),
                    'customer' => $customer,
                    'account'  => $targetAccount,
                ],
            ]);
        }

        // Mode: create_new
        $compName  = $account?->name ?: ($leadObj?->company_name ?: ($leadObj?->contact_person ?: $deal->title));
        $compEmail = $account?->email ?: ($deal->contact?->email ?: ($leadObj?->company_email ?: $leadObj?->email));
        $compPhone = $account?->phone ?: ($deal->contact?->phone ?: ($leadObj?->company_phone ?: $leadObj?->phone));
        $gstin     = $account?->gstin ?: ($leadObj?->gstin);

        Customer::$skipAccountAutoCreate = true;
        $customer = Customer::create([
            'tenant_id'  => $tenantId,
            'company_id' => $companyId,
            'branch_id'  => $branchId,
            'name'       => $compName,
            'email'      => $compEmail ?: null,
            'phone'      => $compPhone,
            'gstin'      => $gstin,
            'status'     => 'active',
        ]);
        Customer::$skipAccountAutoCreate = false;

        $newAccount = CrmAccount::create([
            'tenant_id'   => $tenantId,
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'customer_id' => $customer->id,
            'name'        => $compName,
            'email'       => $compEmail ?: null,
            'phone'       => $compPhone,
            'gstin'       => $gstin,
            'status'      => 'active',
            'owner_id'    => auth()->id() ?: 1,
        ]);

        $deal->update([
            'crm_account_id' => $newAccount->id,
            'stage'          => 'Won',
            'probability'    => 100,
            'closing_date'   => now(),
        ]);

        if ($leadObj) {
            $leadObj->update([
                'status'         => 'Won',
                'crm_account_id' => $newAccount->id,
                'is_customer'    => true,
                'converted_at'   => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Deal #{$deal->deal_number} converted to new Customer & Account successfully.",
            'data'    => [
                'deal'     => $deal->fresh(),
                'customer' => $customer,
                'account'  => $newAccount,
            ],
        ], 201);
    }

    /**
     * POST /api/crm/deals/{deal}/draft-reply
     * AI Multi-Tone draft reply generator.
     */
    public function generateDraftReply(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('view', $deal);

        $tone = $request->input('tone', 'professional');
        $result = $this->healthService->generateDraftReply($deal, $tone);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * PATCH /api/crm/deals/{deal}/owner
     */
    public function updateOwner(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $validator = Validator::make($request->all(), [
            'deal_owner_id' => 'nullable|integer|exists:users,id',
            'owner_id'      => 'nullable|integer|exists:users,id',
            'note'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ownerId = $request->input('deal_owner_id', $request->input('owner_id'));
        $deal->update(['owner_id' => $ownerId]);

        // Sync linked Lead owner if lead exists
        $linkedLead = Lead::where('crm_deal_id', $deal->id)
            ->orWhere(function ($q) use ($deal) {
                if ($deal->lead_id) {
                    $q->where('id', $deal->lead_id);
                }
            })
            ->first();

        if ($linkedLead) {
            $linkedLead->update(['lead_owner_id' => $ownerId]);
            if ($request->filled('note')) {
                \App\Domains\CRM\Models\LeadHistory::logEvent(
                    $linkedLead,
                    'note',
                    null,
                    $request->input('note'),
                    "Deal Assignment Note: {$request->input('note')}"
                );
            }
        }

        $targetUser = $ownerId ? User::find($ownerId) : null;
        $ownerName = $targetUser ? $targetUser->name : 'Unassigned';

        return response()->json([
            'success'       => true,
            'message'       => "Deal owner successfully updated to {$ownerName}.",
            'deal_id'       => $deal->id,
            'owner_id'      => $ownerId,
            'owner_name'    => $ownerName,
            'data'          => $deal->load('owner'),
        ]);
    }

    /**
     * POST /api/crm/deals/bulk-assign
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmDeal::class);
        [$tenantId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'deal_ids'      => 'required|array|min:1',
            'deal_ids.*'    => 'required|integer|exists:crm_deals,id',
            'deal_owner_id' => 'nullable|integer|exists:users,id',
            'owner_id'      => 'nullable|integer|exists:users,id',
            'note'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ownerId = $request->input('deal_owner_id', $request->input('owner_id'));
        $targetUser = $ownerId ? User::find($ownerId) : null;
        $ownerName = $targetUser ? $targetUser->name : 'Unassigned';
        $note = $request->input('note');

        $deals = CrmDeal::where('tenant_id', $tenantId)
            ->whereIn('id', $request->input('deal_ids'))
            ->get();

        $updatedCount = 0;
        foreach ($deals as $deal) {
            $deal->update(['owner_id' => $ownerId]);

            $linkedLead = Lead::where('crm_deal_id', $deal->id)
                ->orWhere(function ($q) use ($deal) {
                    if ($deal->lead_id) {
                        $q->where('id', $deal->lead_id);
                    }
                })
                ->first();

            if ($linkedLead) {
                $linkedLead->update(['lead_owner_id' => $ownerId]);
                if (!empty($note)) {
                    \App\Domains\CRM\Models\LeadHistory::logEvent(
                        $linkedLead,
                        'note',
                        null,
                        $note,
                        "Bulk Deal Assignment Note: {$note}"
                    );
                }
            }
            $updatedCount++;
        }

        return response()->json([
            'success'       => true,
            'message'       => "Successfully assigned {$updatedCount} deal(s) to {$ownerName}.",
            'updated_count' => $updatedCount,
            'owner_id'      => $ownerId,
            'owner_name'    => $ownerName,
        ]);
    }

    /**
     * GET /api/crm/deals/export
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', CrmDeal::class);
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\DealExport($tenantId, $request->all()),
            'deals_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * GET /api/crm/deals/{deal}/documents
     * List all documents attached to deal / linked lead.
     */
    public function documents(CrmDeal $deal): JsonResponse
    {
        $this->authorize('view', $deal);

        $linkedLead = Lead::where('crm_deal_id', $deal->id)
            ->orWhere(function ($q) use ($deal) {
                if ($deal->lead_id) {
                    $q->where('id', $deal->lead_id);
                } elseif ($deal->crm_account_id) {
                    $q->where('crm_account_id', $deal->crm_account_id);
                }
            })
            ->with('leadDocuments')
            ->first();

        $documents = $linkedLead ? $linkedLead->leadDocuments->map(function ($doc) {
            return [
                'id'         => $doc->id,
                'file_name'  => $doc->file_name,
                'file_type'  => $doc->file_type,
                'size'       => $doc->size,
                'url'        => \Storage::disk('public')->url($doc->file_path),
                'created_at' => $doc->created_at?->toIso8601String(),
            ];
        }) : collect();

        return response()->json([
            'success' => true,
            'count'   => $documents->count(),
            'data'    => $documents,
        ]);
    }

    /**
     * POST /api/crm/deals/{deal}/documents
     * Upload documents for a deal.
     */
    public function uploadDocuments(Request $request, CrmDeal $deal): JsonResponse
    {
        $this->authorize('update', $deal);

        $validator = Validator::make($request->all(), [
            'documents'   => 'required',
            'documents.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $linkedLead = Lead::where('crm_deal_id', $deal->id)
            ->orWhere(function ($q) use ($deal) {
                if ($deal->lead_id) {
                    $q->where('id', $deal->lead_id);
                } elseif ($deal->crm_account_id) {
                    $q->where('crm_account_id', $deal->crm_account_id);
                }
            })
            ->first();

        if (!$linkedLead) {
            $linkedLead = Lead::create([
                'tenant_id'      => $tenantId,
                'company_id'     => $companyId,
                'branch_id'      => $branchId,
                'company_name'   => $deal->account ? $deal->account->name : $deal->title,
                'contact_person' => $deal->contact ? $deal->contact->name : 'N/A',
                'phone'          => $deal->contact?->phone ?: $deal->account?->phone,
                'email'          => $deal->contact?->email ?: $deal->account?->email,
                'requirement'    => $deal->title,
                'crm_account_id' => $deal->crm_account_id,
                'crm_contact_id' => $deal->crm_contact_id,
                'crm_deal_id'    => $deal->id,
                'status'         => 'Qualified',
            ]);
        }

        $files = $request->file('documents');
        if (!is_array($files)) {
            $files = [$files];
        }

        app(\App\Domains\CRM\Services\LeadService::class)->uploadDocuments($linkedLead, $files);

        $linkedLead->refresh();
        $documents = $linkedLead->leadDocuments->map(function ($doc) {
            return [
                'id'         => $doc->id,
                'file_name'  => $doc->file_name,
                'file_type'  => $doc->file_type,
                'size'       => $doc->size,
                'url'        => \Storage::disk('public')->url($doc->file_path),
                'created_at' => $doc->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Deal document(s) uploaded successfully.',
            'count'   => $documents->count(),
            'data'    => $documents,
        ], 201);
    }
}
