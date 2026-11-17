<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Repositories\ProjectMemberRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

class ProjectMemberService
{
    public function __construct(
        private readonly ProjectMemberRepositoryInterface $members,
    ) {
    }

    public function list(Project $project): Collection
    {
        return $this->members->getForProject($project->id);
    }

    public function find(int $id): ?ProjectMember
    {
        return $this->members->find($id);
    }

    public function activeMembers(Project $project): Collection
    {
        return $this->members->getActiveForProject($project->id);
    }

    /**
     * The single canonical "must be an active collaborator of this project"
     * check, reused by every project-level user-assignment field (Owner,
     * Manager, Milestone Owner, Task List Owner, ...) so the rule is defined
     * once here rather than duplicated per FormRequest.
     */
    public function activeCollaboratorRule(Project $project): Exists
    {
        return Rule::exists('project_members', 'user_id')
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->where('is_active', true);
    }

    /**
     * Idempotently guarantees a user is an active collaborator of a project —
     * called whenever a user is assigned as Owner/Manager/Milestone Owner/Task
     * List Owner, so the "assigned users are collaborators" invariant holds
     * automatically without requiring a manual add-collaborator step first.
     */
    public function ensureCollaborator(Project $project, int $userId): void
    {
        $existing = $this->members->findForProjectAndUser($project->id, $userId);

        if ($existing === null) {
            $this->members->create([
                'tenant_id' => $project->tenant_id,
                'company_id' => $project->company_id,
                'branch_id' => $project->branch_id,
                'project_id' => $project->id,
                'user_id' => $userId,
                'is_active' => true,
            ]);

            return;
        }

        if (!$existing->is_active) {
            $this->members->update($existing->id, ['is_active' => true]);
        }
    }

    /**
     * Tenant users not yet on the project — the pool "collaborator" search
     * picks from, since a collaborator is just a ProjectMember by another
     * name.
     */
    public function searchAvailableUsers(Project $project, ?string $q): Collection
    {
        return User::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereNotIn('id', $this->members->existingUserIds($project->id))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%');
                });
            })
            ->with(['employee.shift'])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'tenant_id']);
    }

    /**
     * Calculate an employee's baseline hourly cost derived from their Yearly CTC / Salary
     * and their assigned Shift Master working hours (after deducting break time).
     */
    public function calculateBaselineHourlyCost(User|int $user, ?Project $project = null): ?float
    {
        $userModel = $user instanceof User ? $user : User::with('employee.shift')->find($user);
        if (!$userModel) {
            return null;
        }

        // 1. Resolve employee: eager-loaded relation first
        $employee = ($userModel->relationLoaded('employee') && $userModel->employee)
            ? $userModel->employee
            : null;

        // 2. If unlinked or null, resolve via multi-tier canonical HRMS resolver (user_id, office_email/personal_email, full_name)
        if (!$employee) {
            $tenantId = $project?->tenant_id ?? $userModel->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : null);
            $employee = \App\Domains\HRMS\Models\Employee::resolveForUser($userModel, $tenantId);
        }

        if (!$employee) {
            return null;
        }

        // 3. Extract salary / CTC (fallback to latest salary revision if current_salary is not set)
        $salary = (float) ($employee->current_salary ?? 0);
        if ($salary <= 0) {
            $salary = (float) ($employee->revisions()->latest('effective_date')->value('new_ctc') ?? 0);
        }

        if ($salary <= 0) {
            return null;
        }

        // 4. Determine daily net work hours from assigned shift (accounting for breaks)
        if (!$employee->relationLoaded('shift')) {
            $employee->load('shift');
        }

        $shift = $employee->shift ?? $employee->defaultShift;
        $dailyHours = 8.0;
        if ($shift instanceof \App\Domains\Production\Models\ProductionShift) {
            $shiftDuration = $shift->durationHours();
            if ($shiftDuration > 0) {
                $dailyHours = $shiftDuration;
            }
        }

        // Standard working days per month: 20 days (accounting for weekends, public holidays & approved leaves)
        $monthlyDays = 20.0;
        $monthlyNetHours = max(1.0, $monthlyDays * $dailyHours);

        // 5. Normalize Yearly CTC vs Monthly Salary
        // If salary >= 100,000, treat as Yearly CTC (divide by 12 for monthly), else monthly.
        $monthlySalary = $salary >= 100000 ? ($salary / 12.0) : $salary;

        $costPerHour = $monthlySalary / $monthlyNetHours;

        return round($costPerHour, 2);
    }

    public function add(Project $project, array $data): ProjectMember
    {
        $this->guardAgainstDuplicate($project, (int) $data['user_id']);

        if (!isset($data['cost_per_hour'])) {
            $cost = $this->calculateBaselineHourlyCost((int) $data['user_id'], $project);
            if ($cost !== null) {
                $data['cost_per_hour'] = $cost;
            }
        }

        return DB::transaction(function () use ($project, $data) {
            $data['project_id'] = $project->id;
            $data['tenant_id'] = $project->tenant_id;
            if (!isset($data['company_id']) && $project->company_id) {
                $data['company_id'] = $project->company_id;
            }
            if (!isset($data['branch_id']) && $project->branch_id) {
                $data['branch_id'] = $project->branch_id;
            }

            return $this->members->create($data);
        });
    }

    public function update(ProjectMember $member, array $data): ProjectMember
    {
        if (isset($data['user_id']) && (int) $data['user_id'] !== $member->user_id) {
            $this->guardAgainstDuplicate($member->project, (int) $data['user_id']);
            $this->assertRemovable($member);
        }

        if ($member->is_active && array_key_exists('is_active', $data) && !$data['is_active']) {
            $this->assertRemovable($member);
        }

        return $this->members->update($member->id, $data);
    }

    public function setActive(ProjectMember $member, bool $active): ProjectMember
    {
        if (!$active) {
            $this->assertRemovable($member);
        }

        return $this->members->update($member->id, ['is_active' => $active]);
    }

    public function getActiveTasks(ProjectMember $member): Collection
    {
        return Task::query()
            ->where('project_id', $member->project_id)
            ->where('assignee_id', $member->user_id)
            ->whereIn('status', [
                Task::STATUS_OPEN,
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_REVIEW,
                Task::STATUS_ON_HOLD,
            ])
            ->get();
    }

    public function remove(ProjectMember $member, array $taskReassignments = []): bool
    {
        $this->assertRemovable($member);

        return DB::transaction(function () use ($member, $taskReassignments) {
            $activeTasks = $this->getActiveTasks($member);
            if ($activeTasks->isNotEmpty()) {
                if (empty($taskReassignments)) {
                    throw ValidationException::withMessages([
                        'member' => __('projects.collaborator_has_active_tasks_error', ['count' => $activeTasks->count()]),
                    ]);
                }

                foreach ($activeTasks as $task) {
                    if (array_key_exists($task->id, $taskReassignments)) {
                        $newAssignee = $taskReassignments[$task->id];
                        $task->update([
                            'assignee_id' => (!empty($newAssignee) && $newAssignee !== '__unassigned__') ? (int) $newAssignee : null,
                        ]);
                    }
                }
            }

            return $this->members->delete($member->id);
        });
    }

    public function assertCanRemove(ProjectMember $member): void
    {
        $this->assertRemovable($member);
    }

    /**
     * Display label for a collaborator's role — mirrors the same
     * Owner/Manager precedence assertRemovable() guards against.
     */
    public function roleLabel(ProjectMember $member, Project $project): string
    {
        if ((int) $project->owner_id === (int) $member->user_id) {
            return __('projects.owner');
        }

        if ($project->manager_id !== null && (int) $project->manager_id === (int) $member->user_id) {
            return __('projects.role_manager');
        }

        return $member->project_role ?: __('projects.role_collaborator');
    }

    private function guardAgainstDuplicate(Project $project, int $userId): void
    {
        if ($this->members->findForProjectAndUser($project->id, $userId)) {
            throw ValidationException::withMessages([
                'user_id' => __('projects.member_duplicate'),
            ]);
        }
    }

    /**
     * Blocks removing/deactivating a collaborator who is still assigned a
     * project role (Owner, Manager, Milestone Owner, Task List Owner) —
     * assigning another holder of that role must happen first.
     */
    private function assertRemovable(ProjectMember $member): void
    {
        $project = $member->project ?? Project::find($member->project_id);
        $userId = $member->user_id;

        if ($project && (int) $project->owner_id === (int) $userId) {
            throw ValidationException::withMessages([
                'member' => __('projects.collaborator_is_owner'),
            ]);
        }

        if ($project && $project->manager_id !== null && (int) $project->manager_id === (int) $userId) {
            throw ValidationException::withMessages([
                'member' => __('projects.collaborator_is_manager'),
            ]);
        }

        if (Milestone::query()->where('project_id', $member->project_id)->where('owner_id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'member' => __('projects.collaborator_is_milestone_owner'),
            ]);
        }

        if (TaskList::query()->where('project_id', $member->project_id)->where('owner_id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'member' => __('projects.collaborator_is_tasklist_owner'),
            ]);
        }
    }
}
