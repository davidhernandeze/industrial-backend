<?php

namespace Tests\Feature\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManageProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_a_product_only_needs_a_name(): void
    {
        Livewire::test(ManageProducts::class)
            ->callAction('create', data: ['name' => 'Leche'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['name' => 'Leche', 'barcode' => null, 'sku' => null]);
    }

    public function test_the_name_is_required(): void
    {
        Livewire::test(ManageProducts::class)
            ->callAction('create', data: ['name' => null])
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_barcodes_must_be_unique(): void
    {
        Product::factory()->create(['barcode' => '123']);

        Livewire::test(ManageProducts::class)
            ->callAction('create', data: ['name' => 'Otro', 'barcode' => '123'])
            ->assertHasFormErrors(['barcode' => 'unique']);
    }

    public function test_several_products_can_have_no_barcode(): void
    {
        Livewire::test(ManageProducts::class)->callAction('create', data: ['name' => 'Uno'])->assertHasNoFormErrors();
        Livewire::test(ManageProducts::class)->callAction('create', data: ['name' => 'Dos'])->assertHasNoFormErrors();

        $this->assertDatabaseCount('products', 2);
    }

    public function test_a_new_product_stores_its_picture_under_its_id(): void
    {
        Livewire::test(ManageProducts::class)
            ->callAction('create', data: ['name' => 'Leche', 'picture' => UploadedFile::fake()->image('leche.png')])
            ->assertHasNoFormErrors();

        $product = Product::query()->sole();

        $this->assertSame("products/{$product->id}.png", $product->picture);
        Storage::disk('public')->assertExists("products/{$product->id}.png");
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        Livewire::test(ManageProducts::class)
            ->callAction('create', data: ['name' => 'Leche', 'picture' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])
            ->assertHasFormErrors(['picture']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_editing_without_touching_the_picture_keeps_it(): void
    {
        $product = Product::factory()->create();
        $product->replacePicture(UploadedFile::fake()->image('a.png'));

        Livewire::test(ManageProducts::class)
            ->callAction(TestAction::make('edit')->table($product), data: ['name' => 'Otro'])
            ->assertHasNoFormErrors();

        $this->assertSame('Otro', $product->fresh()->name);
        $this->assertSame("products/{$product->id}.png", $product->fresh()->picture);
        Storage::disk('public')->assertExists("products/{$product->id}.png");
    }

    public function test_a_product_is_found_by_scanning_its_barcode_into_the_search(): void
    {
        $match = Product::factory()->create(['barcode' => '7501234567890']);
        $other = Product::factory()->create();

        Livewire::test(ManageProducts::class)
            ->searchTable('7501234567890')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_a_product_can_be_soft_deleted_and_restored(): void
    {
        $product = Product::factory()->create();

        Livewire::test(ManageProducts::class)->callAction(TestAction::make('delete')->table($product));
        $this->assertSoftDeleted($product);

        Livewire::test(ManageProducts::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restore')->table($product));
        $this->assertNotSoftDeleted($product);
    }

    public function test_the_view_page_shows_the_product(): void
    {
        $product = Product::factory()->create(['name' => 'Leche', 'barcode' => '123']);

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('Leche')
            ->assertSee('123');
    }

    public function test_the_catalog_is_only_for_admins(): void
    {
        $manager = User::factory()->create(['role' => 'branch_manager']);
        $manager->branches()->attach(Branch::factory()->create());

        $this->actingAs($manager)->get('/admin/products')->assertForbidden();
    }
}
