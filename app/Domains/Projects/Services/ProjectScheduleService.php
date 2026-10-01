<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Repositories\TaskDependencyRepositoryInterface;
use App\Domains\Projects\Repositories\TaskRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectScheduleService
{
    public const SHIFT_MODE_RIPPLE = 'ripple';
    public const SHIFT_MODE_ISOLATED = 'isolated';

    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly TaskDependencyRepositoryInterface $dependencies,
        private readonly ActivityLogService $activity,
    ) {
    }

    /**
     * Compute full project schedule and Critical Path Method (CPM) metrics.
     *
     * @return array{
     *     range: array{start: string, end: string},
     *     project: array{id: int, code: string, name: string, start_date: ?string, end_date: ?string},
     *     milestones: array<int, array>,
     *     tasks: array<int, array>,
     *     dependencies: array<int, array>,
     *     critical_path_count: int
     * }
     */
    public function calculateSchedule(Project $project, ?int $milestoneId = null): array
    {
        $allTasks = Task::query()
            ->where('project_id', $project->id)
            ->when($milestoneId, fn ($q) => $q->where('milestone_id', $milestoneId))
            ->with(['milestone', 'assignee', 'dependencies.dependsOn', 'dependents.task'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($allTasks->isEmpty()) {
            $today = Carbon::today()->format('Y-m-d');
            $twoWeeks = Carbon::today()->addDays(14)->format('Y-m-d');

            return [
                'range' => [
                    'start' => $project->start_date ? $project->start_date->format('Y-m-d') : $today,
                    'end'   => $project->end_date ? $project->end_date->format('Y-m-d') : $twoWeeks,
                ],
                'project' => [
                    'id'         => $project->id,
                    'code'       => $project->project_code,
                    'name'       => $project->name,
                    'start_date' => $project->start_date?->format('Y-m-d'),
                    'end_date'   => $project->end_date?->format('Y-m-d'),
                ],
                'milestones'          => [],
                'tasks'               => [],
                'dependencies'        => [],
                'links'               => [],
                'critical_path'       => [],
                'critical_path_count' => 0,
                'project_duration'    => 0,
                'min_start'           => $project->start_date ? $project->start_date->format('Y-m-d') : $today,
                'max_due'             => $project->end_date ? $project->end_date->format('Y-m-d') : $twoWeeks,
            ];
        }

        $allEdges = $this->dependencies->allEdgesForProject($project->id);

        // Filter edges to only include tasks present in our calculation set
        $taskIds = $allTasks->pluck('id')->flip()->all();
        $edges = $allEdges->filter(fn ($e) => isset($taskIds[$e->task_id], $taskIds[$e->depends_on_task_id]));

        // Base date definitions
        $projectStart = $project->start_date ? Carbon::parse($project->start_date)->startOfDay() : Carbon::today()->startOfDay();

        // 1. Task duration & planned date normalization
        $tasksData = [];
        foreach ($allTasks as $task) {
            $tPlannedStart = $task->start_date ? Carbon::parse($task->start_date)->startOfDay() : $projectStart->copy();
            $tPlannedDue = $task->due_date ? Carbon::parse($task->due_date)->startOfDay() : $tPlannedStart->copy();
            if ($tPlannedDue->lt($tPlannedStart)) {
                $tPlannedDue = $tPlannedStart->copy();
            }
            $duration = max(1, (int) $tPlannedStart->diffInDays($tPlannedDue) + 1);

            $tasksData[$task->id] = [
                'model'         => $task,
                'id'            => $task->id,
                'task_code'     => $task->task_code,
                'title'         => $task->title,
                'status'        => $task->status,
                'priority'      => $task->priority,
                'milestone_id'  => $task->milestone_id,
                'milestone_name'=> $task->milestone?->name,
                'assignee_id'   => $task->assignee_id,
                'assignee_name' => $task->assignee?->name,
                'start_date'    => $tPlannedStart,
                'due_date'      => $tPlannedDue,
                'duration'      => $duration,
                'early_start'   => $tPlannedStart->copy(),
                'early_finish'  => $tPlannedStart->copy()->addDays($duration - 1),
                'late_start'    => null,
                'late_finish'   => null,
                'total_float'   => 0,
                'is_critical'   => false,
            ];
        }

        // 2. Build graph adjacency (predecessor -> successors and successor -> predecessors)
        $predecessors = []; // task_id => [[pred_id, type, lag]]
        $successors = [];   // pred_id => [[succ_id, type, lag]]
        $inDegree = [];

        foreach ($tasksData as $id => $d) {
            $predecessors[$id] = [];
            $successors[$id] = [];
            $inDegree[$id] = 0;
        }

        foreach ($edges as $edge) {
            $predId = $edge->depends_on_task_id;
            $succId = $edge->task_id;
            $type = $edge->dependency_type ?: TaskDependency::TYPE_FINISH_TO_START;
            $lag = (int) ($edge->lag_days ?? 0);

            $predecessors[$succId][] = ['pred_id' => $predId, 'type' => $type, 'lag' => $lag];
            $successors[$predId][] = ['succ_id' => $succId, 'type' => $type, 'lag' => $lag];
            $inDegree[$succId]++;
        }

        // 3. Topological Sort (Kahn's Algorithm)
        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $topologicalOrder = [];
        while ($queue !== []) {
            $currId = array_shift($queue);
            $topologicalOrder[] = $currId;

            foreach ($successors[$currId] as $edge) {
                $succId = $edge['succ_id'];
                $inDegree[$succId]--;
                if ($inDegree[$succId] === 0) {
                    $queue[] = $succId;
                }
            }
        }

        if (count($topologicalOrder) < count($tasksData)) {
            // Cycle detected among calculation set; append remaining arbitrarily to prevent fatal loop
            foreach (array_keys($tasksData) as $id) {
                if (! in_array($id, $topologicalOrder, true)) {
                    $topologicalOrder[] = $id;
                }
            }
        }

        // 4. Forward Pass: Calculate Early Start (ES) and Early Finish (EF)
        foreach ($topologicalOrder as $taskId) {
            $task = &$tasksData[$taskId];
            $duration = $task['duration'];
            $earliestStart = $task['start_date']->copy();

            foreach ($predecessors[$taskId] as $dep) {
                $pred = $tasksData[$dep['pred_id']];
                $type = $dep['type'];
                $lag = $dep['lag'];

                $bound = match ($type) {
                    TaskDependency::TYPE_START_TO_START => $pred['early_start']->copy()->addDays($lag),
                    TaskDependency::TYPE_FINISH_TO_FINISH => $pred['early_finish']->copy()->addDays($lag - $duration + 1),
                    TaskDependency::TYPE_START_TO_FINISH => $pred['early_start']->copy()->addDays($lag - $duration + 1),
                    default => $pred['early_finish']->copy()->addDays(1 + $lag), // FINISH_TO_START
                };

                if ($bound->gt($earliestStart)) {
                    $earliestStart = $bound->copy();
                }
            }

            $task['early_start'] = $earliestStart;
            $task['early_finish'] = $earliestStart->copy()->addDays($duration - 1);
        }
        unset($task);

        // 5. Backward Pass: Calculate Late Finish (LF) and Late Start (LS)
        // In CPM, terminal boundary is max(EF) of the network so critical path has TF = 0
        $maxEF = collect($tasksData)->max(fn ($t) => $t['early_finish']);
        $tMax = $maxEF ? $maxEF->copy() : $projectStart->copy();

        $reverseOrder = array_reverse($topologicalOrder);

        foreach ($reverseOrder as $taskId) {
            $task = &$tasksData[$taskId];
            $duration = $task['duration'];
            $latestFinish = $tMax->copy();

            foreach ($successors[$taskId] as $dep) {
                $succ = $tasksData[$dep['succ_id']];
                $type = $dep['type'];
                $lag = $dep['lag'];

                $bound = match ($type) {
                    TaskDependency::TYPE_START_TO_START => $succ['late_start']->copy()->subDays($lag)->addDays($duration - 1),
                    TaskDependency::TYPE_FINISH_TO_FINISH => $succ['late_finish']->copy()->subDays($lag),
                    TaskDependency::TYPE_START_TO_FINISH => $succ['late_finish']->copy()->subDays($lag)->addDays($duration - 1),
                    default => $succ['late_start']->copy()->subDays(1 + $lag), // FINISH_TO_START
                };

                if ($bound->lt($latestFinish)) {
                    $latestFinish = $bound->copy();
                }
            }

            $task['late_finish'] = $latestFinish;
            $task['late_start'] = $latestFinish->copy()->subDays($duration - 1);

            // 6. Slack / Total Float & Critical Path
            $float = (int) $task['early_start']->diffInDays($task['late_start'], false);
            $task['total_float'] = $float;
            $task['is_critical'] = ($float <= 0);
        }
        unset($task);

        // 7. Format output
        $criticalCount = 0;
        $formattedTasks = [];
        $earliestBoundary = $projectStart->copy();
        $latestBoundary = $tMax->copy();

        foreach ($tasksData as $t) {
            if ($t['is_critical']) {
                $criticalCount++;
            }
            if ($t['early_start']->lt($earliestBoundary)) {
                $earliestBoundary = $t['early_start']->copy();
            }
            if ($t['late_finish']->gt($latestBoundary)) {
                $latestBoundary = $t['late_finish']->copy();
            }

            $formattedTasks[] = [
                'id'             => $t['id'],
                'task_code'      => $t['task_code'],
                'title'          => $t['title'],
                'status'         => $t['status'],
                'is_completed'   => strtolower($t['status']) === 'completed',
                'priority'       => $t['priority'],
                'milestone_id'   => $t['milestone_id'],
                'milestone_name' => $t['milestone_name'],
                'assignee_id'    => $t['assignee_id'],
                'assignee_name'  => $t['assignee_name'] ?? 'Unassigned',
                'start_date'     => $t['start_date']->format('Y-m-d'),
                'due_date'       => $t['due_date']->format('Y-m-d'),
                'duration'       => $t['duration'],
                'early_start'    => $t['early_start']->format('Y-m-d'),
                'early_finish'   => $t['early_finish']->format('Y-m-d'),
                'late_start'     => $t['late_start']->format('Y-m-d'),
                'late_finish'    => $t['late_finish']->format('Y-m-d'),
                'total_float'    => $t['total_float'],
                'is_critical'    => $t['is_critical'],
            ];
        }

        $milestones = $project->milestones()
            ->when($milestoneId, fn ($q) => $q->where('id', $milestoneId))
            ->orderBy('start_date')
            ->get(['id', 'name', 'start_date', 'due_date', 'status', 'completion_percentage'])
            ->map(fn ($m) => [
                'id'                    => $m->id,
                'name'                  => $m->name,
                'start_date'            => $m->start_date?->format('Y-m-d'),
                'due_date'              => $m->due_date?->format('Y-m-d'),
                'status'                => $m->status,
                'completion_percentage' => $m->completion_percentage ?? 0,
            ])->all();

        $formattedEdges = $edges->map(fn ($e) => [
            'id'                 => $e->id,
            'task_id'            => $e->task_id,
            'depends_on_task_id' => $e->depends_on_task_id,
            'successor_id'       => $e->task_id,
            'predecessor_id'     => $e->depends_on_task_id,
            'dependency_type'    => $e->dependency_type ?: TaskDependency::TYPE_FINISH_TO_START,
            'type'               => $e->dependency_type ?: TaskDependency::TYPE_FINISH_TO_START,
            'lag_days'           => (int) ($e->lag_days ?? 0),
        ])->values()->all();

        $criticalTaskIds = [];
        foreach ($tasksData as $t) {
            if ($t['is_critical']) {
                $criticalTaskIds[] = $t['id'];
            }
        }

        $projectDuration = max(1, (int) $earliestBoundary->diffInDays($latestBoundary) + 1);

        return [
            'range' => [
                'start' => $earliestBoundary->format('Y-m-d'),
                'end'   => $latestBoundary->copy()->addDays(2)->format('Y-m-d'),
            ],
            'project' => [
                'id'         => $project->id,
                'code'       => $project->project_code,
                'name'       => $project->name,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date'   => $project->end_date?->format('Y-m-d'),
            ],
            'milestones'          => $milestones,
            'tasks'               => $formattedTasks,
            'dependencies'        => $formattedEdges,
            'links'               => $formattedEdges,
            'critical_path'       => $criticalTaskIds,
            'critical_path_count' => $criticalCount,
            'project_duration'    => $projectDuration,
            'min_start'           => $earliestBoundary->format('Y-m-d'),
            'max_due'             => $latestBoundary->format('Y-m-d'),
        ];
    }

    /**
     * Reschedule a task with Isolated or Ripple shift mode.
     */
    public function rescheduleTask(
        Task $task,
        Carbon|string $newStartDate,
        Carbon|string $newDueDate,
        string $shiftMode = self::SHIFT_MODE_RIPPLE
    ): array {
        $start = Carbon::parse($newStartDate)->startOfDay();
        $due = Carbon::parse($newDueDate)->startOfDay();

        if ($due->lt($start)) {
            $due = $start->copy();
        }

        $duration = max(1, (int) $start->diffInDays($due) + 1);

        return DB::transaction(function () use ($task, $start, $due, $duration, $shiftMode) {
            $lockedTask = Task::lockForUpdate()->findOrFail($task->id);
            $project = $lockedTask->project;

            $oldStartStr = $lockedTask->start_date?->format('Y-m-d');
            $oldDueStr = $lockedTask->due_date?->format('Y-m-d');

            if ($lockedTask->status === Task::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'task' => "Task '{$lockedTask->task_code}' is already completed and cannot be rescheduled.",
                ]);
            }

            // 1. Verify Predecessor Constraints (applies to BOTH Isolated and Ripple)
            $predecessors = TaskDependency::query()
                ->where('task_id', $lockedTask->id)
                ->with('dependsOn')
                ->get();

            foreach ($predecessors as $dep) {
                $pred = $dep->dependsOn;
                if (! $pred) {
                    continue;
                }

                $pStart = $pred->start_date ? Carbon::parse($pred->start_date)->startOfDay() : null;
                $pDue = $pred->due_date ? Carbon::parse($pred->due_date)->startOfDay() : null;
                $type = $dep->dependency_type ?: TaskDependency::TYPE_FINISH_TO_START;
                $lag = (int) ($dep->lag_days ?? 0);

                if ($type === TaskDependency::TYPE_FINISH_TO_START && $pDue) {
                    $minStart = $pDue->copy()->addDays(1 + $lag);
                    if ($start->lt($minStart)) {
                        throw ValidationException::withMessages([
                            'start_date' => "Cannot start on {$start->format('d/m/Y')}. Finish-to-Start predecessor '{$pred->task_code}' ({$pred->title}) finishes on {$pDue->format('d/m/Y')}" . ($lag ? " with {$lag}d lag." : '.'),
                        ]);
                    }
                } elseif ($type === TaskDependency::TYPE_START_TO_START && $pStart) {
                    $minStart = $pStart->copy()->addDays($lag);
                    if ($start->lt($minStart)) {
                        throw ValidationException::withMessages([
                            'start_date' => "Cannot start on {$start->format('d/m/Y')}. Start-to-Start predecessor '{$pred->task_code}' ({$pred->title}) starts on {$pStart->format('d/m/Y')}" . ($lag ? " with {$lag}d lag." : '.'),
                        ]);
                    }
                } elseif ($type === TaskDependency::TYPE_FINISH_TO_FINISH && $pDue) {
                    $minDue = $pDue->copy()->addDays($lag);
                    if ($due->lt($minDue)) {
                        throw ValidationException::withMessages([
                            'due_date' => "Cannot finish on {$due->format('d/m/Y')}. Finish-to-Finish predecessor '{$pred->task_code}' ({$pred->title}) finishes on {$pDue->format('d/m/Y')}" . ($lag ? " with {$lag}d lag." : '.'),
                        ]);
                    }
                } elseif ($type === TaskDependency::TYPE_START_TO_FINISH && $pStart) {
                    $minDue = $pStart->copy()->addDays($lag);
                    if ($due->lt($minDue)) {
                        throw ValidationException::withMessages([
                            'due_date' => "Cannot finish on {$due->format('d/m/Y')}. Start-to-Finish predecessor '{$pred->task_code}' ({$pred->title}) starts on {$pStart->format('d/m/Y')}" . ($lag ? " with {$lag}d lag." : '.'),
                        ]);
                    }
                }
            }

            // 2. If Isolated Mode: Validate Successor Constraints
            if ($shiftMode === self::SHIFT_MODE_ISOLATED) {
                $successors = TaskDependency::query()
                    ->where('depends_on_task_id', $lockedTask->id)
                    ->with('task')
                    ->get();

                foreach ($successors as $dep) {
                    $succ = $dep->task;
                    if (! $succ) {
                        continue;
                    }

                    $sStart = $succ->start_date ? Carbon::parse($succ->start_date)->startOfDay() : null;
                    $sDue = $succ->due_date ? Carbon::parse($succ->due_date)->startOfDay() : null;
                    $type = $dep->dependency_type ?: TaskDependency::TYPE_FINISH_TO_START;
                    $lag = (int) ($dep->lag_days ?? 0);

                    if ($type === TaskDependency::TYPE_FINISH_TO_START && $sStart) {
                        $maxDue = $sStart->copy()->subDays(1 + $lag);
                        if ($due->gt($maxDue)) {
                            throw ValidationException::withMessages([
                                'due_date' => "Isolated shift blocked: Moving finish to {$due->format('d/m/Y')} violates Finish-to-Start dependency with successor '{$succ->task_code}' ({$succ->title}) starting on {$sStart->format('d/m/Y')}. Use Ripple Shift to cascade changes.",
                            ]);
                        }
                    } elseif ($type === TaskDependency::TYPE_START_TO_START && $sStart) {
                        $maxStart = $sStart->copy()->subDays($lag);
                        if ($start->gt($maxStart)) {
                            throw ValidationException::withMessages([
                                'start_date' => "Isolated shift blocked: Moving start to {$start->format('d/m/Y')} violates Start-to-Start dependency with successor '{$succ->task_code}' ({$succ->title}) starting on {$sStart->format('d/m/Y')}. Use Ripple Shift to cascade changes.",
                            ]);
                        }
                    } elseif ($type === TaskDependency::TYPE_FINISH_TO_FINISH && $sDue) {
                        $maxDue = $sDue->copy()->subDays($lag);
                        if ($due->gt($maxDue)) {
                            throw ValidationException::withMessages([
                                'due_date' => "Isolated shift blocked: Moving finish to {$due->format('d/m/Y')} violates Finish-to-Finish dependency with successor '{$succ->task_code}' ({$succ->title}) finishing on {$sDue->format('d/m/Y')}. Use Ripple Shift to cascade changes.",
                            ]);
                        }
                    } elseif ($type === TaskDependency::TYPE_START_TO_FINISH && $sDue) {
                        $maxStart = $sDue->copy()->subDays($lag);
                        if ($start->gt($maxStart)) {
                            throw ValidationException::withMessages([
                                'start_date' => "Isolated shift blocked: Moving start to {$start->format('d/m/Y')} violates Start-to-Finish dependency with successor '{$succ->task_code}' ({$succ->title}) finishing on {$sDue->format('d/m/Y')}. Use Ripple Shift to cascade changes.",
                            ]);
                        }
                    }
                }

                $lockedTask->update([
                    'start_date' => $start->format('Y-m-d'),
                    'due_date'   => $due->format('Y-m-d'),
                ]);

                $this->activity->record(
                    $project,
                    'project.schedule_shifted',
                    "Task '{$lockedTask->task_code}' rescheduled (Isolated)",
                    "Dates adjusted from {$oldStartStr}–{$oldDueStr} to {$start->format('Y-m-d')}–{$due->format('Y-m-d')}",
                    $lockedTask,
                    [
                        'task_id'       => $lockedTask->id,
                        'shift_mode'    => self::SHIFT_MODE_ISOLATED,
                        'shifted_tasks' => [$lockedTask->task_code],
                    ]
                );

                return [
                    'success'        => true,
                    'shift_mode'     => self::SHIFT_MODE_ISOLATED,
                    'shifted_tasks'  => [$lockedTask->task_code],
                    'affected_count' => 1,
                    'schedule'       => $this->calculateSchedule($project),
                ];
            }

            // 3. Ripple Shift Mode: Update Target and Cascade to Successors
            $lockedTask->update([
                'start_date' => $start->format('Y-m-d'),
                'due_date'   => $due->format('Y-m-d'),
            ]);

            $shiftedCodes = [$lockedTask->task_code];
            $visited = [$lockedTask->id => true];

            // Queue for BFS traversal
            $queue = [$lockedTask->id];

            while ($queue !== []) {
                $currTaskId = array_shift($queue);
                $currTask = Task::find($currTaskId);
                if (! $currTask) {
                    continue;
                }

                $currStart = $currTask->start_date ? Carbon::parse($currTask->start_date)->startOfDay() : null;
                $currDue = $currTask->due_date ? Carbon::parse($currTask->due_date)->startOfDay() : null;

                $downstreamDeps = TaskDependency::query()
                    ->where('depends_on_task_id', $currTaskId)
                    ->with('task')
                    ->get();

                foreach ($downstreamDeps as $dep) {
                    $succ = $dep->task;
                    if (! $succ) {
                        continue;
                    }

                    // Safeguard: Completed tasks cannot be auto-shifted
                    if ($succ->status === Task::STATUS_COMPLETED) {
                        throw ValidationException::withMessages([
                            'shift_mode' => "Cannot ripple schedule: Downstream task '{$succ->task_code}' ({$succ->title}) is already Completed and cannot be automatically rescheduled.",
                        ]);
                    }

                    $sStart = $succ->start_date ? Carbon::parse($succ->start_date)->startOfDay() : Carbon::today()->startOfDay();
                    $sDue = $succ->due_date ? Carbon::parse($succ->due_date)->startOfDay() : $sStart->copy();
                    $sDuration = max(1, (int) $sStart->diffInDays($sDue) + 1);

                    $type = $dep->dependency_type ?: TaskDependency::TYPE_FINISH_TO_START;
                    $lag = (int) ($dep->lag_days ?? 0);

                    $requiredStart = match ($type) {
                        TaskDependency::TYPE_START_TO_START => $currStart ? $currStart->copy()->addDays($lag) : $sStart,
                        TaskDependency::TYPE_FINISH_TO_FINISH => $currDue ? $currDue->copy()->addDays($lag - $sDuration + 1) : $sStart,
                        TaskDependency::TYPE_START_TO_FINISH => $currStart ? $currStart->copy()->addDays($lag - $sDuration + 1) : $sStart,
                        default => $currDue ? $currDue->copy()->addDays(1 + $lag) : $sStart, // FINISH_TO_START
                    };

                    if ($requiredStart->gt($sStart)) {
                        $newSuccStart = $requiredStart->copy();
                        $newSuccDue = $newSuccStart->copy()->addDays($sDuration - 1);

                        Task::lockForUpdate()->where('id', $succ->id)->update([
                            'start_date' => $newSuccStart->format('Y-m-d'),
                            'due_date'   => $newSuccDue->format('Y-m-d'),
                        ]);

                        if (! in_array($succ->task_code, $shiftedCodes, true)) {
                            $shiftedCodes[] = $succ->task_code;
                        }

                        if (! isset($visited[$succ->id])) {
                            $visited[$succ->id] = true;
                            $queue[] = $succ->id;
                        }
                    }
                }
            }

            $this->activity->record(
                $project,
                'project.schedule_shifted',
                "Project schedule ripple shifted from '{$lockedTask->task_code}'",
                'Cascaded date adjustments to ' . count($shiftedCodes) . ' task(s): ' . implode(', ', $shiftedCodes),
                $lockedTask,
                [
                    'task_id'       => $lockedTask->id,
                    'shift_mode'    => self::SHIFT_MODE_RIPPLE,
                    'shifted_tasks' => $shiftedCodes,
                ]
            );

            return [
                'success'        => true,
                'shift_mode'     => self::SHIFT_MODE_RIPPLE,
                'shifted_tasks'  => $shiftedCodes,
                'affected_count' => count($shiftedCodes),
                'schedule'       => $this->calculateSchedule($project),
            ];
        });
    }
}
