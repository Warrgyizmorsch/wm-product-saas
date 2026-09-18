<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrmsApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Acme HRMS Tenant',
            'slug' => 'acme-hrms',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app(TenantContext::class)->set($this->tenant);

        $this->adminUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'HR Admin User',
            'email' => 'hr_admin@acme.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->withHeaders(['X-Tenant' => $this->tenant->slug]);
    }

    /** @test */
    public function hr_admin_can_see_pending_leave_and_document_approvals_in_global_approvals(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'office_email' => 'jane.doe@acme.test',
            'employee_code' => 'EMP-101',
            'status' => true,
        ]);

        $leaveType = LeaveType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Annual Leave',
            'code' => 'AL',
        ]);

        LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'duration' => 2,
            'reason' => 'Family vacation',
            'status' => 'pending',
        ]);

        Document::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Passport Copy',
            'file_name' => 'passport.pdf',
            'file_path' => 'documents/passport.pdf',
            'status' => 'pending',
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->getJson('/global-approvals');

        $response->assertOk()
            ->assertJsonPath('count', 2);

        $items = $response->json('items');
        $this->assertCount(2, $items);
        $this->assertEquals('HRMS', $items[0]['module']);
    }

    /** @test */
    public function wfh_request_notification_uses_correct_named_parameter(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'office_email' => 'john.smith@acme.test',
            'employee_code' => 'EMP-102',
            'status' => true,
        ]);

        $wfh = \App\Domains\HRMS\Models\WfhRequest::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration' => 1,
            'reason' => 'Working from home',
            'status' => 'pending',
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->post("/hrms/wfh/{$wfh->id}/approve");
        $response->assertRedirect();

        $wfh->refresh();
        $this->assertEquals('approved', $wfh->status);
    }

    /** @test */
    public function travel_request_notification_with_employee_id_works(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'office_email' => 'john.smith@acme.test',
            'employee_code' => 'EMP-103',
            'status' => true,
        ]);

        $travel = \App\Domains\HRMS\Models\TravelRequest::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'destination' => 'New York',
            'purpose' => 'Client meeting',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'estimated_budget' => 500,
            'status' => 'pending',
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->post("/hrms/travel-expense/travel/{$travel->id}/approve");
        $response->assertRedirect();

        $travel->refresh();
        $this->assertEquals('approved', $travel->status);
    }

    /** @test */
    public function two_level_leave_approval_workflow_operates_correctly(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Alice',
            'last_name' => 'Worker',
            'office_email' => 'alice@acme.test',
            'employee_code' => 'EMP-200',
            'status' => true,
        ]);

        $leaveType = LeaveType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sick Leave',
            'code' => 'SL',
            'rules' => [
                'approval' => [
                    'workflow_level' => '2_level',
                    'first_approver' => 'reporting_manager',
                    'second_approver' => 'hr_manager',
                ]
            ]
        ]);

        $leaveRequest = LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'duration' => 1,
            'reason' => 'Medical appointment',
            'status' => 'pending',
            'current_level' => '1',
        ]);

        $this->actingAs($this->adminUser);

        // Level 1 Approval: should set current_level to '2' while status stays 'pending'
        $response = $this->post(route('hrms.leaves.update-status', $leaveRequest->id), ['action' => 'approved']);
        $response->assertRedirect();

        $leaveRequest->refresh();
        $this->assertEquals('pending', $leaveRequest->status);
        $this->assertEquals('2', (string)$leaveRequest->current_level);

        // Level 2 Approval: should set status to 'approved' and current_level to 'approved'
        $response2 = $this->post(route('hrms.leaves.update-status', $leaveRequest->id), ['action' => 'approved']);
        $response2->assertRedirect();

        $leaveRequest->refresh();
        $this->assertEquals('approved', $leaveRequest->status);
        $this->assertEquals('approved', (string)$leaveRequest->current_level);
    }
}

