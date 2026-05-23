<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name'              => $name,
            'slug'              => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 999999),
            'phone'             => '05' . $this->faker->numerify('########'),
            'email'             => $this->faker->unique()->safeEmail(),
            'password'          => Hash::make('password'),
            'city_id'           => City::factory(),
            'status'            => 'active',
            'subscription_type' => 'basic',
            'is_featured'       => false,
            'sort_order'        => 0,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn () => ['status' => 'pending', 'subscription_type' => null]);
    }

    public function suspended(): self
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}
