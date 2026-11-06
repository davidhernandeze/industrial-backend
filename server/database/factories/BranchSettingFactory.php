<?php

namespace Database\Factories;

use App\Models\BranchSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchSetting>
 */
class BranchSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->lexify('branch_setting_?????'),
        ];
    }
}
