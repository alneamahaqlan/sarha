<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\Complaint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_mark_in_review_transitions_status(): void
    {
        $this->actingAsAdmin();
        $c = Complaint::factory()->create(['status' => 'new']);

        $this->postJson("/api/v1/admin/complaints/{$c->id}/mark-in-review")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_mark_in_review_blocked_when_not_new(): void
    {
        $this->actingAsAdmin();
        $c = Complaint::factory()->create(['status' => 'resolved']);

        $this->postJson("/api/v1/admin/complaints/{$c->id}/mark-in-review")
            ->assertStatus(403);
    }

    public function test_resolve_requires_resolution_and_sets_timestamp(): void
    {
        $this->actingAsAdmin();
        $c = Complaint::factory()->create(['status' => 'in_review']);

        $this->postJson("/api/v1/admin/complaints/{$c->id}/resolve", [])
            ->assertStatus(422)->assertJsonValidationErrors(['resolution']);

        $this->postJson("/api/v1/admin/complaints/{$c->id}/resolve", ['resolution' => 'fixed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolution', 'fixed');

        $this->assertNotNull($c->fresh()->resolved_at);
    }

    public function test_reject_requires_admin_notes(): void
    {
        $this->actingAsAdmin();
        $c = Complaint::factory()->create(['status' => 'new']);

        $this->postJson("/api/v1/admin/complaints/{$c->id}/reject", ['admin_notes' => 'duplicate'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.admin_notes', 'duplicate');
    }

    public function test_notify_clinic_only_when_clinic_and_not_yet_notified(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();
        $c = Complaint::factory()->create([
            'clinic_id' => $clinic->id, 'clinic_notified' => false,
        ]);

        $this->postJson("/api/v1/admin/complaints/{$c->id}/notify-clinic")
            ->assertOk()
            ->assertJsonPath('data.clinic_notified', true);

        // Second call must be blocked by Policy.
        $this->postJson("/api/v1/admin/complaints/{$c->id}/notify-clinic")
            ->assertStatus(403);
    }

    public function test_creating_complaint_auto_generates_reference_code(): void
    {
        $this->actingAsAdmin();

        $res = $this->postJson('/api/v1/admin/complaints', [
            'customer_name' => 'X',
            'customer_phone' => '0500000000',
            'type' => 'quality',
            'priority' => 'medium',
            'status' => 'new',
            'subject' => 'Issue',
            'description' => 'Details here',
        ])->assertCreated();

        $this->assertStringStartsWith('CMP-', $res->json('data.reference_code'));
    }
}
