<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\PriceQuoteRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceQuoteRequestFactory extends Factory
{
    protected $model = PriceQuoteRequest::class;

    public function definition(): array
    {
        return [
            'clinic_id'      => Clinic::factory(),
            'customer_name'  => $this->faker->name(),
            'customer_phone' => '05' . $this->faker->numerify('########'),
            'service_name'   => $this->faker->words(3, true),
            'description'    => $this->faker->sentence(),
            'status'         => 'new',
        ];
    }
}
