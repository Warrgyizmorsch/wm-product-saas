<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Core\Tenant\TenantContext;
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

        $tenantId = tenant_id() ?? app(TenantContext::class)->id();

        if (!empty($validated['biometric_device_id'])) {
            BiometricDevice::where('id', $validated['biometric_device_id'])->update(['last_ping_at' => now()]);
        }

        $syncedCount = 0;
        foreach ($validated['logs'] as $log) {
            $bioId = (string) $log['biometric_id'];
            $cleanBio = str_replace('-', '', $bioId);
            $employee = Employee::where(function ($q) use ($bioId, $cleanBio) {
                $q->where('employee_id', $bioId)
                  ->orWhereRaw("REPLACE(employee_id, '-', '') = ?", [$cleanBio]);
                if (is_numeric($bioId)) {
                    $q->orWhere('id', (int) $bioId);
                }
            })
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();

            if (!$employee) {
                continue;
            }

            try {
                $punchTime = Carbon::parse($log['timestamp']);
            } catch (\Throwable $e) {
                continue;
            }

            BiometricPunchLog::create([
                'tenant_id'           => $tenantId ?? $employee->tenant_id,
                'biometric_device_id' => $validated['biometric_device_id'] ?? null,
                'employee_id'         => $employee->id,
                'punch_time'          => $punchTime,
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
            return response("registry=not_found\n", 400);
        }

        $device = BiometricDevice::withoutGlobalScopes()->where('device_serial', $serialNumber)->first();
        if (!$device) {
            return response("registry=not_found\n", 404);
        }

        $device->update(['last_ping_at' => now()]);

        if ($device->tenant) {
            app(TenantContext::class)->set($device->tenant);
        }

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

            // Support both tab and comma delimited machine records
            $parts = str_contains($line, "\t") ? explode("\t", $line) : explode(",", $line);
            if (count($parts) < 2) {
                continue;
            }

            // Strip standard ADMS key prefixes if present (e.g., USERID=101, CHECKTIME=..., CHECKTYPE=...)
            $biometricId = preg_replace('/^(USERID|pin|id)=/i', '', trim($parts[0]));
            $timestampStr = preg_replace('/^(CHECKTIME|time|date)=/i', '', trim($parts[1]));
            $stateRaw = isset($parts[2]) ? preg_replace('/^(CHECKTYPE|state|status)=/i', '', trim($parts[2])) : '0';
            $stateVal = is_numeric($stateRaw) ? (int) $stateRaw : 0;

            $cleanBioId = str_replace('-', '', $biometricId);
            $employee = Employee::withoutGlobalScopes()
                ->where('tenant_id', $device->tenant_id)
                ->where(function ($q) use ($biometricId, $cleanBioId) {
                    $q->where('employee_id', $biometricId)
                      ->orWhereRaw("REPLACE(employee_id, '-', '') = ?", [$cleanBioId]);
                    if (is_numeric($biometricId)) {
                        $q->orWhere('id', (int) $biometricId);
                    }
                })
                ->first();

            if (!$employee) {
                continue;
            }

            $punchType = 'auto';
            if ($stateVal === 0 || strtoupper((string) $stateRaw) === 'I') $punchType = 'in';
            elseif ($stateVal === 1 || strtoupper((string) $stateRaw) === 'O') $punchType = 'out';
            elseif ($stateVal === 2) $punchType = 'break_out';
            elseif ($stateVal === 3) $punchType = 'break_in';

            try {
                $punchTime = Carbon::parse($timestampStr);
            } catch (\Throwable $e) {
                continue;
            }

            BiometricPunchLog::create([
                'tenant_id'           => $device->tenant_id,
                'biometric_device_id' => $device->id,
                'employee_id'         => $employee->id,
                'punch_time'          => $punchTime,
                'punch_type'          => $punchType,
                'processed'           => false,
                'raw_data'            => ['raw_line' => $line],
            ]);

            $count++;
        }

        return response("OK\n");
    }
}
