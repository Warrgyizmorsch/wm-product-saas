<?php

declare(strict_types=1);

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Customer;
use App\Http\Controllers\Controller;
use App\Services\PartyMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerMergeController extends Controller
{
    public function __construct(
        private readonly PartyMergeService $mergeService,
    ) {
    }

    /**
     * Preview records that will be moved during customer merge.
     */
    public function preview(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('merge', $customer);

        $targetId = (int) $request->query('target_id');
        if (! $targetId) {
            return response()->json(['error' => 'Target customer ID is required.'], 422);
        }

        try {
            $preview = $this->mergeService->preview(
                PartyMergeService::CUSTOMER,
                (int) $customer->id,
                $targetId,
                $customer->tenant_id
            );

            return response()->json([
                'success' => true,
                'preview' => $preview,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Execute customer merge.
     */
    public function merge(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $this->authorize('merge', $customer);

        $validated = $request->validate([
            'target_id' => ['required', 'integer'],
            'confirm_merge' => ['required', 'accepted'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'confirm_merge.accepted' => 'You must check the confirmation box to authorize merging these records.',
        ]);

        $targetId = (int) $validated['target_id'];

        try {
            $result = $this->mergeService->merge(
                PartyMergeService::CUSTOMER,
                (int) $customer->id,
                $targetId,
                $request->user(),
                $validated
            );

            $target = Customer::withoutGlobalScopes()->find($targetId);
            $targetName = $target ? $target->name : "#{$targetId}";

            $message = "Customer '{$customer->name}' (#{$customer->id}) was successfully merged into '{$targetName}' (#{$targetId}). All orders, invoices, payments, and branches have been consolidated.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'result' => $result,
                    'redirect_url' => route('crm.customers.show', $targetId),
                ]);
            }

            return redirect()
                ->route('crm.customers.show', $targetId)
                ->with('success', $message);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }
}
