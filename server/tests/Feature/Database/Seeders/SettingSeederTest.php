<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\SettingType;
use App\Models\Branch;
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
        $this->assertSame(1, Setting::query()->where('key', 'company_name')->count());
    }

    public function test_it_does_not_fail_on_a_soft_deleted_setting(): void
    {
        Setting::factory()->trashed()->create(['key' => 'company_name']);

        $this->seed(SettingSeeder::class);

        $this->assertSame(1, Setting::withTrashed()->where('key', 'company_name')->count());
    }

    public function test_it_creates_an_empty_main_branch_setting_that_points_at_branches(): void
    {
        $this->seed(SettingSeeder::class);

        $setting = Setting::query()->where('key', 'main_branch')->sole();

        $this->assertSame(SettingType::Entity, $setting->type);
        $this->assertSame(Branch::class, $setting->entity_type);
        $this->assertNull($setting->entity_id);
        $this->assertNull(Setting::valueOf('main_branch'));
    }

    public function test_it_keeps_the_chosen_main_branch(): void
    {
        $branch = Branch::factory()->create();
        $this->seed(SettingSeeder::class);
        Setting::query()->where('key', 'main_branch')->sole()->update(['entity_id' => $branch->id]);

        $this->seed(SettingSeeder::class);

        $this->assertTrue($branch->is(Setting::query()->where('key', 'main_branch')->sole()->parsed_value));
    }
}
