<?php

namespace Database\Factories;

use App\Models\TokenAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TokenAlert>
 */
class TokenAlertFactory extends Factory
{
    protected $model = TokenAlert::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'threshold_tokens' => fake()->numberBetween(1000, 100_000),
            'period' => fake()->randomElement(['daily', 'weekly', 'monthly', 'total']),
            'notify_user' => false,
            'notify_auditors' => true,
            'notify_admins' => false,
            'notify_email' => null,
            'is_active' => true,
            'created_by' => null,
            'last_triggered_at' => null,
        ];
    }
}
