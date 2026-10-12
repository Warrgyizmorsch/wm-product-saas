# Project Management Module — Documentation Suite

## 1. Executive Summary

The **Project Management** module within the `wm-product-saas` Laravel 12 multi-tenant SaaS ERP delivers a native, domain-driven workspace for orchestrating client-facing and internal projects. Built strictly according to Domain-Driven Design (DDD) principles under `app/Domains/Projects/`, it bridges project planning, task scheduling, time tracking, quality control, change management, and billing without duplicating foundational ERP data structures.

This documentation suite reflects **the exact, live implementation as of October 2026**. It does not document aspirational roadmap items or hypothetical features; every workflow, data model, policy, and integration outlined here has been verified against migrations, Eloquent models, domain services, controllers, routes, and automated feature tests.

---

## 2. Audience & Reading Paths

This documentation suite serves diverse stakeholder personas across the enterprise:

| Stakeholder Persona | Primary Objectives | Recommended Reading Sequence |
| :--- | :--- | :--- |
| **Project Managers & Delivery Leads** | Schedule tasks, track critical paths, review timesheets, approve change requests, and close projects. | 1. [USER_MANUAL.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/USER_MANUAL.md)<br>2. [WORKFLOWS.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/WORKFLOWS.md)<br>3. [USE_CASES.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/USE_CASES.md)<br>4. [FAQ.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/FAQ.md) |
| **Team Members & Collaborators** | Execute assigned tasks, submit time logs, report issues, and attach deliverables. | 1. [USER_MANUAL.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/USER_MANUAL.md) (Sections 5–11)<br>2. [FAQ.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/FAQ.md) |
| **System Administrators** | Configure security roles, enforce multi-tenant isolation, manage user permissions, and audit activity. | 1. [ROLES_AND_PERMISSIONS.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/ROLES_AND_PERMISSIONS.md)<br>2. [TROUBLESHOOTING.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/TROUBLESHOOTING.md) |
| **Software Engineers & Architects** | Maintain, extend, and integrate the Project domain; inspect database schema, services, and event buses. | 1. [DEVELOPER_MANUAL.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/DEVELOPER_MANUAL.md)<br>2. [IMPLEMENTATION_AUDIT.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/IMPLEMENTATION_AUDIT.md)<br>3. [INTEGRATIONS.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/INTEGRATIONS.md) |
| **QA Engineers & Compliance Auditors** | Validate test coverage, audit closure gates, verify double-entry accounting boundaries, and inspect QA reports. | 1. [IMPLEMENTATION_AUDIT.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/IMPLEMENTATION_AUDIT.md)<br>2. [DOCUMENTATION_QA_REPORT.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/DOCUMENTATION_QA_REPORT.md) |
| **ERP Implementation Consultants** | Map enterprise business processes into the ERP; establish billing and customer boundaries. | 1. [INTEGRATIONS.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/INTEGRATIONS.md)<br>2. [USE_CASES.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/USE_CASES.md)<br>3. [GLOSSARY.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/GLOSSARY.md) |

---

## 3. Documentation Suite Map

The 12 documents comprising this suite are organized into a cohesive, fully cross-linked hierarchy:

```
docs/user-manuals/project-management/
├── README.md                      ← Master index, architecture summary, and reading paths
├── IMPLEMENTATION_AUDIT.md        ← Code-level feature matrix and phase verification
├── USER_MANUAL.md                 ← Step-by-step user guide with screenshot checklist
├── DEVELOPER_MANUAL.md            ← DDD technical guide, models, services, events, and schema
├── WORKFLOWS.md                   ← End-to-end Mermaid process workflows
├── USE_CASES.md                   ← 11 real-world enterprise operational scenarios
├── ROLES_AND_PERMISSIONS.md       ← Complete RBAC, policy rules, and multi-tenant scoping
├── INTEGRATIONS.md                ← ERP boundaries (CRM, Sales, Accounting, Production, HRMS)
├── TROUBLESHOOTING.md             ← Diagnostic playbooks and safe read-only SQL queries
├── FAQ.md                         ← Practical operational questions and authoritative answers
├── GLOSSARY.md                    ← Standardized ERP and project management terminology
└── DOCUMENTATION_QA_REPORT.md     ← Quality audit report, test evidence, and link integrity
```

---

## 4. Architectural Summary

The Project Management domain adheres strictly to modern Laravel 12 and enterprise DDD standards:

1. **Layered Domain Architecture**:
   - **Domain Models & Enums** (`app/Domains/Projects/Models/`): 13 models extending `App\Core\Database\BaseModel` with explicit `project_` table names.
   - **Domain Services** (`app/Domains/Projects/Services/`): 19 specialized services managing state transitions, business rules, and atomic database transactions.
   - **Repositories** (`app/Domains/Projects/Repositories/`): 13 repository interfaces with Eloquent implementations bound in `AppServiceProvider`.
   - **Controllers & Requests** (`app/Domains/Projects/Controllers/`, `Requests/`): 19 resource controllers and 29 granular Form Request validation classes.
2. **Multi-Tenant Data Isolation**:
   - Every model extends `App\Core\Database\BaseModel`, automatically inheriting tenant, company, and branch scoping via global traits.
   - All database queries, foreign key resolutions, and user lookups are restricted to the active session's tenant boundary (`tenant_id`).
3. **Canonical Resource Ownership**:
   - **Users/Employees**: The module references `App\Models\User` exclusively. No redundant `ProjectEmployee` entity exists. In-app alerts optionally attach `employee_id` if linked to an HRMS Employee.
   - **Customers**: The module references canonical CRM customers (`App\Domains\CRM\Models\Customer`) via `customer_id`. No redundant `ProjectClient` entity exists.
   - **Billing & Invoices**: Project Management **does not** own invoices or ledger accounts. It aggregates approved unbilled hours and completed unbilled milestones, delegates invoice creation to `SalesInvoiceCreationService::createDraftInvoice()` in the **Sales** domain, and receives back a standard Sales `Invoice` in `'Draft'` status.
   - **General Ledger Accounting**: Accounting postings are triggered solely when the Sales Invoice transitions to posted status via the `InvoicePosted` event handled by Sales's `PostSalesInvoiceJournal` listener. Project Management never writes directly to `journal_entries`.

---

## 5. Summary of Domain Capabilities

| Functional Area | Current Implementation Status | Key Domain Entities |
| :--- | :--- | :--- |
| **Project Master & Portfolio** | Fully Implemented | `Project`, `ProjectMember` |
| **Work Breakdown Structure (WBS)** | Fully Implemented | `TaskList`, `Task`, `SubTask` |
| **Scheduling, CPM & Gantt** | Fully Implemented | `TaskDependency`, `ProjectScheduleService` |
| **Time Tracking & Approvals** | Fully Implemented | `TimeLog`, `TimeLogService`, `TimesheetApprovalController` |
| **Issue & Defect Management** | Fully Implemented | `Issue`, `IssueService` |
| **Document Vault & Attachments** | Fully Implemented | `ProjectDocument`, `ProjectDocumentService` |
| **Quality Reviews & Change Requests** | Fully Implemented | `ProjectReview`, `ChangeRequest` |
| **Milestones & Deliverables** | Fully Implemented | `Milestone`, `MilestoneService` |
| **Project Billing & Invoicing Bridge** | Fully Implemented | `ProjectBillingService`, `SalesInvoiceCreationService` |
| **Controlled Closure (5 Gates)** | Fully Implemented | `ProjectClosureService::evaluateGates()` |
| **Domain Events & Notifications** | Fully Implemented | 12 Domain Events, `ProjectNotificationListener` |
| **Executive Dashboard & Reporting** | Fully Implemented | `ProjectDashboardService`, `ProjectReportService` (7 reports) |

---

## 6. Important Ownership Boundaries

A common misunderstanding in ERP implementations is domain responsibility overlap. The following rules govern this module:

> [!IMPORTANT]
> **Canonical Ownership Rules**:
> 1. **Project Management owns project execution, task schedules, timesheets, and issue tracking.**
> 2. **CRM owns Customer master records.** Projects merely associate `customer_id`.
> 3. **Sales owns Customer Invoices.** Project billing gathers billing items and calls `SalesInvoiceCreationService::createDraftInvoice()`.
> 4. **Accounting owns the General Ledger.** Accounting reacts asynchronously to `InvoicePosted` events emitted by Sales via `PostSalesInvoiceJournal`. Project Management never generates journal entries directly.
> 5. **Inventory owns Product Masters.** Project billing queries active 'Service' products for line item SKUs. No physical inventory movements occur.
> 6. **Production Planning operates independently.** Production orders have no `project_id` foreign keys in the current schema; manufacturing handoffs are coordinated administratively or via Make-to-Order sales orders.
> 7. **HRMS manages canonical User profiles.** Project Management assigns `User` instances as members and project managers without maintaining separate employee tables.

---

## 7. Evidence Rule & Verification Standard

This documentation is governed by an absolute evidence rule:
- **Source Code is Ground Truth**: If a requirement in a historical PRD or design specification differs from the live PHP code, migrations, or database schema, **the live code is documented and the discrepancy is recorded**.
- **No Inferred Functionality**: Features are documented as implemented only when verified via explicit routes, controllers, services, migrations, and automated test cases.
- **Strict Read-Only Verification**: During the creation and second-pass audit of this documentation suite, zero application code, migrations, views, or tests were altered.

For detailed audit results and automated test execution logs, consult [DOCUMENTATION_QA_REPORT.md](file:///c:/Users/windo/Documents/GitHub/wm-product-saas/docs/user-manuals/project-management/DOCUMENTATION_QA_REPORT.md).
