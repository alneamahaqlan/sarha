<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_approve_creates_subscription_and_audit_log(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->pending()->create();

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $fresh = $clinic->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertNotNull($fresh->subscription_starts_at);
        $this->assertNotNull($fresh->subscription_ends_at);

        $this->assertDatabaseHas('subscriptions', [
            'clinic_id' => $clinic->id,
            'status'    => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'approved_Clinic',
            'model_type' => Clinic::class,
            'model_id'   => $clinic->id,
        ]);
    }

    public function test_approve_blocked_when_not_pending(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create(['status' => 'active']);

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/approve")
            ->assertStatus(403);
    }

    public function test_reject_requires_reason_and_records_it(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->pending()->create();

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/reject", [])
            ->assertStatus(422)->assertJsonValidationErrors(['rejection_reason']);

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/reject", ['rejection_reason' => 'incomplete docs'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'incomplete docs');
    }

    public function test_suspend_then_activate(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create(['status' => 'active']);

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/suspend")
            ->assertOk()->assertJsonPath('data.status', 'suspended');

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/activate")
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertSame(1, AuditLog::where('action', 'suspended_Clinic')->count());
        $this->assertSame(1, AuditLog::where('action', 'activated_Clinic')->count());
    }

    public function test_extend_only_accepts_30_or_90(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create([
            'status' => 'active',
            'subscription_ends_at' => now()->addDays(10),
        ]);

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/extend", ['days' => 7])
            ->assertStatus(422);

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/extend", ['days' => 30])
            ->assertOk();

        $this->assertTrue($clinic->fresh()->subscription_ends_at->greaterThan(now()->addDays(35)));
    }

    public function test_impersonate_blocked_when_clinic_not_active(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->suspended()->create();

        $this->postJson("/api/v1/admin/clinics/{$clinic->id}/impersonate")
            ->assertStatus(403);
    }
}
