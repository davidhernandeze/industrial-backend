<?php

namespace Tests\Feature\Support;

use App\Enums\ProductUnit;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Support\PosCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCartTest extends TestCase
{
    use RefreshDatabase;

    private PosCart $cart;

    private BranchProduct $row;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::factory()->create();
        $this->row = BranchProduct::factory()->create(['branch_id' => $branch->id, 'price' => '10.00', 'unit' => ProductUnit::Kilogram]);
        $this->cart = new PosCart(1, $branch->id);
    }

    public function test_adding_a_product_again_increases_its_quantity(): void
    {
        $this->cart->add($this->row);
        $this->cart->add($this->row);

        $this->assertSame([$this->row->id => '2.000'], $this->cart->lines());
    }

    public function test_it_survives_between_requests_and_keeps_its_uuid(): void
    {
        $this->cart->add($this->row);

        $again = new PosCart(1, $this->row->branch_id);

        $this->assertSame($this->cart->lines(), $again->lines());
        $this->assertSame($this->cart->uuid(), $again->uuid());
    }

    public function test_carts_are_separate_per_cashier_and_branch(): void
    {
        $this->cart->add($this->row);

        $this->assertTrue((new PosCart(2, $this->row->branch_id))->isEmpty());
        $this->assertTrue((new PosCart(1, $this->row->branch_id + 1))->isEmpty());
    }

    public function test_clearing_starts_a_new_sale_with_a_new_uuid(): void
    {
        $this->cart->add($this->row);
        $uuid = $this->cart->uuid();

        $this->cart->clear();

        $this->assertTrue($this->cart->isEmpty());
        $this->assertNotSame($uuid, $this->cart->uuid());
    }

    public function test_the_total_uses_live_prices(): void
    {
        $this->cart->set($this->row->id, '1.5');
        $this->assertSame('15.00', $this->cart->total());

        $this->row->update(['price' => '20.00']);
        $this->assertSame('30.00', $this->cart->total());
    }

    public function test_unavailable_products_drop_out_of_the_items(): void
    {
        $this->cart->add($this->row);

        $this->row->delete();

        $this->assertTrue($this->cart->items()->isEmpty());
        $this->assertSame('0.00', $this->cart->total());
    }

    public function test_a_discontinued_product_drops_out_of_the_items(): void
    {
        $this->cart->add($this->row);

        Product::query()->findOrFail($this->row->product_id)->delete();

        $this->assertTrue($this->cart->items()->isEmpty());
    }
}
