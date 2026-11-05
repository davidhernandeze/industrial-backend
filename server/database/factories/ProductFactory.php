<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'barcode' => fake()->unique()->ean13(),
            'brand' => fake()->company(),
            'description' => fake()->sentence(),
        ];
    }
}
