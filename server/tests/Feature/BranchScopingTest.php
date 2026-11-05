<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Support\CurrentBranch;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\BranchOwnedRecord;
use Tests\Fixtures\BranchOwnedRecordResource;
use Tests\TestCase;

class BranchScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $centro;

    private Branch $norte;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('branch_owned_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->timestamps();
        });

        $this->user = User::factory()->create();
        $this->centro = Branch::factory()->create(['name' => 'Centro']);
        $this->norte = Branch::factory()->create(['name' => 'Norte']);
        $this->user->branches()->attach([$this->centro->id, $this->norte->id]);

        $this->actingAs($this->user);
    }

    private function select(Branch $branch): void
    {
        $this->from('/admin')->post(route('filament.admin.current-branch'), ['branch_id' => $branch->id])
            ->assertRedirect('/admin');

        app()->forgetScopedInstances();
    }

    public function test_for_branch_filters_records_owned_by_a_branch(): void
    {
        $mine = BranchOwnedRecord::create(['branch_id' => $this->centro->id]);
        BranchOwnedRecord::create(['branch_id' => $this->norte->id]);

        $this->assertSame([$mine->id], BranchOwnedRecord::forBranch($this->centro)->pluck('id')->all());
        $this->assertSame([$mine->id], BranchOwnedRecord::forBranch($this->centro->id)->pluck('id')->all());
    }

    public function test_for_branch_filters_records_assigned_to_a_branch(): void
    {
        $other = User::factory()->create();
        $other->branches()->attach($this->norte);

        $this->assertSame([$this->user->id], User::forBranch($this->centro)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$this->user->id, $other->id], User::forBranch($this->norte->id)->pluck('id')->all());
    }

    public function test_a_new_record_goes_to_the_current_branch(): void
    {
        $this->select($this->norte);

        $this->assertSame($this->norte->id, BranchOwnedRecord::create()->branch_id);
    }

    public function test_an_explicit_branch_is_kept(): void
    {
        $this->select($this->norte);

        $this->assertSame($this->centro->id, BranchOwnedRecord::create(['branch_id' => $this->centro->id])->branch_id);
    }

    public function test_the_first_branch_by_name_is_selected_by_default(): void
    {
        $this->assertTrue(app(CurrentBranch::class)->get()->is($this->centro));
    }

    public function test_a_soft_deleted_selection_falls_back_to_an_available_branch(): void
    {
        $this->select($this->norte);
        $this->norte->delete();
        app()->forgetScopedInstances();

        $this->assertTrue(app(CurrentBranch::class)->get()->is($this->centro));
    }

    public function test_a_user_cannot_select_a_branch_they_are_not_assigned_to(): void
    {
        $foreign = Branch::factory()->create();

        $this->post(route('filament.admin.current-branch'), ['branch_id' => $foreign->id])->assertForbidden();

        $this->assertTrue(app(CurrentBranch::class)->get()->is($this->centro));
    }

    public function test_guests_cannot_select_a_branch(): void
    {
        auth()->logout();

        $this->post(route('filament.admin.current-branch'), ['branch_id' => $this->centro->id])
            ->assertRedirect('/admin/login');
    }

    public function test_a_scoped_resource_only_sees_the_current_branch(): void
    {
        $centro = BranchOwnedRecord::create(['branch_id' => $this->centro->id]);
        $norte = BranchOwnedRecord::create(['branch_id' => $this->norte->id]);

        $this->assertSame([$centro->id], BranchOwnedRecordResource::getEloquentQuery()->pluck('id')->all());

        $this->select($this->norte);

        $this->assertSame([$norte->id], BranchOwnedRecordResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_a_scoped_resource_shows_nothing_to_a_user_without_branches(): void
    {
        BranchOwnedRecord::create(['branch_id' => $this->centro->id]);
        $this->actingAs(User::factory()->create());
        app()->forgetScopedInstances();

        $this->assertSame(0, BranchOwnedRecordResource::getEloquentQuery()->count());
        $this->assertFalse(BranchOwnedRecordResource::canAccess());
    }

    public function test_global_entities_come_before_the_branch_selector(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app()->forgetScopedInstances();

        $this->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Escritorio', 'Sucursales', 'Catálogo de productos', 'Usuarios', 'Configuraciones', 'id="current-branch"'], escape: false);
    }

    public function test_only_assigned_branches_are_offered_with_the_current_one_selected(): void
    {
        Branch::factory()->create(['name' => 'Ajena']);
        $this->select($this->norte);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('<option value="'.$this->norte->id.'" selected>Norte</option>', escape: false)
            ->assertDontSee('Ajena');
    }

    public function test_a_single_branch_user_sees_no_branch_selector_or_label(): void
    {
        $this->user->branches()->detach($this->norte);
        app()->forgetScopedInstances();

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('id="current-branch"', escape: false)
            ->assertDontSee('Sin sucursales asignadas')
            ->assertDontSee('Centro');
    }

    public function test_a_single_branch_user_still_works_in_their_branch(): void
    {
        $this->user->branches()->detach($this->norte);
        app()->forgetScopedInstances();

        $this->assertTrue(app(CurrentBranch::class)->get()->is($this->centro));
        $this->assertSame($this->centro->id, BranchOwnedRecord::create()->branch_id);
    }

    public function test_a_single_branch_user_keeps_their_modules_without_the_selector(): void
    {
        $this->user->branches()->detach($this->norte);
        app()->forgetScopedInstances();
        Filament::getCurrentOrDefaultPanel()->resources([BranchOwnedRecordResource::class]);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Fixture module')
            ->assertDontSee('id="current-branch"', escape: false);
    }

    public function test_an_admin_can_select_and_use_a_branch_without_being_assigned_to_it(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->branches()->detach();
        $this->actingAs($admin);
        app()->forgetScopedInstances();

        $this->get('/admin')->assertOk()->assertSee('id="current-branch"', escape: false);

        $this->select($this->norte);

        $this->assertTrue(app(CurrentBranch::class)->get()->is($this->norte));
        $this->assertSame($this->norte->id, BranchOwnedRecord::create()->branch_id);
    }

    public function test_an_admin_sees_a_message_when_no_branches_exist(): void
    {
        Branch::query()->forceDelete();
        $this->actingAs(User::factory()->admin()->create());
        app()->forgetScopedInstances();

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Sin sucursales asignadas')
            ->assertDontSee('id="current-branch"', escape: false);
    }

    public function test_a_record_cannot_be_placed_in_a_branch_the_user_is_not_assigned_to(): void
    {
        $foreign = Branch::factory()->create();

        $this->expectException(AuthorizationException::class);

        BranchOwnedRecord::create(['branch_id' => $foreign->id]);
    }

    public function test_a_record_cannot_be_moved_to_a_branch_the_user_is_not_assigned_to(): void
    {
        $foreign = Branch::factory()->create();
        $record = BranchOwnedRecord::create(['branch_id' => $this->centro->id]);

        $this->expectException(AuthorizationException::class);

        $record->update(['branch_id' => $foreign->id]);
    }

    public function test_background_work_without_a_user_can_use_any_branch(): void
    {
        auth()->logout();

        $this->assertSame($this->norte->id, BranchOwnedRecord::create(['branch_id' => $this->norte->id])->branch_id);
    }

    public function test_a_branch_scoped_resource_appears_below_the_selector(): void
    {
        Filament::getCurrentOrDefaultPanel()->resources([BranchOwnedRecordResource::class]);

        $this->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Escritorio', 'id="current-branch"', 'Fixture module'], escape: false);
    }

    public function test_a_branch_scoped_resource_is_hidden_when_there_is_no_branch(): void
    {
        Filament::getCurrentOrDefaultPanel()->resources([BranchOwnedRecordResource::class]);
        Branch::query()->forceDelete();
        $this->actingAs(User::factory()->admin()->create());
        app()->forgetScopedInstances();

        $this->get('/admin')->assertOk()->assertDontSee('Fixture module');
    }
}
