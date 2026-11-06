<?php

namespace Tests\Feature\Models;

use App\Enums\SettingType;
use App\Models\Client;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_key_returns_its_own_value(): void
    {
        Setting::factory()->create(['key' => 'first', 'value' => 'one']);
        Setting::factory()->create(['key' => 'second', 'value' => 'two']);

        $this->assertSame('one', Setting::valueOf('first'));
        $this->assertSame('two', Setting::valueOf('second'));
    }

    public function test_a_missing_key_returns_null(): void
    {
        $this->assertNull(Setting::valueOf('missing'));
    }

    public function test_a_setting_is_a_string_by_default(): void
    {
        Setting::query()->create(['key' => 'plain', 'value' => '007']);

        $this->assertSame('007', Setting::valueOf('plain'));
    }

    public function test_a_number_is_read_as_a_number(): void
    {
        Setting::factory()->create(['key' => 'whole', 'type' => SettingType::Number, 'value' => '12']);
        Setting::factory()->create(['key' => 'fraction', 'type' => SettingType::Number, 'value' => '1.5']);

        $this->assertSame(12, Setting::valueOf('whole'));
        $this->assertSame(1.5, Setting::valueOf('fraction'));
    }

    public function test_a_price_is_read_with_two_decimals(): void
    {
        Setting::factory()->create(['key' => 'fee', 'type' => SettingType::Price, 'value' => '12.5']);

        $this->assertSame('12.50', Setting::valueOf('fee'));
    }

    public function test_an_entity_is_read_as_its_model(): void
    {
        $client = Client::factory()->create();
        Setting::factory()->create([
            'key' => 'default_client',
            'type' => SettingType::Entity,
            'value' => null,
            'entity_type' => Client::class,
            'entity_id' => $client->id,
        ]);

        $this->assertTrue($client->is(Setting::valueOf('default_client')));
    }

    public function test_a_deleted_entity_reads_as_null(): void
    {
        $client = Client::factory()->create();
        $setting = Setting::factory()->create([
            'type' => SettingType::Entity,
            'value' => null,
            'entity_type' => Client::class,
            'entity_id' => $client->id,
        ]);

        $client->delete();

        $this->assertNull($setting->parsed_value);
        $this->assertNull($setting->display_value);
    }

    public function test_an_empty_value_reads_as_null_for_every_type(): void
    {
        foreach (SettingType::cases() as $type) {
            foreach ([null, ''] as $value) {
                $setting = Setting::factory()->make(['type' => $type, 'value' => $value]);

                $this->assertNull($setting->parsed_value, $type->value);
                $this->assertNull($setting->display_value, $type->value);
            }
        }
    }

    public function test_a_price_is_displayed_as_money(): void
    {
        $setting = Setting::factory()->make(['type' => SettingType::Price, 'value' => '1234.5']);

        $this->assertSame('$1,234.50', $setting->display_value);
    }

    public function test_an_entity_is_displayed_by_its_name(): void
    {
        $client = Client::factory()->create(['name' => 'Rosa']);
        $setting = Setting::factory()->create([
            'type' => SettingType::Entity,
            'value' => null,
            'entity_type' => Client::class,
            'entity_id' => $client->id,
        ]);

        $this->assertSame('Rosa', $setting->display_value);
    }
}
