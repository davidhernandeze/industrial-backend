<?php

namespace Tests\Feature\Services;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Notifications\SaleOutOfStock;
use App\Services\Inventory;
use App\Services\SaleRejected;
use App\Services\Sales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    private Sales $sales;

    private Branch $branch;

    private User $cashier;

    private BranchProduct $milk;

    private BranchProduct $bread;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->sales = app(Sales::class);
        $this->branch = Branch::factory()->create(['name' => 'Alfa']);
        $this->milk = $this->row('Leche', ProductUnit::Kilogram, '20.00');
        $this->bread = $this->row('Pan', ProductUnit::Piece, '5.50');

        $this->cashier = User::factory()->create(['role' => Role::Cashier]);
        $this->cashier->branches()->attach($this->branch);
        $this->actingAs($this->cashier);
    }

    private function row(string $name, ProductUnit $unit, string $price): BranchProduct
    {
        return BranchProduct::factory()->create([
            'branch_id' => $this->branch->id,
            'product_id' => Product::factory()->create(['name' => $name]),
            'unit' => $unit,
            'price' => $price,
        ]);
    }

    /**
     * @param  array<int, string>|null  $lines
     */
    private function sell(?array $lines = null, PaymentMethod $method = PaymentMethod::Cash, ?string $received = null, ?string $uuid = null): mixed
    {
        return $this->sales->create($uuid ?? (string) Str::uuid(), $lines ?? [$this->bread->id => '2'], $method, $received);
    }

    private function stock(BranchProduct $row, string $quantity): void
    {
        app(Inventory::class)->record($row, MovementType::Purchase, $quantity);
    }

    public function test_a_sale_is_created_paid_with_its_data_and_lines(): void
    {
        $this->stock($this->milk, '10');
        $this->stock($this->bread, '10');

        $sale = $this->sell([$this->milk->id => '1.5', $this->bread->id => '2'], PaymentMethod::Card);

        $this->assertSame(SaleStatus::Paid, $sale->status);
        $this->assertSame(SaleType::PointOfSale, $sale->type);
        $this->assertSame('41.00', $sale->total);
        $this->assertTrue($sale->user->is($this->cashier));
        $this->assertSame($this->branch->id, $sale->branch_id);
        $this->assertSame('8.500', $this->milk->fresh()->stock);
        $this->assertSame('8.000', $this->bread->fresh()->stock);
        $this->assertCount(2, $sale->lines);
        $this->assertSame(PaymentMethod::Card, $sale->payments()->sole()->method);
        $this->assertSame('41.00', $sale->payments()->sole()->amount);
    }

    public function test_lines_keep_the_price_of_the_moment_of_sale(): void
    {
        $sale = $this->sell();

        $this->bread->update(['price' => '99.00']);

        $this->assertSame('5.50', $sale->lines()->sole()->unit_price);
        $this->assertSame('11.00', $sale->fresh()->total);
    }

    public function test_totals_round_each_line_to_cents(): void
    {
        $row = $this->row('Queso', ProductUnit::Kilogram, '33.33');

        $this->assertSame('11.10', $this->sell([$row->id => '0.333'])->total);
    }

    public function test_cash_records_the_amount_received_and_change(): void
    {
        $payment = $this->sell(received: '20')->payments()->sole();

        $this->assertSame('20.00', $payment->amount_received);
        $this->assertSame('9.00', $payment->change);
    }

    public function test_an_amount_received_below_the_total_is_rejected(): void
    {
        $this->expectException(SaleRejected::class);

        $this->sell(received: '1');
    }

    public function test_the_same_uuid_cannot_charge_twice(): void
    {
        $uuid = (string) Str::uuid();

        $first = $this->sell(uuid: $uuid);
        $second = $this->sell(uuid: $uuid);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_folios_count_per_branch(): void
    {
        $other = Branch::factory()->create(['name' => 'Zeta']);
        $this->cashier->branches()->attach($other);
        $otherRow = BranchProduct::factory()->create(['branch_id' => $other->id]);

        $first = $this->sell();
        $second = $this->sell();

        $this->assertSame([1, 2], [$first->folio, $second->folio]);
        $this->assertSame($this->branch->id, $first->branch_id);
        $this->assertNotSame($otherRow->branch_id, $first->branch_id);
    }

    public function test_an_empty_sale_is_rejected(): void
    {
        $this->expectException(SaleRejected::class);

        $this->sell([]);
    }

    public function test_a_zero_or_negative_quantity_is_rejected(): void
    {
        $this->expectException(SaleRejected::class);

        $this->sell([$this->bread->id => '-1']);
    }

    public function test_a_product_from_another_branch_is_rejected_without_leaving_anything(): void
    {
        auth()->logout();
        $foreign = BranchProduct::factory()->create();
        $this->actingAs($this->cashier);

        try {
            $this->sell([$this->bread->id => '1', $foreign->id => '1']);
            $this->fail('Expected the sale to be rejected.');
        } catch (SaleRejected) {
            $this->assertDatabaseCount('sales', 0);
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    public function test_a_discontinued_product_is_rejected(): void
    {
        $this->bread->product->delete();

        $this->expectException(SaleRejected::class);

        $this->sell();
    }

    public function test_a_failure_partway_leaves_no_sale_or_stock_change(): void
    {
        $this->stock($this->bread, '5');

        try {
            DB::transaction(function (): void {
                $this->sell();

                throw new RuntimeException('Failed after the sale.');
            });
        } catch (RuntimeException) {
            //
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame('5.000', $this->bread->fresh()->stock);
    }

    public function test_a_sale_without_stock_goes_through_and_is_flagged(): void
    {
        $sale = $this->sell();

        $this->assertSame('-2.000', $this->bread->fresh()->stock);
        $this->assertTrue($sale->lines()->sole()->isOversold());
    }

    public function test_one_notification_is_sent_per_sale_listing_every_product_that_went_negative(): void
    {
        $admin = User::factory()->admin()->create();
        $this->stock($this->milk, '5');

        $this->sell([$this->bread->id => '1', $this->row('Queso', ProductUnit::Piece, '1.00')->id => '1', $this->milk->id => '1']);

        Notification::assertSentTimes(SaleOutOfStock::class, 1);
        Notification::assertSentTo($admin, SaleOutOfStock::class, fn (SaleOutOfStock $notification): bool => count($notification->lineIds) === 2);
    }

    public function test_nothing_is_sent_when_every_product_stays_at_zero_or_above(): void
    {
        User::factory()->admin()->create();
        $this->stock($this->bread, '2');

        $this->sell();

        Notification::assertNothingSent();
    }

    public function test_a_product_already_negative_stays_flagged_but_sends_nothing_more(): void
    {
        User::factory()->admin()->create();

        $this->sell();
        $second = $this->sell();

        Notification::assertSentTimes(SaleOutOfStock::class, 1);
        $this->assertTrue($second->lines()->sole()->isOversold());
    }

    public function test_a_product_restocked_then_going_negative_notifies_again(): void
    {
        User::factory()->admin()->create();

        $this->sell();
        $this->stock($this->bread, '3');
        $this->sell();

        Notification::assertSentTimes(SaleOutOfStock::class, 2);
    }

    public function test_only_admins_and_that_branchs_managers_are_notified(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => Role::BranchManager]);
        $manager->branches()->attach($this->branch);
        $otherManager = User::factory()->create(['role' => Role::BranchManager]);
        $otherManager->branches()->attach(Branch::factory()->create());

        $this->sell();

        Notification::assertSentTo([$admin, $manager], SaleOutOfStock::class);
        Notification::assertNotSentTo([$otherManager, $this->cashier], SaleOutOfStock::class);
    }

    public function test_a_failing_notification_does_not_fail_the_sale(): void
    {
        Exceptions::fake();
        User::factory()->admin()->create();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('Redis is down.'));

        $sale = $this->sell();

        $this->assertTrue($sale->exists);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_cancelling_restores_stock_through_new_movements(): void
    {
        $this->stock($this->bread, '10');
        $sale = $this->sell();
        $before = InventoryMovement::query()->count();

        $this->sales->cancel($sale, 'Error de captura');

        $fresh = $sale->fresh();

        $this->assertSame(SaleStatus::Cancelled, $fresh->status);
        $this->assertSame('Error de captura', $fresh->cancellation_reason);
        $this->assertTrue($fresh->cancelledBy->is($this->cashier));
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame('10.000', $this->bread->fresh()->stock);
        $this->assertSame($before + 1, InventoryMovement::query()->count());
        $this->assertCount(1, $fresh->lines);
        $this->assertTrue(app(Inventory::class)->discrepancies()->isEmpty());
    }

    public function test_a_sale_cannot_be_cancelled_twice(): void
    {
        $this->stock($this->bread, '10');
        $sale = $this->sell();
        $this->sales->cancel($sale, 'Uno');

        try {
            $this->sales->cancel($sale, 'Dos');
            $this->fail('Expected the second cancellation to be rejected.');
        } catch (SaleRejected) {
            $this->assertSame('10.000', $this->bread->fresh()->stock);
            $this->assertSame('Uno', $sale->fresh()->cancellation_reason);
        }
    }

    public function test_a_sale_can_be_cancelled_after_its_product_left_the_branch(): void
    {
        $sale = $this->sell();
        $this->bread->delete();

        $this->sales->cancel($sale, 'Motivo');

        $this->assertSame('0.000', BranchProduct::withTrashed()->find($this->bread->id)->stock);
    }

    public function test_the_overview_only_counts_todays_paid_sales(): void
    {
        $this->travel(-2)->days();
        $this->sell();
        $this->travelBack();

        $this->sell(method: PaymentMethod::Card);
        $this->sell(method: PaymentMethod::Cash);
        $cancelled = $this->sell(method: PaymentMethod::Cash);
        $this->sales->cancel($cancelled, 'Motivo');

        $overview = $this->sales->overview($this->branch);

        $this->assertSame(2, $overview['count']);
        $this->assertEquals(22, $overview['total']);
        $this->assertSame('11.00', $overview['average']);
        $this->assertSame(1, $overview['cancelled']);
        $this->assertEquals(11, $overview['by_method']['card']);
        $this->assertEquals(11, $overview['by_method']['cash']);
    }

    public function test_the_overview_handles_a_day_without_sales(): void
    {
        $overview = $this->sales->overview($this->branch);

        $this->assertSame(0, $overview['count']);
        $this->assertSame('0', $overview['average']);
    }
}
