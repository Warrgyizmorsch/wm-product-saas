<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Repositories\ShiftChangeRequestRepositoryInterface;
use App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftOvertimeController extends Controller
{
    public function __construct(
        private readonly ShiftChangeRequestRepositoryInterface $shiftChangeRepository,
        private readonly OvertimeRequestRepositoryInterface $overtimeRepository
    ) {}

    public function index(Request $request): View
    {
        $shiftData = $this->shiftChangeRepository->getIndexData($request->all());
        $overtimeData = $this->overtimeRepository->getIndexData($request->all());

        // Merge all metrics and list datasets
        $data = array_merge($shiftData, $overtimeData);

        // Explicitly name lists and counts so they don't overwrite each other
        $data['shiftRequests']          = $shiftData['requests'];
        $data['overtimeRequests']       = $overtimeData['requests'];
        $data['shiftTotalRequests']     = $shiftData['totalRequests'];
        $data['shiftPendingRequests']   = $shiftData['pendingRequests'];
        $data['shiftApprovedRequests']  = $shiftData['approvedRequests'];
        $data['shiftRejectedRequests']  = $shiftData['rejectedRequests'];
        $data['overtimeTotalRequests']   = $overtimeData['totalRequests'];
        $data['overtimePendingRequests'] = $overtimeData['pendingRequests'];
        $data['overtimeApprovedRequests']= $overtimeData['approvedRequests'];
        $data['overtimeRejectedRequests']= $overtimeData['rejectedRequests'];

        $rawTab = $request->input('tab', 'shift');
        $data['activeTab'] = in_array($rawTab, ['shift-change', 'shift_change', 'shift']) ? 'shift' : $rawTab;

        return view('modules.hrms.shift-overtime.index', $data);
    }
}
