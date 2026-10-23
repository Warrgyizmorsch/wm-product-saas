<?php

/*
|--------------------------------------------------------------------------
| Tenant file storage
|--------------------------------------------------------------------------
|
| New uploads are recorded in tenant_files as they're stored (TenantStorage).
| `php artisan tenant:storage-sync` reconciles that ledger with the disks:
| it drops records whose file was deleted, and backfills files that were
| uploaded before tracking existed, found through the path columns below.
|
| Each source: table + column holding a path (or a JSON list of paths when
| 'json' is true). The tenant comes from the row's tenant_id, or from
| 'parent' (table, foreign key) when the row has none. A path is looked up
| on each of 'disks' in turn.
|
*/

return [

    'disks' => ['public', 'local'],

    'sources' => [
        ['table' => 'attendances', 'column' => 'check_in_selfie_path'],
        ['table' => 'attendances', 'column' => 'check_out_selfie_path'],
        ['table' => 'broadcasts', 'column' => 'attachment_path'],
        ['table' => 'broadcasts', 'column' => 'banner_image_path'],
        ['table' => 'candidates', 'column' => 'resume_path'],
        ['table' => 'companies', 'column' => 'logo'],
        ['table' => 'dispatch_orders', 'column' => 'pod_attachment_path'],
        ['table' => 'documents', 'column' => 'file_path'],
        ['table' => 'documents', 'column' => 'signed_file_path'],
        ['table' => 'document_templates', 'column' => 'template_file_path'],
        ['table' => 'employees', 'column' => 'photo'],
        ['table' => 'employees', 'column' => 'resume_path'],
        ['table' => 'employee_exit_documents', 'column' => 'file_path'],
        ['table' => 'expense_claims', 'column' => 'receipt_path', 'parent' => ['table' => 'expense_reports', 'key' => 'expense_report_id']],
        ['table' => 'generated_documents', 'column' => 'file_path'],
        ['table' => 'goal_progress_logs', 'column' => 'attachment_path'],
        ['table' => 'helpdesk_ticket_attachments', 'column' => 'file_path', 'parent' => ['table' => 'helpdesk_tickets', 'key' => 'ticket_id']],
        ['table' => 'lead_documents', 'column' => 'file_path'],
        ['table' => 'leave_requests', 'column' => 'attachment_path'],
        ['table' => 'overtime_requests', 'column' => 'attachment_path'],
        ['table' => 'production_cost_adjustments', 'column' => 'attachment_path'],
        ['table' => 'production_quality_inspections', 'column' => 'attachments_json', 'json' => true],
        ['table' => 'products', 'column' => 'image_path'],
        ['table' => 'product_images', 'column' => 'file_path'],
        ['table' => 'product_images', 'column' => 'thumbnail_path'],
        ['table' => 'project_documents', 'column' => 'file_path'],
        ['table' => 'purchase_rfq_vendors', 'column' => 'attachment_path'],
        ['table' => 'shift_change_requests', 'column' => 'attachment_path'],
        ['table' => 'sop_documents', 'column' => 'attachment_path'],
        ['table' => 'sop_sections', 'column' => 'attachment_path'],
        ['table' => 'users', 'column' => 'avatar'],
        ['table' => 'wfh_requests', 'column' => 'attachment_path'],
    ],

];
