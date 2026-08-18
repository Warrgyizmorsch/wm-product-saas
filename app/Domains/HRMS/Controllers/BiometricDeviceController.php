<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Jobs\ProcessBiometricAttendance;
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
        $data = $this->repository->getIndexData($request->all());
        
        $data['allEmployeesForSim'] = Employee::where('status', true)->orderBy('full_name')->get();

        return view('modules.hrms.biometric-devices.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

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
            'ip_address'       => 'nullable|ip',
            'port'             => 'required|integer|min:1|max:65535',
            'status'           => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->repository->storeDevice($validated);

        return redirect()->route('hrms.biometric-devices.index')
            ->with('success', 'Biometric device registered successfully.');
    }

    public function update(Request $request, BiometricDevice $biometricDevice): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

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
            'ip_address'       => 'nullable|ip',
            'port'             => 'required|integer|min:1|max:65535',
            'status'           => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $this->repository->updateDevice($biometricDevice, $validated);

        return redirect()->route('hrms.biometric-devices.index')
            ->with('success', 'Biometric device updated successfully.');
    }

    public function destroy(BiometricDevice $biometricDevice): RedirectResponse
    {
        $this->repository->deleteDevice($biometricDevice);

        return redirect()->route('hrms.biometric-devices.index')
            ->with('success', 'Biometric device deleted successfully.');
    }

    public function simulatePunch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
            'biometric_device_id' => 'nullable|exists:biometric_devices,id',
            'punch_time'          => 'required|date',
            'punch_type'          => 'required|string|in:in,out,break_in,break_out,auto',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        if (empty($employee->employee_id)) {
            $employee->update(['employee_id' => (string)$employee->id]);
        }

        $log = BiometricPunchLog::create([
            'tenant_id'           => $tenantId,
            'biometric_device_id' => $validated['biometric_device_id'] ?: null,
            'employee_id'         => $employee->id,
            'punch_time'          => Carbon::parse($validated['punch_time']),
            'punch_type'          => $validated['punch_type'],
            'processed'           => false,
            'raw_data'            => ['source' => 'web_simulator'],
        ]);

        // Process the punch synchronously so it updates the UI attendance records instantly
        ProcessBiometricAttendance::dispatchSync($employee->id, $log->punch_time->toDateString());

        return redirect()->route('hrms.biometric-devices.index', ['tab' => 'simulator'])
            ->with('success', 'Mock punch logged and processed successfully! Check Admin Attendance to view the updated entry.');
    }
}
