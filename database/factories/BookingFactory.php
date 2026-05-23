<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'clinic_id'      => Clinic::factory(),
            'user_id'        => null,
            'service_id'     => null,
            'customer_name'  => $this->faker->name(),
            'customer_phone' => '05' . $this->faker->numerify('########'),
            'status'         => 'new',
            'appointment_at' => null,
            'source'         => 'web',
        ];
    }
}
