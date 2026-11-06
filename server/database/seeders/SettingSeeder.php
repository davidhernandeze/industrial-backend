<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\Branch;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Create the settings the application expects, keeping any existing values.
     */
    public function run(): void
    {
        Setting::withTrashed()->firstOrCreate(['key' => 'company_name'], ['value' => 'La Familia']);
        Setting::withTrashed()->firstOrCreate(['key' => 'main_branch'], [
            'type' => SettingType::Entity,
            'entity_type' => Branch::class,
        ]);
    }
}
