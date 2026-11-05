<?php

namespace Tests\Feature\Filament\Resources\Sales\Pages;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Filament\Resources\Sales\Pages\SaleHistory;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Sales\Widgets\SalesOverview;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory;
use App\Services\Sales;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ManageSalesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private BranchProduct $row;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Notification::fake();
        $this->branch = Branch::factory()->create(['name' => 'Alfa']);
        $this->row = BranchProduct::factory()->create([
            'branch_id' => $this->branch->id,
            'product_id' => Product::factory()->create(['name' => 'Pan']),
            'unit' => ProductUnit::Piece,
            'price' => '10.00',
        ]);
        $this->manager = User::factory()->create(['role' => Role::BranchManager, 'name' => 'Gerente']);
        $this->manager->branches()->attach($this->branch);
        $this->actingAs($this->manager);
    }

    private function sale(PaymentMethod $method = PaymentMethod::Cash, string $quantity = '2'): Sale
    {
        return app(Sales::class)->create((string) Str::uuid(), [$this->row->id => $quantity], $method);
    }

    /**
     * A sale of a branch the signed-in manager cannot reach, created as background data.
     */
    private function foreignSale(): Sale
    {
        auth()->logout();
        $sale = Sale::factory()->create();
        $this->actingAs($this->manager);

        return $sale;
    }

    public function test_managers_and_admins_can_open_it_but_cashiers_and_drivers_cannot(): void
    {
        $this->get('/admin/sales')->assertOk();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/sales')->assertOk();

        foreach ([Role::Cashier, Role::Driver] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->branches()->attach($this->branch);

            $this->actingAs($user)->get('/admin/sales')->assertForbidden();
        }
    }

    public function test_the_table_lists_only_the_current_branch(): void
    {
        $mine = $this->sale();
        $other = $this->foreignSale();

        Livewire::test(ManageSales::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_another_branchs_sale_cannot_be_opened(): void
    {
        $this->get(SaleResource::getUrl('view', ['record' => $this->foreignSale()]))->assertNotFound();
    }

    public function test_the_table_filters_by_status_and_payment_method(): void
    {
        $cash = $this->sale(PaymentMethod::Cash);
        $card = $this->sale(PaymentMethod::Card);

        Livewire::test(ManageSales::class)
            ->filterTable('payment_method', PaymentMethod::Card->value)
            ->assertCanSeeTableRecords([$card])
            ->assertCanNotSeeTableRecords([$cash]);

        app(Sales::class)->cancel($card, 'Motivo');

        Livewire::test(ManageSales::class)
            ->filterTable('status', SaleStatus::Cancelled->value)
            ->assertCanSeeTableRecords([$card])
            ->assertCanNotSeeTableRecords([$cash]);
    }

    public function test_the_table_filters_by_date(): void
    {
        $this->travel(-5)->days();
        $old = $this->sale();
        $this->travelBack();
        $recent = $this->sale();

        Livewire::test(ManageSales::class)
            ->filterTable('dates', ['from' => now()->subDay()->toDateString()])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_the_tiles_show_todays_figures(): void
    {
        $this->sale(PaymentMethod::Card);

        Livewire::test(SalesOverview::class)
            ->assertSee('Ticket promedio')
            ->assertSee('$20.00')
            ->assertSee('Tarjeta')
            ->assertSee('Ventas del mes');
    }

    public function test_a_sale_shows_its_lines_payment_and_cashier(): void
    {
        $sale = $this->sale();

        Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])
            ->assertSee("Ver #{$sale->folio}")
            ->assertSee('Pan')
            ->assertSee('2 pza')
            ->assertSee('$20.00')
            ->assertSee('Efectivo')
            ->assertSee('Pagada')
            ->assertSee('Gerente');
    }

    public function test_a_manager_can_cancel_a_sale_and_stock_returns(): void
    {
        app(Inventory::class)->record($this->row, MovementType::Purchase, '10');
        $sale = $this->sale();

        Livewire::test(ManageSales::class)
            ->callAction(TestAction::make('cancelSale')->table($sale), data: ['reason' => 'Error de captura'])
            ->assertHasNoFormErrors();

        $this->assertSame(SaleStatus::Cancelled, $sale->fresh()->status);
        $this->assertSame('10.000', $this->row->fresh()->stock);
    }

    public function test_cancelling_works_from_the_view_page(): void
    {
        $sale = $this->sale();

        Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])
            ->callAction('cancelSale', data: ['reason' => 'Motivo'])
            ->assertHasNoFormErrors();

        $this->assertTrue($sale->fresh()->isCancelled());
    }

    public function test_a_reason_is_required(): void
    {
        $sale = $this->sale();

        Livewire::test(ManageSales::class)
            ->callAction(TestAction::make('cancelSale')->table($sale), data: ['reason' => null])
            ->assertHasFormErrors(['reason' => 'required']);

        $this->assertFalse($sale->fresh()->isCancelled());
    }

    public function test_a_cancelled_sale_offers_no_cancel_action_and_shows_why(): void
    {
        $sale = $this->sale();
        app(Sales::class)->cancel($sale, 'Cliente desistió');

        Livewire::test(ManageSales::class)
            ->assertActionHidden(TestAction::make('cancelSale')->table($sale->fresh()));

        Livewire::test(ViewSale::class, ['record' => $sale->getRouteKey()])
            ->assertSee('Cliente desistió')
            ->assertSee('Cancelada');
    }

    public function test_sales_cannot_be_created_edited_or_deleted_here(): void
    {
        $sale = $this->sale();

        Livewire::test(ManageSales::class)
            ->assertActionDoesNotExist('create')
            ->assertActionDoesNotExist(TestAction::make('edit')->table($sale))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($sale));
    }

    public function test_the_history_shows_the_cancellation(): void
    {
        $sale = $this->sale();
        app(Sales::class)->cancel($sale, 'Motivo');

        Livewire::test(SaleHistory::class, ['record' => $sale->getRouteKey()])
            ->assertSee('Estado: Pagada → Cancelada');
    }

    public function test_the_table_shows_and_filters_by_client(): void
    {
        $client = Client::factory()->create(['name' => 'Juana']);
        $hers = app(Sales::class)->create((string) Str::uuid(), [$this->row->id => '1'], PaymentMethod::Cash, clientId: $client->id);
        $anonymous = $this->sale();

        Livewire::test(ManageSales::class)
            ->assertSee('Juana')
            ->filterTable('client_id', $client->id)
            ->assertCanSeeTableRecords([$hers])
            ->assertCanNotSeeTableRecords([$anonymous]);
    }

    public function test_a_sale_shows_its_client_even_after_the_client_is_deleted(): void
    {
        $client = Client::factory()->create(['name' => 'Juana']);
        $sale = app(Sales::class)->create((string) Str::uuid(), [$this->row->id => '1'], PaymentMethod::Cash, clientId: $client->id);

        $client->delete();

        $this->get(SaleResource::getUrl('view', ['record' => $sale]))->assertOk()->assertSee('Juana');
    }
}
