<?php

namespace Tests\Feature;

use App\Domains\Visitor\Models\Visitor;
use App\Domains\Visitor\Models\VisitorPass;
use App\Domains\Visitor\Services\VisitorService;
use App\Exports\VisitorPassExport;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_overstay_calculation_returns_positive_elapsed_difference(): void
    {
        $pass = new VisitorPass([
            'status'                    => 'Checked-In',
            'check_in_at'               => now()->subMinutes(90),
            'expected_duration_minutes' => 60,
        ]);

        $this->assertTrue($pass->isOverstayed(), 'Pass checked in 90 mins ago with 60 min duration should be flagged overstayed.');

        $recentPass = new VisitorPass([
            'status'                    => 'Checked-In',
            'check_in_at'               => now()->subMinutes(20),
            'expected_duration_minutes' => 60,
        ]);

        $this->assertFalse($recentPass->isOverstayed(), 'Pass checked in 20 mins ago with 60 min duration should not be overstayed.');
    }

    public function test_export_sanitizes_csv_formula_injection(): void
    {
        $export = new VisitorPassExport(1, []);
        $pass = new VisitorPass([
            'pass_number' => '=1+1',
            'purpose'     => '+SUM(A1:A10)',
            'notes'       => '-cmd| /C calc',
        ]);

        $mapped = $export->map($pass);
        foreach ($mapped as $cell) {
            if (is_string($cell) && strlen($cell) > 1 && in_array($cell[0], ['=', '+', '-', '@'])) {
                $this->fail("Cell was not sanitized against formula injection: {$cell}");
            }
        }

        $this->assertTrue(true);
    }

    public function test_checked_out_pass_is_never_considered_overstayed(): void
    {
        $pass = new VisitorPass([
            'status'                    => 'Checked-Out',
            'check_in_at'               => now()->subHours(5),
            'check_out_at'              => now()->subHours(4),
            'expected_duration_minutes' => 60,
        ]);

        $this->assertFalse($pass->isOverstayed(), 'Checked-Out pass must never be flagged as overstayed.');
    }
}
