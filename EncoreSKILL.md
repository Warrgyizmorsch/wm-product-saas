---
name: encore-erp-frs-requirements
description: Requirement guide for the Encore ERP SaaS (Laravel 12 modular monolith). For each FRS v3.5 module it states the CURRENT workflow as built, the REQUIRED workflow from the FRS, the changes needed (with tracker task IDs), how it connects to other modules, and acceptance checks. Use when planning, designing or implementing any change to CRM, Sales, Purchase, Inventory, Production, Projects, Accounting, HRMS, Visitor, Platform or the new domains (Design, Logistics, Installation, Service, Portal, Marketing, Fulfilment, Sustainability, Migration), or when asked "what does the FRS require here" or "how does this flow work today".
---

# Encore ERP - FRS requirement guide

Source: Encore Enterprise ERP Functional Requirements Specification v3.5 (1 Nov 2026) compared with the wm-product-saas codebase (code survey; the application was not run end to end). Companion files: Excel tracker `Encore_ERP_FRS_Progress_Tracker.xlsx` (task IDs A-xx, B-xx, C-xx, D-xx) and `HRMS_Test_Report.md`.

## Module status at a glance
- BUILT (small gaps): 2.2 Visitor, 2.3 Party master, 2.5 CRM (section 2.5 complete and tested; separate flows F04/F05/F10/F17 = C-06..C-08 open), 2.14 Production, 2.15 Quality, 2.20 Accounts, 2.22 HR.
- PARTIAL (improve existing): 2.1/2.26 Common+System, 2.6 Field app, 2.7 Quotation/Sales order, 2.9 Job Order/Projects, 2.10 Product/BOM/Planning, 2.11 Purchase/RFQ, 2.12 Job work/metal, 2.13 Stores/boards, 2.16 Assets/maintenance/tools, 2.18 Dispatch, 2.28 KPIs.
- NOT STARTED (build new): 2.4 Marketing, 2.8 Design, 2.17 Packing, 2.19 Installation, 2.21 Service (+F14 Portal), 2.23 Order tracking, 2.24 AI, 2.25 Carbon, 2.27 Odoo migration, plus F03 Documents, F11 Hand tools, F12 Consumables, F16 Compliance calendar, F17 Commission.

## How to use this skill
1. Start with `references/00-end-to-end-flow.md` for the whole order-to-cash / procure-to-pay chain: how it runs today versus what the FRS requires, and the new events needed.
2. Open the reference for the area you are changing:
   - `01-sales-and-crm.md` - Visitor, party master, Marketing, CRM, field app, quotation and sales order, commission
   - `02-engineering-and-planning.md` - Design, Job Order/projects, product/BOM/planning, documents
   - `03-procurement-and-stores.md` - Purchase/RFQ, job work and metal lots, stores/boards/offcuts, consumables
   - `04-manufacturing-quality-assets.md` - Production, quality, maintenance/assets/tools, material handover
   - `05-logistics-installation-service.md` - Dispatch, packing, installation, service/warranty, customer portal
   - `06-finance-hr-insight-platform.md` - Accounts, HR, KPIs, approvals/system, order tracking, AI, carbon, Odoo, compliance
   - `07-domain-placement-and-build-order.md` - where each new feature lives, setup checklist, phases, estimate
   - `08-change-log.md` - running record of what has been built so far (start here to see what is already done)
3. Each module section has the same shape: Current workflow -> Required workflow -> Changes required (task IDs) -> Connects with -> Acceptance.

## Rules to follow when implementing (from the project's CLAUDE.md and the FRS)
- Row-based tenancy: tenant-owned models extend `App\Core\Database\BaseModel`; use the helpers `tenant()`, `tenant_id()`, `require_tenant_id()`.
- Domain layout `app/Domains/{Module}/{Controllers,Models,Services,Repositories,DTO,Events,Listeners,Requests,Routes/web.php}`; thin controllers, logic in services, persistence behind repositories.
- Cross-module effects go through events/listeners; never query another module's tables directly.
- All permission checks go through `AccessService::allows($user, 'module.entity.action.scope', $context)`; policies call into it.
- Screens live in `resources/views/modules/{module}` (lowercase module names).
- FRS control principles that apply everywhere: no self-approval; posted records are never deleted (reversal/credit note/adjustment); every change keeps who/old/new/when; cost and margin hidden from operational roles; AI recommendations never authorise actions; integrations are idempotent with retry and an error list.

## Caveats
- "Current workflow" statements marked verified were read in code; others come from structure/naming and should be confirmed before relying on them.
- Effort and priority in the tracker are first estimates for the team to confirm.
