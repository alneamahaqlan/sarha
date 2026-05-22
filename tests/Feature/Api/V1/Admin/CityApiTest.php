<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\City;
use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_admin_can_list_cities(): void
    {
        $this->actingAsAdmin();
        City::factory()->count(3)->create();

        $this->getJson('/api/v1/admin/cities')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'name_en', 'is_active', 'sort_order']],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_city(): void
    {
        $this->actingAsAdmin();

        $payload = ['name' => 'الرياض', 'name_en' => 'Riyadh', 'is_active' => true, 'sort_order' => 1];

        $this->postJson('/api/v1/admin/cities', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'الرياض');

        $this->assertDatabaseHas('cities', ['name' => 'الرياض', 'name_en' => 'Riyadh']);
    }

    public function test_admin_cannot_create_city_without_name(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/cities', ['name_en' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_city(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create(['name' => 'Old']);

        $this->patchJson("/api/v1/admin/cities/{$city->id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New');
    }

    public function test_admin_cannot_delete_city_that_has_clinics(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        Clinic::factory()->create(['city_id' => $city->id]);

        $this->deleteJson("/api/v1/admin/cities/{$city->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('cities', ['id' => $city->id]);
    }

    public function test_admin_can_delete_empty_city(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $this->deleteJson("/api/v1/admin/cities/{$city->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cities', ['id' => $city->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/admin/cities')->assertStatus(401);
    }
}
