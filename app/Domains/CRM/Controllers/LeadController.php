<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeadController extends Controller
{
    /**
     * Display a listing of the leads.
     */
    public function index()
    {
        // Direct DB fetch
        $leads = Lead::latest()->get();

        // Calculate metrics
        $totalLeadsCount = $leads->count();
        $expectedRevenue = $leads->sum('expected_amount');
        $highPriorityCount = $leads->where('priority', 'High')->count();
        $enterpriseCount = $leads->where('segment', 'Enterprise')->count();

        $metrics = [
            'total' => $totalLeadsCount,
            'revenue' => $expectedRevenue,
            'high_priority' => $highPriorityCount,
            'enterprise' => $enterpriseCount,
        ];

        return view('modules.crm.leads.index', compact('leads', 'metrics'));
    }

    /**
     * Show the form for creating a new lead.
     */
    public function create()
    {
        $lead = new Lead();
        $users = User::orderBy('name')->get();
        return view('modules.crm.leads.create', compact('lead', 'users'));
    }

    /**
     * Display the specified lead.
     */
    public function show(Lead $lead)
    {
        $lead->load('followups');
        $users = User::orderBy('name')->get();
        return view('modules.crm.leads.show', compact('lead', 'users'));
    }

    /**
     * Store a newly created lead.
     */
    public function store(Request $request)
    {
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
            'product' => 'nullable|string|max:255',
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
            'product' => $validated['product'] ?? null,
        ];

        // Direct DB save
        Lead::create($leadData);

        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully saved to Database!');
    }


    /**
     * Show the form for editing the specified lead.
     */
    public function edit(Lead $lead)
    {
        $users = User::orderBy('name')->get();
        return view('modules.crm.leads.create', compact('lead', 'users'));
    }

    /**
     * Update the specified lead in storage.
     */
    public function update(Request $request, Lead $lead)
    {
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
            'product' => 'nullable|string|max:255',
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
            'product' => $validated['product'] ?? null,
        ];

        // Direct DB update
        $lead->update($leadData);

        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully updated in Database!');
    }

    /**
     * Update the lead status.
     */
    public function updateStatus(Request $request, Lead $lead)
    {
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
            // Check if customer with the same email already exists (if email is provided)
            $existingCustomer = null;
            if ($lead->email) {
                $existingCustomer = Customer::where('email', $lead->email)->first();
            }

            // Create Customer only if it doesn't already exist
            if (!$existingCustomer) {
                Customer::create([
                    'tenant_id' => $lead->tenant_id,
                    'name' => $lead->company_name ?: ($lead->contact_person ?: 'Converted Lead'),
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'status' => 'active',
                ]);
                $message = 'Lead successfully converted and Customer record created!';
            } else {
                $message = 'Lead successfully converted and linked to existing Customer!';
            }

            $updateData['is_customer'] = true;
        }

        $lead->update($updateData);

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
        $lead->delete();
        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully deleted from Database!');
    }
}
