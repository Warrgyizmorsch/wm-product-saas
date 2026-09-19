<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppBotService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $waService,
        protected WhatsAppBotService $botService
    ) {}

    public function index(): View
    {
        $config = $this->waService->getConfig();
        $status = $this->waService->getStatus();

        $tenantId = current_tenant_id() ?? $config->tenant_id;
        $messages = WhatsAppMessage::where('tenant_id', $tenantId)
            ->latest('id')
            ->take(50)
            ->get();

        return view('modules.platform.whatsapp_settings.index', compact('config', 'status', 'messages'));
    }

    public function messages(): JsonResponse
    {
        $config = $this->waService->getConfig();
        $tenantId = current_tenant_id() ?? $config->tenant_id;

        $messages = WhatsAppMessage::where('tenant_id', $tenantId)
            ->latest('id')
            ->take(50)
            ->get()
            ->map(function ($msg) {
                return [
                    'id'            => $msg->id,
                    'sender_number' => $msg->sender_number,
                    'sender_name'   => $msg->sender_name ?: $msg->sender_number,
                    'direction'     => $msg->direction,
                    'message_type'  => $msg->message_type,
                    'message_body'  => $msg->message_body,
                    'status'        => $msg->status,
                    'time_formatted'=> $msg->created_at ? $msg->created_at->format('d M Y, h:i A') : '',
                ];
            });

        return response()->json([
            'success'  => true,
            'messages' => $messages,
        ]);
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $sessionKey = $payload['session_key'] ?? 'default';
        $config = \App\Models\WhatsAppConfiguration::where('session_key', $sessionKey)->first()
            ?? $this->waService->getConfig();

        $rawNumber = (string) ($payload['sender_number'] ?? '');
        $senderNumber = str_contains($rawNumber, '@') ? trim($rawNumber) : preg_replace('/\D/', '', $rawNumber);
        $senderName = (!empty($payload['sender_name']) && $payload['sender_name'] !== $senderNumber) ? $payload['sender_name'] : ($senderNumber ?: 'Unknown');
        $messageBody = (string) ($payload['message_body'] ?? '');
        $messageId = $payload['message_id'] ?? null;
        $direction = $payload['direction'] ?? 'inbound';

        if (empty($messageBody) && empty($senderNumber)) {
            return response()->json(['status' => 'ignored', 'message' => 'Empty message body or sender']);
        }

        $msg = WhatsAppMessage::create([
            'tenant_id'     => $config->tenant_id,
            'company_id'    => $config->company_id,
            'branch_id'     => $config->branch_id,
            'session_key'   => $config->session_key,
            'sender_number' => $senderNumber,
            'sender_name'   => $senderName,
            'direction'     => $direction,
            'message_type'  => $payload['message_type'] ?? 'text',
            'message_body'  => $messageBody,
            'message_id'    => $messageId,
            'status'        => $direction === 'inbound' ? 'received' : 'sent',
            'received_at'   => now(),
        ]);

        // Trigger Interactive WhatsApp Bot for Inbound Messages
        if ($direction === 'inbound' && !empty($senderNumber) && !empty($messageBody)) {
            try {
                $this->botService->handleIncomingMessage($config, $senderNumber, $senderName, $messageBody, $messageId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('WhatsApp Bot Execution Error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status'     => 'success',
            'message_id' => $msg->id,
        ]);
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
