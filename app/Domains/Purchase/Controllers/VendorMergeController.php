<?php

declare(strict_types=1);

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use App\Services\PartyMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VendorMergeController extends Controller
{
    public function __construct(
        private readonly PartyMergeService $mergeService,
    ) {
    }

    /**
     * Preview records that will be moved during vendor merge.
     */
    public function preview(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorizeVendorMerge($request->user(), $vendor);

        $targetId = (int) $request->query('target_id');
        if (! $targetId) {
            return response()->json(['error' => 'Target vendor ID is required.'], 422);
        }

        try {
            $preview = $this->mergeService->preview(
                PartyMergeService::VENDOR,
                (int) $vendor->id,
                $targetId,
                $vendor->tenant_id
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
     * Execute vendor merge.
     */
    public function merge(Request $request, Vendor $vendor): RedirectResponse|JsonResponse
    {
        $this->authorizeVendorMerge($request->user(), $vendor);

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
                PartyMergeService::VENDOR,
                (int) $vendor->id,
                $targetId,
                $request->user(),
                $validated
            );

            $target = Vendor::withoutGlobalScopes()->find($targetId);
            $targetName = $target ? $target->name : "#{$targetId}";

            $message = "Vendor '{$vendor->name}' (#{$vendor->id}) was successfully merged into '{$targetName}' (#{$targetId}). All purchase orders, bills, payments, and branches have been consolidated.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'result' => $result,
                    'redirect_url' => route('purchase.vendors.show', $targetId),
                ]);
            }

            return redirect()
                ->route('purchase.vendors.show', $targetId)
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

    private function authorizeVendorMerge($user, Vendor $vendor): void
    {
        if ($vendor->isMerged()) {
            abort(422, "This vendor has already been merged into vendor #{$vendor->merged_into_id}.");
        }

        abort_unless(
            app(AccessService::class)->allows($user, 'purchase.vendors.merge', ['tenant_id' => $vendor->tenant_id]),
            403
        );
    }
}
