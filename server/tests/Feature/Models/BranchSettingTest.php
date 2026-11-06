<?php

namespace Tests\Feature\Models;

use App\Enums\SettingType;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchSettingValue;
use App\Models\Client;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BranchSettingTest extends TestCase
{
    use RefreshDatabase;

    private function value(Branch $branch, BranchSetting $setting, array $attributes = []): BranchSettingValue
    {
        return BranchSettingValue::factory()->create([
            'branch_id' => $branch->id,
            'branch_setting_id' => $setting->id,
            ...$attributes,
        ]);
    }

    public function test_each_branch_reads_its_own_value(): void
    {
        $setting = BranchSetting::factory()->create(['key' => 'greeting']);
        $centro = Branch::factory()->create();
        $norte = Branch::factory()->create();
        $this->value($centro, $setting, ['value' => 'Hola']);
        $this->value($norte, $setting, ['value' => 'Buenas']);

        $this->assertSame('Hola', $centro->setting('greeting'));
        $this->assertSame('Buenas', $norte->setting('greeting'));
    }

    public function test_a_setting_the_branch_never_set_reads_as_null(): void
    {
        BranchSetting::factory()->create(['key' => 'greeting']);

        $this->assertNull(Branch::factory()->create()->setting('greeting'));
    }

    public function test_an_unknown_setting_reads_as_null(): void
    {
        $this->assertNull(Branch::factory()->create()->setting('missing'));
    }

    public function test_values_are_parsed_by_the_type_of_the_setting(): void
    {
        $branch = Branch::factory()->create();
        $client = Client::factory()->create();
        $this->value($branch, BranchSetting::factory()->create(['key' => 'count', 'type' => SettingType::Number]), ['value' => '3']);
        $this->value($branch, BranchSetting::factory()->create(['key' => 'fee', 'type' => SettingType::Price]), ['value' => '9.5']);
        $this->value(
            $branch,
            BranchSetting::factory()->create(['key' => 'client', 'type' => SettingType::Entity, 'entity_type' => Client::class]),
            ['value' => null, 'entity_id' => $client->id],
        );

        $this->assertSame(3, $branch->setting('count'));
        $this->assertSame('9.50', $branch->setting('fee'));
        $this->assertTrue($client->is($branch->setting('client')));
    }

    public function test_reading_several_settings_loads_them_once(): void
    {
        $branch = Branch::factory()->create();
        $this->value($branch, BranchSetting::factory()->create(['key' => 'first']));
        $this->value($branch, BranchSetting::factory()->create(['key' => 'second']));
        $branch = $branch->fresh();

        DB::enableQueryLog();
        $branch->setting('first');
        $queries = count(DB::getQueryLog());
        $branch->setting('second');

        $this->assertCount($queries, DB::getQueryLog());
    }

    public function test_saving_creates_the_row_of_the_branch(): void
    {
        $setting = BranchSetting::factory()->create();
        $branch = Branch::factory()->create();

        $setting->saveValueFor($branch, ['value' => 'Hola']);

        $this->assertDatabaseHas('branch_setting_values', [
            'branch_id' => $branch->id,
            'branch_setting_id' => $setting->id,
            'value' => 'Hola',
        ]);
    }

    public function test_saving_updates_the_existing_row_and_the_value_read_after(): void
    {
        $setting = BranchSetting::factory()->create(['key' => 'greeting']);
        $branch = Branch::factory()->create();
        $this->value($branch, $setting, ['value' => 'Hola']);
        $branch->setting('greeting');

        $setting->saveValueFor($branch, ['value' => 'Buenas']);

        $this->assertDatabaseCount('branch_setting_values', 1);
        $this->assertSame('Buenas', $branch->setting('greeting'));
    }

    public function test_emptying_a_value_keeps_its_row(): void
    {
        $setting = BranchSetting::factory()->create();
        $branch = Branch::factory()->create();
        $row = $this->value($branch, $setting, ['value' => 'Hola']);

        $setting->saveValueFor($branch, ['value' => null]);

        $this->assertNotNull($row->fresh());
        $this->assertNull($row->fresh()->value);
    }

    public function test_saving_an_empty_value_on_an_unused_setting_creates_no_row(): void
    {
        $setting = BranchSetting::factory()->create();

        $this->assertNull($setting->saveValueFor(Branch::factory()->create(), ['value' => null]));

        $this->assertDatabaseEmpty('branch_setting_values');
    }

    public function test_saving_restores_a_soft_deleted_row(): void
    {
        $setting = BranchSetting::factory()->create();
        $branch = Branch::factory()->create();
        $row = $this->value($branch, $setting);
        $row->delete();

        $setting->saveValueFor($branch, ['value' => 'Hola']);

        $this->assertDatabaseCount('branch_setting_values', 1);
        $this->assertFalse($row->fresh()->trashed());
        $this->assertSame('Hola', $row->fresh()->value);
    }

    public function test_a_branch_cannot_have_a_setting_twice(): void
    {
        $setting = BranchSetting::factory()->create();
        $branch = Branch::factory()->create();
        $this->value($branch, $setting);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->value($branch, $setting);
    }

    public function test_a_setting_cannot_be_defined_twice(): void
    {
        BranchSetting::factory()->create(['key' => 'greeting']);

        $this->expectException(UniqueConstraintViolationException::class);

        BranchSetting::factory()->create(['key' => 'greeting']);
    }
}
