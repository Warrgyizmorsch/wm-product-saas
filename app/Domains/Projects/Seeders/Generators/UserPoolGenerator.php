<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Seeders\Context\TenantIsolationContext;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserPoolGenerator
{
    protected static array $curatedProfiles = [
        ['name' => 'Priya Nair', 'email' => 'priya.nair@example.com', 'role' => 'Project Manager'],
        ['name' => 'Rahul Sharma', 'email' => 'rahul.sharma@example.com', 'role' => 'Tech Lead'],
        ['name' => 'Ananya Patel', 'email' => 'ananya.patel@example.com', 'role' => 'UI/UX Designer'],
        ['name' => 'Arjun Mehta', 'email' => 'arjun.mehta@example.com', 'role' => 'QA Lead'],
        ['name' => 'Neha Singh', 'email' => 'neha.singh@example.com', 'role' => 'DevOps Engineer'],
        ['name' => 'Vikram Verma', 'email' => 'vikram.verma@example.com', 'role' => 'Backend Developer'],
        ['name' => 'Kavya Iyer', 'email' => 'kavya.iyer@example.com', 'role' => 'Frontend Developer'],
        ['name' => 'Rohan Joshi', 'email' => 'rohan.joshi@example.com', 'role' => 'Business Analyst'],
        ['name' => 'Meera Reddy', 'email' => 'meera.reddy@example.com', 'role' => 'Scrum Master'],
        ['name' => 'Aditya Kapoor', 'email' => 'aditya.kapoor@example.com', 'role' => 'Mobile Developer'],
        ['name' => 'Deepa Suresh', 'email' => 'deepa.suresh@example.com', 'role' => 'Product Owner'],
        ['name' => 'Sanjay Dutt', 'email' => 'sanjay.dutt@example.com', 'role' => 'Security Auditor'],
        ['name' => 'Pooja Sundaram', 'email' => 'pooja.sundaram@example.com', 'role' => 'Data Engineer'],
        ['name' => 'Amit Trivedi', 'email' => 'amit.trivedi@example.com', 'role' => 'Solutions Architect'],
        ['name' => 'Sneha Kulkarni', 'email' => 'sneha.kulkarni@example.com', 'role' => 'QA Engineer'],
        ['name' => 'Tarun Saxena', 'email' => 'tarun.saxena@example.com', 'role' => 'Backend Developer'],
        ['name' => 'Swati Malhotra', 'email' => 'swati.malhotra@example.com', 'role' => 'UI/UX Designer'],
        ['name' => 'Varun Dave', 'email' => 'varun.dave@example.com', 'role' => 'DevOps Engineer'],
        ['name' => 'Ritu Singhania', 'email' => 'ritu.singhania@example.com', 'role' => 'Release Manager'],
        ['name' => 'Manish Gupta', 'email' => 'manish.gupta@example.com', 'role' => 'System Administrator'],
    ];

    /**
     * Generate or fetch user pool for target tenant.
     * Returns an array of User model IDs.
     *
     * @return int[]
     */
    public static function generate(TenantIsolationContext $context, int $targetCount = 20): array
    {
        $existingUserIds = User::query()
            ->where('tenant_id', $context->tenantId)
            ->pluck('id')
            ->toArray();

        if (count($existingUserIds) >= $targetCount) {
            return array_slice($existingUserIds, 0, $targetCount);
        }

        $passwordHash = Hash::make('password');
        $userIds = $existingUserIds;

        foreach (self::$curatedProfiles as $profile) {
            if (count($userIds) >= $targetCount) {
                break;
            }

            $user = User::query()->firstOrCreate(
                [
                    'tenant_id' => $context->tenantId,
                    'email' => $profile['email'],
                ],
                [
                    'name' => $profile['name'],
                    'password' => $passwordHash,
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(10),
                ]
            );

            if (!in_array($user->id, $userIds, true)) {
                $userIds[] = $user->id;
            }
        }

        // Fill remaining if targetCount > curated list length
        $counter = 1;
        while (count($userIds) < $targetCount) {
            $user = User::query()->create([
                'tenant_id' => $context->tenantId,
                'name' => "Demo Engineer {$counter}",
                'email' => "engineer{$counter}.{$context->tenantId}@example.com",
                'password' => $passwordHash,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]);
            $userIds[] = $user->id;
            $counter++;
        }

        return $userIds;
    }
}
