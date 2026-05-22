<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_admin_can_list_subscriptions(): void
    {
        $this->actingAsAdmin();
        Subscription::factory()->count(3)->create();

        $this->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_subscription(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();

        $this->postJson('/api/v1/admin/subscriptions', [
            'clinic_id' => $clinic->id,
            'type'      => 'premium',
            'amount'    => 400,
            'starts_at' => now()->toIso8601String(),
            'ends_at'   => now()->addDays(90)->toIso8601String(),
            'status'    => 'active',
        ])->assertCreated();
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();

        $this->postJson('/api/v1/admin/subscriptions', [
            'clinic_id' => $clinic->id,
            'type'      => 'basic',
            'amount'    => 300,
            'starts_at' => now()->toIso8601String(),
            'ends_at'   => now()->subDay()->toIso8601String(),
            'status'    => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors(['ends_at']);
    }

    public function test_delete_endpoint_not_registered(): void
    {
        $this->actingAsAdmin();
        $sub = Subscription::factory()->create();

        $this->deleteJson("/api/v1/admin/subscriptions/{$sub->id}")
            ->assertStatus(405);
    }
}
