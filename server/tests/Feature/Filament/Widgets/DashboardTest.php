<?php

namespace Tests\Feature\Filament\Widgets;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Filament\Widgets\BranchOverview;
use App\Filament\Widgets\NotificationsTable;
use App\Filament\Widgets\PaymentMethodsChart;
use App\Filament\Widgets\SalesChart;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\User;
use App\Notifications\SaleOutOfStock;
use App\Services\Inventory;
use App\Services\Sales;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private BranchProduct $row;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->branch = Branch::factory()->create(['name' => 'Centro']);
        $this->row = BranchProduct::factory()->create([
            'branch_id' => $this->branch->id,
            'product_id' => Product::factory()->create(['name' => 'Pan']),
            'unit' => ProductUnit::Piece,
            'price' => '10.00',
        ]);
        $this->manager = User::factory()->create(['role' => Role::BranchManager]);
        $this->manager->branches()->attach($this->branch);
        $this->actingAs($this->manager);
    }

    private function sell(string $quantity = '2', PaymentMethod $method = PaymentMethod::Cash): void
    {
        app(Sales::class)->create((string) Str::uuid(), [$this->row->id => $quantity], $method);
    }

    public function test_managers_and_admins_see_the_indicators_and_charts(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(BranchOverview::class)
            ->assertSeeLivewire(SalesChart::class)
            ->assertSeeLivewire(PaymentMethodsChart::class)
            ->assertSeeLivewire(NotificationsTable::class);

        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSeeLivewire(BranchOverview::class);
    }

    public function test_other_roles_only_see_their_notifications(): void
    {
        $driver = User::factory()->create(['role' => Role::Driver]);
        $driver->branches()->attach($this->branch);

        $this->actingAs($driver)->get('/admin')
            ->assertOk()
            ->assertDontSeeLivewire(BranchOverview::class)
            ->assertDontSeeLivewire(SalesChart::class)
            ->assertSeeLivewire(NotificationsTable::class);
    }

    public function test_an_admin_without_any_branch_gets_no_indicators_and_no_error(): void
    {
        BranchProduct::query()->forceDelete();
        Branch::query()->forceDelete();

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertOk()
            ->assertDontSeeLivewire(BranchOverview::class);
    }

    public function test_the_indicators_show_todays_figures_and_the_inventory(): void
    {
        app(Inventory::class)->record($this->row, MovementType::Purchase, '10');
        $this->sell('2');

        Livewire::test(BranchOverview::class)
            ->assertSee('Centro')
            ->assertSee('$20.00')
            ->assertSee('$80.00')
            ->assertSee('Sin ventas ayer');
    }

    public function test_sales_are_compared_with_yesterday(): void
    {
        app(Inventory::class)->record($this->row, MovementType::Purchase, '100');
        $this->travel(-1)->days();
        $this->sell('2');
        $this->travelBack();
        $this->sell('3');

        Livewire::test(BranchOverview::class)->assertSee('+50% respecto a ayer');
    }

    public function test_a_drop_from_yesterday_reads_negative(): void
    {
        app(Inventory::class)->record($this->row, MovementType::Purchase, '100');
        $this->travel(-1)->days();
        $this->sell('4');
        $this->travelBack();
        $this->sell('1');

        Livewire::test(BranchOverview::class)->assertSee('-75% respecto a ayer');
    }

    public function test_negative_stock_is_counted_and_does_not_lower_the_inventory_value(): void
    {
        $inventory = app(Inventory::class);
        $inventory->record($this->row, MovementType::Adjustment, '-3');
        $other = BranchProduct::factory()->create(['branch_id' => $this->branch->id, 'price' => '5.00']);
        $inventory->record($other, MovementType::Purchase, '2');

        $this->assertSame(1, $inventory->negativeStockCount($this->branch));
        $this->assertEquals(10, $inventory->valueAtSalePrice($this->branch));
    }

    public function test_daily_totals_fill_days_without_sales_and_skip_cancellations(): void
    {
        $this->travel(-2)->days();
        $this->sell('1');
        $this->travelBack();

        app(Sales::class)->cancel(app(Sales::class)->create((string) Str::uuid(), [$this->row->id => '1'], PaymentMethod::Cash), 'Motivo');
        $this->sell('3');

        $days = app(Sales::class)->dailyTotals($this->branch, 3);

        $this->assertCount(3, $days);
        $this->assertSame(now()->toDateString(), $days->keys()->last());
        $this->assertEquals(10, $days->first()['total']);
        $this->assertSame(0, $days->slice(1, 1)->first()['count']);
        $this->assertEquals(30, $days->last()['total']);
    }

    public function test_the_notifications_table_lists_only_my_notifications(): void
    {
        $other = User::factory()->admin()->create();
        $this->manager->notify(new TestNotification('Para mí'));
        $other->notify(new TestNotification('Para otro'));

        Livewire::test(NotificationsTable::class)
            ->assertSee('Para mí')
            ->assertDontSee('Para otro');
    }

    public function test_a_notification_can_be_marked_as_read(): void
    {
        $this->manager->notify(new TestNotification('Aviso'));
        $notification = $this->manager->notifications()->sole();

        Livewire::test(NotificationsTable::class)
            ->callAction(TestAction::make('markAsRead')->table($notification));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_all_notifications_can_be_marked_as_read(): void
    {
        $this->manager->notify(new TestNotification('Uno'));
        $this->manager->notify(new TestNotification('Dos'));

        Livewire::test(NotificationsTable::class)->callAction(TestAction::make('markAllAsRead')->table());

        $this->assertSame(0, $this->manager->unreadNotifications()->count());
    }

    public function test_a_sale_notification_appears_with_a_link_to_the_sale(): void
    {
        $cashier = User::factory()->create(['role' => Role::Cashier]);
        $cashier->branches()->attach($this->branch);
        $this->actingAs($cashier);
        $this->sell('1');
        $this->actingAs($this->manager);

        $notification = $this->manager->notifications()->sole();

        $this->assertSame(SaleOutOfStock::class, $notification->type);

        Livewire::test(NotificationsTable::class)
            ->assertSee('Venta sin existencia en Centro')
            ->assertActionVisible(TestAction::make('open')->table($notification));
    }
}
