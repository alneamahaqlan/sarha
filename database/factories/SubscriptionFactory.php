<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'type'      => 'basic',
            'amount'    => 300,
            'starts_at' => now(),
            'ends_at'   => now()->addDays(90),
            'status'    => 'active',
        ];
    }
}
