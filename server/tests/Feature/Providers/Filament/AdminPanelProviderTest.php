<?php

namespace Tests\Feature\Providers\Filament;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_brand_name_is_the_company_name_setting(): void
    {
        Setting::factory()->create(['key' => 'company_name', 'value' => 'Panadería Lupita']);

        $this->get('/admin/login')->assertOk()->assertSeeText('Panadería Lupita');
    }

    public function test_the_brand_name_falls_back_to_the_app_name_when_the_setting_is_empty(): void
    {
        config(['app.name' => 'Fallback Name']);
        Setting::factory()->create(['key' => 'company_name', 'value' => null]);

        $this->get('/admin/login')->assertOk()->assertSeeText('Fallback Name');
    }

    public function test_the_brand_name_falls_back_to_the_app_name_when_the_setting_is_missing(): void
    {
        config(['app.name' => 'Fallback Name']);

        $this->get('/admin/login')->assertOk()->assertSeeText('Fallback Name');
    }
}
