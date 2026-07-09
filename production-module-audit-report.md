# Production Module Security, Quality & Architecture Audit Report

This report presents a comprehensive, read-only audit of the **Production** module of this Laravel 12 Multi-Tenant SaaS ERP. The audit was conducted using bootstrap reflection, controller-route analysis, database schema scanning, and layout/view reference tracing. 

---

## 1. Executive Summary

The Production module is built with clean separation of concerns and robust multi-tenant enforcement. Overall, the codebase exhibits high MVC conformity, strict validation layers, and consistent database transaction safety.

However, during this deep-dive audit, we identified several critical security vulnerabilities, performance bottlenecks, and model-schema discrepancies that should be addressed before deploying to a production environment.

### Codebase Health Metrics
* **Total Audited Models:** 50
* **Total Audited Controllers:** 34
* **Total Automated Tests:** 87 tests passed successfully (315 assertions)
* **MVC Separation Score:** 100% (No direct database queries executed in views)
* **Blade Reference Integrity:** 100% (Zero broken layouts, includes, or custom components)
* **Mass Assignment Conformity:** 100% (All models explicitly define `$fillable` or `$guarded = ['*']`)

---

## 2. Architecture & Domain Design

The Production module is structured as a domain-driven package under `app/Domains/Production`. It follows a standardized layout:
* **Models:** Extended from `App\Core\Database\BaseModel` to enforce tenant isolation.
* **Controllers:** Standard MVC controllers bridging routing requests to services.
* **Services:** Core business logic layers implementing transactions (`DB::transaction`) and raising domain events.
* **Repositories:** Data-access layers decoupling Eloquent queries from services.
* **DTOs (Data Transfer Objects):** Ensuring typed data validation between controllers and services.
* **Requests:** Custom FormRequest classes enforcing payload schema validations.
* **Policies:** Custom authorization classes mapping domain actions to user permissions.

---

## 3. Multi-Tenant Isolation & Security

### 3.1 Tenant Isolation Implementation
Tenant isolation is enforced globally through the `App\Models\Concerns\BelongsToTenant` trait. This trait is inherited by all Production models via `App\Core\Database\BaseModel`.
1. **Global Query Scope:** Automatically appends `where tenant_id = ?` to all database queries.
2. **Eloquent Event Hooking:** Automatically injects `tenant_id` from the current session context on model creation.
3. **Database Hardening:** Composite unique constraints `[tenant_id, number]` are used instead of global unique constraints across entities like NCRs, CAPAs, Rework Orders, and Deviations.

### 3.2 Authorization & RBAC Architecture
Authorization check calls delegate to `HasProductionPermissions::hasProductionPermission(string $permission, ?int $targetTenantId = null)`.
* This trait bridges checking logic to `App\Services\Access\AccessService::allows`.
* If a database role-permission check fails, it falls back to a **legacy permission map** defined in `config/production.php` based on the user's primary role slug.

---

### 3.3 Security Gaps & Vulnerabilities Identified

> [!CAUTION]
> **Vulnerability 1: Unprotected Controller Endpoint (Authorization Bypass)**
> * **File:** [ProductionOrderController.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Controllers/ProductionOrderController.php#L93-L103)
> * **Method:** `createFromPlan(int $planId)`
> * **Issue:** Unlike other creation methods in this controller, `createFromPlan` contains **no authorization gates** (e.g., `Gate::authorize()` or `this->authorize()`). Any authenticated user (even a shop operator without order privileges) can trigger a POST request to `plans/{plan}/create-order` and generate a Production Order from a plan.
> * **Impact:** High. Privilege escalation.

> [!WARNING]
> **Vulnerability 2: Missing Permission Gating on Lot Traceability**
> * **File:** [LotTraceabilityController.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Controllers/LotTraceabilityController.php#L19-L57)
> * **Methods:** `index(Request $request)`, `search(SearchLotTraceabilityRequest $request)`
> * **Issue:** The controller has **no permission gates** whatsoever. While routes are protected by the `auth` middleware, any authenticated tenant user can access and run searches on the detailed genealogy of batches, serial numbers, and production orders.
> * **Impact:** Medium. Unauthorized access to trace logs.

> [!CRITICAL]
> **Vulnerability 3: Cross-Tenant Data Leak Vectors in Service Layer**
> * **File:** [DashboardRefreshService.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Services/DashboardRefreshService.php#L67-L78)
> * **Methods:** `refreshMachineDashboard(int $tenantId, int $machineId)` and `refreshWorkCenterDashboard(int $tenantId, int $wcId)`
> * **Issue:** These methods use raw query builders (`DB::table`) bypassing Eloquent's global scopes. However:
>   * In `refreshMachineDashboard`, it queries `DB::table('production_machines')->where('id', $machineId)->first()` without checking if the machine belongs to the passed `$tenantId`.
>   * In `refreshWorkCenterDashboard`, it counts running machines inside `$wcId` without validating if the work center actually belongs to the passed `$tenantId`.
> * **Impact:** High. Cross-tenant leakage of machine status, run hours, OEE values, and work-center configurations if accessed via direct API endpoints or queue jobs.

---

## 4. Code Quality & MVC Separation

### 4.1 Mass Assignment Protection
Every production model is fully protected against mass assignment. All models define strict `$fillable` arrays. No models use `$guarded = []`.

### 4.2 Separation of Concerns (MVC Conformity)
No database queries, Eloquent retrievals, or raw SQL statements exist inside any Blade views under `resources/views/modules/production/`. All data is resolved in controllers or services and passed explicitly to views.

### 4.3 Layout & Include Integrity
All Blade layouts extended (e.g., `layouts.duralux`), all sub-views included (via `@include`), and all custom component references (e.g., `<x-ui.toast>`, `<x-ui.odoo-form-ui>`, etc.) resolve to existing, active files on disk. There are zero broken references.

---

## 5. SQL / Database & Performance

### 5.1 Eager Loading Analysis
Repositories consistently utilize eager loading (e.g., `with(['product', 'creator', 'operations.workCenter'])`) in listings to minimize the N+1 query problem.

### 5.2 SQL Performance Bottlenecks

> [!IMPORTANT]
> **Performance Bottleneck: Missing Pagination in Listings**
> * **Files:** [RoutingController.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Controllers/RoutingController.php#L32-L41), [WorkCenterController.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Controllers/WorkCenterController.php#L24-L34), and [MachineController.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Controllers/MachineController.php#L27-L37)
> * **Issue:** The `index` methods in these controllers call `$this->repository->getAll($filters)` which retrieves the **entire collection** of records. As data grows, this will cause high database overhead, high memory usage in PHP, and slow response times.
> * **Fix:** Implement pagination (`->paginate()`) on these repositories and controllers, matching the pattern used in `ProductionBomController` and `ProductionPlanController`.

---

## 6. Missing / Broken Features & Model Mismatches

### 6.1 Database Schema vs. Eloquent Model Discrepancy
* **Model:** [ProductionNcr.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Models/ProductionNcr.php)
* **Discrepancy:** The database schema and fillable array contain `batch_id` and `serial_number_id` columns, but the model has **no relation methods** defining `batch()` or `serialNumber()`. Any attempt to load `$ncr->batch` or `$ncr->serialNumber` will return undefined properties.

### 6.2 Missing UI Configurations (Backend Only)
* **Model:** [ProductionKpiTarget.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Models/ProductionKpiTarget.php)
* **Issue:** This model defines target KPI values used by `KpiCalculationService` to compute performance variances. However, **no controller, routes, or views** exist to allow tenant managers to edit, configure, or view these targets in the UI.

### 6.3 Missing Audit View (Logs Only)
* **Model:** [ProductionScanLog.php](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/app/Domains/Production/Models/ProductionScanLog.php)
* **Issue:** Barcode and QR scan logs are written to the database via `CodeService` during MES operator interactions, but there is **no UI view or log board** to inspect or audit these scans.

---

## 7. Test Coverage & Verification

Automated test verification was executed successfully:
```bash
php artisan test --filter=Production
```
* **Status:** Passed
* **Tests Run:** 87 tests
* **Assertions:** 315 assertions
* **Database Driver:** In-memory SQLite (replaces standard MySQL driver during test execution)

---

## 8. Audited Models Reference (50 Models)

| Model Name | Database Table | Tenancy Enforced | Relationships | Notes / Findings |
| :--- | :--- | :---: | :--- | :--- |
| **Machine** | `production_machines` | Yes | `workCenter`, `operations` | Active scope helper exists. |
| **ProductionAlertConfiguration** | `production_alert_configurations` | Yes | *None* | Used for automated trigger thresholds. |
| **ProductionBatch** | `production_batches` | Yes | `order`, `product`, `serials`, `parentGenealogies`, `childGenealogies` | Supports split & merge genealogies. |
| **ProductionBatchGenealogy** | `production_batch_genealogies` | Yes | `parentBatch`, `childBatch` | Junction table for batch mapping. |
| **ProductionBom** | `production_boms` | Yes | `product`, `baseUom`, `routing`, `items`, `approvals`, `creator`, `approver` | Major/Minor/Patch versioning service. |
| **ProductionBomApproval** | `production_bom_approvals` | Yes | `bom`, `user` | Audit log for BOM approval process. |
| **ProductionBomItem** | `production_bom_items` | Yes | `bom`, `childBom`, `material`, `uom` | Supports nested sub-BOM links. |
| **ProductionCalendar** | `production_calendars` | Yes | `holidays` | Shop floor operational calendar. |
| **ProductionCalendarHoliday** | `production_calendar_holidays` | Yes | `calendar` | Specific calendar exception dates. |
| **ProductionCapa** | `production_capas` | Yes | `ncr`, `owner`, `closer` | Quality CAPA action logs. |
| **ProductionDashboardPreference** | `production_dashboard_preferences` | Yes | `user` | User settings for MES dashboards. |
| **ProductionDeviation** | `production_deviations` | Yes | `approver` | Temp manufacturing variances. |
| **ProductionEventTimeline** | `production_event_timelines` | Yes | `order`, `operation`, `batch`, `serialNumber`, `machine`, `operator`, `triggerUser` | Timeline logging engine backend. |
| **ProductionKpiTarget** | `production_kpi_targets` | Yes | *None* | KPI targets. **No UI to configure.** |
| **ProductionLotTrace** | `production_lot_traces` | Yes | `source`, `target` | Lot tracing ledger. |
| **ProductionMachineDowntime** | `production_machine_downtimes` | Yes | `machine`, `workCenter`, `order`, `operation`, `creator`, `approver` | Machine downtime events tracker. |
| **ProductionMachineStateHistory** | `production_machine_state_histories` | Yes | `machine`, `changer` | Machine state timeline. |
| **ProductionNcr** | `production_ncrs` | Yes | `inspection`, `order`, `operation`, `machine`, `operator`, `closer`, `reworkOrder`, `scrapDisposal` | **Missing batch() & serialNumber() relationships.** |
| **ProductionOperatorAssignment** | `production_operator_assignments` | Yes | `operation`, `user`, `assigner`, `logs` | Operator allocation ledger. |
| **ProductionOperatorAssignmentLog** | `production_operator_assignment_logs` | Yes | `assignment`, `previousOperator`, `newOperator`, `changer` | Operator history tracking logs. |
| **ProductionOperatorSkill** | `production_operator_skills` | Yes | `user`, `workCenter`, `machine` | Skill levels mapped to equipment. |
| **ProductionOrder** | `production_orders` | Yes | `plan`, `product`, `bom`, `routing`, `creator`, `releaser`, `completer`, `closer`, `operations`, `reservations`, `issues`, `progressLogs`, `receipts`, `scraps`, `reworks`, `batches`, `serialNumbers` | Core order ledger tracking progress. |
| **ProductionOrderIssue** | `production_order_issues` | Yes | `order`, `reservation`, `product`, `user` | Material issues list. |
| **ProductionOrderOperation** | `production_order_operations` | Yes | `order`, `routingOperation`, `previousOperation`, `workCenter`, `machine`, `machineUsed`, `operator`, `progressLogs` | Shop floor step tracking. |
| **ProductionOrderProgressLog** | `production_order_progress_logs` | Yes | `order`, `operation`, `user`, `machine` | Progress log entries. |
| **ProductionOrderReceipt** | `production_order_receipts` | Yes | `order`, `product`, `user` | FG receipt ledger. |
| **ProductionOrderReservation** | `production_order_reservations` | Yes | `order`, `bomItem`, `product`, `uom`, `issues` | Material allocations. |
| **ProductionOrderRework** | `production_order_reworks` | Yes | `order`, `operation`, `user` | Logged rework sessions. |
| **ProductionOrderScrap** | `production_order_scraps` | Yes | `order`, `operation`, `product`, `user` | Material scrap logs. |
| **ProductionPlan** | `production_plans` | Yes | `product`, `bom`, `routing`, `creator`, `approver`, `requirements`, `operations` | Planning ledger. |
| **ProductionPlanOperation** | `production_plan_operations` | Yes | `plan`, `routingOperation`, `workCenter`, `machine` | Operations plan tracking. |
| **ProductionPlanRequirement** | `production_plan_requirements` | Yes | `plan`, `bomItem`, `product`, `uom`, `sourceItem` | MRP calculated allocations. |
| **ProductionQualityInspection** | `production_quality_inspections` | Yes | `plan`, `order`, `operation`, `machine`, `operator`, `auditor`, `results` | QA inspection entries. |
| **ProductionQualityInspectionResult** | `production_quality_inspection_results` | Yes | `inspection`, `parameter` | Specific results details. |
| **ProductionQualityPlan** | `production_quality_plans` | Yes | `product`, `workCenter`, `parameters`, `creator`, `approver` | Inspection checklists. |
| **ProductionQualityPlanParameter** | `production_quality_plan_parameters` | Yes | `plan` | Defined parameters for test. |
| **ProductionReworkOperation** | `production_rework_operations` | Yes | `reworkOrder`, `workCenter`, `machine` | Specific steps in rework cycle. |
| **ProductionReworkOrder** | `production_rework_orders` | Yes | `ncr`, `originalOrder`, `operations` | Rework order ticket. |
| **ProductionScanLog** | `production_scan_logs` | Yes | `user` | Scan log auditing. **No view in UI.** |
| **ProductionSchedule** | `production_schedules` | Yes | `order`, `creator`, `releasedBy`, `completedBy`, `cancelledBy`, `operations` | Shop floor schedule. |
| **ProductionScheduleOperation** | `production_schedule_operations` | Yes | `schedule`, `order`, `orderOperation`, `workCenter`, `machine`, `actualMachine` | Active task scheduler. |
| **ProductionScrapDisposal** | `production_scrap_disposals` | Yes | `ncr`, `disposer` | Scrap authorization ticket. |
| **ProductionSerialNumber** | `production_serial_numbers` | Yes | `order`, `batch`, `product` | Generated serial tracker. |
| **ProductionShift** | `production_shifts` | Yes | `workCenters` | Standard shifts configurations. |
| **Routing** | `routings` | Yes | `product`, `operations`, `approvals`, `boms`, `creator`, `approver` | Manufacturing steps structure. |
| **RoutingApproval** | `production_routing_approvals` | Yes | `routing`, `user` | Approval workflows logs. |
| **RoutingOperation** | `production_routing_operations` | Yes | `routing`, `workCenter`, `machine`, `materials`, `alternateMachines` | Operation step details. |
| **RoutingOperationAlternateMachine** | `production_routing_operation_alternate_machines` | Yes | `routingOperation`, `machine` | Alternate resource mappings. |
| **RoutingOperationMaterial** | `production_routing_operation_materials` | Yes | `routingOperation`, `material`, `uom` | Material consumption steps. |
| **WorkCenter** | `production_work_centers` | Yes | `parent`, `children`, `machines`, `calendar`, `shifts`, `activeMachines`, `operations` | Core work center configurations. |

---

## 9. Audited Controllers & Routes Reference (34 Controllers)

| Controller Name | Audited Methods / Endpoints | Authorization Gating | Validation Layer | DB Transaction (Safety) |
| :--- | :--- | :---: | :---: | :---: |
| **AlertController** | `index`, `update` | Manual check (`production.intelligence.view`) | Standard | Yes (in service) |
| **AnalyticsController** | `historical` | Manual check (`production.intelligence.view`) | Standard | No (read-only) |
| **AndonController** | `index` | Manual check (`production.intelligence.view`) | Standard | No (read-only) |
| **BatchProductionController** | `create`, `split`, `merge` | Policy (`manage`, ProductionBatch) | Request DTO | Yes (in service) |
| **CalendarController** | `index`, `create`, `store`, `edit`, `update`, `destroy` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **CapaController** | `index`, `create`, `store`, `show`, `saveRca`, `close` | Policy (`QualityManagementPolicy`) | Request | Yes (in service) |
| **DeviationController** | `index`, `store`, `approve` | Policy (`QualityManagementPolicy`) | Request | Yes (in service) |
| **DowntimeController** | `start`, `end` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **LotTraceabilityController** | `index`, `search` | **None (Bypass Risk)** | Request | No (read-only) |
| **MachineController** | `index`, `create`, `store`, `edit`, `update`, `destroy`, `byWorkCenter` | Gate check (`Machine::class`) | Request | Yes (in service) |
| **MachineDashboardController** | `index`, `show` | Manual check (`production.mes.execute`) | Standard | No (read-only) |
| **MachineStateController** | `overrideState` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **ManufacturingDashboardController** | `executiveDashboard`, `workCenterDashboard`, `savePreferences` | Manual check (`production.intelligence.view`) | Standard | No (read-only) |
| **MesController** | `dashboard`, `start`, `pause`, `resume`, `complete`, `hold`, `cancel`, `operatorDashboard`, `myOperations`, `operationExecution` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **NcrController** | `index`, `create`, `store`, `show`, `disposition`, `close` | Policy (`QualityManagementPolicy`) | Request | Yes (in service) |
| **OperatorAssignmentController** | `assign`, `reassign`, `accept`, `reject` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **OperatorSkillController** | `index`, `create`, `store`, `edit`, `update`, `destroy` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **ProductionBomController** | `index`, `checkChildBom`, `createRevision`, `show`, `create`, `store`, `edit`, `update`, `destroy`, `submitApproval`, `approve`, `reject`, `cancel`, `duplicateVersion` | Policy (`ProductionBomPolicy`) | Request DTO | Yes (in service) |
| **ProductionOrderController** | `index`, `create`, `store`, `createFromPlan`, `show`, `edit`, `update`, `destroy`, `release`, `complete`, `close`, `cancel`, `issueMaterial`, `returnMaterial`, `logProgress`, `logScrap`, `logRework`, `receiveFg` | Gate / Policy (Note: `createFromPlan` has **no auth check**) | Request DTO | Yes (in service) |
| **ProductionPlanController** | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `submitApproval`, `approve`, `reject`, `release`, `complete`, `close`, `cancel`, `runMrp`, `getEngineeringOptions` | Gate / Policy (`ProductionPlanPolicy`) | Request DTO | Yes (in service) |
| **ProductionScheduleController** | `index`, `create`, `store`, `show`, `destroy`, `release`, `cancel`, `calendarView`, `workCenterView` | Policy (`ProductionSchedulePolicy`) | Request | Yes (in service) |
| **ProductionTimelineController** | `index` | Manual check (`production.mes.execute`) | Standard | No (read-only) |
| **QualityDashboardController** | `index` | Policy (`QualityManagementPolicy`) | Standard | No (read-only) |
| **QualityInspectionController** | `index`, `create`, `store`, `show`, `saveResults`, `approve` | Policy (`QualityManagementPolicy`) | Request | Yes (in service) |
| **QualityPlanController** | `index`, `create`, `store`, `edit`, `update`, `destroy` | Policy (`QualityManagementPolicy`) | Request | Yes (in service) |
| **ReportsController** | `index`, `show` | Manual check (`production.intelligence.view`) | Standard | No (read-only) |
| **ReworkController** | `index`, `show`, `startOp`, `completeOp` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **RoutingController** | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `submitApproval`, `approve`, `reject`, `cancel`, `duplicateVersion`, `getOperationsForAjax` | Gate / Policy (`RoutingPolicy`) | Request DTO | Yes (in service) |
| **ScannerController** | `index`, `scan` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **ScrapController** | `index`, `store`, `approve` | Policy (`QualityManagementPolicy`) | Request | Yes (in service) |
| **SerialNumberController** | `generate`, `manualAssign` | Policy (`manage`, ProductionSerialNumber) | Request DTO | Yes (in service) |
| **ShiftController** | `index`, `create`, `store`, `edit`, `update`, `destroy` | Manual check (`production.mes.execute`) | Standard | Yes (in service) |
| **WorkCenterController** | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` | Gate check (`WorkCenter::class`) | Request DTO | Yes (in service) |
| **WorkCenterDashboardController** | `index`, `show` | Manual check (`production.mes.execute`) | Standard | No (read-only) |
