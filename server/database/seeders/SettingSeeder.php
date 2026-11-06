<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\Branch;
use App\Models\Setting;

class SettingSeeder extends DefaultsSeeder
{
    protected string $model = Setting::class;

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaults(): array
    {
        return [
            ['key' => 'company_name', 'value' => 'La Familia'],
            ['key' => 'main_branch', 'type' => SettingType::Entity, 'entity_type' => Branch::class],
        ];
    }
}
