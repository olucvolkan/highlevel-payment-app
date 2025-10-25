<?php

namespace Database\Factories;

use App\Models\HLAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HLAccount>
 */
class HLAccountFactory extends Factory
{
    protected $model = HLAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => 'loc_' . Str::random(20),
            'company_id' => 'comp_' . Str::random(20),
            'user_id' => 'user_' . Str::random(20),
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'token_expires_at' => now()->addDays(30),
            'scopes' => json_encode([
                'payments/orders.readonly',
                'payments/orders.write',
                'payments/custom-provider.write',
            ]),
            'integration_id' => 'int_' . Str::random(20),
            'config_id' => 'cfg_' . Str::random(20),
            'is_active' => true,
            'installed_at' => now(),
        ];
    }

    /**
     * Indicate that the account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'uninstalled_at' => now(),
        ]);
    }

    /**
     * Indicate that the account has expired token.
     */
    public function expiredToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subDays(1),
        ]);
    }
}
