<?php

namespace Tests\Feature\Notifications;

use App\Enums\PaymentMethod;
use App\Enums\ProductUnit;
use App\Enums\Role;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\SaleOutOfStock;
use App\Services\Sales;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleOutOfStockTest extends TestCase
{
    use RefreshDatabase;

    private function saleWithoutStock(): Sale
    {
        $branch = Branch::factory()->create(['name' => 'Centro']);
        $row = BranchProduct::factory()->create([
            'branch_id' => $branch->id,
            'product_id' => Product::factory()->create(['name' => 'Leche']),
            'unit' => ProductUnit::Kilogram,
        ]);
        $cashier = User::factory()->create(['role' => Role::Cashier]);
        $cashier->branches()->attach($branch);
        $this->actingAs($cashier);

        return app(Sales::class)->create((string) Str::uuid(), [$row->id => '1.5'], PaymentMethod::Cash);
    }

    public function test_it_is_stored_for_the_user_in_the_format_the_panel_reads(): void
    {
        $admin = User::factory()->admin()->create();

        $this->saleWithoutStock();

        $notification = $admin->notifications()->sole();

        $this->assertSame('filament', $notification->data['format']);
        $this->assertSame('Venta sin existencia en Centro', $notification->data['title']);
        $this->assertSame('Leche: se vendieron 1.5 kg y la existencia quedó en -1.5 kg.', $notification->data['body']);
        $this->assertNull($notification->read_at);
    }

    public function test_the_email_is_in_spanish_and_links_to_the_sale(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana']);
        Notification::fake();
        $sale = $this->saleWithoutStock();

        $mail = (new SaleOutOfStock($sale, $sale->lines()->pluck('id')->all()))->toMail($admin);
        $html = (string) $mail->render();

        $this->assertSame('Venta sin existencia en Centro', $mail->subject);
        $this->assertStringContainsString('Hola, Ana', $html);
        $this->assertStringContainsString("La venta #{$sale->folio} dejó productos sin existencia", $html);
        $this->assertStringContainsString('Leche: se vendieron 1.5 kg', $html);
        $this->assertStringContainsString('Saludos', $html);
        $this->assertStringContainsString(SaleResource::getUrl('view', ['record' => $sale]), $html);
    }

    public function test_it_goes_through_the_queue(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(SaleOutOfStock::class));
    }
}
