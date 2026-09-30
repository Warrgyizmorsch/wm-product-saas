<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\SopAssignment;
use App\Domains\HRMS\Models\SopCategory;
use App\Domains\HRMS\Models\SopDocument;
use App\Models\User;

interface SopRepositoryInterface
{
    /**
     * Get aggregate dashboard data, listing collections, and compliance analytics.
     */
    public function getIndexData(array $filters, ?Employee $currentEmployee, bool $isHrOrAdmin, int $tenantId): array;

    /**
     * Find an SOP document by ID with relations.
     */
    public function findDocument(int $id, int $tenantId): SopDocument;

    /**
     * Create a new SOP document with sections and initial version.
     */
    public function createDocument(array $validated, ?User $user): SopDocument;

    /**
     * Update an existing SOP document, sections, and bump version history.
     */
    public function updateDocument(SopDocument $sop, array $validated, ?User $user): SopDocument;

    /**
     * Delete an SOP document.
     */
    public function deleteDocument(SopDocument $sop): bool;

    /**
     * Store new SOP Category.
     */
    public function storeCategory(array $validated): SopCategory;

    /**
     * Update an SOP Category.
     */
    public function updateCategory(SopCategory $category, array $validated): bool;

    /**
     * Delete an SOP Category.
     */
    public function deleteCategory(SopCategory $category): bool;

    /**
     * Employee digital sign-off and acknowledgment.
     */
    public function acknowledgeDocument(SopDocument $sop, Employee $employee, array $data): SopAssignment;

    /**
     * Dispatch assignments to target employees.
     */
    public function dispatchAssignments(SopDocument $sop): int;

    /**
     * Send bulk compliance reminders.
     */
    public function sendBulkReminders(SopDocument $sop, int $tenantId): int;

    /**
     * Generate compliance matrix audit CSV.
     */
    public function generateAuditCsv(int $tenantId): string;
}
