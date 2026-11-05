<?php

namespace Tests\Feature\Filament\Resources\InventoryMovements\Widgets;

use App\Enums\MovementType;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\InventoryMovements\Pages\ManageInventoryMovements;
use App\Filament\Resources\InventoryMovements\Widgets\ProductInventoryOverview;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ProductInventoryOverviewTest extends TestCase
{
    use RefreshDatabase;

    private BranchProduct $row;

    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Notification::fake();
        $this->inventory = app(Inventory::class);

        $branch = Branch::factory()->create(['name' => 'Alfa']);
        $this->row = BranchProduct::factory()->create([
            'branch_id' => $branch->id,
            'product_id' => Product::factory()->create(['name' => 'Leche']),
            'unit' => ProductUnit::Kilogram,
            'price' => '20.00',
        ]);

        $manager = User::factory()->create(['role' => Role::BranchManager]);
        $manager->branches()->attach($branch);
        $this->actingAs($manager);
    }

    private function overview(?int $productId = null): mixed
    {
        return Livewire::test(ProductInventoryOverview::class, [
            'tableFilters' => ['product' => ['value' => $productId ?? $this->row->product_id]],
        ]);
    }

    public function test_the_tiles_appear_when_the_table_is_filtered_by_a_product(): void
    {
        $this->get(InventoryMovementResource::getUrlForProduct($this->row->product_id))
            ->assertOk()
            ->assertSeeLivewire(ProductInventoryOverview::class);
    }

    public function test_the_tiles_stay_away_without_a_product_filter(): void
    {
        $this->get(InventoryMovementResource::getUrl('index'))
            ->assertOk()
            ->assertDontSeeLivewire(ProductInventoryOverview::class);
    }

    public function test_the_tiles_stay_away_for_a_product_the_branch_never_offered(): void
    {
        $this->get(InventoryMovementResource::getUrlForProduct(Product::factory()->create()->id))
            ->assertOk()
            ->assertDontSeeLivewire(ProductInventoryOverview::class);
    }

    public function test_applying_and_removing_the_filter_toggles_the_tiles(): void
    {
        $page = Livewire::test(ManageInventoryMovements::class);
        $this->assertSame([], $page->instance()->getVisibleHeaderWidgets());

        $page->filterTable('product', $this->row->product_id);
        $this->assertNotEmpty($page->instance()->getVisibleHeaderWidgets());

        $page->removeTableFilters();
        $this->assertSame([], $page->instance()->getVisibleHeaderWidgets());
    }

    public function test_recording_a_movement_tells_the_tiles_to_refresh(): void
    {
        Livewire::test(ManageInventoryMovements::class)
            ->callAction('create', data: [
                'branch_product_id' => $this->row->id,
                'type' => MovementType::Purchase->value,
                'quantity' => '1',
            ])
            ->assertHasNoFormErrors()
            ->assertDispatched(ProductInventoryOverview::MOVEMENT_RECORDED);
    }

    public function test_the_tiles_show_new_figures_after_a_refresh(): void
    {
        $overview = $this->overview()->assertSee('0 kg');

        $this->inventory->record($this->row, MovementType::Purchase, '4');

        $overview->dispatch(ProductInventoryOverview::MOVEMENT_RECORDED)->assertSee('4 kg');
    }

    public function test_it_shows_the_stock_price_and_value(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '2.5');

        $this->overview()
            ->assertSee('Leche')
            ->assertSee('2.5 kg')
            ->assertSee('$20.00')
            ->assertSee('Valor a precio de venta: $50.00');
    }

    public function test_it_warns_about_negative_stock(): void
    {
        $this->inventory->record($this->row, MovementType::Adjustment, '-3');

        $this->overview()
            ->assertSee('-3 kg')
            ->assertSee('Existencia negativa')
            ->assertDontSee('Valor a precio de venta');
    }

    public function test_sold_and_purchased_only_count_the_last_thirty_days(): void
    {
        $this->travel(-40)->days();
        $this->inventory->record($this->row, MovementType::Purchase, '100');
        $this->inventory->record($this->row, MovementType::Sale, '90');
        $this->travelBack();

        $this->inventory->record($this->row, MovementType::Purchase, '7');
        $this->inventory->record($this->row, MovementType::Sale, '4');

        $summary = $this->inventory->summaryFor($this->row->fresh(), 30);

        $this->assertSame('7.000', $summary['purchased']);
        $this->assertSame('4.000', $summary['sold']);
    }

    public function test_cancelled_sales_do_not_count_as_sold(): void
    {
        $this->inventory->record($this->row, MovementType::Sale, '4');
        $this->inventory->record($this->row, MovementType::SaleCancellation, '1.5');

        $this->assertSame('2.500', $this->inventory->summaryFor($this->row->fresh(), 30)['sold']);
    }

    public function test_it_counts_sales_without_stock_but_not_negative_adjustments(): void
    {
        $this->inventory->record($this->row, MovementType::Adjustment, '-1');
        $this->inventory->record($this->row, MovementType::Sale, '1');
        $this->inventory->record($this->row, MovementType::Sale, '1');

        $this->assertSame(2, $this->inventory->summaryFor($this->row->fresh(), 30)['oversold']);
    }

    public function test_it_shows_the_last_movement_even_when_it_is_older_than_the_period(): void
    {
        $this->travel(-40)->days();
        $movement = $this->inventory->record($this->row, MovementType::Purchase, '1');
        $this->travelBack();

        $this->assertTrue($this->inventory->summaryFor($this->row->fresh(), 30)['last']->is($movement));
    }

    public function test_a_product_without_movements_shows_empty_figures(): void
    {
        $this->overview()
            ->assertSee('0 kg')
            ->assertSee('Último movimiento')
            ->assertSee('—');
    }

    public function test_it_ignores_other_products(): void
    {
        $other = BranchProduct::factory()->create(['branch_id' => $this->row->branch_id]);
        $this->inventory->record($other, MovementType::Sale, '5');

        $summary = $this->inventory->summaryFor($this->row, 30);

        $this->assertSame('0.000', $summary['sold']);
        $this->assertNull($summary['last']);
    }

    public function test_it_uses_the_current_branch_for_a_product_offered_by_two_branches(): void
    {
        $elsewhere = Branch::factory()->create(['name' => 'Zeta']);
        auth()->user()->branches()->attach($elsewhere);
        $row = new BranchProduct(['product_id' => $this->row->product_id, 'unit' => ProductUnit::Kilogram, 'price' => '99.00']);
        $row->branch_id = $elsewhere->id;
        $row->save();

        $this->overview()->assertSee('$20.00')->assertDontSee('$99.00');
    }

    public function test_a_row_removed_from_the_branch_still_shows_its_figures(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '5');
        $this->row->delete();

        $this->overview()->assertSee('5 kg');
    }
}
