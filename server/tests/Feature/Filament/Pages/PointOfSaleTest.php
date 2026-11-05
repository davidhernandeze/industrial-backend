<?php

namespace Tests\Feature\Filament\Pages;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Filament\Pages\PointOfSale;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory;
use App\Support\PosCart;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PointOfSaleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $cashier;

    private BranchProduct $bread;

    private BranchProduct $cheese;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Notification::fake();

        $this->branch = Branch::factory()->create();
        $this->bread = $this->row(['name' => 'Pan', 'barcode' => '7501', 'sku' => 'PAN-1'], ProductUnit::Piece, '10.00');
        $this->cheese = $this->row(['name' => 'Queso', 'barcode' => '7502'], ProductUnit::Kilogram, '100.00');

        $this->cashier = User::factory()->create(['role' => Role::Cashier]);
        $this->cashier->branches()->attach($this->branch);
        $this->actingAs($this->cashier);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function row(array $product, ProductUnit $unit, string $price, ?Branch $branch = null): BranchProduct
    {
        return BranchProduct::factory()->create([
            'branch_id' => ($branch ?? $this->branch)->id,
            'product_id' => Product::factory()->create($product),
            'unit' => $unit,
            'price' => $price,
        ]);
    }

    private function pos(): mixed
    {
        return Livewire::test(PointOfSale::class);
    }

    public function test_only_cashiers_can_open_it(): void
    {
        $this->get(PointOfSale::getUrl())->assertOk();

        foreach ([Role::Admin, Role::BranchManager, Role::Driver] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->branches()->syncWithoutDetaching($this->branch);

            $this->actingAs($user)->get(PointOfSale::getUrl())->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get(PointOfSale::getUrl())->assertRedirect('/admin/login');
    }

    public function test_cashiers_get_no_menu_or_dashboard_link(): void
    {
        $this->get(PointOfSale::getUrl())
            ->assertOk()
            ->assertDontSee('fi-sidebar-item', escape: false)
            ->assertDontSee('Escritorio');
    }

    public function test_cashiers_opening_the_dashboard_go_to_the_till(): void
    {
        $this->get('/admin')->assertRedirect(PointOfSale::getUrl());
    }

    public function test_the_brand_link_points_cashiers_to_the_till(): void
    {
        $this->assertSame(PointOfSale::getUrl(), Filament::getCurrentOrDefaultPanel()->getHomeUrl());
    }

    public function test_other_roles_keep_their_menu_and_it_does_not_offer_the_till(): void
    {
        $manager = User::factory()->create(['role' => Role::BranchManager]);
        $manager->branches()->attach($this->branch);

        $this->actingAs($manager)->get('/admin')
            ->assertOk()
            ->assertSee('Escritorio')
            ->assertDontSee('Punto de venta');
    }

    public function test_cashiers_get_no_notification_bell(): void
    {
        $this->assertFalse(Filament::getCurrentOrDefaultPanel()->hasDatabaseNotifications());
    }

    public function test_a_multi_branch_cashier_chooses_the_branch_on_the_page(): void
    {
        $this->cashier->branches()->attach(Branch::factory()->create());

        $html = $this->get(PointOfSale::getUrl())->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="current-branch"'));
    }

    public function test_the_on_screen_keyboard_is_shown(): void
    {
        $this->get(PointOfSale::getUrl())
            ->assertOk()
            ->assertSee('press(', escape: false)
            ->assertSee('Espacio')
            ->assertSee('Ñ');
    }

    public function test_cashiers_land_on_it_after_logging_in(): void
    {
        auth()->logout();

        Livewire::test(Login::class)
            ->fillForm(['email' => $this->cashier->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect(PointOfSale::getUrl());
    }

    public function test_other_roles_land_on_the_dashboard_after_logging_in(): void
    {
        $admin = User::factory()->admin()->create();
        auth()->logout();

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect(Filament::getUrl());
    }

    public function test_scanning_a_barcode_adds_the_product_and_scanning_again_adds_one_more(): void
    {
        $this->pos()->call('scan', '7501')->call('scan', '7501')->assertSee('Pan')->assertSee('$20.00');

        $this->assertSame([$this->bread->id => '2.000'], PosCart::current()->lines());
    }

    public function test_a_sku_also_scans(): void
    {
        $this->pos()->call('scan', 'PAN-1');

        $this->assertArrayHasKey($this->bread->id, PosCart::current()->lines());
    }

    public function test_scanner_whitespace_is_ignored(): void
    {
        $this->pos()->call('scan', " 7501\n");

        $this->assertArrayHasKey($this->bread->id, PosCart::current()->lines());
    }

    public function test_an_unknown_code_changes_nothing_and_warns(): void
    {
        $this->pos()->call('scan', '0000')->assertNotified();

        $this->assertTrue(PosCart::current()->isEmpty());
    }

    public function test_a_product_of_another_branch_cannot_be_scanned(): void
    {
        auth()->logout();
        $this->row(['barcode' => '9999'], ProductUnit::Piece, '1.00', Branch::factory()->create());
        $this->actingAs($this->cashier);

        $this->pos()->call('scan', '9999');

        $this->assertTrue(PosCart::current()->isEmpty());
    }

    public function test_a_product_from_another_branch_cannot_be_added_by_id(): void
    {
        auth()->logout();
        $foreign = $this->row([], ProductUnit::Piece, '1.00', Branch::factory()->create());
        $this->actingAs($this->cashier);

        $this->pos()->call('addProduct', $foreign->id);

        $this->assertTrue(PosCart::current()->isEmpty());
    }

    public function test_a_kilogram_product_asks_for_its_weight(): void
    {
        $this->pos()->call('scan', '7502')->assertDispatched('pos-focus-quantity', id: $this->cheese->id);
    }

    public function test_a_piece_product_does_not_ask_for_a_quantity(): void
    {
        $this->pos()->call('scan', '7501')->assertNotDispatched('pos-focus-quantity');
    }

    public function test_searching_lists_matches_and_adding_one_clears_the_results(): void
    {
        $page = $this->pos()->call('search', 'Que')->assertSet('results.0.name', 'Queso');

        $page->call('addProduct', $this->cheese->id)->assertSet('results', []);

        $this->assertArrayHasKey($this->cheese->id, PosCart::current()->lines());
    }

    public function test_a_quantity_can_be_changed(): void
    {
        $this->pos()->call('scan', '7502')->call('updateQuantity', $this->cheese->id, '0.750')->assertSee('$75.00');

        $this->assertSame('0.750', PosCart::current()->lines()[$this->cheese->id]);
    }

    public function test_a_quantity_of_zero_removes_the_line(): void
    {
        $this->pos()->call('scan', '7501')->call('updateQuantity', $this->bread->id, '0');

        $this->assertTrue(PosCart::current()->isEmpty());
    }

    public function test_pieces_cannot_be_fractional_and_bad_quantities_are_refused(): void
    {
        $page = $this->pos()->call('scan', '7501');

        foreach (['1.5', '-1', 'abc'] as $quantity) {
            $page->call('updateQuantity', $this->bread->id, $quantity)->assertNotified();
        }

        $this->assertSame('1.000', PosCart::current()->lines()[$this->bread->id]);
    }

    public function test_a_line_can_be_removed(): void
    {
        $this->pos()->call('scan', '7501')->call('removeLine', $this->bread->id);

        $this->assertTrue(PosCart::current()->isEmpty());
    }

    public function test_the_sale_can_be_cleared(): void
    {
        $this->pos()->call('scan', '7501')->callAction('clear');

        $this->assertTrue(PosCart::current()->isEmpty());
    }

    public function test_checkout_is_disabled_on_an_empty_sale(): void
    {
        $this->pos()->assertActionDisabled('checkout');
    }

    public function test_a_sale_is_submitted_and_a_new_one_starts_immediately(): void
    {
        app(Inventory::class)->record($this->bread, MovementType::Purchase, '10');
        $page = $this->pos()->call('scan', '7501')->call('scan', '7501');
        $uuid = PosCart::current()->uuid();

        $page->callAction('checkout', data: ['payment_method' => PaymentMethod::Cash->value])
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertDispatched('pos-focus-scan')
            ->assertSee(__('point_of_sale.empty'));

        $sale = Sale::query()->sole();

        $this->assertSame('20.00', $sale->total);
        $this->assertSame($this->cashier->id, $sale->user_id);
        $this->assertSame($this->branch->id, $sale->branch_id);
        $this->assertSame('8.000', $this->bread->fresh()->stock);
        $this->assertTrue(PosCart::current()->isEmpty());
        $this->assertNotSame($uuid, PosCart::current()->uuid());
    }

    public function test_recibido_is_filled_with_the_total_and_locked(): void
    {
        $this->pos()->call('scan', '7501')
            ->mountAction('checkout')
            ->assertSchemaStateSet(['amount_received' => '10.00'])
            ->assertFormFieldDisabled('amount_received');
    }

    public function test_cash_records_the_total_as_received_whatever_the_client_sends(): void
    {
        $this->pos()->call('scan', '7501')
            ->callAction('checkout', data: ['payment_method' => PaymentMethod::Cash->value, 'amount_received' => '500'])
            ->assertHasNoFormErrors();

        $payment = Sale::query()->sole()->payments()->sole();

        $this->assertSame('10.00', $payment->amount_received);
        $this->assertSame('0.00', $payment->change);
    }

    public function test_the_plus_and_minus_buttons_change_a_piece_by_one(): void
    {
        $page = $this->pos()->call('scan', '7501')->call('changeQuantity', $this->bread->id, 1);
        $this->assertSame('2.000', PosCart::current()->lines()[$this->bread->id]);

        $page->call('changeQuantity', $this->bread->id, -1);
        $this->assertSame('1.000', PosCart::current()->lines()[$this->bread->id]);
    }

    public function test_the_buttons_change_a_kilogram_product_by_a_quarter(): void
    {
        $this->pos()->call('scan', '7502')->call('changeQuantity', $this->cheese->id, 1);

        $this->assertSame('1.250', PosCart::current()->lines()[$this->cheese->id]);
    }

    public function test_the_minus_button_removes_the_last_unit(): void
    {
        $this->pos()->call('scan', '7501')->call('changeQuantity', $this->bread->id, -1);

        $this->assertTrue(PosCart::current()->isEmpty());
    }

    public function test_the_buttons_ignore_a_product_not_in_the_cart(): void
    {
        $this->pos()->call('changeQuantity', $this->bread->id, 1);

        $this->assertTrue(PosCart::current()->isEmpty());
    }

    public function test_kilogram_lines_offer_common_weights_and_piece_lines_do_not(): void
    {
        $this->pos()->call('scan', '7502')->assertSee('250 g')->assertSee('1.5 kg');

        PosCart::current()->clear();

        $this->pos()->call('scan', '7501')->assertDontSee('250 g');
    }

    public function test_choosing_a_common_weight_sets_the_quantity(): void
    {
        $this->pos()->call('scan', '7502')->call('updateQuantity', $this->cheese->id, '0.25')->assertSee('$25.00');

        $this->assertSame('0.250', PosCart::current()->lines()[$this->cheese->id]);
    }

    public function test_card_and_transfer_keep_a_reference(): void
    {
        $this->pos()->call('scan', '7501')
            ->callAction('checkout', data: ['payment_method' => PaymentMethod::Card->value, 'reference' => 'AUT-123'])
            ->assertHasNoFormErrors();

        $payment = Sale::query()->sole()->payments()->sole();

        $this->assertSame(PaymentMethod::Card, $payment->method);
        $this->assertSame('AUT-123', $payment->reference);
        $this->assertNull($payment->amount_received);
    }

    public function test_a_price_change_while_paying_stops_the_sale_for_review(): void
    {
        $page = $this->pos()->call('scan', '7501')->mountAction('checkout');

        $this->bread->update(['price' => '99.00']);

        $page->callMountedAction()->assertNotified();

        $this->assertDatabaseCount('sales', 0);
        $this->assertFalse(PosCart::current()->isEmpty());
    }

    public function test_a_product_removed_from_the_branch_mid_sale_drops_out(): void
    {
        $page = $this->pos()->call('scan', '7501')->call('scan', '7502');

        $this->bread->delete();

        $page->call('$refresh')->assertDontSee('Pan')->assertSee('Queso');
    }

    public function test_the_cart_survives_a_page_reload(): void
    {
        $this->pos()->call('scan', '7501');

        $this->pos()->assertSee('Pan');
    }

    public function test_a_single_branch_cashier_sees_no_branch_selector(): void
    {
        $this->get(PointOfSale::getUrl())->assertOk()->assertDontSee('id="current-branch"', escape: false);
    }
}
