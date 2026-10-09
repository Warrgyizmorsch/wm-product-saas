<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Jobs\ProcessBiometricAttendance;
use App\Domains\HRMS\Models\BiometricDevice;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\BiometricDeviceRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BiometricDeviceApiController extends Controller
{
    public function __construct(
        private readonly BiometricDeviceRepositoryInterface $repository
    ) {}

    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Check if user is authorized for HR settings/attendance administration.
     */
    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->role === 'admin'
            || $user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hrms.attendance.manage')
            || $user->hasHrPermission('hrms.biometric_devices.manage')
            || $user->hasHrPermission('hrms.biometric_devices.view');
    }

    /**
     * Null-safe authorization check.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please provide valid bearer token or credentials.', 401);
            }
        }

        return null;
    }

    /**
     * GET /api/hrms/biometric-devices
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $query = BiometricDevice::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['company:id,company_name', 'businessUnit:id,name', 'branch:id,name'])
            ->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('device_serial', 'like', $search)
                  ->orWhere('ip_address', 'like', $search);
            });
        }

        $perPage = min(100, max(5, (int) $request->input('per_page', 20)));
        $devices = $query->paginate($perPage);

        return $this->sendSuccess($devices, 'Biometric devices retrieved successfully.');
    }

    /**
     * GET /api/hrms/biometric-devices/summary
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $baseQuery = BiometricDevice::where('tenant_id', $tenantId);

        $summary = [
            'total_devices'   => (clone $baseQuery)->count(),
            'active_devices'  => (clone $baseQuery)->where('status', true)->count(),
            'inactive_devices'=> (clone $baseQuery)->where('status', false)->count(),
            'total_punches'   => BiometricPunchLog::where('tenant_id', $tenantId)->count(),
            'today_punches'   => BiometricPunchLog::where('tenant_id', $tenantId)->whereDate('punch_time', Carbon::today())->count(),
        ];

        return $this->sendSuccess($summary, 'Biometric summary loaded successfully.');
    }

    /**
     * POST /api/hrms/biometric-devices
     */
    public function store(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to register biometric device.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'device_serial'    => [
                'required',
                'string',
                'max:255',
                Rule::unique('biometric_devices')->where('tenant_id', $tenantId),
            ],
            'company_id'       => 'required|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'ip_address'       => 'nullable|string|max:255',
            'port'             => 'required|integer|min:1|max:65535',
            'status'           => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $device = $this->repository->storeDevice($validated);

        return $this->sendSuccess($device->fresh(['company:id,company_name', 'businessUnit:id,name', 'branch:id,name']), 'Biometric device registered successfully.', 201);
    }

    /**
     * GET /api/hrms/biometric-devices/{id}
     */
    public function show(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $device = BiometricDevice::with(['company:id,company_name', 'businessUnit:id,name', 'branch:id,name'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$device) {
            return $this->sendError("Biometric device with ID '{$id}' not found.", 404);
        }

        return $this->sendSuccess($device, 'Biometric device details loaded successfully.');
    }

    /**
     * PUT /api/hrms/biometric-devices/{id}
     */
    public function update(Request $request, mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to update biometric device.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $device = BiometricDevice::where('tenant_id', $tenantId)->find($id);

        if (!$device) {
            return $this->sendError("Biometric device with ID '{$id}' not found.", 404);
        }

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'device_serial'    => [
                'required',
                'string',
                'max:255',
                Rule::unique('biometric_devices')->where('tenant_id', $tenantId)->ignore($device->id),
            ],
            'company_id'       => 'required|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'ip_address'       => 'nullable|string|max:255',
            'port'             => 'required|integer|min:1|max:65535',
            'status'           => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->repository->updateDevice($device, $validated);

        return $this->sendSuccess($device->fresh(['company:id,company_name', 'businessUnit:id,name', 'branch:id,name']), 'Biometric device updated successfully.');
    }

    /**
     * DELETE /api/hrms/biometric-devices/{id}
     */
    public function destroy(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to delete biometric device.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $device = BiometricDevice::where('tenant_id', $tenantId)->find($id);

        if (!$device) {
            return $this->sendError("Biometric device with ID '{$id}' not found.", 404);
        }

        $this->repository->deleteDevice($device);

        return $this->sendSuccess(['id' => (int) $id], 'Biometric device deleted successfully.');
    }

    /**
     * POST /api/hrms/biometric-devices/simulate-punch
     */
    public function simulatePunch(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to simulate punch.', 403);
        }

        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
            'biometric_device_id' => 'nullable|exists:biometric_devices,id',
            'punch_time'          => 'required|date',
            'punch_type'          => 'required|string|in:in,out,break_in,break_out,auto',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;

        if (empty($employee->employee_id)) {
            $employee->update(['employee_id' => (string) $employee->id]);
        }

        $punchTime = Carbon::parse($validated['punch_time']);

        $log = BiometricPunchLog::create([
            'tenant_id'           => $tenantId,
            'biometric_device_id' => $validated['biometric_device_id'] ?: null,
            'employee_id'         => $employee->id,
            'punch_time'          => $punchTime,
            'punch_type'          => $validated['punch_type'],
            'processed'           => false,
            'raw_data'            => ['source' => 'api_simulator'],
        ]);

        // Process the punch synchronously so attendance is updated immediately
        ProcessBiometricAttendance::dispatchSync($employee->id, $punchTime->toDateString());

        return $this->sendSuccess([
            'punch_log_id' => $log->id,
            'employee_id'  => $employee->id,
            'employee_name'=> $employee->full_name,
            'punch_time'   => $punchTime->toIso8601String(),
            'punch_type'   => $validated['punch_type'],
        ], 'Mock biometric punch logged and processed successfully.');
    }

    /**
     * POST /api/hrms/biometric-devices/{id}/test-connection
     */
    public function testConnection(mixed $id): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to test biometric connection.', 403);
        }

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id;
        $device = BiometricDevice::where('tenant_id', $tenantId)->find($id);

        if (!$device) {
            return $this->sendError("Biometric device with ID '{$id}' not found.", 404);
        }

        $ip = trim((string)$device->ip_address);
        $port = (int)($device->port ?: 4370);

        if (empty($ip)) {
            $lastPing = $device->last_ping_at ? $device->last_ping_at->diffForHumans() : 'Never';
            return $this->sendSuccess([
                'connected' => !empty($device->last_ping_at),
                'mode'      => 'adms_push',
                'last_ping' => $device->last_ping_at?->toIso8601String(),
            ], "Device operates in Cloud ADMS Push Mode. Last heartbeat from terminal: {$lastPing}.");
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($ip, $port, $errno, $errstr, 2.0);

        if ($socket) {
            fclose($socket);
            $device->update(['last_ping_at' => now()]);

            return $this->sendSuccess([
                'connected' => true,
                'mode'      => 'direct_tcp',
                'ip'        => $ip,
                'port'      => $port,
                'last_ping' => now()->toIso8601String(),
            ], "Device at {$ip}:{$port} is online and connected successfully!");
        }

        return $this->sendError("Unable to reach {$ip}:{$port} directly (" . ($errstr ?: 'Connection timed out') . "). If behind NAT, configure Cloud ADMS Push Mode.", 200, [
            'connected' => false,
            'mode'      => 'direct_tcp',
            'ip'        => $ip,
            'port'      => $port,
            'last_ping' => $device->last_ping_at?->toIso8601String(),
        ]);
    }
}
