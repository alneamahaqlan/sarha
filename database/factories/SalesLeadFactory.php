<?php

namespace Database\Factories;

use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesLeadFactory extends Factory
{
    protected $model = SalesLead::class;

    public function definition(): array
    {
        return [
            'clinic_name'  => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'phone'        => '05' . $this->faker->numerify('########'),
            'email'        => $this->faker->safeEmail(),
            'status'       => 'new',
        ];
    }
}
