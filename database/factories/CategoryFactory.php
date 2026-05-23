<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name'       => $name,
            'name_en'    => $name,
            'slug'       => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'emoji'      => '🩺',
            'icon'       => null,
            'is_active'  => true,
            'sort_order' => 0,
        ];
    }
}
