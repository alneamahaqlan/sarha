<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_can_create_service(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();

        $this->postJson('/api/v1/admin/services', [
            'clinic_id' => $clinic->id,
            'name' => 'تنظيف أسنان',
            'price' => 200,
        ])->assertCreated()
          ->assertJsonPath('data.name', 'تنظيف أسنان');
    }

    public function test_old_price_must_be_greater_than_price(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();

        $this->postJson('/api/v1/admin/services', [
            'clinic_id' => $clinic->id,
            'name' => 'X',
            'price' => 200,
            'old_price' => 150,
            'offer_expires_at' => now()->addDays(5)->toIso8601String(),
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['old_price']);
    }

    public function test_offer_expires_at_required_when_old_price_present(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();

        $this->postJson('/api/v1/admin/services', [
            'clinic_id' => $clinic->id,
            'name' => 'X',
            'price' => 200,
            'old_price' => 300,
            // offer_expires_at missing
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['offer_expires_at']);
    }

    public function test_can_filter_by_clinic(): void
    {
        $this->actingAsAdmin();
        $c1 = Clinic::factory()->create();
        $c2 = Clinic::factory()->create();
        Service::factory()->count(2)->create(['clinic_id' => $c1->id]);
        Service::factory()->create(['clinic_id' => $c2->id]);

        $this->getJson("/api/v1/admin/services?filter[clinic_id]={$c1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
