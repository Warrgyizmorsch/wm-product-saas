<?php

namespace App\Domains\Projects\Seeders\Distributions;

use App\Domains\Projects\Models\Task;

class TaskPriorityDistribution
{
    protected static array $profiles = [
        TaskStatusDistribution::PROFILE_ACTIVE_DEV => [
            'Low' => 15,
            'Medium' => 45,
            'High' => 30,
            'Critical' => 10,
        ],
        TaskStatusDistribution::PROFILE_ENTERPRISE => [
            'Low' => 30,
            'Medium' => 40,
            'High' => 20,
            'Critical' => 10,
        ],
        TaskStatusDistribution::PROFILE_MAINTENANCE => [
            'Low' => 50,
            'Medium' => 35,
            'High' => 10,
            'Critical' => 5,
        ],
        TaskStatusDistribution::PROFILE_STARTUP => [
            'Low' => 10,
            'Medium' => 30,
            'High' => 45,
            'Critical' => 15,
        ],
    ];

    public static function randomPriority(string $profile = TaskStatusDistribution::PROFILE_ACTIVE_DEV): string
    {
        $weights = self::$profiles[$profile] ?? self::$profiles[TaskStatusDistribution::PROFILE_ACTIVE_DEV];
        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($weights as $priority => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $priority;
            }
        }

        return 'Medium';
    }
}
