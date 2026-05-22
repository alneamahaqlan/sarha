<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): Admin
    {
        $admin = Admin::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function actingAsRegularAdmin(): Admin
    {
        $admin = Admin::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_super_admin_can_create_admin(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/admin/admins', [
            'name' => 'New Admin',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'is_active' => true,
        ])->assertCreated()
          ->assertJsonMissingPath('data.password');
    }

    public function test_regular_admin_cannot_create_admin(): void
    {
        $this->actingAsRegularAdmin();

        $this->postJson('/api/v1/admin/admins', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertStatus(403);
    }

    public function test_update_without_password_keeps_existing_hash(): void
    {
        $self = $this->actingAsSuperAdmin();
        $target = Admin::factory()->create();
        $originalPassword = $target->password;

        $this->patchJson("/api/v1/admin/admins/{$target->id}", [
            'name' => 'Renamed',
        ])->assertOk();

        $this->assertSame($originalPassword, $target->fresh()->password);
        $this->assertSame('Renamed', $target->fresh()->name);
        $this->assertNotNull($self);
    }

    public function test_cannot_delete_self(): void
    {
        $self = $this->actingAsSuperAdmin();

        $this->deleteJson("/api/v1/admin/admins/{$self->id}")
            ->assertStatus(403);
    }
}
