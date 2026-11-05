<?php

namespace Tests\Feature\Models\Concerns;

use App\Models\Branch;
use App\Models\Concerns\Auditable;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Framework and package tables, which do not hold business records.
     */
    private const array EXEMPT_TABLES = [
        'activity_log',
        'branchables',
        'inventory_movements', // append-only ledger, see InventoryMovement
        'notifications',
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
    ];

    public function test_every_business_table_supports_soft_deletes(): void
    {
        $tables = collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn (string $table): bool => str_starts_with($table, 'sqlite_'))
            ->diff(self::EXEMPT_TABLES);

        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'deleted_at'), "Table [{$table}] has no deleted_at column.");
        }
    }

    public function test_every_model_is_auditable(): void
    {
        foreach (glob(app_path('Models/*.php')) as $file) {
            $model = 'App\\Models\\'.basename($file, '.php');

            if ($model === InventoryMovement::class) {
                continue;
            }

            $this->assertContains(Auditable::class, class_uses_recursive($model), "[{$model}] does not use Auditable.");
        }
    }

    public function test_changes_are_logged_with_the_user_who_made_them(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['name' => 'Centro']);
        $branch->update(['name' => 'Norte']);

        $activity = Activity::query()->where('subject_type', $branch->getMorphClass())->where('event', 'updated')->sole();

        $this->assertTrue($activity->causer->is($user));
        $this->assertSame('Norte', $activity->attribute_changes['attributes']['name']);
        $this->assertSame('Centro', $activity->attribute_changes['old']['name']);
    }

    public function test_date_only_attributes_are_logged_as_dates(): void
    {
        $branch = Branch::factory()->create(['opening_date' => '2026-02-01']);

        $activity = Activity::query()->where('subject_type', $branch->getMorphClass())->where('event', 'created')->sole();

        $this->assertSame('2026-02-01', $activity->attribute_changes['attributes']['opening_date']);
    }

    public function test_deleting_and_restoring_are_logged(): void
    {
        $branch = Branch::factory()->create();

        $branch->delete();
        $branch->restore();

        $this->assertSame(
            ['created', 'deleted', 'restored'],
            Activity::query()->where('subject_id', $branch->id)->where('subject_type', $branch->getMorphClass())
                ->orderBy('id')->pluck('event')->all(),
        );
    }

    public function test_saving_without_changes_is_not_logged(): void
    {
        $branch = Branch::factory()->create();

        $branch->update(['name' => $branch->name]);

        $this->assertSame(1, Activity::query()->where('subject_id', $branch->id)->where('subject_type', $branch->getMorphClass())->count());
    }

    public function test_hidden_attributes_are_never_logged(): void
    {
        $user = User::factory()->create(['password' => 'first-secret', 'remember_token' => 'first-token']);
        $user->update(['password' => 'second-secret', 'remember_token' => 'second-token']);

        $logged = Activity::query()->where('subject_id', $user->id)->where('subject_type', $user->getMorphClass())->get()
            ->toJson();

        $this->assertStringNotContainsString('password', $logged);
        $this->assertStringNotContainsString('remember_token', $logged);
    }

    public function test_a_soft_deleted_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'secret']);
        $user->delete();

        $this->assertFalse(auth()->attempt(['email' => $user->email, 'password' => 'secret']));
    }
}
