<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'clinic_id'  => Clinic::factory(),
            'name'       => $this->faker->words(3, true),
            'description'=> $this->faker->sentence(),
            'price'      => $this->faker->numberBetween(50, 5000),
            'is_active'  => true,
            'sort_order' => 0,
        ];
    }
}
