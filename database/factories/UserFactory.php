<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        // This project uses OTP-based auth: users have phone + otp_code + is_active,
        // no password / email_verified_at columns (see migration 2024_01_01_000030).
        return [
            'name'      => fake()->name(),
            'email'     => fake()->unique()->safeEmail(),
            'phone'     => '05' . fake()->unique()->numerify('########'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
