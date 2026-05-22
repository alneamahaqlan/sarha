<?php

namespace Database\Factories;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'customer_name'   => $this->faker->name(),
            'customer_phone'  => '05' . $this->faker->numerify('########'),
            'customer_email'  => $this->faker->safeEmail(),
            'type'            => 'quality',
            'priority'        => 'medium',
            'status'          => 'new',
            'subject'         => $this->faker->sentence(3),
            'description'     => $this->faker->paragraph(),
            'clinic_notified' => false,
        ];
    }
}
