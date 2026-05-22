<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\PriceQuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceQuoteRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_admin_can_list_price_quote_requests(): void
    {
        $this->actingAsAdmin();
        PriceQuoteRequest::factory()->count(2)->create();

        $this->getJson('/api/v1/admin/price-quotes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_status_validation_blocks_invalid_value(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();

        $this->postJson('/api/v1/admin/price-quotes', [
            'clinic_id'      => $clinic->id,
            'customer_name'  => 'X',
            'customer_phone' => '0500000000',
            'service_name'   => 'consult',
            'status'         => 'invalid_status',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_admin_can_update_reply(): void
    {
        $this->actingAsAdmin();
        $quote = PriceQuoteRequest::factory()->create();

        $this->patchJson("/api/v1/admin/price-quotes/{$quote->id}", [
            'status' => 'replied',
            'clinic_reply' => 'Price is 500 SAR',
        ])->assertOk()->assertJsonPath('data.status', 'replied');
    }

    public function test_admin_can_delete_price_quote(): void
    {
        $this->actingAsAdmin();
        $quote = PriceQuoteRequest::factory()->create();

        $this->deleteJson("/api/v1/admin/price-quotes/{$quote->id}")
            ->assertNoContent();
    }
}
