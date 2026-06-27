<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Services\CustomerService;
use App\Domains\CRM\Services\QuotationService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotations,
        private readonly CustomerService $customers,
    ) {
    }

    public function index(): View
    {
        return view('modules.crm.quotations.index', [
            'quotations' => $this->quotations->latest(),
        ]);
    }

    public function create(): View
    {
        return view('modules.crm.quotations.create', [
            'quotation' => null,
            'customers' => $this->customers->latest(),
            'users' => User::query()->latest()->get(),
            'nextQuotationNumber' => $this->quotations->getNextQuotationNumber(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'sales_person_id' => ['nullable', 'exists:users,id'],
            'quotation_number' => ['required', 'string', 'max:255'],
            'quotation_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:Draft,Sent,Accepted,Declined'],
            'terms_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $quotation = $this->quotations->create($validated, $request->input('items'));

        return redirect()
            ->route('crm.quotations.show', $quotation->id)
            ->with('success', 'Quotation successfully created!');
    }

    public function show(int $id): View
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        return view('modules.crm.quotations.show', [
            'quotation' => $quotation,
        ]);
    }

    public function edit(int $id): View
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        return view('modules.crm.quotations.create', [
            'quotation' => $quotation,
            'customers' => $this->customers->latest(),
            'users' => User::query()->latest()->get(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'sales_person_id' => ['nullable', 'exists:users,id'],
            'quotation_number' => ['required', 'string', 'max:255'],
            'quotation_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:Draft,Sent,Accepted,Declined'],
            'terms_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->quotations->update($quotation, $validated, $request->input('items'));

        return redirect()
            ->route('crm.quotations.show', $quotation->id)
            ->with('success', 'Quotation successfully updated!');
    }

    public function destroy(int $id): RedirectResponse
    {
        $quotation = $this->quotations->find($id);

        if (!$quotation) {
            abort(404, 'Quotation not found.');
        }

        $this->quotations->delete($quotation);

        return redirect()
            ->route('crm.quotations.index')
            ->with('success', 'Quotation successfully deleted.');
    }
}
