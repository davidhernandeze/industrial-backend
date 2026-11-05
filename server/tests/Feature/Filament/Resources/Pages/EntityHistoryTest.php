<?php

namespace Tests\Feature\Filament\Resources\Pages;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Branches\Pages\BranchHistory;
use App\Filament\Resources\Branches\Pages\ManageBranches;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Resources\Settings\Pages\SettingHistory;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EntityHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->user = User::factory()->admin()->create(['name' => 'Ana']);
        $this->actingAs($this->user);
    }

    private function history(Branch $branch): mixed
    {
        return Livewire::test(BranchHistory::class, ['record' => $branch->getRouteKey()]);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $branch = Branch::factory()->create();
        auth()->logout();

        $this->get(BranchResource::getUrl('history', ['record' => $branch]))->assertRedirect('/admin/login');
    }

    public function test_branch_rows_link_to_their_history(): void
    {
        $branch = Branch::factory()->create();

        Livewire::test(ManageBranches::class)
            ->assertActionHasIcon(TestAction::make('history')->table($branch), 'app-history')
            ->assertActionHasUrl(TestAction::make('history')->table($branch), BranchResource::getUrl('history', ['record' => $branch]));
    }

    public function test_setting_rows_link_to_their_history(): void
    {
        $setting = Setting::factory()->create();

        Livewire::test(ManageSettings::class)->assertActionExists(TestAction::make('history')->table($setting));
    }

    public function test_every_resource_has_view_and_history_pages(): void
    {
        foreach (glob(app_path('Filament/Resources/*/*Resource.php')) as $file) {
            $directory = basename(dirname($file));
            $resource = "App\\Filament\\Resources\\{$directory}\\".basename($file, '.php');

            $this->assertArrayHasKey('view', $resource::getPages(), "[{$resource}] has no view page.");

            if ($resource !== InventoryMovementResource::class) {
                $this->assertArrayHasKey('history', $resource::getPages(), "[{$resource}] has no history page.");
            }
        }
    }

    public function test_it_lists_only_the_logs_of_the_record_with_the_newest_first(): void
    {
        $branch = Branch::factory()->create();
        $branch->update(['name' => 'Otro']);
        $other = Branch::factory()->create();

        $logs = Activity::query()->whereMorphedTo('subject', $branch)->orderByDesc('id')->get();

        $this->history($branch)
            ->assertCanSeeTableRecords($logs, inOrder: true)
            ->assertCanNotSeeTableRecords(Activity::query()->whereMorphedTo('subject', $other)->get());
    }

    public function test_it_shows_the_user_the_event_and_the_changes_in_spanish(): void
    {
        $branch = Branch::factory()->create(['name' => 'Centro']);
        $branch->update(['name' => 'Norte']);

        $this->history($branch)
            ->assertSee('Historial: Norte')
            ->assertSee('Ana')
            ->assertSee('Creado')
            ->assertSee('Actualizado')
            ->assertSee('Nombre: Centro → Norte');
    }

    public function test_a_created_entry_skips_empty_attributes(): void
    {
        $branch = Branch::factory()->create(['name' => 'Centro', 'address' => null]);

        $this->history($branch)
            ->assertSee('Nombre: Centro')
            ->assertDontSee('Dirección');
    }

    public function test_date_values_use_the_same_format_as_the_rest_of_the_panel(): void
    {
        $branch = Branch::factory()->create(['opening_date' => '2026-01-15']);
        $branch->update(['opening_date' => '2026-02-01']);

        $this->history($branch)->assertSee('Fecha de apertura: 15/01/2026 → 01/02/2026');
    }

    public function test_clearing_a_value_shows_it_as_empty(): void
    {
        $branch = Branch::factory()->create(['address' => 'Av. Juárez 1']);
        $branch->update(['address' => null]);

        $this->history($branch)->assertSee('Dirección: Av. Juárez 1 → —');
    }

    public function test_changes_made_by_a_since_deleted_user_keep_their_author(): void
    {
        $branch = Branch::factory()->create();
        $this->user->delete();

        $this->history($branch)->assertSee('Ana')->assertDontSee('Sistema');
    }

    public function test_changes_without_a_user_are_attributed_to_the_system(): void
    {
        auth()->logout();
        $branch = Branch::factory()->create();
        $this->actingAs($this->user);

        $this->history($branch)->assertSee('Sistema');
    }

    public function test_logged_values_are_escaped(): void
    {
        $branch = Branch::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->history($branch)->assertDontSeeHtml('<script>alert(1)</script>');
    }

    public function test_a_deleted_branch_keeps_its_history_across_page_requests(): void
    {
        $branch = Branch::factory()->create();
        $branch->delete();

        $this->history($branch)
            ->assertOk()
            ->assertSee('Eliminado')
            ->call('$refresh')
            ->assertOk()
            ->assertSee('Eliminado');
    }

    public function test_a_record_without_logs_shows_an_empty_state(): void
    {
        $branch = Activity::withoutEvents(fn () => Branch::withoutEvents(fn () => Branch::factory()->create()));

        $this->history($branch)->assertSee('Sin historial');
    }

    public function test_a_setting_uses_its_translated_name_and_field_labels(): void
    {
        $setting = Setting::factory()->create(['key' => 'company_name', 'value' => 'Antes']);
        $setting->update(['value' => 'Después']);

        Livewire::test(SettingHistory::class, ['record' => $setting->getRouteKey()])
            ->assertSee('Historial: Nombre de la empresa')
            ->assertSee('Valor: Antes → Después');
    }
}
