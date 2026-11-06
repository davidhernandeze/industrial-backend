<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $settings = Setting::query()->count();
        $activity = Activity::query()->count();

        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, $settings);
        $this->assertSame($settings, Setting::query()->count());
        $this->assertSame($activity, Activity::query()->count());
    }
}
