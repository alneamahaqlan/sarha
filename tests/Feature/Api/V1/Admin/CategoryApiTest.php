<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_admin_can_list_categories(): void
    {
        $this->actingAsAdmin();
        Category::factory()->count(2)->create();

        $this->getJson('/api/v1/admin/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'أسنان',
            'name_en' => 'Dental',
            'slug' => 'dental',
            'emoji' => '🦷',
            'is_active' => true,
            'sort_order' => 1,
        ])->assertCreated()
          ->assertJsonPath('data.slug', 'dental');

        $this->assertDatabaseHas('categories', ['slug' => 'dental']);
    }

    public function test_slug_must_be_unique(): void
    {
        $this->actingAsAdmin();
        Category::factory()->create(['slug' => 'dermatology']);

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'X',
            'slug' => 'dermatology',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_can_update_slug_keeping_same_value(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create(['slug' => 'cardio']);

        $this->patchJson("/api/v1/admin/categories/{$category->id}", ['slug' => 'cardio', 'name' => 'New'])
            ->assertOk();
    }

    public function test_cannot_delete_category_with_clinics(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        $clinic = Clinic::factory()->create();
        $category->clinics()->attach($clinic->id);

        $this->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertStatus(403);
    }

    public function test_can_reorder_categories(): void
    {
        $this->actingAsAdmin();
        $a = Category::factory()->create(['sort_order' => 0]);
        $b = Category::factory()->create(['sort_order' => 0]);

        $this->postJson('/api/v1/admin/categories/reorder', [
            'order' => [
                ['id' => $b->id, 'sort_order' => 1],
                ['id' => $a->id, 'sort_order' => 2],
            ],
        ])->assertOk();

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }
}
