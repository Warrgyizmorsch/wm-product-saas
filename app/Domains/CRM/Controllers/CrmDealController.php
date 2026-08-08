<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\CrmDeal;
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

        // Calculate Stage Counts for Top Tabs
        $stageCounts = [
            'all'            => (clone $baseQuery)->count(),
            'Qualification'  => (clone $baseQuery)->whereIn('stage', ['Qualification', 'New'])->count(),
            'Needs Analysis' => (clone $baseQuery)->whereIn('stage', ['Needs Analysis', 'Qualified'])->count(),
            'Proposal'       => (clone $baseQuery)->where('stage', 'Proposal')->count(),
            'Negotiation'    => (clone $baseQuery)->where('stage', 'Negotiation')->count(),
            'Won'            => (clone $baseQuery)->whereIn('stage', ['Won', 'Closed Won'])->count(),
            'Lost'           => (clone $baseQuery)->whereIn('stage', ['Lost', 'Closed Lost'])->count(),
        ];

        $query = CrmDeal::with(['account', 'contact', 'owner', 'quotations'])
            ->where('tenant_id', $tenantId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('deal_number', 'like', "%{$search}%")
                  ->orWhereHas('account', fn($aq) => $aq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('contact', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
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

        return view('modules.crm.deals.index', compact('deals', 'search', 'stage', 'stageCounts', 'dateFrom', 'dateTo'));
    }

    public function kanban(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;

        $stages = [
            'Qualification'  => ['label' => 'Qualification',  'color' => 'primary', 'prob' => 10],
            'Needs Analysis' => ['label' => 'Needs Analysis', 'color' => 'info',    'prob' => 30],
            'Proposal'       => ['label' => 'Proposal',       'color' => 'warning', 'prob' => 60],
            'Negotiation'    => ['label' => 'Negotiation',    'color' => 'dark',    'prob' => 80],
            'Won'            => ['label' => 'Won',            'color' => 'success', 'prob' => 100],
            'Lost'           => ['label' => 'Lost',           'color' => 'danger',  'prob' => 0],
        ];

        $allDeals = CrmDeal::with(['account', 'contact', 'quotations'])
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

        return view('modules.crm.deals.kanban', compact('stages', 'kanbanData'));
    }

    public function create(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;
        $accounts = CrmAccount::where('tenant_id', $tenantId)->orderBy('name')->get();
        $selectedAccountId = $request->input('account_id');
        $contacts = collect();
        if ($selectedAccountId) {
            $contacts = CrmContact::where('crm_account_id', $selectedAccountId)->orderBy('name')->get();
        }

        return view('modules.crm.deals.create', compact('accounts', 'contacts', 'selectedAccountId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'crm_account_id'  => 'required|exists:crm_accounts,id',
            'crm_contact_id'  => 'nullable|exists:crm_contacts,id',
            'title'           => 'required|string|max:255',
            'estimated_value' => 'required|numeric|min:0',
            'stage'           => 'nullable|string|in:Qualification,Needs Analysis,Proposal,Negotiation,Won,Lost,Closed Won,Closed Lost,New,Qualified',
            'closing_date'    => 'nullable|date',
            'lead_source'     => 'nullable|string|max:100',
            'notes'           => 'nullable|string',
        ]);

        $stage = $validated['stage'] ?? 'Qualification';
        if ($stage === 'Closed Won') $stage = 'Won';
        if ($stage === 'Closed Lost') $stage = 'Lost';
        if ($stage === 'New') $stage = 'Qualification';
        if ($stage === 'Qualified') $stage = 'Needs Analysis';

        if ($stage === 'Won') {
            return back()->withErrors(['stage' => 'Deal cannot be created in Won stage directly. Quotation must be created and Accepted first.'])->withInput();
        }

        $nextNumber = 'DL-' . date('Y') . '-' . str_pad(CrmDeal::where('tenant_id', $tenantId)->count() + 1, 5, '0', STR_PAD_LEFT);

        $probMap = [
            'Qualification'  => 10,
            'Needs Analysis' => 30,
            'Proposal'       => 60,
            'Negotiation'    => 80,
            'Won'            => 100,
            'Lost'           => 0,
        ];

        $deal = CrmDeal::create([
            'tenant_id'       => $tenantId,
            'crm_account_id'  => $validated['crm_account_id'],
            'crm_contact_id'  => $validated['crm_contact_id'] ?? null,
            'deal_number'     => $nextNumber,
            'title'           => $validated['title'],
            'estimated_value' => $validated['estimated_value'],
            'stage'           => $stage,
            'probability'     => $probMap[$stage] ?? 20,
            'closing_date'    => $validated['closing_date'] ?? null,
            'lead_source'     => $validated['lead_source'] ?? null,
            'notes'           => $validated['notes'] ?? null,
        ]);

        return redirect()->route('crm.deals.show', $deal)->with('success', 'Deal created successfully.');
    }

    public function show(Request $request, CrmDeal $deal): View
    {
        $tenantId = tenant_id() ?? 1;
        $deal->load(['account.contacts', 'contact', 'quotations.items.product', 'salesOrders']);
        $nextQuotationNumber = app(\App\Domains\CRM\Services\QuotationService::class)->getNextQuotationNumber();
        $products = \App\Domains\Inventory\Models\Product::sellable()->with('parent')->orderBy('name')->get();
        $users = \App\Models\User::orderBy('name')->get();

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
        $linkedLead = \App\Domains\CRM\Models\Lead::where('crm_deal_id', $deal->id)
            ->orWhere(function($q) use ($deal) {
                if ($deal->crm_account_id) {
                    $q->where('crm_account_id', $deal->crm_account_id);
                }
            })
            ->with(['followups.taggedUser', 'histories.user', 'leadDocuments'])
            ->first();

        $followups = $linkedLead ? $linkedLead->followups : collect();
        $histories = $linkedLead ? $linkedLead->histories : collect();
        $leadDocuments = $linkedLead ? $linkedLead->leadDocuments : collect();

        $prefilledDealItems = [];
        if ($linkedLead) {
            $rawItems = $linkedLead->product_items ?: [];
            if (empty($rawItems) && !empty($linkedLead->product_ids)) {
                foreach ($linkedLead->product_ids as $pid) {
                    $rawItems[] = ['product_id' => (int)$pid, 'quantity' => 1.0];
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
        }

        return view('modules.crm.deals.show', compact('deal', 'nextQuotationNumber', 'products', 'users', 'prevDeal', 'nextDeal', 'activeQuotation', 'linkedLead', 'followups', 'histories', 'leadDocuments', 'prefilledDealItems'));
    }

    public function edit(CrmDeal $deal): View
    {
        $tenantId = tenant_id() ?? 1;
        $accounts = CrmAccount::where('tenant_id', $tenantId)->orderBy('name')->get();
        $contacts = CrmContact::where('crm_account_id', $deal->crm_account_id)->orderBy('name')->get();

        return view('modules.crm.deals.edit', compact('deal', 'accounts', 'contacts'));
    }

    public function update(Request $request, CrmDeal $deal): RedirectResponse
    {
        $validated = $request->validate([
            'crm_account_id'  => 'required|exists:crm_accounts,id',
            'crm_contact_id'  => 'nullable|exists:crm_contacts,id',
            'title'           => 'required|string|max:255',
            'estimated_value' => 'required|numeric|min:0',
            'stage'           => 'required|string|in:New,Qualified,Proposal,Won,Lost,Closed Won,Closed Lost,Qualification,Needs Analysis,Negotiation',
            'close_reason'    => 'nullable|string|max:255',
            'closing_date'    => 'nullable|date',
            'lead_source'     => 'nullable|string|max:100',
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

        $validated['stage'] = $stage;

        $probMap = [
            'Qualification'  => 10,
            'Needs Analysis' => 30,
            'Proposal'       => 60,
            'Negotiation'    => 80,
            'Won'            => 100,
            'Lost'           => 0,
        ];
        $validated['probability'] = $probMap[$stage] ?? $deal->probability;

        $deal->update($validated);

        return redirect()->route('crm.deals.show', $deal)->with('success', 'Deal updated successfully.');
    }

    public function updateStage(Request $request, CrmDeal $deal)
    {
        $validated = $request->validate([
            'stage'        => 'required|string|in:Qualification,Needs Analysis,Proposal,Negotiation,Won,Lost,Closed Won,Closed Lost,New,Qualified',
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

        $probMap = [
            'Qualification'  => 10,
            'Needs Analysis' => 30,
            'Proposal'       => 60,
            'Negotiation'    => 80,
            'Won'            => 100,
            'Lost'           => 0,
        ];

        $deal->update([
            'stage'        => $stage,
            'probability'  => $probMap[$stage] ?? $deal->probability,
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
}
