<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Vendor;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\PartyBankChangeRequest;
use App\Models\PartyVerificationLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PartyBankChangeException;
use App\Services\PartyBankChangeService;
use App\Services\PartyVerificationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRS 2.3 / task C-16 (bank part): callback-verified changes to a vendor's bank details.
 */
class VendorBankChangeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $requester;
    private User $approver;
    private User $executive;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->requester = $this->userWithRole('requester@example.com', 'tenant_owner');
        $this->approver = $this->userWithRole('approver@example.com', 'company_admin');
        $this->executive = $this->userWithRole('exec@example.com', 'sales_executive');

        // A vendor created the old way: verified, with locked bank details and a phone on file.
        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Traders',
            'code' => 'VEND-0001',
            'status' => 'active',
            'phone' => '9876543210',
            'bank_name' => 'HDFC',
            'account_number' => '111122223333',
            'ifsc_code' => 'HDFC0001234',
        ]);
    }

    private function userWithRole(string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'test-tenant');
    }

    private function newDetails(): array
    {
        return ['bank_name' => 'ICICI', 'account_number' => '999988887777', 'ifsc_code' => 'ICIC0000001'];
    }

    private function raise(?User $by = null): PartyBankChangeRequest
    {
        return app(PartyBankChangeService::class)->request($this->vendor->fresh(), $by ?? $this->requester, $this->newDetails(), 'Vendor changed bank');
    }

    private function callbackData(array $overrides = []): array
    {
        return array_merge([
            'callback_number' => '9876543210',
            'callback_contact' => 'Mr Sharma, accounts',
            'callback_notes' => 'Confirmed new account',
            'number_from_master' => 1,
            'confirmed' => 1,
        ], $overrides);
    }

    // ---------------------------------------------------------------- protection

    /** @test */
    public function a_normal_edit_cannot_change_locked_bank_details(): void
    {
        $response = $this->as($this->requester)->put(route('purchase.vendors.update', $this->vendor->id), [
            'name' => 'Steel Traders',
            'code' => 'VEND-0001',
            'phone' => '9876543210',
            'status' => 'active',
            'bank_name' => 'ICICI',
            'account_number' => '999988887777',
            'ifsc_code' => 'ICIC0000001',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');
        $this->vendor->refresh();
        $this->assertSame('111122223333', $this->vendor->account_number);
        $this->assertSame('HDFC', $this->vendor->bank_name);
        $this->assertSame('verified', $this->vendor->verification_status);
    }

    /** @test */
    public function the_model_blocks_direct_changes_so_there_is_no_side_door(): void
    {
        $this->vendor->update(['account_number' => '000000000001', 'ifsc_code' => 'EVIL0000001', 'phone' => '9000000000']);

        $this->vendor->refresh();
        $this->assertSame('111122223333', $this->vendor->account_number);
        $this->assertSame('HDFC0001234', $this->vendor->ifsc_code);
        $this->assertSame('9000000000', $this->vendor->phone, 'non-bank fields still save');
    }

    /** @test */
    public function the_api_update_also_ignores_bank_fields_for_a_locked_vendor(): void
    {
        $service = app(PartyBankChangeService::class);
        [$clean, $attempted] = $service->stripProtectedFields($this->vendor, ['name' => 'X', 'account_number' => '5']);

        $this->assertSame(['name' => 'X'], $clean);
        $this->assertSame(['account_number'], $attempted);
    }

    /** @test */
    public function a_new_vendor_can_enter_bank_details_then_locks_when_first_verified(): void
    {
        $this->as($this->requester)->post(route('purchase.vendors.store'), [
            'name' => 'Fresh Vendor',
            'status' => 'active',
            'phone' => '9123456780',
            'bank_name' => 'SBI',
            'account_number' => '555566667777',
            'ifsc_code' => 'SBIN0000001',
        ]);

        $fresh = Vendor::where('name', 'Fresh Vendor')->firstOrFail();
        $this->assertSame('555566667777', $fresh->account_number);
        $this->assertFalse((bool) $fresh->bank_details_locked);

        // Still unverified: its first details can be corrected directly.
        $fresh->update(['account_number' => '555566668888']);
        $this->assertSame('555566668888', $fresh->fresh()->account_number);

        app(PartyVerificationService::class)->verify($fresh->fresh(), $this->approver, ['bank' => 1]);
        $fresh->refresh();
        $this->assertTrue((bool) $fresh->bank_details_locked);

        $fresh->update(['account_number' => '1']);
        $this->assertSame('555566668888', $fresh->fresh()->account_number);
    }

    // ---------------------------------------------------------------- request

    /** @test */
    public function raising_a_request_leaves_the_live_bank_details_and_status_unchanged(): void
    {
        $this->as($this->requester)->post(route('purchase.vendors.bank-changes.store', $this->vendor->id), [
            'new_bank_name' => 'ICICI',
            'new_account_number' => '999988887777',
            'new_ifsc_code' => 'ICIC0000001',
            'reason' => 'Vendor switched banks',
        ])->assertRedirect();

        $this->vendor->refresh();
        $this->assertSame('111122223333', $this->vendor->account_number);
        $this->assertSame('active', $this->vendor->status);

        $request = PartyBankChangeRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame('9876543210', $request->phone_on_file);
        $this->assertSame('111122223333', $request->old_account_number);
        $this->assertSame('999988887777', $request->new_account_number);
        $this->assertSame(1, PartyVerificationLog::where('party_id', $this->vendor->id)->where('action', 'bank_change_requested')->count());
    }

    /** @test */
    public function only_one_open_request_unchanged_details_and_missing_phone_are_refused(): void
    {
        $this->raise();

        try {
            $this->raise();
            $this->fail('A second open request should be refused.');
        } catch (PartyBankChangeException $e) {
            $this->assertStringContainsString('already open', $e->getMessage());
        }

        $other = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'No Phone', 'status' => 'active', 'bank_name' => 'A', 'account_number' => '1', 'ifsc_code' => 'B']);
        try {
            app(PartyBankChangeService::class)->request($other, $this->requester, $this->newDetails(), 'x');
            $this->fail('A vendor without a phone should be refused.');
        } catch (PartyBankChangeException $e) {
            $this->assertStringContainsString('no phone', $e->getMessage());
        }

        $same = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Same', 'status' => 'active', 'phone' => '9000000001', 'bank_name' => 'A', 'account_number' => '1', 'ifsc_code' => 'B']);
        try {
            app(PartyBankChangeService::class)->request($same, $this->requester, ['bank_name' => 'A', 'account_number' => '1', 'ifsc_code' => 'B'], 'x');
            $this->fail('Identical details should be refused.');
        } catch (PartyBankChangeException $e) {
            $this->assertStringContainsString('same as the current', $e->getMessage());
        }
    }

    /** @test */
    public function a_user_without_vendor_edit_rights_cannot_raise_or_open_the_queue(): void
    {
        $this->as($this->executive)->post(route('purchase.vendors.bank-changes.store', $this->vendor->id), [
            'new_account_number' => '1', 'new_ifsc_code' => 'X', 'reason' => 'y',
        ])->assertForbidden();

        $this->as($this->executive)->get(route('purchase.vendors.bank-changes.index'))->assertForbidden();
        $this->assertSame(0, PartyBankChangeRequest::count());
    }

    // ---------------------------------------------------------------- approval

    /** @test */
    public function the_requester_cannot_approve_their_own_request(): void
    {
        $request = $this->raise();

        $this->as($this->requester)->post(route('purchase.vendors.bank-changes.approve', $request->id), $this->callbackData())
            ->assertSessionHas('error');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('111122223333', $this->vendor->fresh()->account_number);
    }

    /** @test */
    public function approval_needs_a_proper_callback(): void
    {
        $request = $this->raise();
        $service = app(PartyBankChangeService::class);

        $bad = [
            'number not from the record' => $this->callbackData(['number_from_master' => 0]),
            'no contact person' => $this->callbackData(['callback_contact' => ' ']),
            'vendor did not confirm' => $this->callbackData(['confirmed' => 0]),
            'wrong number called' => $this->callbackData(['callback_number' => '9000000000']),
        ];

        foreach ($bad as $label => $callback) {
            try {
                $service->approve($request->fresh(), $this->approver, $callback);
                $this->fail("Approval should be refused: $label");
            } catch (PartyBankChangeException $e) {
                $this->assertSame('pending', $request->fresh()->status, $label);
            }
        }

        $this->assertSame('111122223333', $this->vendor->fresh()->account_number);
    }

    /** @test */
    public function a_different_authorised_person_can_approve_after_a_callback_and_the_details_change(): void
    {
        $request = $this->raise();

        $this->as($this->approver)->post(route('purchase.vendors.bank-changes.approve', $request->id), $this->callbackData([
            'callback_number' => '+91 98765 43210',
        ]))->assertSessionHas('success');

        $this->vendor->refresh();
        $this->assertSame('999988887777', $this->vendor->account_number);
        $this->assertSame('ICICI', $this->vendor->bank_name);
        $this->assertSame('ICIC0000001', $this->vendor->ifsc_code);
        $this->assertSame('active', $this->vendor->status);
        $this->assertSame('verified', $this->vendor->verification_status);
        $this->assertTrue((bool) $this->vendor->bank_details_locked);

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($this->approver->id, (int) $request->callback_by);
        $this->assertSame('Mr Sharma, accounts', $request->callback_contact);
        $this->assertNotNull($request->applied_at);

        $log = PartyVerificationLog::where('party_id', $this->vendor->id)->where('action', 'bank_change_approved')->firstOrFail();
        $this->assertSame('111122223333', $log->changes['old']['account_number']);
        $this->assertSame('999988887777', $log->changes['new']['account_number']);

        // Locked again afterwards: a direct change is still blocked.
        $this->vendor->update(['account_number' => '1']);
        $this->assertSame('999988887777', $this->vendor->fresh()->account_number);
    }

    /** @test */
    public function approval_is_refused_if_the_phone_or_bank_details_changed_after_the_request(): void
    {
        $request = $this->raise();
        $this->vendor->update(['phone' => '9000000099']);

        try {
            app(PartyBankChangeService::class)->approve($request->fresh(), $this->approver, $this->callbackData());
            $this->fail('Approval should be refused after the phone changed.');
        } catch (PartyBankChangeException $e) {
            $this->assertStringContainsString('phone number was changed', $e->getMessage());
        }
        $this->assertSame('111122223333', $this->vendor->fresh()->account_number);
    }

    // ---------------------------------------------------------------- reject / cancel

    /** @test */
    public function rejecting_keeps_the_old_details_and_needs_a_reason(): void
    {
        $request = $this->raise();

        $this->as($this->approver)->post(route('purchase.vendors.bank-changes.reject', $request->id), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->as($this->approver)->post(route('purchase.vendors.bank-changes.reject', $request->id), ['reason' => 'Vendor denied the change'])
            ->assertSessionHas('success');

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('111122223333', $this->vendor->fresh()->account_number);
    }

    /** @test */
    public function only_the_requester_can_cancel_and_a_new_request_is_then_allowed(): void
    {
        $request = $this->raise();

        $this->as($this->approver)->post(route('purchase.vendors.bank-changes.cancel', $request->id))->assertSessionHas('error');
        $this->assertSame('pending', $request->fresh()->status);

        $this->as($this->requester)->post(route('purchase.vendors.bank-changes.cancel', $request->id))->assertSessionHas('success');
        $this->assertSame('cancelled', $request->fresh()->status);

        $this->assertSame('pending', $this->raise()->status);
    }

    // ---------------------------------------------------------------- switch + pages

    /** @test */
    public function switching_the_control_off_restores_direct_editing(): void
    {
        config(['party_verification.bank_change_enabled' => false]);

        $this->vendor->update(['account_number' => '222233334444']);

        $this->assertSame('222233334444', $this->vendor->fresh()->account_number);
    }

    /** @test */
    public function the_pages_render_for_the_right_people(): void
    {
        $this->as($this->requester)->get(route('purchase.vendors.bank-changes.create', $this->vendor->id))
            ->assertOk()->assertSee('Change bank details for Steel Traders')->assertSee('9876543210');

        $this->as($this->requester)->get(route('purchase.vendors.edit', $this->vendor->id))
            ->assertOk()->assertSee('Request bank change')->assertDontSee('id="account_number"', false);

        $request = $this->raise();

        $this->as($this->requester)->get(route('purchase.vendors.show', $this->vendor->id))
            ->assertOk()->assertSee('A bank change request is open for this vendor');

        $this->as($this->requester)->get(route('purchase.vendors.bank-changes.show', $request->id))
            ->assertOk()->assertSee('a different person must do the callback')->assertDontSee('Approve and apply');

        $this->as($this->approver)->get(route('purchase.vendors.bank-changes.show', $request->id))
            ->assertOk()->assertSee('Approve and apply new bank details');

        $this->as($this->approver)->get(route('purchase.vendors.bank-changes.index'))
            ->assertOk()->assertSee('Steel Traders');
    }

    // ---------------------------------------------------------------- payment protection

    /** @test */
    public function payment_screen_renders_verified_bank_details_and_warns_when_bank_change_is_pending(): void
    {
        // 1. Without open request: displays verified bank account
        $this->as($this->approver)->get(route('purchase.payments.create', ['vendor_id' => $this->vendor->id]))
            ->assertOk()
            ->assertSee('Beneficiary Payout Details')
            ->assertSee('111122223333')
            ->assertSee('HDFC')
            ->assertDontSee('Security Alert: Bank Change Request Pending Verification');

        // 2. When bank change request is raised: displays security warning and unapproved details
        $this->raise();

        $this->as($this->approver)->get(route('purchase.payments.create', ['vendor_id' => $this->vendor->id]))
            ->assertOk()
            ->assertSee('Security Alert: Bank Change Request Pending Verification')
            ->assertSee('Unapproved Account')
            ->assertSee('ICICI')
            ->assertSee('999988887777')
            ->assertSee('confirm_pending_bank_change');
    }

    /** @test */
    public function payment_store_requires_confirmation_when_bank_change_request_is_pending(): void
    {
        $this->raise();

        // Submitting without confirmation is blocked
        $response = $this->as($this->approver)->post(route('purchase.payments.store'), [
            'vendor_id' => $this->vendor->id,
            'payment_date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'Bank Transfer',
        ]);

        $response->assertSessionHasErrors('vendor_id');
        $this->assertSame(0, \App\Domains\Purchase\Models\VendorPayment::count());

        // Submitting with explicit confirmation succeeds
        $responseSuccess = $this->as($this->approver)->post(route('purchase.payments.store'), [
            'vendor_id' => $this->vendor->id,
            'payment_date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'Bank Transfer',
            'confirm_pending_bank_change' => 1,
        ]);

        $responseSuccess->assertRedirect(route('purchase.payments.index'));
        $this->assertSame(1, \App\Domains\Purchase\Models\VendorPayment::count());
    }

    /** @test */
    public function payment_show_and_pdf_display_beneficiary_bank_details(): void
    {
        $payment = \App\Domains\Purchase\Models\VendorPayment::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'payment_number' => 'PAY-TEST-001',
            'payment_date' => now()->toDateString(),
            'amount' => 1500,
            'payment_method' => 'Bank Transfer',
            'status' => 'completed',
        ]);

        // Show page
        $this->as($this->approver)->get(route('purchase.payments.show', $payment->id))
            ->assertOk()
            ->assertSee('Beneficiary Payout Account')
            ->assertSee('HDFC')
            ->assertSee('111122223333')
            ->assertSee('HDFC0001234');

        // PDF download
        $pdfResponse = $this->as($this->approver)->get(route('purchase.payments.download', $payment->id));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $pdfResponse->headers->get('content-type'));
    }

    /** @test */
    public function editing_vendor_phone_sends_to_pending_verification_and_blocks_bank_change_request(): void
    {
        $this->assertSame('active', $this->vendor->status);
        $this->assertTrue($this->vendor->isVerified());

        // Requester changes phone number to a new number
        $this->as($this->requester)->put(route('purchase.vendors.update', $this->vendor->id), [
            'name' => $this->vendor->name,
            'code' => $this->vendor->code,
            'status' => 'active',
            'phone' => '9999888800',
        ])->assertRedirect();

        $this->vendor->refresh();
        $this->assertSame('9999888800', $this->vendor->phone);
        // Phone is in sensitive_fields, so changing it sends vendor to pending verification and inactive
        $this->assertSame('pending_verification', $this->vendor->verification_status);
        $this->assertSame('inactive', $this->vendor->status);
        $this->assertSame($this->requester->id, (int) $this->vendor->submitted_by);

        // Cannot raise a bank change request while vendor is pending verification
        try {
            app(\App\Services\PartyBankChangeService::class)->request(
                $this->vendor,
                $this->requester,
                $this->newDetails(),
                'Changed bank'
            );
            $this->fail('Raising a bank change request on a vendor pending verification should be refused.');
        } catch (\App\Services\PartyBankChangeException $e) {
            $this->assertStringContainsString('pending verification', $e->getMessage());
        }

        // Maker cannot verify their own sensitive phone change
        try {
            app(\App\Services\PartyVerificationService::class)->verify($this->vendor, $this->requester, ['bank' => 1]);
            $this->fail('Maker cannot verify their own sensitive phone change.');
        } catch (\App\Services\PartyVerificationException $e) {
            $this->assertStringContainsString('different person must verify', $e->getMessage());
        }
    }
}
