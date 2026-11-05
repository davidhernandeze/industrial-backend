<?php

namespace Tests\Feature\Notifications;

use App\Enums\MovementType;
use App\Enums\ProductUnit;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Notifications\SaleOutOfStock;
use App\Services\Inventory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SaleOutOfStockTest extends TestCase
{
    use RefreshDatabase;

    private function saleWithoutStock(): BranchProduct
    {
        $row = BranchProduct::factory()->create([
            'branch_id' => Branch::factory()->create(['name' => 'Centro']),
            'product_id' => Product::factory()->create(['name' => 'Leche']),
            'unit' => ProductUnit::Kilogram,
        ]);

        app(Inventory::class)->record($row, MovementType::Sale, '1.5');

        return $row;
    }

    public function test_it_is_stored_for_the_user_in_the_format_the_panel_reads(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana']);

        $this->saleWithoutStock();

        $notification = $admin->notifications()->sole();

        $this->assertSame('filament', $notification->data['format']);
        $this->assertSame('Venta sin existencia: Leche en Centro', $notification->data['title']);
        $this->assertSame('Se vendieron 1.5 kg y la existencia quedó en -1.5 kg.', $notification->data['body']);
        $this->assertNull($notification->read_at);
    }

    public function test_the_email_is_in_spanish_and_links_to_the_movement(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana']);
        Notification::fake();
        $this->saleWithoutStock();
        $movement = InventoryMovement::query()->sole();

        $mail = (new SaleOutOfStock($movement))->toMail($admin);
        $html = (string) $mail->render();

        $this->assertSame('Venta sin existencia: Leche en Centro', $mail->subject);
        $this->assertStringContainsString('Hola, Ana', $html);
        $this->assertStringContainsString('Se vendieron 1.5 kg', $html);
        $this->assertStringContainsString('Saludos', $html);
        $this->assertStringContainsString(InventoryMovementResource::getUrl('view', ['record' => $movement]), $html);
    }

    public function test_it_goes_through_the_queue(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(SaleOutOfStock::class));
    }
}
