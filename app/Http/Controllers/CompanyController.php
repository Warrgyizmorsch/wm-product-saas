<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Organization;
use Illuminate\Http\Request;

class CompanyController extends Controller {
    public function index() {
        $companies = Company::all();

        return view('modules.hrms.org-structure.company', compact('companies'));
    }

    public function create() {
        return view('modules.hrms.org-structure.create-company');
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|max:255',
            'legal_name' => 'required|max:255',
            'gst_number' => 'nullable|max:50',
            'pan_number' => 'nullable|max:50',
            'cin_number' => 'nullable|max:100',
            'registration_number' => 'nullable|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|max:20',
            'website' => 'nullable',
            'address' => 'nullable',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'currency' => 'nullable', // Removed max:20 constraint since select option labels are long
            'time_zone' => 'nullable|max:100',
            'status' => 'required',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Ensure default organization exists for foreign key constraint
        Organization::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Default Organization',
                'slug' => 'default-organization',
                'subscription_plan' => 'enterprise',
                'status' => true,
            ]
        );

        $logo = null;

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('legal_entities', 'public');
        }

        // Clean currency input (e.g. "USD - US Dollar - $" -> "USD")
        $currency = null;
        if ($request->currency) {
            $parts = explode('-', $request->currency);
            $currency = trim($parts[0]);
            $currency = substr($currency, 0, 10);
        }

        // Normalize status to boolean
        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active');

        Company::create([
            'organization_id' => 1,
            'company_name' => $request->company_name,
            'legal_name' => $request->legal_name,
            'gst_number' => $request->gst_number,
            'pan_number' => $request->pan_number,
            'cin_number' => $request->cin_number,
            'registration_number' => $request->registration_number,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'currency' => $currency,
            'timezone' => $request->time_zone, // mapped to correct Eloquent model field 'timezone'
            'status' => $status,
            'logo' => $logo,
        ]);

        return redirect()->route('company.index')->with('success', 'Legal Entity created successfully.');
    }
}