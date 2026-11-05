<?php

namespace Tests\Feature\Models;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_barcode_is_found_with_scanner_whitespace(): void
    {
        $product = Product::factory()->create(['barcode' => '7501234567890']);

        $this->assertTrue(Product::findByBarcode(" 7501234567890\n")->is($product));
        $this->assertNull(Product::findByBarcode('0000'));
    }

    public function test_barcodes_are_stored_trimmed_and_empty_ones_stay_null(): void
    {
        $this->assertSame('123', Product::factory()->create(['barcode' => " 123\t"])->barcode);

        Product::factory()->count(2)->create(['barcode' => '']);

        $this->assertSame(2, Product::query()->whereNull('barcode')->count());
    }

    public function test_the_picture_is_stored_under_the_product_id(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $product->replacePicture(UploadedFile::fake()->image('anything.png'));

        $this->assertSame("products/{$product->id}.png", $product->fresh()->picture);
        Storage::disk('public')->assertExists("products/{$product->id}.png");
    }

    public function test_replacing_a_picture_with_another_format_leaves_no_old_file(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $product->replacePicture(UploadedFile::fake()->image('first.png'));
        $product->replacePicture(UploadedFile::fake()->image('second.jpg'));

        Storage::disk('public')->assertMissing("products/{$product->id}.png");
        Storage::disk('public')->assertExists("products/{$product->id}.jpg");
    }

    public function test_removing_the_picture_deletes_the_file(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $product->replacePicture(UploadedFile::fake()->image('first.png'));

        $product->removePicture();

        $this->assertNull($product->fresh()->picture);
        Storage::disk('public')->assertMissing("products/{$product->id}.png");
    }

    public function test_not_in_branch_excludes_only_products_offered_by_that_branch(): void
    {
        $branch = Branch::factory()->create();
        $offered = BranchProduct::factory()->create(['branch_id' => $branch->id])->product;
        $elsewhere = BranchProduct::factory()->create()->product;
        $unused = Product::factory()->create();

        $ids = Product::notInBranch($branch)->pluck('id');

        $this->assertNotContains($offered->id, $ids);
        $this->assertContains($elsewhere->id, $ids);
        $this->assertContains($unused->id, $ids);
    }

    public function test_a_product_removed_from_a_branch_counts_as_not_in_it(): void
    {
        $row = BranchProduct::factory()->create();

        $row->delete();

        $this->assertContains($row->product_id, Product::notInBranch($row->branch_id)->pluck('id'));
    }
}
