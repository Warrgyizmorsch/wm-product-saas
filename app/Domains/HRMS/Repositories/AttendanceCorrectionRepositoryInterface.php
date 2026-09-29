<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Http\Request;

interface AttendanceCorrectionRepositoryInterface
{
    public function getIndexData(array $inputs, ?User $user, int $tenantId): array;

    public function storeCorrection(array $validated, ?User $user, int $tenantId): array;

    public function approve(AttendanceCorrection $correction, ?User $user): array;

    public function reject(AttendanceCorrection $correction, ?string $reason, ?User $user): array;

    public function withdraw(AttendanceCorrection $correction, ?User $user): array;

    public function requestCancellation(AttendanceCorrection $correction, string $reason, ?User $user): array;

    public function approveCancellation(AttendanceCorrection $correction, ?User $user): array;

    public function denyCancellation(AttendanceCorrection $correction, ?string $reason, ?User $user): array;
}
