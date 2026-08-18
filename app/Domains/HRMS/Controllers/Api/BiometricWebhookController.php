<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\BiometricDevice;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\HRMS\Models\Employee;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BiometricWebhookController extends Controller
{
    /**
     * POST /api/hrms/attendance/biometric-sync
     * Receive batch sync logs from local connector client (Authenticated via Sanctum token).
     */
    public function syncLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logs'                   => 'required|array',
            'logs.*.biometric_id'    => 'required|string',
            'logs.*.timestamp'       => 'required|date',
            'logs.*.punch_type'      => 'required|string|in:in,out,break_in,break_out,auto',
            'biometric_device_id'    => 'nullable|exists:biometric_devices,id',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $syncedCount = 0;
        foreach ($validated['logs'] as $log) {
            $employee = Employee::where('employee_id', $log['biometric_id'])->first();
            if (!$employee) {
                continue;
            }

            BiometricPunchLog::create([
                'tenant_id'           => $tenantId,
                'biometric_device_id' => $validated['biometric_device_id'] ?? null,
                'employee_id'         => $employee->id,
                'punch_time'          => Carbon::parse($log['timestamp']),
                'punch_type'          => $log['punch_type'],
                'processed'           => false,
                'raw_data'            => array_merge($log, ['ip' => $request->ip()]),
            ]);

            $syncedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully synced {$syncedCount} raw punches to staging logs.",
        ]);
    }

    /**
     * POST/GET /api/hrms/biometric/webhook
     * Webhook receiver for ADMS devices.
     */
    public function handleAdmsRequest(Request $request): Response
    {
        $serialNumber = $request->query('SN');
        if (!$serialNumber) {
            return response("registry=not_found", 400);
        }

        $device = BiometricDevice::where('device_serial', $serialNumber)->first();
        if (!$device) {
            return response("registry=not_found", 404);
        }

        $device->update(['last_ping_at' => now()]);

        $rawContent = $request->getContent();
        if (empty($rawContent)) {
            return response("OK\n");
        }

        $lines = explode("\n", $rawContent);
        $count = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = explode("\t", $line);
            if (count($parts) < 2) {
                continue;
            }

            $biometricId = $parts[0];
            $timestampStr = $parts[1];
            $stateVal = isset($parts[2]) ? (int)$parts[2] : 0;

            $employee = Employee::where('employee_id', $biometricId)->first();
            if (!$employee) {
                continue;
            }

            $punchType = 'auto';
            if ($stateVal === 0) $punchType = 'in';
            elseif ($stateVal === 1) $punchType = 'out';
            elseif ($stateVal === 2) $punchType = 'break_out';
            elseif ($stateVal === 3) $punchType = 'break_in';

            BiometricPunchLog::create([
                'tenant_id'           => $device->tenant_id,
                'biometric_device_id' => $device->id,
                'employee_id'         => $employee->id,
                'punch_time'          => Carbon::parse($timestampStr),
                'punch_type'          => $punchType,
                'processed'           => false,
                'raw_data'            => ['raw_line' => $line],
            ]);

            $count++;
        }

        return response("OK\n");
    }
}
