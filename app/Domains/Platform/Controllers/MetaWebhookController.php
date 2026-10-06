<?php

namespace App\Domains\Platform\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MetaLeadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class MetaWebhookController extends Controller
{
    public function __construct(
        protected MetaLeadService $metaLeadService
    ) {}

    /**
     * Entry point for Meta Webhook (Handles both GET verification & POST lead ingestion).
     */
    public function handle(Request $request): Response|JsonResponse
    {
        // 1. GET Request: Meta Webhook Handshake Verification
        if ($request->isMethod('get')) {
            $challenge = $this->metaLeadService->verifyWebhook($request);

            if ($challenge !== null) {
                return response($challenge, 200)
                    ->header('Content-Type', 'text/plain');
            }

            return response('Invalid or mismatched verification token.', 403)
                ->header('Content-Type', 'text/plain');
        }

        // 2. POST Request: Meta Lead Event Ingestion
        try {
            $results = $this->metaLeadService->processWebhook($request);

            return response()->json([
                'success' => true,
                'status' => 'EVENT_RECEIVED',
                'results' => $results,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 200); // Return 200 so Meta doesn't aggressively retry on parse failures
        }
    }
}
