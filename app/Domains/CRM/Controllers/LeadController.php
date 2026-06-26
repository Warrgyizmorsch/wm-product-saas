<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
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
        return view('modules.crm.leads.create', compact('lead'));
    }

    /**
     * Store a newly created lead.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
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
            'call_date' => 'required|string',
            'industry_type' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'product' => 'nullable|string|max:255',
        ]);

        // Parse Call Date & Time
        try {
            $callDateTime = Carbon::parse($validated['call_date']);
        } catch (\Exception $e) {
            $callDateTime = Carbon::now();
        }

        // Build data array
        $leadData = [
            'call_date' => $callDateTime,
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
            'industry_type' => $validated['industry_type'],
            'country' => $validated['country'],
            'state' => $validated['state'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'product' => $validated['product'],
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
        return view('modules.crm.leads.create', compact('lead'));
    }

    /**
     * Update the specified lead in storage.
     */
    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
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
            'call_date' => 'required|string',
            'industry_type' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'product' => 'nullable|string|max:255',
        ]);

        // Parse Call Date & Time
        try {
            $callDateTime = Carbon::parse($validated['call_date']);
        } catch (\Exception $e) {
            $callDateTime = Carbon::now();
        }

        // Build data array
        $leadData = [
            'call_date' => $callDateTime,
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
            'industry_type' => $validated['industry_type'],
            'country' => $validated['country'],
            'state' => $validated['state'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'product' => $validated['product'],
        ];

        // Direct DB update
        $lead->update($leadData);

        return redirect()->route('crm.leads.index')->with('success', 'Lead successfully updated in Database!');
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
