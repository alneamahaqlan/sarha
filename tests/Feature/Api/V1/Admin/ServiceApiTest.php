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

    // Discount fields (old_price, offer_expires_at, is_featured_offer)
    // were removed when Offer became a standalone entity. Validation now
    // lives in StoreOfferRequest / UpdateOfferRequest, exercised by
    // tests/Feature/Api/V1/Clinic/OfferApiTest.php.

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
