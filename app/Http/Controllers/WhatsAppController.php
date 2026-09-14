<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $waService
    ) {}

    public function index(): View
    {
        $config = $this->waService->getConfig();
        $status = $this->waService->getStatus();
        return view('modules.crm.whatsapp_settings.index', compact('config', 'status'));
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bridge_url'   => 'required|url',
            'bridge_token' => 'required|string',
        ]);

        $config = $this->waService->getConfig();
        $config->update([
            'bridge_url'   => $validated['bridge_url'],
            'bridge_token' => $validated['bridge_token'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp Database configuration updated successfully!',
            'config'  => $config,
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json($this->waService->getStatus());
    }

    public function connect(): JsonResponse
    {
        return response()->json($this->waService->connect());
    }

    public function disconnect(): JsonResponse
    {
        return response()->json($this->waService->disconnect());
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'mobile'  => 'required|string',
            'caption' => 'required|string',
        ]);

        $result = $this->waService->sendMessage(
            mobile: $request->input('mobile'),
            message: $request->input('caption')
        );

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 422);
        }
    }
}
