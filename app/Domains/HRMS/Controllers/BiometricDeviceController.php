<?php

namespace App\Domains\HRMS\Controllers;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Jobs\ProcessBiometricAttendance;
use App\Domains\HRMS\Models\AttendanceRule;
use App\Domains\HRMS\Models\BiometricDevice;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Repositories\BiometricDeviceRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BiometricDeviceController extends Controller
{
    public function __construct(
        protected readonly BiometricDeviceRepositoryInterface $repository
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BiometricDevice::class);

        $tenantId = tenant_id() ?? app(TenantContext::class)->id();
        $hasBiometricRule = AttendanceRule::where('office_biometric', true)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->exists();

        $data = $this->repository->getIndexData($request->all());
        $data['hasBiometricRule'] = $hasBiometricRule;
        $data['allEmployeesForSim'] = Employee::where('status', true)->orderBy('full_name')->get();

        return view('modules.hrms.biometric-devices.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', BiometricDevice::class);

        $tenantId = tenant_id() ?? app(TenantContext::class)->id();

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

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->repository->storeDevice($validated);

        return redirect()->route('hrms.biometric-devices.index')
            ->with('success', __('hrms.biometric.created_success'));
    }

    public function update(Request $request, BiometricDevice $biometricDevice): RedirectResponse
    {
        $this->authorize('update', $biometricDevice);

        $tenantId = tenant_id() ?? app(TenantContext::class)->id();

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'device_serial'    => [
                'required',
                'string',
                'max:255',
                Rule::unique('biometric_devices')->where('tenant_id', $tenantId)->ignore($biometricDevice->id),
            ],
            'company_id'       => 'required|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'ip_address'       => 'nullable|string|max:255',
            'port'             => 'required|integer|min:1|max:65535',
            'status'           => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->repository->updateDevice($biometricDevice, $validated);

        return redirect()->route('hrms.biometric-devices.index')
            ->with('success', __('hrms.biometric.updated_success'));
    }

    public function destroy(BiometricDevice $biometricDevice): RedirectResponse
    {
        $this->authorize('delete', $biometricDevice);

        $this->repository->deleteDevice($biometricDevice);

        return redirect()->route('hrms.biometric-devices.index')
            ->with('success', __('hrms.biometric.deleted_success'));
    }

    public function simulatePunch(Request $request): RedirectResponse
    {
        $this->authorize('create', BiometricDevice::class);

        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
            'biometric_device_id' => 'nullable|exists:biometric_devices,id',
            'punch_time'          => 'required|date',
            'punch_type'          => 'required|string|in:in,out,break_in,break_out,auto',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $tenantId = tenant_id() ?? app(TenantContext::class)->id();

        if (empty($employee->employee_id)) {
            $employee->update(['employee_id' => (string)$employee->id]);
        }

        $punchTime = Carbon::parse($validated['punch_time']);

        $log = BiometricPunchLog::create([
            'tenant_id'           => $tenantId,
            'biometric_device_id' => $validated['biometric_device_id'] ?: null,
            'employee_id'         => $employee->id,
            'punch_time'          => $punchTime,
            'punch_type'          => $validated['punch_type'],
            'processed'           => false,
            'raw_data'            => ['source' => 'web_simulator'],
        ]);

        // Process the punch synchronously so it updates the UI attendance records instantly
        ProcessBiometricAttendance::dispatchSync($employee->id, $punchTime->toDateString());

        return redirect()->route('hrms.biometric-devices.index', ['tab' => 'simulator'])
            ->with('success', __('hrms.biometric.mock_punch_success'));
    }

    public function testConnection(BiometricDevice $biometricDevice): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $biometricDevice);

        $ip = trim((string)$biometricDevice->ip_address);
        $port = (int)($biometricDevice->port ?: 4370);

        if (empty($ip)) {
            $lastPing = $biometricDevice->last_ping_at ? $biometricDevice->last_ping_at->diffForHumans() : 'Never';
            return response()->json([
                'success'   => true,
                'connected' => !empty($biometricDevice->last_ping_at),
                'mode'      => 'adms_push',
                'message'   => "Device operates in Cloud ADMS Push Mode. Last terminal heartbeat: {$lastPing}.",
                'last_ping' => $biometricDevice->last_ping_at ? $biometricDevice->last_ping_at->format('d M Y, h:i A') : 'Never',
            ]);
        }

        // Non-blocking TCP connection test with 2.0 second timeout
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($ip, $port, $errno, $errstr, 2.0);

        if ($socket) {
            fclose($socket);
            $biometricDevice->update(['last_ping_at' => now()]);

            return response()->json([
                'success'   => true,
                'connected' => true,
                'mode'      => 'direct_tcp',
                'message'   => "Device at {$ip}:{$port} is online and connected successfully!",
                'last_ping' => now()->format('d M Y, h:i A'),
            ]);
        }

        $lastPing = $biometricDevice->last_ping_at ? $biometricDevice->last_ping_at->diffForHumans() : 'Never';
        return response()->json([
            'success'   => false,
            'connected' => false,
            'mode'      => 'direct_tcp',
            'message'   => "Unable to reach {$ip}:{$port} directly (" . ($errstr ?: 'Connection timed out') . "). If device is behind a router/firewall, configure it to push punches via the Cloud ADMS Webhook.",
            'last_ping' => $biometricDevice->last_ping_at ? $biometricDevice->last_ping_at->format('d M Y, h:i A') : 'Never',
        ]);
    }
}
