<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_creating_booking_auto_generates_reference_code(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();

        $res = $this->postJson('/api/v1/admin/bookings', [
            'clinic_id' => $clinic->id,
            'customer_name' => 'Ahmad',
            'customer_phone' => '0501112222',
            'status' => 'new',
        ])->assertCreated();

        $code = $res->json('data.reference_code');
        $this->assertStringStartsWith('SAR-', $code);
    }

    public function test_status_must_be_within_allowed_values(): void
    {
        $this->actingAsAdmin();
        $clinic = Clinic::factory()->create();

        $this->postJson('/api/v1/admin/bookings', [
            'clinic_id' => $clinic->id,
            'customer_name' => 'X', 'customer_phone' => '0500000000',
            'status' => 'invalid_status',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_trashed_filter_only_returns_soft_deleted(): void
    {
        $this->actingAsAdmin();
        $alive = Booking::factory()->create();
        $trashed = Booking::factory()->create();
        $trashed->delete();

        $this->getJson('/api/v1/admin/bookings?filter[trashed]=only')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trashed->id);

        $this->getJson('/api/v1/admin/bookings?filter[trashed]=without')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alive->id);

        $this->getJson('/api/v1/admin/bookings?filter[trashed]=with')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_restore_trashed_booking(): void
    {
        $this->actingAsAdmin();
        $b = Booking::factory()->create();
        $b->delete();

        $this->postJson("/api/v1/admin/bookings/{$b->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.is_trashed', false);

        $this->assertNull($b->fresh()->deleted_at);
    }
}
