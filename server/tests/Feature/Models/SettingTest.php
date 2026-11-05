<?php

namespace Tests\Feature\Models;

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
}
