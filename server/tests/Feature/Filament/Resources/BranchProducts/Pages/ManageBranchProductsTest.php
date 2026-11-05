<?php

namespace Tests\Feature\Filament\Resources\BranchProducts\Pages;

use App\Enums\MovementType;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Filament\Resources\BranchProducts\BranchProductResource;
use App\Filament\Resources\BranchProducts\Pages\BranchProductHistory;
use App\Filament\Resources\BranchProducts\Pages\ManageBranchProducts;
use App\Filament\Resources\BranchProducts\Pages\ViewBranchProduct;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageBranchProductsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Branch $centro;

    private Branch $norte;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->centro = Branch::factory()->create(['name' => 'Centro']);
        $this->norte = Branch::factory()->create(['name' => 'Norte']);
        $this->manager = User::factory()->create(['role' => Role::BranchManager]);
        $this->manager->branches()->attach([$this->centro->id, $this->norte->id]);
        $this->actingAs($this->manager);
    }

    private function select(Branch $branch): void
    {
        $this->from('/admin')->post(route('filament.admin.current-branch'), ['branch_id' => $branch->id]);
        app()->forgetScopedInstances();
    }

    private function row(Branch $branch, array $attributes = []): BranchProduct
    {
        return BranchProduct::factory()->create(['branch_id' => $branch->id, ...$attributes]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(Product $product, array $overrides = []): array
    {
        return ['product_id' => $product->id, 'unit' => ProductUnit::Kilogram->value, 'price' => '12.50', ...$overrides];
    }

    public function test_adding_a_product_sets_the_branch_automatically(): void
    {
        $this->select($this->norte);
        $product = Product::factory()->create();

        Livewire::test(ManageBranchProducts::class)
            ->callAction('create', data: $this->validData($product))
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('branch_product', [
            'branch_id' => $this->norte->id,
            'product_id' => $product->id,
            'unit' => 'kg',
            'price' => '12.50',
        ]);
    }

    public function test_the_form_cannot_choose_another_branch(): void
    {
        $product = Product::factory()->create();

        Livewire::test(ManageBranchProducts::class)
            ->callAction('create', data: $this->validData($product, ['branch_id' => $this->norte->id]))
            ->assertHasNoFormErrors();

        $this->assertSame($this->centro->id, BranchProduct::query()->sole()->branch_id);
    }

    public function test_the_product_dropdown_only_offers_products_not_in_the_branch(): void
    {
        $offered = $this->row($this->centro)->product;
        $elsewhere = $this->row($this->norte)->product;
        $unused = Product::factory()->create();
        $deleted = Product::factory()->trashed()->create();

        Livewire::test(ManageBranchProducts::class)
            ->mountAction('create')
            ->assertFormFieldExists('product_id', function (Select $field) use ($offered, $elsewhere, $unused, $deleted): bool {
                $options = $field->getOptions();

                return ! array_key_exists($offered->id, $options)
                    && array_key_exists($elsewhere->id, $options)
                    && array_key_exists($unused->id, $options)
                    && ! array_key_exists($deleted->id, $options);
            });
    }

    public function test_the_product_dropdown_finds_a_scanned_barcode_and_excludes_offered_ones(): void
    {
        $match = Product::factory()->create(['name' => 'Leche', 'barcode' => '7501234567890']);
        $offered = $this->row($this->centro, ['product_id' => Product::factory()->create(['barcode' => '7509999999999'])->id])->product;

        Livewire::test(ManageBranchProducts::class)
            ->mountAction('create')
            ->assertFormFieldExists('product_id', fn (Select $field): bool => array_key_exists($match->id, $field->getSearchResults('7501234567890'))
                && ! array_key_exists($offered->id, $field->getSearchResults('7509999999999')));
    }

    public function test_a_product_already_in_the_branch_cannot_be_added_again(): void
    {
        $row = $this->row($this->centro);

        Livewire::test(ManageBranchProducts::class)
            ->callAction('create', data: $this->validData($row->product))
            ->assertHasFormErrors(['product_id']);

        $this->assertDatabaseCount('branch_product', 1);
    }

    public function test_the_database_also_refuses_a_duplicate(): void
    {
        $row = $this->row($this->centro);

        $this->expectException(UniqueConstraintViolationException::class);

        BranchProduct::factory()->create(['branch_id' => $this->centro->id, 'product_id' => $row->product_id]);
    }

    public function test_the_same_product_can_be_in_two_branches_with_different_prices(): void
    {
        $product = Product::factory()->create();

        $this->row($this->centro, ['product_id' => $product->id, 'price' => '10.00']);
        $this->row($this->norte, ['product_id' => $product->id, 'price' => '20.00']);

        $this->assertDatabaseCount('branch_product', 2);
    }

    public function test_adding_a_removed_product_restores_its_row_with_the_new_price(): void
    {
        $row = $this->row($this->centro, ['price' => '1.00']);
        $row->delete();

        Livewire::test(ManageBranchProducts::class)
            ->callAction('create', data: $this->validData($row->product, ['price' => '99.00']))
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('branch_product', 1);
        $this->assertNotSoftDeleted($row);
        $this->assertSame('99.00', $row->fresh()->price);
    }

    public function test_a_restored_row_keeps_its_unit_and_stock(): void
    {
        $row = $this->row($this->centro, ['unit' => ProductUnit::Piece]);
        app(Inventory::class)->record($row, MovementType::Purchase, '7');
        $row->delete();

        Livewire::test(ManageBranchProducts::class)
            ->callAction('create', data: $this->validData($row->product))
            ->assertHasNoFormErrors();

        $this->assertSame(ProductUnit::Piece, $row->fresh()->unit);
        $this->assertSame('7.000', $row->fresh()->stock);
    }

    public function test_the_form_shows_that_a_removed_product_keeps_its_unit(): void
    {
        $row = $this->row($this->centro, ['unit' => ProductUnit::Kilogram]);
        $row->delete();

        Livewire::test(ManageBranchProducts::class)
            ->mountAction('create')
            ->fillForm(['product_id' => $row->product_id])
            ->assertFormFieldDisabled('unit')
            ->assertSchemaStateSet(['unit' => ProductUnit::Kilogram]);
    }

    public function test_the_unit_cannot_be_changed_when_editing(): void
    {
        $row = $this->row($this->centro, ['unit' => ProductUnit::Piece]);

        Livewire::test(ManageBranchProducts::class)
            ->callAction(TestAction::make('edit')->table($row), data: ['unit' => ProductUnit::Kilogram->value, 'price' => '5.00'])
            ->assertHasNoFormErrors();

        $this->assertSame(ProductUnit::Piece, $row->fresh()->unit);
        $this->assertSame('5.00', $row->fresh()->price);
    }

    public function test_the_table_shows_stock_and_it_cannot_be_edited(): void
    {
        $row = $this->row($this->centro, ['unit' => ProductUnit::Kilogram]);
        app(Inventory::class)->record($row, MovementType::Purchase, '2.5');

        Livewire::test(ManageBranchProducts::class)
            ->assertSee('2.5 kg')
            ->callAction(TestAction::make('edit')->table($row), data: ['stock' => '999'])
            ->assertHasNoFormErrors();

        $this->assertSame('2.500', $row->fresh()->stock);
    }

    public function test_the_unit_and_price_are_required(): void
    {
        $product = Product::factory()->create();

        Livewire::test(ManageBranchProducts::class)
            ->callAction('create', data: $this->validData($product, ['unit' => null, 'price' => null]))
            ->assertHasFormErrors(['unit' => 'required', 'price' => 'required']);
    }

    public function test_the_price_cannot_be_negative_or_have_more_than_two_decimals(): void
    {
        $product = Product::factory()->create();

        Livewire::test(ManageBranchProducts::class)
            ->callAction('create', data: $this->validData($product, ['price' => '-1']))
            ->assertHasFormErrors(['price']);

        Livewire::test(ManageBranchProducts::class)
            ->callAction('create', data: $this->validData($product, ['price' => '1.999']))
            ->assertHasFormErrors(['price']);
    }

    public function test_a_row_can_be_edited_but_not_moved_to_another_product(): void
    {
        $row = $this->row($this->centro);
        $other = Product::factory()->create();

        Livewire::test(ManageBranchProducts::class)
            ->callAction(TestAction::make('edit')->table($row), data: ['product_id' => $other->id, 'price' => '5.00'])
            ->assertHasNoFormErrors();

        $this->assertSame('5.00', $row->fresh()->price);
        $this->assertNotSame($other->id, $row->fresh()->product_id);
    }

    public function test_a_row_can_be_soft_deleted_and_restored(): void
    {
        $row = $this->row($this->centro);

        Livewire::test(ManageBranchProducts::class)->callAction(TestAction::make('delete')->table($row));
        $this->assertSoftDeleted($row);

        Livewire::test(ManageBranchProducts::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restore')->table($row));
        $this->assertNotSoftDeleted($row);
    }

    public function test_the_table_only_lists_the_current_branch(): void
    {
        $mine = $this->row($this->centro);
        $other = $this->row($this->norte);

        Livewire::test(ManageBranchProducts::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_table_can_be_searched_by_barcode(): void
    {
        $match = $this->row($this->centro, ['product_id' => Product::factory()->create(['barcode' => '7501234567890'])->id]);
        $other = $this->row($this->centro);

        Livewire::test(ManageBranchProducts::class)
            ->searchTable('7501234567890')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_a_row_of_another_branch_cannot_be_opened(): void
    {
        $other = $this->row($this->norte);

        foreach (['view', 'history'] as $page) {
            $this->get(BranchProductResource::getUrl($page, ['record' => $other]))->assertNotFound();
        }
    }

    public function test_a_row_stays_readable_after_its_product_is_deleted(): void
    {
        $row = $this->row($this->centro, ['product_id' => Product::factory()->create(['name' => 'Descontinuado'])->id]);
        $row->product->delete();

        Livewire::test(ManageBranchProducts::class)->assertSee('Descontinuado');
        Livewire::test(ViewBranchProduct::class, ['record' => $row->getRouteKey()])->assertSee('Descontinuado');
    }

    public function test_the_history_shows_the_product_unit_and_price_as_labels(): void
    {
        $row = BranchProduct::addToCurrentBranch([
            'product_id' => Product::factory()->create(['name' => 'Leche'])->id,
            'unit' => ProductUnit::Kilogram,
            'price' => '10.00',
        ]);

        Livewire::test(BranchProductHistory::class, ['record' => $row->getRouteKey()])
            ->assertSee('Historial: Leche')
            ->assertSee('Producto: Leche')
            ->assertSee('Sucursal: Centro')
            ->assertSee('Unidad: Kilogramo')
            ->assertSee('Precio: 10.00');
    }

    public function test_the_view_page_is_titled_with_the_product_name(): void
    {
        $row = $this->row($this->centro, ['product_id' => Product::factory()->create(['name' => 'Leche'])->id]);

        Livewire::test(ViewBranchProduct::class, ['record' => $row->getRouteKey()])->assertSee('Ver Leche');
    }

    public function test_the_product_name_links_to_its_inventory(): void
    {
        $row = $this->row($this->centro);

        Livewire::test(ManageBranchProducts::class)
            ->assertTableColumnExists('product.name', fn (TextColumn $column): bool => $column
                ->record($row)
                ->getUrl() === InventoryMovementResource::getUrlForProduct($row->product_id));
    }

    public function test_the_section_appears_below_the_selector_for_managers(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['id="current-branch"', 'Productos'], escape: false);
    }

    public function test_a_single_branch_manager_gets_the_section_without_a_selector(): void
    {
        $this->manager->branches()->detach($this->norte);
        app()->forgetScopedInstances();

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Productos')
            ->assertDontSee('id="current-branch"', escape: false);
    }

    public function test_the_section_is_closed_to_cashiers_and_drivers(): void
    {
        foreach ([Role::Cashier, Role::Driver] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->branches()->attach($this->centro);

            $this->actingAs($user)->get('/admin/branch-products')->assertForbidden();
        }
    }

    public function test_the_catalog_and_the_section_have_distinct_menu_names(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app()->forgetScopedInstances();

        $this->get('/admin')
            ->assertSeeInOrder(['Catálogo de productos', 'id="current-branch"', 'Productos'], escape: false);
    }
}
