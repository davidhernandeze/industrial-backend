<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'branch_id' => Branch::factory(),
            'folio' => fake()->unique()->numberBetween(1, 1000000),
            'user_id' => User::factory(),
            'status' => SaleStatus::Paid,
            'type' => SaleType::PointOfSale,
            'total' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
