<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\HLAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cardBrands = ['visa', 'mastercard', 'amex'];
        $cardBrand = $this->faker->randomElement($cardBrands);

        return [
            'hl_account_id' => HLAccount::factory(),
            'location_id' => 'loc_' . Str::random(20),
            'contact_id' => 'contact_' . Str::random(20),
            'provider' => 'paytr',
            'utoken' => 'utoken_' . Str::random(30),
            'ctoken' => 'ctoken_' . Str::random(30),
            'card_type' => $cardBrand,
            'card_brand' => $cardBrand,
            'card_last_four' => str_pad($this->faker->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'expiry_month' => str_pad($this->faker->numberBetween(1, 12), 2, '0', STR_PAD_LEFT),
            'expiry_year' => $this->faker->numberBetween(2025, 2030),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the payment method is default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the payment method is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_month' => '01',
            'expiry_year' => now()->subYear()->year,
        ]);
    }

    /**
     * Create a Visa card.
     */
    public function visa(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => 'visa',
            'card_brand' => 'visa',
        ]);
    }

    /**
     * Create a Mastercard.
     */
    public function mastercard(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => 'mastercard',
            'card_brand' => 'mastercard',
        ]);
    }

    /**
     * Create an American Express card.
     */
    public function amex(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => 'amex',
            'card_brand' => 'amex',
        ]);
    }
}
