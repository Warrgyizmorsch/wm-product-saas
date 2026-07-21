<?php

namespace App\Domains\Projects\Seeders\Distributions;

use App\Domains\Projects\Models\Task;

class TaskStatusDistribution
{
    public const PROFILE_ACTIVE_DEV = 'active-dev';
    public const PROFILE_ENTERPRISE = 'enterprise';
    public const PROFILE_MAINTENANCE = 'maintenance';
    public const PROFILE_STARTUP = 'startup';

    protected static array $profiles = [
        self::PROFILE_ACTIVE_DEV => [
            Task::STATUS_OPEN => 25,
            Task::STATUS_IN_PROGRESS => 45,
            Task::STATUS_REVIEW => 15,
            Task::STATUS_COMPLETED => 15,
        ],
        self::PROFILE_ENTERPRISE => [
            Task::STATUS_OPEN => 15,
            Task::STATUS_IN_PROGRESS => 30,
            Task::STATUS_REVIEW => 25,
            Task::STATUS_COMPLETED => 30,
        ],
        self::PROFILE_MAINTENANCE => [
            Task::STATUS_OPEN => 10,
            Task::STATUS_IN_PROGRESS => 20,
            Task::STATUS_REVIEW => 10,
            Task::STATUS_COMPLETED => 60,
        ],
        self::PROFILE_STARTUP => [
            Task::STATUS_OPEN => 40,
            Task::STATUS_IN_PROGRESS => 40,
            Task::STATUS_REVIEW => 10,
            Task::STATUS_COMPLETED => 10,
        ],
    ];

    public static function randomStatus(string $profile = self::PROFILE_ACTIVE_DEV): string
    {
        $weights = self::$profiles[$profile] ?? self::$profiles[self::PROFILE_ACTIVE_DEV];
        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($weights as $status => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return Task::STATUS_OPEN;
    }
}
