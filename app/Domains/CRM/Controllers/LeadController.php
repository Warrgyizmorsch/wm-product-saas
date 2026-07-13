<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\LeadDocument;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Services\QuotationService;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Exports\LeadSampleExport;
use Maatwebsite\Excel\Facades\Excel;

class LeadController extends Controller
{
    /**
     * Display a listing of the leads.
     */
    public function index(Request $request, QuotationService $quotationService)
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::query();

        // Search Keywords
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%");
            });
        }

        // Priority Filter
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Segment Filter
        if ($segment = $request->input('segment')) {
            $query->where('segment', $segment);
        }

        // Status Filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'call_date');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['call_date', 'company_name', 'expected_amount', 'priority', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $leads = $query->paginate(10)->withQueryString();

        $quotations = $quotationService->latest();

        return view('modules.crm.leads.index', compact('leads', 'quotations'));
    }

    /**
     * Show the track status page.
     */
    public function trackStatus()
    {
        $this->authorize('viewAny', Lead::class);

        return view('modules.crm.leads.track-status');
    }

    /**
     * Download the sample leads Excel sheet.
     */
    public function downloadSample()
    {
        $this->authorize('viewAny', Lead::class);

        return Excel::download(new LeadSampleExport, 'lead_sample.xlsx');
    }

    /**
     * Show the form for creating a new lead.
     */
    public function create()
    {
        $this->authorize('create', Lead::class);

        $lead = new Lead();
        $users = User::orderBy('name')->get();
        $products = Product::whereIn('type', ['finished_good', 'component'])->orderBy('name')->get();
        return view('modules.crm.leads.create', compact('lead', 'users', 'products'));
    }

    /**
     * Display the specified lead.
     */
    public function show(Lead $lead)
    {
        $lead->load(['followups', 'histories.user', 'leadDocuments']);
        $this->authorize('view', $lead);

        $lead->load(['followups', 'histories.user']);
        $users = User::orderBy('name')->get();

        // Step 1: Get the linked customer using email/phone
        $customer = null;
        if ($lead->email) {
            $customer = Customer::where('email', $lead->email)->first();
        }
        if (!$customer && $lead->phone) {
            $customer = Customer::where('phone', $lead->phone)->first();
        }

        // Step 1b: If creating quotation and no customer exists, auto-create one
        if (request()->has('create_quotation') && !$customer) {
            $customer = Customer::create([
                'tenant_id' => $lead->tenant_id,
                'name'      => $lead->contact_person ?: ($lead->company_name ?: 'Converted Lead'),
                'email'     => $lead->email,
                'phone'     => $lead->phone,
                'status'    => 'inactive',
            ]);
        }

        // Step 2: Get quotations ONLY for this specific lead (by lead_id)
        $quotations = Quotation::where('lead_id', $lead->id)->latest()->get();
        if (request()->has('active_quotation_id')) {
            $activeQuotation = Quotation::find(request()->input('active_quotation_id'));
        } else {
            $activeQuotation = $quotations->where('is_current', true)->first() ?: $quotations->first();
        }

        $customers = Customer::orderBy('name')->get();
        $nextQuotationNumber = app(QuotationService::class)->getNextQuotationNumber();
        $products = Product::whereIn('type', ['finished_good', 'component'])->orderBy('name')->get();

        // Get previous and next leads based on ID order (latest chronological order)
        $prevLead = Lead::where('id', '>', $lead->id)->orderBy('id', 'asc')->first();
        $nextLead = Lead::where('id', '<', $lead->id)->orderBy('id', 'desc')->first();

        return view('modules.crm.leads.show', compact(
            'lead', 'users', 'customer', 'quotations', 'activeQuotation', 
            'customers', 'nextQuotationNumber', 'prevLead', 'nextLead', 'products'
        ));
    }

    /**
     * Store a newly created lead.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Lead::class);

        $rules = [
            'lead_owner_id' => 'nullable|exists:users,id',
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'requirement' => 'nullable|string',
            'expected_amount' => 'nullable|numeric|min:0',
            'expected_sale_date' => 'nullable|date',
            'source' => 'nullable|string|max:255',
            'priority' => 'nullable|string|max:255',
            'segment' => 'nullable|string|max:255',
            'industry_type' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'product_id' => 'nullable|integer|exists:products,id',
        ];

        if ($request->has('call_date')) {
            $rules['call_date'] = 'required|string';
        } else {
            $rules['call_date_date'] = 'required|date';
            $rules['call_date_hour'] = 'required|string|max:2';
            $rules['call_date_minute'] = 'required|string|max:2';
            $rules['call_date_ampm'] = 'required|string|in:AM,PM';
        }

        $validated = $request->validate($rules);

        // Construct Call Date & Time
        if (isset($validated['call_date'])) {
            try {
                $callDateTime = Carbon::parse($validated['call_date']);
            } catch (\Exception $e) {
                $callDateTime = Carbon::now();
            }
        } else {
            try {
                $hour = intval($validated['call_date_hour']);
                if ($validated['call_date_ampm'] === 'PM' && $hour < 12) {
                    $hour += 12;
                } elseif ($validated['call_date_ampm'] === 'AM' && $hour === 12) {
                    $hour = 0;
                }
                
                $timeString = sprintf('%02d:%02d:00', $hour, intval($validated['call_date_minute']));
                $callDateTime = Carbon::parse($validated['call_date_date'] . ' ' . $timeString);
            } catch (\Exception $e) {
                $callDateTime = Carbon::now();
            }
        }

        // Build data array
        $leadData = [
            'call_date' => $callDateTime,
            'lead_owner_id' => $validated['lead_owner_id'],
            'company_name' => $validated['company_name'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'requirement' => $validated['requirement'],
            'expected_amount' => $validated['expected_amount'] ? floatval($validated['expected_amount']) : 0.00,
            'expected_sale_date' => $validated['expected_sale_date'] ? Carbon::parse($validated['expected_sale_date']) : null,
            'source' => $validated['source'] ?: 'Select an Option',
            'priority' => $validated['priority'] ?: 'Select an Option',
            'segment' => $validated['segment'] ?: 'Select an Option',
            'industry_type' => $validated['industry_type'] ?? null,
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
        ];

        // Direct DB save
        $lead = Lead::create($leadData);

        \App\Domains\CRM\Models\LeadHistory::logEvent(
            $lead,
            'created',
            null,
            $lead->company_name,
            'Lead created with initial stage: ' . ($lead->status ?: 'New')
        );

        if ($lead->lead_owner_id) {
            $ownerName = \App\Models\User::find($lead->lead_owner_id)?->name ?: 'Unknown';
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead,
                'assigned',
                null,
                $ownerName,
                'Lead automatically assigned to lead owner: ' . $ownerName
            );
        }

        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully saved to Database!');
    }


    /**
     * Show the form for editing the specified lead.
     */
    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);

        $users = User::orderBy('name')->get();
        $products = Product::whereIn('type', ['finished_good', 'component'])->orderBy('name')->get();
        return view('modules.crm.leads.create', compact('lead', 'users', 'products'));
    }

    /**
     * Update the specified lead in storage.
     */
    public function update(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $rules = [
            'lead_owner_id' => 'nullable|exists:users,id',
            'company_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'requirement' => 'nullable|string',
            'expected_amount' => 'nullable|numeric|min:0',
            'expected_sale_date' => 'nullable|date',
            'source' => 'nullable|string|max:255',
            'priority' => 'nullable|string|max:255',
            'segment' => 'nullable|string|max:255',
            'industry_type' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'product_id' => 'nullable|integer|exists:products,id',
        ];

        if ($request->has('call_date')) {
            $rules['call_date'] = 'required|string';
        } else {
            $rules['call_date_date'] = 'required|date';
            $rules['call_date_hour'] = 'required|string|max:2';
            $rules['call_date_minute'] = 'required|string|max:2';
            $rules['call_date_ampm'] = 'required|string|in:AM,PM';
        }

        $validated = $request->validate($rules);

        // Construct Call Date & Time
        if (isset($validated['call_date'])) {
            try {
                $callDateTime = Carbon::parse($validated['call_date']);
            } catch (\Exception $e) {
                $callDateTime = Carbon::now();
            }
        } else {
            try {
                $hour = intval($validated['call_date_hour']);
                if ($validated['call_date_ampm'] === 'PM' && $hour < 12) {
                    $hour += 12;
                } elseif ($validated['call_date_ampm'] === 'AM' && $hour === 12) {
                    $hour = 0;
                }
                
                $timeString = sprintf('%02d:%02d:00', $hour, intval($validated['call_date_minute']));
                $callDateTime = Carbon::parse($validated['call_date_date'] . ' ' . $timeString);
            } catch (\Exception $e) {
                $callDateTime = Carbon::now();
            }
        }

        // Build data array
        $leadData = [
            'call_date' => $callDateTime,
            'lead_owner_id' => $validated['lead_owner_id'],
            'company_name' => $validated['company_name'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'requirement' => $validated['requirement'],
            'expected_amount' => $validated['expected_amount'] ? floatval($validated['expected_amount']) : 0.00,
            'expected_sale_date' => $validated['expected_sale_date'] ? Carbon::parse($validated['expected_sale_date']) : null,
            'source' => $validated['source'] ?: 'Select an Option',
            'priority' => $validated['priority'] ?: 'Select an Option',
            'segment' => $validated['segment'] ?: 'Select an Option',
            'industry_type' => $validated['industry_type'] ?? null,
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
        ];

        $oldOwnerId = $lead->lead_owner_id;

        // Direct DB update
        $lead->update($leadData);

        if ($oldOwnerId != $lead->lead_owner_id) {
            $oldOwnerName = $oldOwnerId ? (\App\Models\User::find($oldOwnerId)?->name ?: 'Unknown') : 'None';
            $newOwnerName = $lead->lead_owner_id ? (\App\Models\User::find($lead->lead_owner_id)?->name ?: 'Unknown') : 'None';
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead,
                'assigned',
                $oldOwnerName,
                $newOwnerName,
                "Lead owner updated from {$oldOwnerName} to {$newOwnerName}"
            );
        }

        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully updated in Database!');
    }

    /**
     * Update the lead status.
     */
    public function updateStatus(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'status' => 'required|string|in:New,Follow-up Scheduled,Contacted,Qualified,Converted,Lost',
        ]);

        // Security check 1: If already converted, prevent changing back to any other status
        if ($lead->is_customer && $validated['status'] !== 'Converted') {
            return redirect()->back()->withErrors(['status' => 'This lead has already been converted to a customer and its status cannot be changed.']);
        }

        $updateData = [
            'status' => $validated['status'],
        ];

        // Handle Conversion to Customer
        if ($validated['status'] === 'Converted' && !$lead->is_customer) {
            // Check if the lead has an accepted quotation
            $hasAcceptedQuotation = $lead->getQuotations()->where('status', 'Accepted')->isNotEmpty();
            if (!$hasAcceptedQuotation) {
                return redirect()->back()->withErrors(['status' => 'This lead cannot be converted to a customer because there is no accepted quotation.']);
            }

            $customer = $lead->getCustomer();
            if ($customer) {
                $customer->update(['status' => 'active']);
                $message = 'Lead successfully converted and Customer record activated!';
            } else {
                Customer::create([
                    'tenant_id' => $lead->tenant_id,
                    'name' => $lead->company_name ?: ($lead->contact_person ?: 'Converted Lead'),
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'status' => 'active',
                ]);
                $message = 'Lead successfully converted and Customer record created!';
            }

            $updateData['is_customer'] = true;
        }

        $oldStatus = $lead->status ?: 'New';
        $lead->update($updateData);

        if ($oldStatus !== $lead->status) {
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead,
                'status_changed',
                $oldStatus,
                $lead->status,
                "Lead stage status updated from '{$oldStatus}' to '{$lead->status}'"
            );
        }

        if (!isset($message)) {
            $message = 'Lead status successfully updated!';
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Update the lead owner.
     */
    public function updateOwner(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'lead_owner_id' => 'nullable|exists:users,id',
        ]);

        $lead->update([
            'lead_owner_id' => $validated['lead_owner_id'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Lead owner successfully updated!');
    }

    /**
     * Remove the specified lead from storage.
     */
    public function destroy(Lead $lead)
    {
        $lead->leadDocuments->each(function ($document) {
            Storage::disk('public')->delete($document->file_path);
            $document->delete();
        });
        $this->authorize('delete', $lead);

        $lead->delete();
        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully deleted from Database!');
    }

    /**
     * Upload documents for the lead.
     */
    public function uploadDocuments(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'documents' => 'required',
            'documents.*' => 'file|max:10240',
        ]);

        $uploadedIds = $lead->documents ?? [];

        foreach ($request->file('documents') as $file) {
            $path = $file->store('lead_documents/' . $lead->id, 'public');

            $document = LeadDocument::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'file_path' => $path,
                'size' => $file->getSize(),
            ]);

            $uploadedIds[] = $document->id;
        }

        $lead->documents = array_values(array_filter($uploadedIds));
        $lead->save();

        return redirect()->back()->with('success', 'Lead documents uploaded successfully!');
    }

    /**
     * View a lead document in the browser.
     */
    public function viewDocument(LeadDocument $document)
    {
        if (!$document->lead) {
            abort(404);
        }

        $path = Storage::disk('public')->path($document->file_path);
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        return response()->file($path);
    }

    /**
     * Download a lead document.
     */
    public function downloadDocument(LeadDocument $document)
    {
        if (!$document->lead) {
            abort(404);
        }

        $path = Storage::disk('public')->path($document->file_path);
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        return response()->download($path, $document->file_name);
    }

    /**
     * Delete a lead document.
     */
    public function deleteDocument(LeadDocument $document)
    {
        if (!$document->lead) {
            abort(404);
        }

        $lead = $document->lead;
        $document->delete();
        Storage::disk('public')->delete($document->file_path);

        $lead->documents = array_values(array_filter(array_diff($lead->documents ?? [], [$document->id])));
        $lead->save();

        return redirect()->back()->with('success', 'Lead document removed successfully.');
    }

    /**
     * Convert lead to customer and redirect to create quotation.
     */
    public function convertToQuotation(Lead $lead)
    {
        $this->authorize('update', $lead);

        // 1. Ensure the customer record is created for this lead as inactive
        $existingCustomer = null;
        if ($lead->email) {
            $existingCustomer = Customer::where('email', $lead->email)->first();
        }

        if (!$existingCustomer) {
            $customer = Customer::create([
                'tenant_id' => $lead->tenant_id,
                'name' => $lead->company_name ?: ($lead->contact_person ?: 'Converted Lead'),
                'email' => $lead->email,
                'phone' => $lead->phone,
                'status' => 'inactive',
            ]);
        } else {
            $customer = $existingCustomer;
        }

        // 2. DO NOT update lead status to Converted or is_customer to true here.
        // It will only be updated to Converted when the quotation is accepted.

        // 3. Redirect to Lead show page with create_quotation parameter
        return redirect()->route('crm.leads.show', [
            'lead' => $lead->id,
            'create_quotation' => 1
        ])->with('success', 'Quotation draft initiated! Please fill in the quotation details below.');
    }
}
