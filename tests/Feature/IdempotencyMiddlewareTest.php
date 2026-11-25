<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/test-idempotency-target', function () {
            static $callCount = 0;
            $callCount++;
            return response()->json([
                'success'    => true,
                'call_count' => $callCount,
                'data'       => request()->all(),
            ]);
        })->middleware('idempotency');
    }

    public function test_normal_request_without_idempotency_key_executes_each_time(): void
    {
        $res1 = $this->postJson('/test-idempotency-target', ['val' => 1]);
        $res1->assertStatus(200);
        $res1->assertHeaderMissing('X-Idempotent-Replay');
        $this->assertEquals(1, $res1->json('call_count'));

        $res2 = $this->postJson('/test-idempotency-target', ['val' => 1]);
        $res2->assertStatus(200);
        $res2->assertHeaderMissing('X-Idempotent-Replay');
        $this->assertEquals(2, $res2->json('call_count'));
    }

    public function test_request_with_x_idempotency_key_replays_cached_response(): void
    {
        $key = 'test-key-' . uniqid();

        // 1. First execution
        $res1 = $this->withHeader('X-Idempotency-Key', $key)
            ->postJson('/test-idempotency-target', ['item' => 'leave_application']);

        $res1->assertStatus(200);
        $res1->assertHeaderMissing('X-Idempotent-Replay');
        $callCount1 = $res1->json('call_count');

        // 2. Second execution with same key (simulating mobile double-tap or network retry)
        $res2 = $this->withHeader('X-Idempotency-Key', $key)
            ->postJson('/test-idempotency-target', ['item' => 'leave_application']);

        $res2->assertStatus(200);
        $res2->assertHeader('X-Idempotent-Replay', 'true');
        // Controller was NOT executed a second time, call_count remains identical
        $this->assertEquals($callCount1, $res2->json('call_count'));
    }

    public function test_reusing_same_key_with_different_payload_returns_409_conflict(): void
    {
        $key = 'conflict-key-' . uniqid();

        $res1 = $this->withHeader('X-Idempotency-Key', $key)
            ->postJson('/test-idempotency-target', ['amount' => 100]);
        $res1->assertStatus(200);

        // Submitting different payload with same key must conflict
        $res2 = $this->withHeader('X-Idempotency-Key', $key)
            ->postJson('/test-idempotency-target', ['amount' => 500]);
        $res2->assertStatus(409);
        $res2->assertJson(['success' => false]);
    }
}
