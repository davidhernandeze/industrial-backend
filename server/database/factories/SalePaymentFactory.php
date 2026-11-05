<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalePayment>
 */
class SalePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
