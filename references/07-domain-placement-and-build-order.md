# 07 - Where each feature lives, and the build order

## New domains / shared services (create under app/Domains or app/Core)

| Name | Kind | Covers | Reads / connects with |
|---|---|---|---|
| Fulfilment | New domain (read-only) | 2.23 order tracking | every module via status contract |
| Logistics | New domain | 2.17 packing, 2.18 dispatch (move from Sales/Inventory), gate pass, site receipt | Production, Inventory, Accounting |
| Installation | New domain | 2.19 | Projects, Logistics, Accounting, Service |
| Service | New domain | 2.21 | Installation, CRM, Production QC, Portal |
| Portal | New domain (own guard) | F14 | Design, Sales, Accounting, Installation |
| Design | New domain | 2.8 | CRM, Sales, Production, Projects |
| Marketing | New domain | 2.4, F06 | CRM, Platform (Meta), Accounting |
| Sustainability | New domain | 2.25 | Purchase, Inventory, Production, Accounting |
| Migration | New domain or commands | 2.27 | all masters |
| Core/Documents | Shared service | F03 | Design, Projects, Purchase, Accounting, Portal |
| Core/Ai | Shared service | 2.24, F04, F05, F09 | CRM, Marketing, Platform |
| Approval engine | Shared service (extend app/Services/Approval) | 2.1 | all modules |

## Features that go into existing modules
Sales (engineering gate, change requests, confirmation pack, invoice gate) | CRM (SLA, stage gating, commission F17, party verification) | Purchase (RFQ merge, price approval, acknowledgement) | Production (configurable BOM, snapshot, job-work reconciliation, unit gating) | Inventory (metal lots, board fit, cycle count, hand tools F11, consumables F12) | Projects (auto Job Order, templates, handovers, closure gate) | Accounting (profitability, compliance calendar F16, multi-currency, reminders) | HRMS (field-app hardening, payroll controls, KPI scorecards).

## Setup checklist for every new domain
1. Standard folders: Controllers, Models, Services, Repositories, Events, Listeners, Requests, Routes/web.php (routes are auto-loaded).
2. Models extend BaseModel (tenant isolation); screens under resources/views/modules/{module}; menu.php for the sidebar.
3. Permissions seeded as module.entity.action.scope; policies call AccessService.
4. Provisioning listener for tenant defaults; register as a sellable module in Platform (TenantModule, ModulePrice).
5. Repository bindings in AppServiceProvider; cross-module effects go through events, never direct queries into another module's tables.

## Build order (four phases)
- Phase 1 - Job Order spine: A-01..A-27 (auto Job Order, engineering gate, options + BOM snapshot, change requests, earliest date, tracking board, approval engine).
- Phase 2 - Downstream operations: B-01..B-45 (packing, dispatch rework, installation, service, portal, metal lots, boards, tools, handover).
- Phase 3 - Front office and masters: C-01..C-38 (CRM controls, marketing, party master, RFQ merge, field app, design, documents).
- Phase 4 - Governance and rollout: D-01..D-30 (AI, carbon, finance extras, KPIs, Odoo, security, backup, acceptance tests).
Dependencies: Design before/with the engineering gate; Packing before dispatch rework; Installation before Service; Fulfilment early (add columns as stages arrive); AI, Carbon, Migration can run in parallel.

## Estimate (existing code counted as built)
About 1,700 person-days of development (about 80 person-months); +20% for testing/bug fixes/client review.
