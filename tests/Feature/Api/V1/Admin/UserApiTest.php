<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_admin_can_list_users(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(3)->create();

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Test User',
            'phone' => '0501234567',
            'email' => 'u@example.com',
            'is_active' => true,
        ])->assertCreated();
    }

    public function test_phone_must_be_unique(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['phone' => '0501234567']);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'X', 'phone' => '0501234567',
        ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_delete_route_is_not_registered(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->deleteJson("/api/v1/admin/users/{$user->id}")
            ->assertStatus(405); // Method not allowed (matches Filament parity).
    }
}
