<?php

namespace App\Services\Approval;

use App\Domains\CRM\Models\Quotation;
use App\Domains\HRMS\Models\AssetRequest;
use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\ShiftChangeRequest;
use App\Domains\HRMS\Models\TravelRequest;
use App\Domains\HRMS\Models\WfhRequest;
use App\Domains\Inventory\Models\StockAdjustment;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Models\Routing;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesReturn;
use App\Models\User;
use App\Services\Access\AccessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

class ApprovalCenterService
{
    private const MAX_HEADER_ITEMS = 5;

    public function __construct(
        private readonly AccessService $accessService,
    ) {
    }

    /**
     * Get pending approvals currently actionable by the authenticated user across active modules.
     *
     * @return array{
     *     count: int,
     *     items: list<array{
     *         module: string,
     *         type: string,
     *         title: string,
     *         subtitle: string,
     *         url: string,
     *         icon: string,
     *         time: string|null,
     *         created_at: string|null
     *     }>
     * }
     */
    public function getPendingApprovals(?User $user): array
    {
        if ($user === null || empty($user->tenant_id)) {
            return [
                'count' => 0,
                'items' => [],
            ];
        }

        $isProdEnabled = $this->isProductionModuleEnabled();
        $isHrmsEnabled = $this->isHrmsModuleEnabled();
        $isCrmEnabled = $this->isCrmModuleEnabled();
        $isSalesEnabled = $this->isSalesModuleEnabled();
        $isPurchaseEnabled = $this->isPurchaseModuleEnabled();
        $isInventoryEnabled = $this->isInventoryModuleEnabled();

        if (! $isProdEnabled && ! $isHrmsEnabled && ! $isCrmEnabled && ! $isSalesEnabled && ! $isPurchaseEnabled && ! $isInventoryEnabled) {
            return [
                'count' => 0,
                'items' => [],
            ];
        }

        $totalActionableCount = 0;
        $candidateItems = [];

        // ==========================================
        // 1. Production Module Approvals
        // ==========================================
        if ($isProdEnabled) {
            $canApproveBom = $this->hasPermission($user, 'production.bom.approve');
            $canApproveRouting = $this->hasPermission($user, 'production.routing.approve');
            $canApprovePlan = $this->hasPermission($user, 'production.planning.approve') || $user->role === 'admin';
            $canApproveScrap = $this->hasPermission($user, 'production.quality.approve');
            $canApproveEco = $canApproveBom || $canApproveRouting || $user->role === 'admin';

            // BOM Approvals
            if ($canApproveBom) {
                $bomCount = ProductionBom::query()->where('status', 'pending_approval')->count();
                $totalActionableCount += $bomCount;

                if ($bomCount > 0) {
                    $boms = ProductionBom::query()
                        ->where('status', 'pending_approval')
                        ->with(['product'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($boms as $bom) {
                        $subtitle = $bom->bom_name ?: ($bom->product?->name ? "Product: {$bom->product->name}" : 'Approval required');
                        $candidateItems[] = [
                            'module' => 'Production',
                            'type' => 'BOM',
                            'title' => (string) $bom->bom_number,
                            'subtitle' => $subtitle,
                            'url' => Route::has('production.boms.show') ? route('production.boms.show', $bom->id) : url("/production/boms/{$bom->id}"),
                            'icon' => 'feather-layers',
                            'time' => $bom->created_at?->diffForHumans(),
                            'created_at' => $bom->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Routing Approvals
            if ($canApproveRouting) {
                $routingCount = Routing::query()->where('status', Routing::STATUS_PENDING_APPROVAL)->count();
                $totalActionableCount += $routingCount;

                if ($routingCount > 0) {
                    $routings = Routing::query()
                        ->where('status', Routing::STATUS_PENDING_APPROVAL)
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($routings as $routing) {
                        $subtitle = $routing->routing_number ? "Routing #{$routing->routing_number}" : 'Approval required';
                        $candidateItems[] = [
                            'module' => 'Production',
                            'type' => 'Routing',
                            'title' => (string) ($routing->name ?: "Routing #{$routing->id}"),
                            'subtitle' => $subtitle,
                            'url' => Route::has('production.routing.show') ? route('production.routing.show', $routing->id) : url("/production/routing/{$routing->id}"),
                            'icon' => 'feather-git-commit',
                            'time' => $routing->created_at?->diffForHumans(),
                            'created_at' => $routing->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Production Plan Approvals
            if ($canApprovePlan) {
                $planCount = ProductionPlan::query()->where('status', ProductionPlan::STATUS_PENDING_APPROVAL)->count();
                $totalActionableCount += $planCount;

                if ($planCount > 0) {
                    $plans = ProductionPlan::query()
                        ->where('status', ProductionPlan::STATUS_PENDING_APPROVAL)
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($plans as $plan) {
                        $subtitle = $plan->name ? (string) $plan->name : 'Approval required';
                        $candidateItems[] = [
                            'module' => 'Production',
                            'type' => 'Production Plan',
                            'title' => (string) $plan->plan_number,
                            'subtitle' => $subtitle,
                            'url' => Route::has('production.plans.show') ? route('production.plans.show', $plan->id) : url("/production/plans/{$plan->id}"),
                            'icon' => 'feather-calendar',
                            'time' => $plan->created_at?->diffForHumans(),
                            'created_at' => $plan->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Quality Scrap Disposal Approvals
            if ($canApproveScrap) {
                $scrapCount = ProductionScrapDisposal::query()->where('status', 'pending_approval')->count();
                $totalActionableCount += $scrapCount;

                if ($scrapCount > 0) {
                    $scraps = ProductionScrapDisposal::query()
                        ->where('status', 'pending_approval')
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($scraps as $scrap) {
                        $subtitle = $scrap->category ? "Category: {$scrap->category} ({$scrap->quantity} units)" : 'Approval required';
                        $candidateItems[] = [
                            'module' => 'Production',
                            'type' => 'Scrap Disposal',
                            'title' => "Scrap #{$scrap->id}",
                            'subtitle' => $subtitle,
                            'url' => Route::has('production.quality.scrap.index') ? route('production.quality.scrap.index') : url('/production/quality/scrap'),
                            'icon' => 'feather-trash-2',
                            'time' => $scrap->created_at?->diffForHumans(),
                            'created_at' => $scrap->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Engineering Change Order Approvals
            if ($canApproveEco) {
                $ecoCount = ProductionEco::query()->where('status', ProductionEco::STATUS_UNDER_REVIEW)->count();
                $totalActionableCount += $ecoCount;

                if ($ecoCount > 0) {
                    $ecos = ProductionEco::query()
                        ->where('status', ProductionEco::STATUS_UNDER_REVIEW)
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($ecos as $eco) {
                        $subtitle = $eco->title ? (string) $eco->title : 'Engineering Change Review';
                        $candidateItems[] = [
                            'module' => 'Production',
                            'type' => 'ECO',
                            'title' => (string) $eco->eco_number,
                            'subtitle' => $subtitle,
                            'url' => Route::has('production.ecos.show') ? route('production.ecos.show', $eco->id) : url("/production/ecos/{$eco->id}"),
                            'icon' => 'feather-git-pull-request',
                            'time' => $eco->created_at?->diffForHumans(),
                            'created_at' => $eco->created_at?->toIso8601String(),
                        ];
                    }
                }
            }
        }

        // ==========================================
        // 2. HRMS Module Approvals
        // ==========================================
        if ($isHrmsEnabled && $this->canApproveHrms($user)) {
            $isHrAdmin = $user->role === 'admin' || (method_exists($user, 'hasHrPermission') && $user->hasHrPermission('hr.settings.manage'));
            $canApproveLeave = $isHrAdmin || $this->hasPermission($user, 'hrms.leave.approve') || $this->hasPermission($user, 'hrms.leaves.manage');
            $canApproveWfh = $isHrAdmin || $this->hasPermission($user, 'hrms.wfh.approve') || $this->hasPermission($user, 'hrms.leaves.manage');
            $canApproveOvertime = $isHrAdmin || $this->hasPermission($user, 'hrms.overtime.approve') || $this->hasPermission($user, 'hrms.attendance.manage');
            $canApproveShift = $isHrAdmin || $this->hasPermission($user, 'hrms.shift.approve') || $this->hasPermission($user, 'hrms.attendance.manage');
            $canApproveAttendance = $isHrAdmin || $this->hasPermission($user, 'hrms.attendance.approve') || $this->hasPermission($user, 'hrms.attendance.manage');
            $canApproveExpense = $isHrAdmin || $this->hasPermission($user, 'hrms.travel_expenses.approve');
            $canApproveAsset = $isHrAdmin || $this->hasPermission($user, 'hrms.assets.allocate') || $this->hasPermission($user, 'hrms.assets.manage');
            $canApproveExit = $isHrAdmin || $this->hasPermission($user, 'hrms.exits.approve') || $this->hasPermission($user, 'hrms.employees.update');
            $canApprovePip = $isHrAdmin || $this->hasPermission($user, 'hrms.pip.evaluate') || $this->hasPermission($user, 'hrms.employees.update');
            $canApproveDoc = $isHrAdmin || $this->hasPermission($user, 'hrms.documents.approve') || $this->hasPermission($user, 'hrms.employees.update');

            // Leave Requests
            if ($canApproveLeave) {
                $leaveQuery = LeaveRequest::query()->where('status', 'pending');
                $leaveCount = (clone $leaveQuery)->count();
                $totalActionableCount += $leaveCount;
                if ($leaveCount > 0) {
                    $leaves = $leaveQuery->with(['employee', 'leaveType'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($leaves as $leave) {
                        $empName = $leave->employee?->full_name ?: 'Employee';
                        $leaveTypeName = $leave->leaveType?->name ?: 'Leave';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Leave Request',
                            'title' => "{$empName} - {$leaveTypeName}",
                            'subtitle' => "{$leave->duration} day(s) requested",
                            'url' => Route::has('hrms.leaves.index') ? route('hrms.leaves.index') : url('/hrms/leaves'),
                            'icon' => 'feather-calendar',
                            'time' => $leave->created_at?->diffForHumans(),
                            'created_at' => $leave->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // WFH Requests
            if ($canApproveWfh) {
                $wfhQuery = WfhRequest::query()->where('status', 'pending');
                $wfhCount = (clone $wfhQuery)->count();
                $totalActionableCount += $wfhCount;
                if ($wfhCount > 0) {
                    $wfhs = $wfhQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($wfhs as $wfh) {
                        $empName = $wfh->employee?->full_name ?: 'Employee';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'WFH Request',
                            'title' => "{$empName} - WFH",
                            'subtitle' => "{$wfh->duration} day(s) WFH requested",
                            'url' => Route::has('hrms.wfh.index') ? route('hrms.wfh.index') : url('/hrms/wfh'),
                            'icon' => 'feather-home',
                            'time' => $wfh->created_at?->diffForHumans(),
                            'created_at' => $wfh->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Overtime Requests
            if ($canApproveOvertime) {
                $otQuery = OvertimeRequest::query()->where('status', 'pending');
                $otCount = (clone $otQuery)->count();
                $totalActionableCount += $otCount;
                if ($otCount > 0) {
                    $ots = $otQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($ots as $ot) {
                        $empName = $ot->employee?->full_name ?: 'Employee';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Overtime Request',
                            'title' => "{$empName} - Overtime",
                            'subtitle' => "{$ot->duration_hours} hr(s) Overtime requested",
                            'url' => Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index') : url('/hrms/shift-overtime'),
                            'icon' => 'feather-clock',
                            'time' => $ot->created_at?->diffForHumans(),
                            'created_at' => $ot->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Shift Change Requests
            if ($canApproveShift) {
                $shiftQuery = ShiftChangeRequest::query()->where('status', 'pending');
                $shiftCount = (clone $shiftQuery)->count();
                $totalActionableCount += $shiftCount;
                if ($shiftCount > 0) {
                    $shifts = $shiftQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($shifts as $shift) {
                        $empName = $shift->employee?->full_name ?: 'Employee';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Shift Change',
                            'title' => "{$empName} - Shift Swap",
                            'subtitle' => 'Shift change approval requested',
                            'url' => Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index') : url('/hrms/shift-overtime'),
                            'icon' => 'feather-repeat',
                            'time' => $shift->created_at?->diffForHumans(),
                            'created_at' => $shift->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Attendance Corrections
            if ($canApproveAttendance) {
                $corrQuery = AttendanceCorrection::query()->where('status', 'pending');
                $corrCount = (clone $corrQuery)->count();
                $totalActionableCount += $corrCount;
                if ($corrCount > 0) {
                    $corrs = $corrQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($corrs as $corr) {
                        $empName = $corr->employee?->full_name ?: 'Employee';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Attendance Correction',
                            'title' => "{$empName} - Attendance Punch",
                            'subtitle' => 'Attendance correction approval requested',
                            'url' => Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : url('/hrms/attendance'),
                            'icon' => 'feather-check-circle',
                            'time' => $corr->created_at?->diffForHumans(),
                            'created_at' => $corr->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Expense Reports
            if ($canApproveExpense) {
                $expQuery = ExpenseReport::query()->whereIn('status', ['pending', 'submitted', 'l1_approved']);
                $expCount = (clone $expQuery)->count();
                $totalActionableCount += $expCount;
                if ($expCount > 0) {
                    $exps = $expQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($exps as $exp) {
                        $empName = $exp->employee?->full_name ?: 'Employee';
                        $amount = number_format((float) $exp->total_amount, 2);
                        $stageLabel = $exp->status === 'l1_approved' ? ' (Level 2)' : '';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Expense Report',
                            'title' => (string) ($exp->title ?: "Expense #{$exp->id}") . $stageLabel,
                            'subtitle' => "{$empName} • Total: \${$amount}",
                            'url' => Route::has('hrms.travel-expense.index') ? route('hrms.travel-expense.index') : url('/hrms/travel-expense'),
                            'icon' => 'feather-dollar-sign',
                            'time' => $exp->created_at?->diffForHumans(),
                            'created_at' => $exp->created_at?->toIso8601String(),
                        ];
                    }
                }

                // Travel Requests
                $travelQuery = TravelRequest::query()->whereIn('status', ['pending', 'l1_approved']);
                $travelCount = (clone $travelQuery)->count();
                $totalActionableCount += $travelCount;
                if ($travelCount > 0) {
                    $travels = $travelQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($travels as $travel) {
                        $empName = $travel->employee?->full_name ?: 'Employee';
                        $stageLabel = $travel->status === 'l1_approved' ? ' (Level 2)' : '';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Travel Request',
                            'title' => "{$empName} - Travel{$stageLabel}",
                            'subtitle' => "Destination: {$travel->destination}",
                            'url' => Route::has('hrms.travel-expense.index') ? route('hrms.travel-expense.index') : url('/hrms/travel-expense'),
                            'icon' => 'feather-briefcase',
                            'time' => $travel->created_at?->diffForHumans(),
                            'created_at' => $travel->created_at?->toIso8601String(),
                        ];
                    }
                }

                // Cash Advances
                $advQuery = CashAdvance::query()->whereIn('status', ['pending', 'l1_approved']);
                $advCount = (clone $advQuery)->count();
                $totalActionableCount += $advCount;
                if ($advCount > 0) {
                    $advs = $advQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($advs as $adv) {
                        $empName = $adv->employee?->full_name ?: 'Employee';
                        $amount = number_format((float) $adv->amount, 2);
                        $stageLabel = $adv->status === 'l1_approved' ? ' (Level 2)' : '';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Cash Advance',
                            'title' => "{$empName} - Cash Advance{$stageLabel}",
                            'subtitle' => "Amount: \${$amount}",
                            'url' => Route::has('hrms.travel-expense.index') ? route('hrms.travel-expense.index') : url('/hrms/travel-expense'),
                            'icon' => 'feather-credit-card',
                            'time' => $adv->created_at?->diffForHumans(),
                            'created_at' => $adv->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Asset Requests
            if ($canApproveAsset) {
                $assetReqQuery = AssetRequest::query()->whereIn('status', ['pending', 'partially_allocated']);
                $assetReqCount = (clone $assetReqQuery)->count();
                $totalActionableCount += $assetReqCount;
                if ($assetReqCount > 0) {
                    $assetReqs = $assetReqQuery->with(['employee', 'category'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($assetReqs as $assetReq) {
                        $empName = $assetReq->employee?->full_name ?: 'Employee';
                        $catName = $assetReq->category?->name ?: 'Asset';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Asset Request',
                            'title' => "{$empName} - {$catName}",
                            'subtitle' => 'Asset allocation pending',
                            'url' => Route::has('hrms.assets-module.index') ? route('hrms.assets-module.index') : url('/hrms/assets-module'),
                            'icon' => 'feather-box',
                            'time' => $assetReq->created_at?->diffForHumans(),
                            'created_at' => $assetReq->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Employee Exits
            if ($canApproveExit) {
                $exitQuery = EmployeeExit::query()->where('status', 'pending');
                $exitCount = (clone $exitQuery)->count();
                $totalActionableCount += $exitCount;
                if ($exitCount > 0) {
                    $exits = $exitQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($exits as $exit) {
                        $empName = $exit->employee?->full_name ?: 'Employee';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Employee Exit',
                            'title' => "{$empName} - Exit Approval",
                            'subtitle' => 'Offboarding & exit clearance required',
                            'url' => Route::has('hrms.exits.index') ? route('hrms.exits.index') : url('/hrms/exits'),
                            'icon' => 'feather-user-minus',
                            'time' => $exit->created_at?->diffForHumans(),
                            'created_at' => $exit->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // PIP Reviews
            if ($canApprovePip) {
                $pipQuery = PerformanceImprovementPlan::query()->whereIn('status', ['pending', 'under_review']);
                $pipCount = (clone $pipQuery)->count();
                $totalActionableCount += $pipCount;
                if ($pipCount > 0) {
                    $pips = $pipQuery->with(['employee'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($pips as $pip) {
                        $empName = $pip->employee?->full_name ?: 'Employee';
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'PIP Review',
                            'title' => (string) ($pip->pip_number ?: "PIP #{$pip->id}"),
                            'subtitle' => "{$empName} • Performance review required",
                            'url' => Route::has('hrms.pip.index') ? route('hrms.pip.index') : url('/hrms/pip'),
                            'icon' => 'feather-trending-up',
                            'time' => $pip->created_at?->diffForHumans(),
                            'created_at' => $pip->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Employee Documents
            if ($canApproveDoc) {
                $docQuery = Document::query()->where('status', 'pending');
                $docCount = (clone $docQuery)->count();
                $totalActionableCount += $docCount;
                if ($docCount > 0) {
                    $docs = $docQuery->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();
                    foreach ($docs as $doc) {
                        $docTitle = $doc->name ?: ($doc->file_name ?: "Document #{$doc->id}");
                        $candidateItems[] = [
                            'module' => 'HRMS',
                            'type' => 'Document Verification',
                            'title' => (string) $docTitle,
                            'subtitle' => 'Verification & approval required',
                            'url' => Route::has('hrms.documents.index') ? route('hrms.documents.index') : url('/hrms/documents'),
                            'icon' => 'feather-file-text',
                            'time' => $doc->created_at?->diffForHumans(),
                            'created_at' => $doc->created_at?->toIso8601String(),
                        ];
                    }
                }
            }
        }

        // ==========================================
        // 3. CRM Module Approvals (Quotations)
        // ==========================================
        if ($isCrmEnabled) {
            $canApproveQuote = $user->role === 'admin'
                || (bool) ($user->is_super_admin ?? false)
                || $this->hasPermission($user, 'crm.quotations.approve')
                || $this->hasPermission($user, 'crm.quotations.manage')
                || $this->hasPermission($user, 'crm.leads.manage');

            if ($canApproveQuote && class_exists(Quotation::class)) {
                $quoteQuery = Quotation::query()
                    ->where('is_current', true)
                    ->where('status', 'Pending Approval');
                $quoteCount = (clone $quoteQuery)->count();
                $totalActionableCount += $quoteCount;

                if ($quoteCount > 0) {
                    $quotes = $quoteQuery->with(['lead'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($quotes as $quote) {
                        $custName = $quote->lead?->company_name ?: ($quote->lead?->contact_person ?: 'Customer');
                        $amountFormatted = function_exists('format_currency')
                            ? format_currency($quote->total_amount ?? 0)
                            : '$' . number_format($quote->total_amount ?? 0, 2);

                        $candidateItems[] = [
                            'module' => 'CRM',
                            'type' => 'Quotation',
                            'title' => (string) $quote->quotation_number,
                            'subtitle' => "{$custName} • {$amountFormatted}",
                            'url' => Route::has('crm.approvals.quotations.index')
                                ? route('crm.approvals.quotations.index')
                                : (Route::has('crm.quotations.show') ? route('crm.quotations.show', $quote->id) : url("/crm/quotations/{$quote->id}")),
                            'icon' => 'feather-file-text',
                            'time' => $quote->created_at?->diffForHumans(),
                            'created_at' => $quote->created_at?->toIso8601String(),
                        ];
                    }
                }
            }
        }

        // ==========================================
        // 4. Purchase Module Approvals (PRs & POs)
        // ==========================================
        if ($isPurchaseEnabled) {
            $canApprovePr = $user->role === 'admin'
                || (bool) ($user->is_super_admin ?? false)
                || $this->hasPermission($user, 'purchase.requisitions.approve')
                || $this->hasPermission($user, 'purchase.requisitions.manage');
            $canApprovePo = $user->role === 'admin'
                || (bool) ($user->is_super_admin ?? false)
                || $this->hasPermission($user, 'purchase.orders.approve')
                || $this->hasPermission($user, 'purchase.orders.manage');

            // Purchase Requisitions
            if ($canApprovePr && class_exists(PurchaseRequisition::class)) {
                $prQuery = PurchaseRequisition::query()
                    ->whereIn('status', ['pending_approval', 'submitted', 'Draft', 'Pending']);
                $prCount = (clone $prQuery)->count();
                $totalActionableCount += $prCount;

                if ($prCount > 0) {
                    $prs = $prQuery->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($prs as $pr) {
                        $candidateItems[] = [
                            'module' => 'Purchase',
                            'type' => 'Purchase Requisition',
                            'title' => (string) ($pr->requisition_number ?: "PR #{$pr->id}"),
                            'subtitle' => 'Purchase Requisition approval required',
                            'url' => Route::has('purchase.pr-approvals.index')
                                ? route('purchase.pr-approvals.index')
                                : (Route::has('purchase.requisitions.show') ? route('purchase.requisitions.show', $pr->id) : url("/purchase/requisitions/{$pr->id}")),
                            'icon' => 'feather-file-plus',
                            'time' => $pr->created_at?->diffForHumans(),
                            'created_at' => $pr->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Purchase Orders
            if ($canApprovePo && class_exists(PurchaseOrder::class)) {
                $poQuery = PurchaseOrder::query()
                    ->whereIn('status', ['Draft', 'Pending', 'pending_approval']);
                $poCount = (clone $poQuery)->count();
                $totalActionableCount += $poCount;

                if ($poCount > 0) {
                    $pos = $poQuery->with(['vendor'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($pos as $po) {
                        $vName = $po->vendor?->name ?: 'Vendor';
                        $amountFormatted = function_exists('format_currency')
                            ? format_currency($po->grand_total ?? 0)
                            : '$' . number_format($po->grand_total ?? 0, 2);

                        $candidateItems[] = [
                            'module' => 'Purchase',
                            'type' => 'Purchase Order',
                            'title' => (string) ($po->purchase_order_number ?: "PO #{$po->id}"),
                            'subtitle' => "{$vName} • {$amountFormatted}",
                            'url' => Route::has('purchase.po-approvals.index')
                                ? route('purchase.po-approvals.index')
                                : (Route::has('purchase.orders.show') ? route('purchase.orders.show', $po->id) : url("/purchase/orders/{$po->id}")),
                            'icon' => 'feather-shopping-cart',
                            'time' => $po->created_at?->diffForHumans(),
                            'created_at' => $po->created_at?->toIso8601String(),
                        ];
                    }
                }
            }
        }

        // ==========================================
        // 5. Sales Module Approvals (Orders & Returns)
        // ==========================================
        if ($isSalesEnabled) {
            $canApproveSo = $user->role === 'admin'
                || (bool) ($user->is_super_admin ?? false)
                || $this->hasPermission($user, 'sales.orders.approve')
                || $this->hasPermission($user, 'sales.orders.manage');
            $canApproveSr = $user->role === 'admin'
                || (bool) ($user->is_super_admin ?? false)
                || $this->hasPermission($user, 'sales.returns.approve')
                || $this->hasPermission($user, 'sales.returns.manage');

            // Sales Orders
            if ($canApproveSo && class_exists(SalesOrder::class)) {
                $soQuery = SalesOrder::query()
                    ->whereIn('status', ['Draft', 'Pending', 'pending_approval']);
                $soCount = (clone $soQuery)->count();
                $totalActionableCount += $soCount;

                if ($soCount > 0) {
                    $sos = $soQuery->with(['customer'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($sos as $so) {
                        $cName = $so->customer?->name ?: 'Customer';
                        $amountFormatted = function_exists('format_currency')
                            ? format_currency($so->total_amount ?? 0)
                            : '$' . number_format($so->total_amount ?? 0, 2);

                        $candidateItems[] = [
                            'module' => 'Sales',
                            'type' => 'Sales Order',
                            'title' => (string) ($so->sales_order_number ?: "SO #{$so->id}"),
                            'subtitle' => "{$cName} • {$amountFormatted}",
                            'url' => Route::has('sales.orders.show')
                                ? route('sales.orders.show', $so->id)
                                : url("/sales/orders/{$so->id}"),
                            'icon' => 'feather-shopping-bag',
                            'time' => $so->created_at?->diffForHumans(),
                            'created_at' => $so->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Sales Returns
            if ($canApproveSr && class_exists(SalesReturn::class)) {
                $srQuery = SalesReturn::query()
                    ->whereIn('status', ['Pending', 'pending', 'Draft']);
                $srCount = (clone $srQuery)->count();
                $totalActionableCount += $srCount;

                if ($srCount > 0) {
                    $srs = $srQuery->with(['customer'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($srs as $sr) {
                        $cName = $sr->customer?->name ?: 'Customer';
                        $candidateItems[] = [
                            'module' => 'Sales',
                            'type' => 'Sales Return',
                            'title' => (string) ($sr->return_number ?: "Return #{$sr->id}"),
                            'subtitle' => "{$cName} • Return clearance required",
                            'url' => Route::has('sales.returns.show')
                                ? route('sales.returns.show', $sr->id)
                                : (Route::has('sales.returns.index') ? route('sales.returns.index') : url("/sales/returns")),
                            'icon' => 'feather-corner-up-left',
                            'time' => $sr->created_at?->diffForHumans(),
                            'created_at' => $sr->created_at?->toIso8601String(),
                        ];
                    }
                }
            }
        }

        // ==========================================
        // 6. Inventory / Store Module Approvals
        // ==========================================
        if ($isInventoryEnabled) {
            $canApproveTransfer = $user->role === 'admin'
                || (bool) ($user->is_super_admin ?? false)
                || $this->hasPermission($user, 'inventory.transfers.approve')
                || $this->hasPermission($user, 'inventory.transfers.manage')
                || $this->hasPermission($user, 'inventory.stock.manage');
            $canApproveAdjustment = $user->role === 'admin'
                || (bool) ($user->is_super_admin ?? false)
                || $this->hasPermission($user, 'inventory.adjustments.approve')
                || $this->hasPermission($user, 'inventory.adjustments.manage')
                || $this->hasPermission($user, 'inventory.stock.manage');

            // Stock Transfers
            if ($canApproveTransfer && class_exists(StockTransfer::class)) {
                $transferQuery = StockTransfer::query()
                    ->whereIn('status', ['pending', 'draft']);
                $transferCount = (clone $transferQuery)->count();
                $totalActionableCount += $transferCount;

                if ($transferCount > 0) {
                    $transfers = $transferQuery->with(['fromWarehouse', 'toWarehouse'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($transfers as $st) {
                        $from = $st->fromWarehouse?->name ?: 'Source';
                        $to = $st->toWarehouse?->name ?: 'Destination';
                        $candidateItems[] = [
                            'module' => 'Inventory',
                            'type' => 'Stock Transfer',
                            'title' => (string) ($st->transfer_number ?: "Transfer #{$st->id}"),
                            'subtitle' => "{$from} → {$to}",
                            'url' => Route::has('inventory.transfers.show')
                                ? route('inventory.transfers.show', $st->id)
                                : (Route::has('inventory.transfers.index') ? route('inventory.transfers.index') : url("/inventory/transfers")),
                            'icon' => 'feather-repeat',
                            'time' => $st->created_at?->diffForHumans(),
                            'created_at' => $st->created_at?->toIso8601String(),
                        ];
                    }
                }
            }

            // Stock Adjustments
            if ($canApproveAdjustment && class_exists(StockAdjustment::class)) {
                $adjQuery = StockAdjustment::query()
                    ->whereIn('status', ['pending', 'draft']);
                $adjCount = (clone $adjQuery)->count();
                $totalActionableCount += $adjCount;

                if ($adjCount > 0) {
                    $adjs = $adjQuery->with(['warehouse'])
                        ->orderBy('created_at', 'asc')
                        ->take(self::MAX_HEADER_ITEMS)
                        ->get();

                    foreach ($adjs as $adj) {
                        $wh = $adj->warehouse?->name ?: 'Warehouse';
                        $candidateItems[] = [
                            'module' => 'Inventory',
                            'type' => 'Stock Adjustment',
                            'title' => (string) ($adj->adjustment_number ?: "Adjustment #{$adj->id}"),
                            'subtitle' => "{$wh} • " . ($adj->reason ?: 'Stock audit adjustment'),
                            'url' => Route::has('inventory.adjustments.show')
                                ? route('inventory.adjustments.show', $adj->id)
                                : (Route::has('inventory.adjustments.index') ? route('inventory.adjustments.index') : url("/inventory/adjustments")),
                            'icon' => 'feather-sliders',
                            'time' => $adj->created_at?->diffForHumans(),
                            'created_at' => $adj->created_at?->toIso8601String(),
                        ];
                    }
                }
            }
        }

        // Bounded Results Sorting: oldest pending first for workflow queue priority
        usort($candidateItems, function (array $a, array $b): int {
            $timeA = $a['created_at'] ?? '';
            $timeB = $b['created_at'] ?? '';
            return strcmp($timeA, $timeB);
        });

        $finalItems = array_slice($candidateItems, 0, self::MAX_HEADER_ITEMS);

        return [
            'count' => $totalActionableCount,
            'items' => $finalItems,
        ];
    }

    /**
     * Verify if the Production module is active in tenant's subscription plan.
     */
    private function isProductionModuleEnabled(): bool
    {
        $planModules = tenant_allowed_modules();

        if ($planModules === null || in_array('*', $planModules, true)) {
            return true;
        }

        return in_array('production', $planModules, true);
    }

    /**
     * Verify if the HRMS module is active in tenant's subscription plan.
     */
    private function isHrmsModuleEnabled(): bool
    {
        $planModules = tenant_allowed_modules();

        if ($planModules === null || in_array('*', $planModules, true)) {
            return true;
        }

        return in_array('hrms', $planModules, true);
    }

    /**
     * Verify if the CRM module is active in tenant's subscription plan.
     */
    private function isCrmModuleEnabled(): bool
    {
        $planModules = tenant_allowed_modules();

        if ($planModules === null || in_array('*', $planModules, true)) {
            return true;
        }

        return in_array('crm', $planModules, true);
    }

    /**
     * Verify if the Sales module is active in tenant's subscription plan.
     */
    private function isSalesModuleEnabled(): bool
    {
        $planModules = tenant_allowed_modules();

        if ($planModules === null || in_array('*', $planModules, true)) {
            return true;
        }

        return in_array('sales', $planModules, true);
    }

    /**
     * Verify if the Purchase module is active in tenant's subscription plan.
     */
    private function isPurchaseModuleEnabled(): bool
    {
        $planModules = tenant_allowed_modules();

        if ($planModules === null || in_array('*', $planModules, true)) {
            return true;
        }

        return in_array('purchase', $planModules, true);
    }

    /**
     * Verify if the Inventory module is active in tenant's subscription plan.
     */
    private function isInventoryModuleEnabled(): bool
    {
        $planModules = tenant_allowed_modules();

        if ($planModules === null || in_array('*', $planModules, true)) {
            return true;
        }

        return in_array('inventory', $planModules, true);
    }

    /**
     * Check if the user is authorized to view / approve HRMS pending items.
     */
    private function canApproveHrms(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if (method_exists($user, 'hasHrPermission') && $user->hasHrPermission('hr.settings.manage')) {
            return true;
        }

        return $this->hasPermission($user, 'hrms.employees.update')
            || $this->hasPermission($user, 'hrms.leave.approve')
            || $this->hasPermission($user, 'hrms.leaves.manage')
            || $this->hasPermission($user, 'hrms.attendance.approve')
            || $this->hasPermission($user, 'hrms.attendance.manage')
            || $this->hasPermission($user, 'hrms.travel_expenses.approve')
            || $this->hasPermission($user, 'hrms.assets.manage')
            || $this->hasPermission($user, 'hrms.exits.approve');
    }

    /**
     * Check if the user holds the given permission in their tenant context.
     */
    private function hasPermission(User $user, string $permission): bool
    {
        return $this->accessService->allows($user, $permission, [
            'tenant_id' => $user->tenant_id,
            'branch_id' => $user->branch_id,
            'company_id' => $user->company_id,
            'department_id' => $user->department_id,
            'owner_id' => $user->id,
        ]);
    }
}

