<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_company_name_setting(): void
    {
        $this->seed(SettingSeeder::class);

        $this->assertDatabaseHas('settings', ['key' => 'company_name', 'value' => 'La Familia']);
    }

    public function test_it_keeps_an_existing_value(): void
    {
        Setting::factory()->create(['key' => 'company_name', 'value' => 'Changed by the owner']);

        $this->seed(SettingSeeder::class);

        $this->assertDatabaseHas('settings', ['key' => 'company_name', 'value' => 'Changed by the owner']);
        $this->assertDatabaseCount('settings', 1);
    }

    public function test_it_does_not_fail_on_a_soft_deleted_setting(): void
    {
        Setting::factory()->trashed()->create(['key' => 'company_name']);

        $this->seed(SettingSeeder::class);

        $this->assertDatabaseCount('settings', 1);
    }
}
