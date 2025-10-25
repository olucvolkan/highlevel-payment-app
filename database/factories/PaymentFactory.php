<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\HLAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hl_account_id' => HLAccount::factory(),
            'location_id' => 'loc_' . Str::random(20),
            'contact_id' => 'contact_' . Str::random(20),
            'merchant_oid' => 'ORDER_' . time() . '_' . rand(1000, 9999),
            'transaction_id' => 'txn_' . Str::random(20),
            'charge_id' => 'ch_' . Str::random(20),
            'subscription_id' => null,
            'order_id' => 'ord_' . Str::random(20),
            'provider' => 'paytr',
            'provider_payment_id' => 'pay_' . Str::random(20),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'TRY',
            'status' => Payment::STATUS_PENDING,
            'payment_mode' => 'payment',
            'payment_type' => 'card',
            'installment_count' => 0,
            'user_ip' => $this->faker->ipv4(),
            'email' => $this->faker->email(),
            'user_basket' => json_encode([
                ['Product', '100.00', 1]
            ]),
            'metadata' => json_encode([
                'test' => true,
            ]),
        ];
    }

    /**
     * Indicate that the payment is successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_SUCCESS,
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the payment has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_FAILED,
            'error_message' => 'Payment failed - Test error',
        ]);
    }

    /**
     * Indicate that the payment is refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_REFUNDED,
            'paid_at' => now()->subDays(2),
            'metadata' => json_encode([
                'refunded_amount' => $attributes['amount'],
                'refund_history' => [[
                    'amount' => $attributes['amount'],
                    'date' => now()->toIso8601String(),
                ]],
            ]),
        ]);
    }

    /**
     * Indicate that the payment has installments.
     */
    public function withInstallments(int $count = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'installment_count' => $count,
        ]);
    }
}
