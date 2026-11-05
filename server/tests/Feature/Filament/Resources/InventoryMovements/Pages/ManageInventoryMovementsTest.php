<?php

namespace Tests\Feature\Filament\Resources\InventoryMovements\Pages;

use App\Enums\MovementType;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\InventoryMovements\Pages\ManageInventoryMovements;
use App\Filament\Resources\InventoryMovements\Pages\ViewInventoryMovement;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ManageInventoryMovementsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private BranchProduct $row;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Notification::fake();
        $this->branch = Branch::factory()->create();
        $this->row = BranchProduct::factory()->create([
            'branch_id' => $this->branch->id,
            'product_id' => Product::factory()->create(['name' => 'Leche', 'barcode' => '7501234567890']),
            'unit' => ProductUnit::Kilogram,
        ]);

        $manager = User::factory()->create(['role' => Role::BranchManager]);
        $manager->branches()->attach($this->branch);
        $this->actingAs($manager);
    }

    /**
     * A row of a branch the signed-in manager cannot reach, created as background data.
     */
    private function foreignRow(): BranchProduct
    {
        $manager = auth()->user();
        auth()->logout();

        $row = BranchProduct::factory()->create();

        $this->actingAs($manager);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(MovementType $type, string $quantity, ?BranchProduct $row = null): array
    {
        return ['branch_product_id' => ($row ?? $this->row)->id, 'type' => $type->value, 'quantity' => $quantity];
    }

    public function test_a_purchase_updates_stock(): void
    {
        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Purchase, '12.5'))
            ->assertHasNoFormErrors();

        $this->assertSame('12.500', $this->row->fresh()->stock);
    }

    public function test_an_adjustment_can_take_stock_below_zero(): void
    {
        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Adjustment, '-4'))
            ->assertHasNoFormErrors();

        $this->assertSame('-4.000', $this->row->fresh()->stock);
    }

    public function test_sales_cannot_be_entered_by_hand(): void
    {
        foreach ([MovementType::Sale, MovementType::SaleCancellation] as $type) {
            Livewire::test(ManageInventoryMovements::class)
                ->callAction('create', data: $this->data($type, '2'))
                ->assertHasFormErrors(['type']);
        }

        $this->assertSame('0.000', $this->row->fresh()->stock);
    }

    public function test_flagged_sales_are_shown_in_the_table(): void
    {
        app(Inventory::class)->record($this->row, MovementType::Sale, '2');

        Livewire::test(ManageInventoryMovements::class)->assertSee('Venta sin existencia');
    }

    public function test_the_oversold_filter_only_shows_flagged_sales(): void
    {
        $inventory = app(Inventory::class);
        $fine = $inventory->record($this->row, MovementType::Purchase, '1');
        $inventory->record($this->row, MovementType::Sale, '1');
        $flagged = $inventory->record($this->row, MovementType::Sale, '1');

        Livewire::test(ManageInventoryMovements::class)
            ->filterTable('oversold')
            ->assertCanSeeTableRecords([$flagged])
            ->assertCanNotSeeTableRecords([$fine]);
    }

    public function test_the_product_filter_only_shows_that_product(): void
    {
        $inventory = app(Inventory::class);
        $other = BranchProduct::factory()->create(['branch_id' => $this->branch->id]);
        $match = $inventory->record($this->row, MovementType::Purchase, '1');
        $hidden = $inventory->record($other, MovementType::Purchase, '1');

        Livewire::test(ManageInventoryMovements::class)
            ->filterTable('product', $this->row->product_id)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$hidden]);
    }

    public function test_the_product_link_opens_the_table_already_filtered(): void
    {
        $inventory = app(Inventory::class);
        $other = BranchProduct::factory()->create(['branch_id' => $this->branch->id]);
        $match = $inventory->record($this->row, MovementType::Purchase, '1');
        $hidden = $inventory->record($other, MovementType::Purchase, '1');

        parse_str(parse_url(InventoryMovementResource::getUrlForProduct($this->row->product_id), PHP_URL_QUERY), $query);

        $this->assertSame((string) $this->row->product_id, $query['filters']['product']['value']);

        Livewire::withQueryParams($query)
            ->test(ManageInventoryMovements::class)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$hidden]);
    }

    public function test_the_filtered_url_opens_over_http_showing_the_product_name(): void
    {
        app(Inventory::class)->record($this->row, MovementType::Purchase, '1');

        $this->get(InventoryMovementResource::getUrlForProduct($this->row->product_id))
            ->assertOk()
            ->assertSee('Leche');
    }

    public function test_filtering_by_another_branchs_product_shows_nothing_from_it(): void
    {
        $foreign = $this->foreignRow();
        auth()->logout();
        $other = app(Inventory::class)->record($foreign, MovementType::Purchase, '1');
        $this->actingAs(User::query()->sole());

        Livewire::withQueryParams(['filters' => ['product' => ['value' => $foreign->product_id]]])
            ->test(ManageInventoryMovements::class)
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_product_filter_keeps_products_removed_from_the_branch(): void
    {
        $removed = BranchProduct::factory()->create([
            'branch_id' => $this->branch->id,
            'product_id' => Product::factory()->create(['name' => 'Retirado']),
        ]);
        $movement = app(Inventory::class)->record($removed, MovementType::Purchase, '1');
        $removed->delete();

        Livewire::test(ManageInventoryMovements::class)
            ->filterTable('product', $removed->product_id)
            ->assertCanSeeTableRecords([$movement]);
    }

    public function test_product_names_in_the_table_link_to_their_filter(): void
    {
        $movement = app(Inventory::class)->record($this->row, MovementType::Purchase, '1');

        Livewire::test(ManageInventoryMovements::class)
            ->assertTableColumnExists('branchProduct.product.name', fn (TextColumn $column): bool => $column
                ->record($movement)
                ->getUrl() === InventoryMovementResource::getUrlForProduct($this->row->product_id));
    }

    public function test_quantity_cannot_be_zero(): void
    {
        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Purchase, '0'))
            ->assertHasFormErrors(['quantity']);
    }

    public function test_purchases_cannot_be_negative(): void
    {
        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Purchase, '-1'))
            ->assertHasFormErrors(['quantity']);
    }

    public function test_pieces_must_be_whole_and_kilograms_can_be_fractional(): void
    {
        $pieces = BranchProduct::factory()->create(['branch_id' => $this->branch->id, 'unit' => ProductUnit::Piece]);

        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Purchase, '1.5', $pieces))
            ->assertHasFormErrors(['quantity']);

        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Purchase, '2', $pieces))
            ->assertHasNoFormErrors();

        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Purchase, '1.5'))
            ->assertHasNoFormErrors();
    }

    public function test_more_than_three_decimals_is_rejected(): void
    {
        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Purchase, '1.2345'))
            ->assertHasFormErrors(['quantity']);
    }

    public function test_a_product_from_another_branch_cannot_be_used(): void
    {
        $foreign = $this->foreignRow();

        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: $this->data(MovementType::Purchase, '1', $foreign))
            ->assertNotFound();

        $this->assertSame('0.000', $foreign->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_the_product_dropdown_is_limited_to_the_branch_and_finds_barcodes(): void
    {
        $foreign = $this->foreignRow();
        $discontinued = BranchProduct::factory()->create([
            'branch_id' => $this->branch->id,
            'product_id' => Product::factory()->trashed(),
        ]);

        Livewire::test(ManageInventoryMovements::class)
            ->mountAction('create')
            ->assertFormFieldExists('branch_product_id', function (Select $field) use ($foreign, $discontinued): bool {
                $options = $field->getOptions();

                return array_key_exists($this->row->id, $options)
                    && ! array_key_exists($foreign->id, $options)
                    && ! array_key_exists($discontinued->id, $options)
                    && array_key_exists($this->row->id, $field->getSearchResults('7501234567890'));
            });
    }

    public function test_movements_offer_no_edit_or_delete(): void
    {
        $movement = app(Inventory::class)->record($this->row, MovementType::Purchase, '1');

        Livewire::test(ManageInventoryMovements::class)
            ->assertActionDoesNotExist(TestAction::make('edit')->table($movement))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($movement));
    }

    public function test_the_table_only_lists_the_current_branch(): void
    {
        $mine = app(Inventory::class)->record($this->row, MovementType::Purchase, '1');
        $foreign = $this->foreignRow();
        auth()->logout();
        $other = app(Inventory::class)->record($foreign, MovementType::Purchase, '1');
        $this->actingAs(User::query()->sole());

        Livewire::test(ManageInventoryMovements::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_a_movement_of_another_branch_cannot_be_opened(): void
    {
        $foreign = $this->foreignRow();
        auth()->logout();
        $other = app(Inventory::class)->record($foreign, MovementType::Purchase, '1');
        $this->actingAs(User::query()->sole());

        $this->get(InventoryMovementResource::getUrl('view', ['record' => $other]))->assertNotFound();
    }

    public function test_the_view_page_shows_the_movement(): void
    {
        $movement = app(Inventory::class)->record($this->row, MovementType::Sale, '1.5', 'Mostrador');

        Livewire::test(ViewInventoryMovement::class, ['record' => $movement->getRouteKey()])
            ->assertSee('Ver Leche')
            ->assertSee('-1.5 kg')
            ->assertSee('Venta sin existencia')
            ->assertSee('Mostrador');
    }

    public function test_the_section_is_closed_to_cashiers_and_drivers(): void
    {
        foreach ([Role::Cashier, Role::Driver] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->branches()->attach($this->branch);

            $this->actingAs($user)->get('/admin/inventory')->assertForbidden();
        }
    }
}
