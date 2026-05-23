<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $admin = $this->actingAsAdmin();
        AuditLog::create([
            'admin_id'   => $admin->id,
            'admin_name' => $admin->name,
            'action'     => 'updated_Clinic',
            'model_type' => 'App\\Models\\Clinic',
            'model_id'   => 1,
        ]);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'updated_Clinic')
            ->assertJsonPath('data.0.model_basename', 'Clinic');
    }

    public function test_create_endpoint_is_not_registered(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/audit-logs', [])
            ->assertStatus(405);
    }
}
