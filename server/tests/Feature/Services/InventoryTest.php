<?php

namespace Tests\Feature\Services;

use App\Enums\MovementType;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Notifications\SaleOutOfStock;
use App\Services\Inventory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Inventory $inventory;

    private BranchProduct $row;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->inventory = app(Inventory::class);
        $this->row = BranchProduct::factory()->create(['unit' => ProductUnit::Kilogram]);
    }

    public function test_a_purchase_adds_and_a_sale_subtracts(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '10');
        $sale = $this->inventory->record($this->row, MovementType::Sale, '2.5');

        $this->assertSame('-2.500', $sale->quantity);
        $this->assertSame('7.500', $sale->balance_after);
        $this->assertSame('7.500', $this->row->fresh()->stock);
    }

    public function test_quantities_are_positive_for_any_input_sign_on_purchases_and_sales(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '-10');
        $this->inventory->record($this->row, MovementType::Sale, '-4');

        $this->assertSame('6.000', $this->row->fresh()->stock);
    }

    public function test_an_adjustment_keeps_its_sign(): void
    {
        $this->inventory->record($this->row, MovementType::Adjustment, '5');
        $this->inventory->record($this->row, MovementType::Adjustment, '-8');

        $this->assertSame('-3.000', $this->row->fresh()->stock);
    }

    public function test_decimals_stay_exact_where_floats_would_drift(): void
    {
        foreach (range(1, 10) as $i) {
            $this->inventory->record($this->row, MovementType::Purchase, '0.1');
        }

        $this->assertSame('1.000', $this->row->fresh()->stock);
    }

    public function test_a_stale_model_cannot_produce_a_wrong_balance(): void
    {
        $stale = BranchProduct::query()->find($this->row->id);

        $this->inventory->record($this->row, MovementType::Purchase, '10');
        $movement = $this->inventory->record($stale, MovementType::Sale, '1');

        $this->assertSame('9.000', $movement->balance_after);
    }

    public function test_a_zero_quantity_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->inventory->record($this->row, MovementType::Purchase, '0.000');
    }

    public function test_the_movement_records_its_branch_user_and_reference(): void
    {
        $user = User::factory()->admin()->create();
        $reference = Product::factory()->create();
        $this->actingAs($user);

        $movement = $this->inventory->record($this->row, MovementType::Sale, '1', 'Nota', $reference);

        $this->assertSame($this->row->branch_id, $movement->branch_id);
        $this->assertTrue($movement->user->is($user));
        $this->assertTrue($movement->reference->is($reference));
        $this->assertSame('Nota', $movement->note);
    }

    public function test_stock_and_ledger_commit_together_or_not_at_all(): void
    {
        try {
            DB::transaction(function (): void {
                $this->inventory->record($this->row, MovementType::Purchase, '5');

                throw new RuntimeException('The sale failed.');
            });
        } catch (RuntimeException) {
            //
        }

        $this->assertSame('0.000', $this->row->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_a_movement_cannot_be_recorded_in_a_branch_the_user_does_not_belong_to(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::BranchManager]));

        try {
            $this->inventory->record($this->row, MovementType::Purchase, '1');
            $this->fail('Expected the movement to be refused.');
        } catch (AuthorizationException) {
            $this->assertSame('0.000', $this->row->fresh()->stock);
        }
    }

    public function test_a_movement_cannot_be_edited(): void
    {
        $movement = $this->inventory->record($this->row, MovementType::Purchase, '1');

        $this->expectException(LogicException::class);

        $movement->update(['note' => 'Cambiado']);
    }

    public function test_a_movement_cannot_be_deleted(): void
    {
        $movement = $this->inventory->record($this->row, MovementType::Purchase, '1');

        $this->expectException(LogicException::class);

        $movement->delete();
    }

    public function test_editing_a_row_cannot_change_its_stock(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '10');

        $this->row->fresh()->update(['stock' => '999', 'price' => '5.00']);

        $this->assertSame('10.000', $this->row->fresh()->stock);
    }

    public function test_stock_changes_do_not_write_entity_logs(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '10');

        $this->assertDatabaseMissing('activity_log', ['subject_id' => $this->row->id, 'event' => 'updated']);
    }

    public function test_the_unit_of_a_row_is_final(): void
    {
        $this->expectException(LogicException::class);

        $this->row->update(['unit' => ProductUnit::Piece]);
    }

    public function test_a_sale_reaching_exactly_zero_is_not_oversold(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '2');
        $sale = $this->inventory->record($this->row, MovementType::Sale, '2');

        $this->assertFalse($sale->isOversold());
        Notification::assertNothingSent();
    }

    public function test_a_sale_without_stock_is_recorded_and_flagged(): void
    {
        $sale = $this->inventory->record($this->row, MovementType::Sale, '3');

        $this->assertSame('-3.000', $this->row->fresh()->stock);
        $this->assertTrue($sale->isOversold());
        $this->assertSame([$sale->id], InventoryMovement::oversold()->pluck('id')->all());
    }

    public function test_a_negative_adjustment_is_not_flagged_as_a_sale(): void
    {
        $this->inventory->record($this->row, MovementType::Adjustment, '-3');

        $this->assertSame(0, InventoryMovement::oversold()->count());
        Notification::assertNothingSent();
    }

    public function test_an_oversold_sale_notifies_admins_and_only_that_branchs_managers(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => Role::BranchManager]);
        $manager->branches()->attach($this->row->branch_id);
        $otherManager = User::factory()->create(['role' => Role::BranchManager]);
        $otherManager->branches()->attach(Branch::factory()->create());
        $cashier = User::factory()->create(['role' => Role::Cashier]);
        $cashier->branches()->attach($this->row->branch_id);
        $deleted = User::factory()->admin()->create();
        $deleted->delete();

        $this->inventory->record($this->row, MovementType::Sale, '1');

        Notification::assertSentTo([$admin, $manager], SaleOutOfStock::class);
        Notification::assertNotSentTo([$otherManager, $cashier, $deleted], SaleOutOfStock::class);
    }

    public function test_nothing_is_notified_when_the_transaction_rolls_back(): void
    {
        User::factory()->admin()->create();

        try {
            DB::transaction(function (): void {
                $this->inventory->record($this->row, MovementType::Sale, '1');

                throw new RuntimeException('The sale failed.');
            });
        } catch (RuntimeException) {
            //
        }

        Notification::assertNothingSent();
    }

    public function test_a_failing_notification_does_not_fail_the_sale(): void
    {
        Exceptions::fake();
        User::factory()->admin()->create();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('Redis is down.'));

        $sale = $this->inventory->record($this->row, MovementType::Sale, '1');

        $this->assertTrue($sale->exists);
        $this->assertSame('-1.000', $this->row->fresh()->stock);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_reconcile_passes_when_stock_matches_the_ledger(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '5');

        $this->artisan('inventory:reconcile')->assertSuccessful();
    }

    public function test_reconcile_reports_a_drifted_balance(): void
    {
        Log::spy();
        $this->inventory->record($this->row, MovementType::Purchase, '5');
        DB::table('branch_product')->where('id', $this->row->id)->update(['stock' => '4']);

        $this->artisan('inventory:reconcile')->assertFailed();

        Log::shouldHaveReceived('error')->once();
    }

    public function test_reconcile_includes_soft_deleted_rows_and_rows_without_movements(): void
    {
        $this->inventory->record($this->row, MovementType::Purchase, '5');
        $this->row->delete();
        $other = BranchProduct::factory()->create();
        DB::table('branch_product')->where('id', $other->id)->update(['stock' => '1']);

        $this->assertSame([$other->id], $this->inventory->discrepancies()->pluck('id')->all());
    }
}
