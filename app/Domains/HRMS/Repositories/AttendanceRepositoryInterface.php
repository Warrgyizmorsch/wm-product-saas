<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Attendance;

interface AttendanceRepositoryInterface
{
    public function getEmployeeTodayAttendance(int $employeeId): ?Attendance;

    public function checkIn(int $employeeId, ?float $latitude = null, ?float $longitude = null, ?string $selfiePath = null): Attendance;

    public function checkOut(int $attendanceId, ?float $latitude = null, ?float $longitude = null, ?string $selfiePath = null): Attendance;

    public function breakIn(int $attendanceId);

    public function breakOut(int $attendanceId);

    public function getEmployeeAttendanceLogs(int $employeeId);

    public function getAllAttendanceLogs(array $filters);

    public function trackLocation(int $employeeId, float $latitude, float $longitude): array;
}
