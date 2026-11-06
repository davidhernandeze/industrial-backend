<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchSettingValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchSettingValue>
 */
class BranchSettingValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'branch_setting_id' => BranchSetting::factory(),
            'value' => fake()->word(),
        ];
    }
}
