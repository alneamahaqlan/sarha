<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\SalesLead;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLeadApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        return $admin;
    }

    public function test_admin_can_create_sales_lead(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/sales-leads', [
            'clinic_name' => 'New Dental Clinic',
            'phone' => '0501234567',
            'status' => 'new',
        ])->assertCreated();
    }

    public function test_convert_to_basic_creates_clinic_and_subscription(): void
    {
        $this->actingAsAdmin();
        $lead = SalesLead::factory()->create(['clinic_name' => 'Acme Clinic', 'status' => 'new']);

        $this->postJson("/api/v1/admin/sales-leads/{$lead->id}/convert", ['plan' => 'basic'])
            ->assertStatus(201)
            ->assertJsonPath('data.lead.status', 'converted')
            ->assertJsonPath('data.clinic.subscription_type', 'basic');

        $clinic = Clinic::where('name', 'Acme Clinic')->first();
        $this->assertNotNull($clinic);
        $this->assertSame('active', $clinic->status);

        $subscription = Subscription::where('clinic_id', $clinic->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame('basic', $subscription->type);
        $this->assertEquals(300, (int) $subscription->amount);
    }

    public function test_convert_to_premium_uses_premium_price(): void
    {
        $this->actingAsAdmin();
        $lead = SalesLead::factory()->create(['clinic_name' => 'Premium Clinic']);

        $this->postJson("/api/v1/admin/sales-leads/{$lead->id}/convert", ['plan' => 'premium'])
            ->assertStatus(201);

        $sub = Subscription::where('type', 'premium')->latest('id')->first();
        $this->assertEquals(400, (int) $sub->amount);
    }

    public function test_cannot_convert_already_converted_lead(): void
    {
        $this->actingAsAdmin();
        $lead = SalesLead::factory()->create(['status' => 'converted']);

        $this->postJson("/api/v1/admin/sales-leads/{$lead->id}/convert", ['plan' => 'basic'])
            ->assertStatus(403);
    }

    public function test_invalid_plan_fails_validation(): void
    {
        $this->actingAsAdmin();
        $lead = SalesLead::factory()->create();

        $this->postJson("/api/v1/admin/sales-leads/{$lead->id}/convert", ['plan' => 'enterprise'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }
}
