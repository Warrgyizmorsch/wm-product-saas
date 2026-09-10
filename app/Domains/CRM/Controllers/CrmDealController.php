<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\DealStatus;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CrmDealController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;
        $search = $request->input('search');
        $stage = $request->input('stage');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);

        $baseQuery = CrmDeal::where('tenant_id', $tenantId);

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('deal_number', 'like', "%{$search}%")
                  ->orWhereHas('account', fn($aq) => $aq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('contact', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($dateFrom) {
            $baseQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $baseQuery->whereDate('created_at', '<=', $dateTo);
        }

        // Calculate Stage Counts for Top Tabs dynamically from DealStatus master
        $stageCounts = [
            'all' => (clone $baseQuery)->count(),
        ];

        foreach ($dealStatuses as $st) {
            if ($st->name === 'Won') {
                $stageCounts[$st->name] = (clone $baseQuery)->whereIn('stage', ['Won', 'Closed Won'])->count();
            } elseif ($st->name === 'Lost') {
                $stageCounts[$st->name] = (clone $baseQuery)->whereIn('stage', ['Lost', 'Closed Lost'])->count();
            } else {
                $stageCounts[$st->name] = (clone $baseQuery)->where('stage', $st->name)->count();
            }
        }

        $query = CrmDeal::with(['account', 'contact', 'owner', 'quotations', 'lead'])
            ->where('tenant_id', $tenantId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('deal_number', 'like', "%{$search}%")
                  ->orWhereHas('account', fn($aq) => $aq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('contact', fn($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('lead', fn($lq) => $lq->where('company_name', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('company_email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('company_phone', 'like', "%{$search}%"));
            });
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($stage) {
            if ($stage === 'Qualification') {
                $query->whereIn('stage', ['Qualification', 'New']);
            } elseif ($stage === 'Needs Analysis') {
                $query->whereIn('stage', ['Needs Analysis', 'Qualified']);
            } elseif ($stage === 'Won') {
                $query->whereIn('stage', ['Won', 'Closed Won']);
            } elseif ($stage === 'Lost') {
                $query->whereIn('stage', ['Lost', 'Closed Lost']);
            } else {
                $query->where('stage', $stage);
            }
        }

        $deals = $query->orderBy('id', 'desc')->paginate(100);

        return view('modules.crm.deals.index', compact('deals', 'search', 'stage', 'stageCounts', 'dateFrom', 'dateTo', 'dealStatuses'));
    }

    public function kanban(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;

        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);

        $stages = [];
        foreach ($dealStatuses as $st) {
            $color = match(strtolower($st->name)) {
                'qualification' => 'primary',
                'needs analysis' => 'info',
                'proposal' => 'warning',
                'negotiation' => 'dark',
                'won', 'closed won' => 'success',
                'lost', 'closed lost' => 'danger',
                default => str_replace('bg-', '', $st->color ?: 'primary'),
            };
            $prob = $st->probability ?? match(strtolower($st->name)) {
                'qualification' => 10,
                'needs analysis' => 30,
                'proposal' => 60,
                'negotiation' => 80,
                'won', 'closed won' => 100,
                'lost', 'closed lost' => 0,
                default => 50,
            };
            $stages[$st->name] = [
                'label' => $st->name,
                'color' => $color,
                'prob'  => $prob,
            ];
        }

        $allDeals = CrmDeal::with(['account', 'contact', 'quotations', 'lead'])
            ->where('tenant_id', $tenantId)
            ->orderBy('updated_at', 'desc')
            ->get();

        $kanbanData = [];
        foreach ($stages as $stageKey => $info) {
            $filteredDeals = $allDeals->filter(function($d) use ($stageKey) {
                $stg = $d->stage;
                if ($stg === 'Closed Won') $stg = 'Won';
                if ($stg === 'Closed Lost') $stg = 'Lost';
                if ($stg === 'New') $stg = 'Qualification';
                if ($stg === 'Qualified') $stg = 'Needs Analysis';
                return $stg === $stageKey;
            });

            $kanbanData[$stageKey] = [
                'info'  => $info,
                'deals' => $filteredDeals,
                'total' => $filteredDeals->sum(fn($d) => $d->actual_value ?: $d->estimated_value),
            ];
        }

        return view('modules.crm.deals.kanban', compact('stages', 'kanbanData', 'dealStatuses'));
    }

    public function create(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;
        $accounts = CrmAccount::where('tenant_id', $tenantId)->orderBy('name')->get();
        $selectedAccountId = $request->input('account_id') ?? $request->input('crm_account_id');
        $contacts = $selectedAccountId 
            ? CrmContact::where('crm_account_id', $selectedAccountId)->orderBy('name')->get()
            : collect();

        $products = \App\Domains\Inventory\Models\Product::sellable()->with('parent')->orderBy('name')->get();
        $users = \App\Models\User::orderBy('name')->get();
        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);

        return view('modules.crm.deals.create', compact('accounts', 'contacts', 'selectedAccountId', 'products', 'users', 'dealStatuses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;

        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);
        $validStages = array_unique(array_merge(
            $dealStatuses->pluck('name')->toArray(),
            ['Qualification', 'Needs Analysis', 'Proposal', 'Negotiation', 'Won', 'Lost', 'Closed Won', 'Closed Lost', 'New', 'Qualified']
        ));

        $validated = $request->validate([
            'crm_account_id'  => 'required|exists:crm_accounts,id',
            'crm_contact_id'  => 'nullable|exists:crm_contacts,id',
            'title'           => 'required|string|max:255',
            'estimated_value' => 'required|numeric|min:0',
            'stage'           => 'nullable|string|in:' . implode(',', $validStages),
            'closing_date'    => 'nullable|date',
            'lead_source'     => 'nullable|string|max:100',
            'owner_id'        => 'nullable|exists:users,id',
            'notes'           => 'nullable|string',
        ]);

        $stage = $validated['stage'] ?? ($dealStatuses->first()?->name ?: 'Qualification');
        if ($stage === 'Closed Won') $stage = 'Won';
        if ($stage === 'Closed Lost') $stage = 'Lost';
        if ($stage === 'New') $stage = 'Qualification';
        if ($stage === 'Qualified') $stage = 'Needs Analysis';

        if ($stage === 'Won') {
            return back()->withErrors(['stage' => 'Deal cannot be created in Won stage directly. Quotation must be created and Accepted first.'])->withInput();
        }

        $nextNumber = 'DL-' . date('Y') . '-' . str_pad(CrmDeal::where('tenant_id', $tenantId)->count() + 1, 5, '0', STR_PAD_LEFT);

        $targetStatus = $dealStatuses->firstWhere('name', $stage);
        $prob = $targetStatus ? $targetStatus->probability : ($probMap[$stage] ?? 50);

        $rawItems = $request->input('items', []);
        $rawProductIds = $request->input('product_ids', []);
        $processed = app(\App\Domains\CRM\Services\LeadService::class)->processItems($rawItems, $rawProductIds);

        $deal = CrmDeal::create([
            'tenant_id'       => $tenantId,
            'crm_account_id'  => $validated['crm_account_id'],
            'crm_contact_id'  => $validated['crm_contact_id'] ?? null,
            'deal_number'     => $nextNumber,
            'title'           => $validated['title'],
            'estimated_value' => $validated['estimated_value'],
            'stage'           => $stage,
            'probability'     => $prob,
            'closing_date'    => $validated['closing_date'] ?? null,
            'lead_source'     => $validated['lead_source'] ?? null,
            'owner_id'        => $validated['owner_id'] ?? (auth()->id() ?: 1),
            'notes'           => $validated['notes'] ?? null,
            'product_ids'     => $processed['product_ids'],
            'product_items'   => $processed['product_items'],
        ]);

        // Process any additional contacts submitted in form
        $additional = $request->input('additional_contacts', []);
        if (is_array($additional) && !empty($validated['crm_account_id'])) {
            foreach ($additional as $add) {
                if (empty($add['name'])) continue;
                CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'crm_account_id' => $validated['crm_account_id'],
                    'name'           => $add['name'],
                    'designation'    => $add['designation'] ?? null,
                    'role'           => 'Additional Contact',
                    'email'          => $add['email'] ?? null,
                    'phone'          => $add['phone'] ?? null,
                    'mobile'         => $add['phone'] ?? null,
                    'is_primary'     => false,
                    'status'         => 'active',
                ]);
            }
        }

        return redirect()->route('crm.deals.show', $deal)->with('success', 'Deal created successfully.');
    }

    public function show(Request $request, CrmDeal $deal): View
    {
        $tenantId = tenant_id() ?? 1;
        $deal->load(['account.contacts', 'account.owner', 'contact', 'quotations.items.product', 'salesOrders', 'owner']);
        $nextQuotationNumber = app(\App\Domains\CRM\Services\QuotationService::class)->getNextQuotationNumber();
        $products = \App\Domains\Inventory\Models\Product::sellable()->with('parent')->orderBy('name')->get();
        $users = \App\Models\User::orderBy('name')->get();
        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);

        $prevDeal = CrmDeal::where('tenant_id', $tenantId)->where('id', '<', $deal->id)->orderBy('id', 'desc')->first();
        $nextDeal = CrmDeal::where('tenant_id', $tenantId)->where('id', '>', $deal->id)->orderBy('id', 'asc')->first();

        $activeQuotationId = $request->input('quotation_id');
        $activeQuotation = $activeQuotationId 
            ? $deal->quotations->where('id', $activeQuotationId)->first() 
            : $deal->quotations->where('is_current', true)->first();

        if (!$activeQuotation && $deal->quotations->isNotEmpty()) {
            $activeQuotation = $deal->quotations->first();
        }

        // Fetch linked lead for complete activity roll-up & document history
        $linkedLead = null;
        if (!empty($deal->lead_id)) {
            $linkedLead = \App\Domains\CRM\Models\Lead::with(['histories.user', 'leadDocuments'])->find($deal->lead_id);
        }
        if (!$linkedLead) {
            $linkedLead = \App\Domains\CRM\Models\Lead::where('crm_deal_id', $deal->id)
                ->orWhere(function($q) use ($deal) {
                    if ($deal->crm_account_id) {
                        $q->where('crm_account_id', $deal->crm_account_id);
                    }
                })
                ->with(['histories.user', 'leadDocuments'])
                ->first();
        }

        $followupsQuery = \App\Domains\CRM\Models\LeadFollowup::where('crm_deal_id', $deal->id);
        if ($linkedLead) {
            $followupsQuery->orWhere('lead_id', $linkedLead->id);
        }
        $followups = $followupsQuery->with(['taggedUser', 'rescheduledFrom', 'rescheduledTo'])->orderBy('followup_date', 'desc')->get();
        $histories = $linkedLead ? $linkedLead->histories : collect();
        $leadDocuments = $linkedLead ? $linkedLead->leadDocuments : collect();

        $prefilledDealItems = [];
        $rawItems = $deal->product_items ?: [];
        if (empty($rawItems) && !empty($deal->product_ids)) {
            foreach ($deal->product_ids as $pid) {
                $rawItems[] = ['product_id' => (int)$pid, 'quantity' => 1.0];
            }
        }
        if (empty($rawItems) && $linkedLead) {
            $rawItems = $linkedLead->product_items ?: [];
            if (empty($rawItems) && !empty($linkedLead->product_ids)) {
                foreach ($linkedLead->product_ids as $pid) {
                    $rawItems[] = ['product_id' => (int)$pid, 'quantity' => 1.0];
                }
            }
        }
        foreach ($rawItems as $it) {
            if (empty($it['product_id'])) continue;
            $productObj = \App\Domains\Inventory\Models\Product::find($it['product_id']);
            if ($productObj) {
                $prefilledDealItems[] = [
                    'product_id' => $productObj->id,
                    'quantity'   => floatval($it['quantity'] ?? 1),
                    'unit_price' => floatval($productObj->selling_price ?: $productObj->unit_cost ?: 0),
                    'tax_rate'   => floatval($productObj->gst_rate ?: 18),
                ];
            }
        }

        return view('modules.crm.deals.show', compact('deal', 'nextQuotationNumber', 'products', 'users', 'prevDeal', 'nextDeal', 'activeQuotation', 'linkedLead', 'followups', 'histories', 'leadDocuments', 'prefilledDealItems', 'dealStatuses'));
    }

    public function edit(CrmDeal $deal): View
    {
        $tenantId = tenant_id() ?? 1;
        $accounts = CrmAccount::where('tenant_id', $tenantId)->orderBy('name')->get();
        $contacts = $deal->crm_account_id 
            ? CrmContact::where('crm_account_id', $deal->crm_account_id)->orderBy('name')->get()
            : collect();
        $products = \App\Domains\Inventory\Models\Product::sellable()->with('parent')->orderBy('name')->get();
        $users = \App\Models\User::orderBy('name')->get();
        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);

        return view('modules.crm.deals.edit', compact('deal', 'accounts', 'contacts', 'products', 'users', 'dealStatuses'));
    }

    public function update(Request $request, CrmDeal $deal): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;

        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);
        $validStages = array_unique(array_merge(
            $dealStatuses->pluck('name')->toArray(),
            ['Qualification', 'Needs Analysis', 'Proposal', 'Negotiation', 'Won', 'Lost', 'Closed Won', 'Closed Lost', 'New', 'Qualified']
        ));

        $validated = $request->validate([
            'crm_account_id'  => 'required|exists:crm_accounts,id',
            'crm_contact_id'  => 'nullable|exists:crm_contacts,id',
            'title'           => 'required|string|max:255',
            'estimated_value' => 'required|numeric|min:0',
            'stage'           => 'required|string|in:' . implode(',', $validStages),
            'close_reason'    => 'nullable|string|max:255',
            'closing_date'    => 'nullable|date',
            'lead_source'     => 'nullable|string|max:100',
            'owner_id'        => 'nullable|exists:users,id',
            'notes'           => 'nullable|string',
        ]);

        $stage = $validated['stage'];
        if ($stage === 'Closed Won') $stage = 'Won';
        if ($stage === 'Closed Lost') $stage = 'Lost';

        if ($stage === 'Won') {
            $hasAcceptedQuote = $deal->quotations()->where('status', 'Accepted')->exists();
            if (!$hasAcceptedQuote) {
                return back()->withErrors(['stage' => 'Deal cannot be marked as Won directly. A Quotation must be created and Accepted first.'])->withInput();
            }
        }

        $targetStatus = $dealStatuses->firstWhere('name', $stage);
        $prob = $targetStatus ? $targetStatus->probability : ($probMap[$stage] ?? $deal->probability);
        $validated['probability'] = $prob;

        $rawItems = $request->input('items', []);
        $rawProductIds = $request->input('product_ids', []);
        $processed = app(\App\Domains\CRM\Services\LeadService::class)->processItems($rawItems, $rawProductIds);

        $validated['product_ids'] = $processed['product_ids'];
        $validated['product_items'] = $processed['product_items'];

        $deal->update($validated);

        // Process any additional contacts submitted in form
        $additional = $request->input('additional_contacts', []);
        if (is_array($additional) && !empty($validated['crm_account_id'])) {
            foreach ($additional as $add) {
                if (empty($add['name'])) continue;
                CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'crm_account_id' => $validated['crm_account_id'],
                    'name'           => $add['name'],
                    'role'           => 'Additional Contact',
                    'email'          => $add['email'] ?? null,
                    'phone'          => $add['phone'] ?? null,
                    'mobile'         => $add['phone'] ?? null,
                    'is_primary'     => false,
                    'status'         => 'active',
                ]);
            }
        }

        return redirect()->route('crm.deals.show', $deal)->with('success', 'Deal updated successfully.');
    }

    public function updateStage(Request $request, CrmDeal $deal)
    {
        $tenantId = tenant_id() ?? 1;

        $dealStatuses = DealStatus::getOrderedStatuses($tenantId);
        $validStages = array_unique(array_merge(
            $dealStatuses->pluck('name')->toArray(),
            ['Qualification', 'Needs Analysis', 'Proposal', 'Negotiation', 'Won', 'Lost', 'Closed Won', 'Closed Lost', 'New', 'Qualified']
        ));

        $validated = $request->validate([
            'stage'        => 'required|string|in:' . implode(',', $validStages),
            'close_reason' => 'nullable|string|max:255',
        ]);

        $stage = $validated['stage'];
        if ($stage === 'Closed Won') $stage = 'Won';
        if ($stage === 'Closed Lost') $stage = 'Lost';
        if ($stage === 'New') $stage = 'Qualification';
        if ($stage === 'Qualified') $stage = 'Needs Analysis';

        if ($stage === 'Won') {
            $hasAcceptedQuote = $deal->quotations()->where('status', 'Accepted')->exists();
            if (!$hasAcceptedQuote) {
                $errMsg = 'Deal cannot be marked as Won directly. A Quotation must be created and Accepted first.';
                if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
                    return response()->json([
                        'success' => false,
                        'message' => $errMsg
                    ], 422);
                }
                return redirect()->back()->with('error', $errMsg);
            }
        }

        $targetStatus = $dealStatuses->firstWhere('name', $stage);
        $prob = $targetStatus ? $targetStatus->probability : ($probMap[$stage] ?? $deal->probability);

        $deal->update([
            'stage'        => $stage,
            'probability'  => $prob,
            'close_reason' => $validated['close_reason'] ?? $deal->close_reason,
        ]);

        $msg = "Deal stage updated to {$stage} successfully!";

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'deal'    => $deal
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function destroy(CrmDeal $deal): RedirectResponse
    {
        $deal->delete();
        return redirect()->route('crm.deals.index')->with('success', 'Deal deleted successfully.');
    }

    public function uploadDocuments(Request $request, CrmDeal $deal): RedirectResponse
    {
        $request->validate([
            'documents' => 'required',
            'documents.*' => 'file|max:10240'
        ]);

        $linkedLead = \App\Domains\CRM\Models\Lead::where('crm_deal_id', $deal->id)
            ->orWhere(function($q) use ($deal) {
                if ($deal->crm_account_id) {
                    $q->where('crm_account_id', $deal->crm_account_id);
                }
            })
            ->first();

        if (!$linkedLead) {
            $tenantId = tenant_id() ?? 1;
            $linkedLead = \App\Domains\CRM\Models\Lead::create([
                'tenant_id'      => $tenantId,
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

        app(\App\Domains\CRM\Services\LeadService::class)->uploadDocuments($linkedLead, $request->file('documents'));

        return redirect()->back()->with('success', 'Document uploaded successfully!');
    }

    public function updateRequirement(Request $request, CrmDeal $deal)
    {
        $validated = $request->validate([
            'notes'       => 'nullable|string',
            'requirement' => 'nullable|string',
        ]);

        $notes = $validated['notes'] ?? $validated['requirement'] ?? null;
        $deal->notes = $notes;
        $deal->save();

        // Also update linked lead requirement if exists
        $linkedLead = \App\Domains\CRM\Models\Lead::where('crm_deal_id', $deal->id)->first();
        if ($linkedLead) {
            $linkedLead->requirement = $notes;
            $linkedLead->save();
        }

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success'     => true,
                'message'     => 'Requirements updated successfully!',
                'requirement' => $deal->notes,
                'notes'       => $deal->notes,
            ]);
        }

        return redirect()->back()->with('success', 'Deal requirements updated successfully!');
    }

    public function showConvertForm(CrmDeal $deal)
    {
        $tenantId = $deal->tenant_id ?? (tenant_id() ?? 1);
        $deal->load(['account.contacts', 'contact', 'lead']);

        $account = $deal->account;
        $contact = $deal->contact;
        $lead = $deal->lead;

        $gstin = trim((string)($account?->gstin ?: ($lead?->gstin ?: '')));
        $email = strtolower(trim((string)($account?->email ?: ($contact?->email ?: ($lead?->company_email ?: $lead?->email ?: '')))));
        $phone = trim((string)($account?->phone ?: ($contact?->phone ?: ($lead?->company_phone ?: $lead?->phone ?: ''))));
        $companyName = trim((string)($account?->name ?: ($lead?->company_name ?: ($lead?->contact_person ?: $deal->title))));
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        $matchedCustomers = collect();
        $matchReasons = [];

        // 1. Check by GSTIN
        if (!empty($gstin)) {
            $byGstin = Customer::where('tenant_id', $tenantId)->where('gstin', $gstin)->get();
            if ($byGstin->isNotEmpty()) {
                $matchedCustomers = $matchedCustomers->merge($byGstin);
                $matchReasons[] = "GSTIN ({$gstin})";
            }
        }

        // 2. Check by Email
        if (!empty($email)) {
            $byEmail = Customer::where('tenant_id', $tenantId)->whereRaw('LOWER(email) = ?', [$email])->get();
            if ($byEmail->isNotEmpty()) {
                $matchedCustomers = $matchedCustomers->merge($byEmail);
                $matchReasons[] = "Email ({$email})";
            }
        }

        // 3. Check by Phone
        if (!empty($phone) || (!empty($cleanPhone) && strlen($cleanPhone) >= 5)) {
            $byPhone = Customer::where('tenant_id', $tenantId)
                ->where(function ($q) use ($phone, $cleanPhone) {
                    if (!empty($phone)) $q->where('phone', 'like', "%{$phone}%");
                    if (!empty($cleanPhone) && strlen($cleanPhone) >= 5) {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?", ["%{$cleanPhone}%"]);
                    }
                })->get();
            if ($byPhone->isNotEmpty()) {
                $matchedCustomers = $matchedCustomers->merge($byPhone);
                $matchReasons[] = "Phone ({$phone})";
            }
        }

        // 4. Check by Name
        if (!empty($companyName) && strlen($companyName) >= 3 && !in_array($companyName, ['Client', 'New Client'], true)) {
            $cleanComp = trim(preg_replace('/(pvt|ltd|private|limited|inc|corp|co)/i', '', $companyName));
            if (!empty($cleanComp)) {
                $byName = Customer::where('tenant_id', $tenantId)
                    ->where('name', 'like', "%{$cleanComp}%")
                    ->get();
                if ($byName->isNotEmpty()) {
                    $matchedCustomers = $matchedCustomers->merge($byName);
                    $matchReasons[] = "Name ({$companyName})";
                }
            }
        }

        $matchedCustomers = $matchedCustomers->unique('id')->values();

        // IF NO DUPLICATE MATCHED: Directly convert Deal to Customer!
        if ($matchedCustomers->isEmpty()) {
            return $this->executeDealCustomerConversion($deal, null, 'create_new');
        }

        // IF DUPLICATES EXIST: Show Zoho CRM prompt
        $quotation = null;
        return view('modules.crm.leads.convert', compact('deal', 'lead', 'quotation', 'matchedCustomers', 'matchReasons'));
    }

    public function processConvert(Request $request, CrmDeal $deal): RedirectResponse|View
    {
        $request->validate([
            'conversion_mode'      => 'required|string|in:existing,create_new',
            'existing_customer_id' => 'required_if:conversion_mode,existing|nullable|integer|exists:customers,id',
        ]);

        $mode = $request->input('conversion_mode');
        $existingCustomerId = $request->input('existing_customer_id');

        return $this->executeDealCustomerConversion($deal, $existingCustomerId, $mode, skipDuplicateCheck: true);
    }

    private function executeDealCustomerConversion(CrmDeal $deal, ?int $existingCustomerId, string $mode, bool $skipDuplicateCheck = false): RedirectResponse|View
    {
        $tenantId = $deal->tenant_id ?? (tenant_id() ?? 1);
        $deal->load(['account', 'contact', 'lead']);
        $account = $deal->account;

        $leadObj = null;
        if (!empty($deal->lead_id)) {
            $leadObj = Lead::find($deal->lead_id);
        }
        if (!$leadObj) {
            $leadObj = Lead::where('crm_deal_id', $deal->id)->first();
        }

        if ($mode === 'existing' && $existingCustomerId) {
            $customer = Customer::find($existingCustomerId);

            if ($account) {
                $account->update([
                    'customer_id' => $customer->id,
                    'status'      => 'active',
                ]);
            } else {
                $account = CrmAccount::create([
                    'tenant_id'   => $tenantId,
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
                'crm_account_id' => $account->id,
                'stage'          => 'Won',
                'probability'    => 100,
                'closing_date'   => now(),
            ]);

            if ($leadObj) {
                $leadObj->update([
                    'status'         => 'Won',
                    'crm_account_id' => $account->id,
                    'is_customer'    => true,
                    'converted_at'   => now(),
                ]);
            }

            // Sync lead contacts → account
            $this->syncLeadContactsToAccount($account, $leadObj, $tenantId);

            return redirect()->route('crm.deals.show', $deal->id)
                ->with('success', "Deal #{$deal->deal_number} marked as Won and linked to existing customer '{$customer->name}'!");
        } else {
            // Mode: Create New Customer & Account
            $compName  = $account?->name  ?: ($deal->lead?->company_name  ?: ($deal->lead?->contact_person ?: $deal->title));
            $compEmail = $account?->email  ?: ($deal->contact?->email       ?: ($deal->lead?->company_email  ?: $deal->lead?->email));
            $compPhone = $account?->phone  ?: ($deal->contact?->phone       ?: ($deal->lead?->company_phone  ?: $deal->lead?->phone));
            $gstin     = $account?->gstin  ?: ($deal->lead?->gstin);
            $companyName = $compName;

            // Note: email unique constraint removed from customers table,
            // so we can safely store the email as-is even if another customer has the same one.

            // Create Customer — flag prevents boot hook from auto-creating a duplicate account
            Customer::$skipAccountAutoCreate = true;
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'name'      => $compName,
                'email'     => $compEmail ?: null,
                'phone'     => $compPhone,
                'gstin'     => $gstin,
                'status'    => 'active',
            ]);
            Customer::$skipAccountAutoCreate = false;

            // Create exactly ONE CrmAccount linked to this new customer
            $newAccount = CrmAccount::create([
                'tenant_id'   => $tenantId,
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

            // Sync lead contacts → account
            $this->syncLeadContactsToAccount($newAccount, $leadObj, $tenantId);

            return redirect()->route('crm.deals.show', $deal->id)
                ->with('success', "Deal #{$deal->deal_number} marked as Won and converted to new Customer & Account!");
        }
    }

    /**
     * Create primary contact + additional contacts from Lead data into a CrmAccount.
     * Skips creation if a contact already exists to avoid duplicates.
     */
    private function syncLeadContactsToAccount(CrmAccount $account, ?Lead $lead, int $tenantId): void
    {
        if (!$lead) return;

        // --- Primary Contact (lead's contact_person field) ---
        $primaryName  = $lead->contact_person;
        $primaryEmail = $lead->email;       // personal email
        $primaryPhone = $lead->phone;       // personal phone
        $primaryDesg  = $lead->designation;

        if ($primaryName) {
            $alreadyExists = $account->contacts()->where('is_primary', true)->exists();

            if (!$alreadyExists) {
                CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'company_id'     => $account->company_id,
                    'branch_id'      => $account->branch_id,
                    'crm_account_id' => $account->id,
                    'name'           => $primaryName,
                    'designation'    => $primaryDesg,
                    'email'          => $primaryEmail,
                    'phone'          => $primaryPhone,
                    'is_primary'     => true,
                    'status'         => 'active',
                ]);
            }
        }

        // --- Additional Contacts (from lead's additional_contacts JSON array) ---
        $additionalContacts = $lead->additional_contacts;
        if (!empty($additionalContacts) && is_array($additionalContacts)) {
            foreach ($additionalContacts as $extra) {
                $extraName  = $extra['name']  ?? $extra['contact_person'] ?? null;
                $extraEmail = $extra['email'] ?? null;
                $extraPhone = $extra['phone'] ?? null;
                $extraDesg  = $extra['designation'] ?? null;

                if (!$extraName) continue;

                // Skip if same email contact already exists in this account
                if ($extraEmail) {
                    $dup = $account->contacts()
                        ->whereRaw('LOWER(email) = ?', [strtolower(trim($extraEmail))])
                        ->exists();
                    if ($dup) continue;
                }

                CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'company_id'     => $account->company_id,
                    'branch_id'      => $account->branch_id,
                    'crm_account_id' => $account->id,
                    'name'           => $extraName,
                    'designation'    => $extraDesg,
                    'email'          => $extraEmail,
                    'phone'          => $extraPhone,
                    'is_primary'     => false,
                    'status'         => 'active',
                ]);
            }
        }
    }
}
