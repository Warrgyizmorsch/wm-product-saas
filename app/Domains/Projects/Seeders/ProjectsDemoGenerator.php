<?php

namespace App\Domains\Projects\Seeders;

use App\Domains\Projects\Seeders\Context\TenantIsolationContext;
use App\Domains\Projects\Seeders\Generators\ActivityLogGenerator;
use App\Domains\Projects\Seeders\Generators\CustomerPoolGenerator;
use App\Domains\Projects\Seeders\Generators\MilestoneGenerator;
use App\Domains\Projects\Seeders\Generators\ProjectGenerator;
use App\Domains\Projects\Seeders\Generators\ProjectMemberGenerator;
use App\Domains\Projects\Seeders\Generators\SubTaskGenerator;
use App\Domains\Projects\Seeders\Generators\TaskDependencyGenerator;
use App\Domains\Projects\Seeders\Generators\TaskGenerator;
use App\Domains\Projects\Seeders\Generators\TaskListGenerator;
use App\Domains\Projects\Seeders\Generators\UserPoolGenerator;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectsDemoGenerator
{
    public function execute(
        TenantIsolationContext $context,
        array $options,
        ?OutputStyle $output = null
    ): array {
        $projectCount = (int) ($options['projects'] ?? 20);
        $milestonesPerProject = (int) ($options['milestones'] ?? 18);
        $listsPerMilestone = (int) ($options['lists'] ?? 15);
        $tasksPerList = (int) ($options['tasks'] ?? 18);
        $userPoolSize = (int) ($options['users'] ?? 20);
        $profile = (string) ($options['profile'] ?? 'active-dev');
        $chunkSize = (int) ($options['chunk-size'] ?? 2000);
        $isWipe = (bool) ($options['wipe'] ?? false);
        $isDryRun = (bool) ($options['dry-run'] ?? false);
        $isFailFast = (bool) ($options['fail-fast'] ?? false);
        $seed = isset($options['seed']) ? (int) $options['seed'] : null;

        // 1. Seed RNG if deterministic
        if ($seed !== null) {
            mt_srand($seed);
            srand($seed);
        }

        // Calculate total estimates
        $estProjects = $projectCount;
        $estMembers = $projectCount * 5; // Avg 5 members
        $estMilestones = $projectCount * $milestonesPerProject;
        $estTaskLists = $estMilestones * $listsPerMilestone;
        $estTasks = $estTaskLists * $tasksPerList;
        $estSubTasks = (int) ($estTasks * 0.1); // ~10% tasks get subtasks
        $estLogs = $projectCount * 10;
        $estDependencies = $projectCount * 20;

        $estTotalRows = $estProjects + $estMembers + $estMilestones + $estTaskLists + $estTasks + $estSubTasks + $estLogs + $estDependencies;
        $estBatches = (int) ceil($estTasks / max(1, $chunkSize));

        // 2. Handle Dry-Run
        if ($isDryRun) {
            return [
                'dry_run' => true,
                'tenant_id' => $context->tenantId,
                'tenant_name' => $context->tenantName,
                'projects' => $estProjects,
                'user_pool' => $userPoolSize,
                'est_members' => $estMembers,
                'est_milestones' => $estMilestones,
                'est_task_lists' => $estTaskLists,
                'est_tasks' => $estTasks,
                'est_subtasks' => $estSubTasks,
                'est_activity_logs' => $estLogs,
                'est_task_dependencies' => $estDependencies,
                'est_total_rows' => $estTotalRows,
                'est_batches' => $estBatches,
                'est_memory' => '~50 MB',
                'est_time' => '< 15 seconds',
            ];
        }

        // Disable Query Log and Enable Performance Mode
        DB::disableQueryLog();
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // 3. Perform Wipe if requested
        if ($isWipe) {
            $this->wipeTenantProjectData($context->tenantId);
        }

        // 4. Generate User & Customer Pools
        $userIds = UserPoolGenerator::generate($context, $userPoolSize);
        $customerIds = CustomerPoolGenerator::generate($context, 10);

        // 5. Generate Projects
        $rawProjects = ProjectGenerator::generate($context, $userIds, $customerIds, $projectCount);

        $insertedCounts = [
            'projects' => 0,
            'members' => 0,
            'milestones' => 0,
            'task_lists' => 0,
            'tasks' => 0,
            'sub_tasks' => 0,
            'task_dependencies' => 0,
            'activity_logs' => 0,
        ];

        $taskBuffer = [];
        $subtaskBuffer = [];
        $globalTaskCounter = 1;

        if ($output) {
            $progressBar = $output->createProgressBar($projectCount);
            $progressBar->start();
        }

        foreach ($rawProjects as $index => $projectData) {
            try {
                DB::beginTransaction();

                // Insert Project
                $projectId = DB::table('projects')->insertGetId($projectData);
                $insertedCounts['projects']++;

                // Insert Project Activity Logs
                $activityLogs = ActivityLogGenerator::generateForProject(
                    $context,
                    $projectId,
                    $projectData['name'],
                    $userIds,
                    10
                );
                DB::table('project_activity_logs')->insert($activityLogs);
                $insertedCounts['activity_logs'] += count($activityLogs);

                // Insert Project Members
                $members = ProjectMemberGenerator::generateForProject($context, $projectId, $userIds);
                DB::table('project_members')->insert($members);
                $insertedCounts['members'] += count($members);

                // Insert Milestones
                $milestoneData = MilestoneGenerator::generateForProject(
                    $context,
                    $projectId,
                    $projectData['start_date'],
                    $userIds,
                    $milestonesPerProject
                );

                $projectTaskIds = [];

                foreach ($milestoneData as $mRaw) {
                    $milestoneId = DB::table('project_milestones')->insertGetId($mRaw);
                    $insertedCounts['milestones']++;

                    // Insert Task Lists
                    $listData = TaskListGenerator::generateForMilestone(
                        $context,
                        $projectId,
                        $milestoneId,
                        $userIds,
                        $listsPerMilestone
                    );

                    foreach ($listData as $lRaw) {
                        $listId = DB::table('project_task_lists')->insertGetId($lRaw);
                        $insertedCounts['task_lists']++;

                        // Generate Tasks
                        $taskData = TaskGenerator::generateForTaskList(
                            $context,
                            $projectId,
                            $milestoneId,
                            $listId,
                            $mRaw['start_date'],
                            $userIds,
                            $tasksPerList,
                            $profile,
                            $globalTaskCounter
                        );

                        foreach ($taskData as $taskRow) {
                            $taskId = DB::table('project_tasks')->insertGetId($taskRow);
                            $insertedCounts['tasks']++;

                            if (count($projectTaskIds) < 100) {
                                $projectTaskIds[] = $taskId;
                            }

                            // Generate SubTasks for sample tasks (~1 in 10 tasks)
                            if ($taskId % 10 === 0) {
                                $subtasks = SubTaskGenerator::generateForTask($context, $taskId, $userIds, 3);
                                foreach ($subtasks as $st) {
                                    $subtaskBuffer[] = $st;
                                    if (count($subtaskBuffer) >= $chunkSize) {
                                        DB::table('project_sub_tasks')->insert($subtaskBuffer);
                                        $insertedCounts['sub_tasks'] += count($subtaskBuffer);
                                        $subtaskBuffer = [];
                                    }
                                }
                            }
                        }
                    }
                }

                // Generate Task Dependencies for the project
                if (count($projectTaskIds) >= 2) {
                    $dependencies = TaskDependencyGenerator::generateForTaskIds(
                        $context,
                        $projectId,
                        $projectTaskIds,
                        20
                    );
                    if (!empty($dependencies)) {
                        DB::table('project_task_dependencies')->insert($dependencies);
                        $insertedCounts['task_dependencies'] += count($dependencies);
                    }
                }

                // Flush remaining subtasks buffer
                if (!empty($subtaskBuffer)) {
                    DB::table('project_sub_tasks')->insert($subtaskBuffer);
                    $insertedCounts['sub_tasks'] += count($subtaskBuffer);
                    $subtaskBuffer = [];
                }

                DB::commit();

                if ($output) {
                    $progressBar->advance();
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("Failed to seed project {$projectData['name']}: " . $e->getMessage());

                if ($isFailFast) {
                    if ($output) {
                        $progressBar->finish();
                    }
                    throw $e;
                }
            }
        }

        if ($output) {
            $progressBar->finish();
            $output->newLine();
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        $peakMemoryMB = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        return [
            'dry_run' => false,
            'tenant_id' => $context->tenantId,
            'tenant_name' => $context->tenantName,
            'inserted_projects' => $insertedCounts['projects'],
            'inserted_members' => $insertedCounts['members'],
            'inserted_milestones' => $insertedCounts['milestones'],
            'inserted_task_lists' => $insertedCounts['task_lists'],
            'inserted_tasks' => $insertedCounts['tasks'],
            'inserted_sub_tasks' => $insertedCounts['sub_tasks'],
            'inserted_task_dependencies' => $insertedCounts['task_dependencies'],
            'inserted_activity_logs' => $insertedCounts['activity_logs'],
            'total_inserted_rows' => array_sum($insertedCounts),
            'execution_time_seconds' => $executionTime,
            'peak_memory_mb' => $peakMemoryMB,
        ];
    }

    /**
     * Safely wipe tenant project data in reverse foreign key order.
     */
    protected function wipeTenantProjectData(int $tenantId): void
    {
        DB::transaction(function () use ($tenantId) {
            DB::table('project_sub_tasks')->where('tenant_id', $tenantId)->delete();
            DB::table('project_task_dependencies')->where('tenant_id', $tenantId)->delete();
            DB::table('project_tasks')->where('tenant_id', $tenantId)->delete();
            DB::table('project_task_lists')->where('tenant_id', $tenantId)->delete();
            DB::table('project_milestones')->where('tenant_id', $tenantId)->delete();
            DB::table('project_members')->where('tenant_id', $tenantId)->delete();
            DB::table('project_activity_logs')->where('tenant_id', $tenantId)->delete();
            DB::table('projects')->where('tenant_id', $tenantId)->delete();
        });
    }
}

