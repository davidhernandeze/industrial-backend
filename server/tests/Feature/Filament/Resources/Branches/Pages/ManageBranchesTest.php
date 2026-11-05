<?php

namespace Tests\Feature\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\Pages\ManageBranches;
use App\Models\Branch;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ManageBranchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_a_branch_can_be_created_with_only_a_name(): void
    {
        Livewire::test(ManageBranches::class)
            ->callAction('create', data: ['name' => 'Centro'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('branches', [
            'name' => 'Centro',
            'address' => null,
            'google_maps_url' => null,
            'opening_date' => null,
        ]);
    }

    public function test_a_branch_can_be_created_with_all_its_data(): void
    {
        Livewire::test(ManageBranches::class)
            ->callAction('create', data: [
                'name' => 'Centro',
                'address' => 'Av. Juárez 1',
                'google_maps_url' => 'https://maps.google.com/?q=centro',
                'opening_date' => '2026-01-15',
            ])
            ->assertHasNoFormErrors();

        $branch = Branch::query()->sole();

        $this->assertSame('Av. Juárez 1', $branch->address);
        $this->assertSame('https://maps.google.com/?q=centro', $branch->google_maps_url);
        $this->assertSame('2026-01-15', $branch->opening_date->toDateString());
    }

    public function test_the_name_is_required(): void
    {
        Livewire::test(ManageBranches::class)
            ->callAction('create', data: ['name' => null])
            ->assertHasFormErrors(['name' => 'required']);

        $this->assertDatabaseCount('branches', 0);
    }

    public function test_the_google_maps_url_must_be_a_url(): void
    {
        Livewire::test(ManageBranches::class)
            ->callAction('create', data: ['name' => 'Centro', 'google_maps_url' => 'not a url'])
            ->assertHasFormErrors(['google_maps_url' => 'url']);
    }

    public function test_a_javascript_url_is_rejected(): void
    {
        Livewire::test(ManageBranches::class)
            ->callAction('create', data: ['name' => 'Centro', 'google_maps_url' => 'javascript:alert(1)'])
            ->assertHasFormErrors(['google_maps_url' => 'url']);
    }

    public function test_a_branch_can_be_edited(): void
    {
        $branch = Branch::factory()->create(['name' => 'Centro']);

        Livewire::test(ManageBranches::class)
            ->callAction(TestAction::make('edit')->table($branch), data: ['name' => 'Norte'])
            ->assertHasNoFormErrors();

        $this->assertSame('Norte', $branch->fresh()->name);
    }

    public function test_deleting_a_branch_keeps_the_row(): void
    {
        $branch = Branch::factory()->create();

        Livewire::test(ManageBranches::class)
            ->callAction(TestAction::make('delete')->table($branch));

        $this->assertSoftDeleted($branch);
    }

    public function test_a_deleted_branch_can_be_restored(): void
    {
        $branch = Branch::factory()->trashed()->create();

        Livewire::test(ManageBranches::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restore')->table($branch));

        $this->assertNotSoftDeleted($branch);
    }

    public function test_deleted_branches_are_hidden_by_default(): void
    {
        $active = Branch::factory()->create();
        $deleted = Branch::factory()->trashed()->create();

        Livewire::test(ManageBranches::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$deleted]);
    }

    public function test_a_branch_cannot_be_permanently_deleted(): void
    {
        $branch = Branch::factory()->trashed()->create();

        Livewire::test(ManageBranches::class)
            ->filterTable('trashed', true)
            ->assertActionDoesNotExist(TestAction::make('forceDelete')->table($branch));
    }

    public function test_bulk_deleting_keeps_the_rows_and_logs_each_one(): void
    {
        $branches = Branch::factory()->count(2)->create();

        Livewire::test(ManageBranches::class)
            ->selectTableRecords($branches->pluck('id')->all())
            ->callAction(TestAction::make(DeleteBulkAction::getDefaultName())->table()->bulk());

        $branches->each(fn (Branch $branch) => $this->assertSoftDeleted($branch));
        $this->assertSame(2, Activity::query()->where('event', 'deleted')->count());
    }
}
