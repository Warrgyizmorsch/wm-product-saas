<?php

namespace App\Domains\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\GenerateProjectInvoiceRequest;
use App\Domains\Projects\Services\ProjectBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectBillingController extends Controller
{
    public function __construct(
        private readonly ProjectBillingService $billingService
    ) {}

    /**
     * Display billing overview and invoice ledger for the project.
     */
    public function index(Project $project): View|RedirectResponse
    {
        $this->authorize('viewBilling', $project);

        return redirect()->route('projects.show', [$project, 'tab' => 'billing']);
    }

    /**
     * Return live preview calculations for selected deliverables in the invoice generator modal.
     */
    public function preview(Project $project, Request $request): JsonResponse
    {
        $this->authorize('generateInvoice', $project);

        $payload = $request->validate([
            'time_log_ids'       => ['nullable', 'array'],
            'time_log_ids.*'     => ['integer'],
            'milestone_ids'      => ['nullable', 'array'],
            'milestone_ids.*'    => ['integer'],
            'service_product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $preview = $this->billingService->previewInvoice($project, $payload);

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Generate a standard Draft Sales Invoice from selected project deliverables.
     */
    public function store(GenerateProjectInvoiceRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('generateInvoice', $project);

        try {
            $invoice = $this->billingService->generateInvoice($project, $request->validated());

            return redirect()
                ->route('projects.show', [$project, 'tab' => 'billing'])
                ->with('success', __('projects.invoice_draft_generated_successfully', ['invoice_number' => $invoice->invoice_number])
                    ?: "Draft Invoice {$invoice->invoice_number} created successfully.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('projects.show', [$project, 'tab' => 'billing'])
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
