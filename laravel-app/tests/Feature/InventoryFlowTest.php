<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array<string, mixed>
     */
    private function managerSession(): array
    {
        return [
            'auth_user_id' => 1,
            'user_id' => 1,
            'user_role' => 'manager',
            'user_name' => 'Manager User',
        ];
    }

    public function test_inventory_page_loads(): void
    {
        $response = $this->withSession($this->managerSession())->get('/inventory');

        $response->assertOk();
        $response->assertSee('Inventory & Reorder', false);
        $response->assertSee('Tenant:');
        $response->assertSee('Location:');
    }

    public function test_can_add_inventory_product_and_place_order(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withSession($this->managerSession())->get('/inventory')->assertOk();
        $locationId = (int) Location::query()->value('id');

        $this->withSession($this->managerSession())->post('/inventory/products', [
            'location_id' => $locationId,
            'sku' => 'SKU-1001',
            'name' => 'Test Coffee',
            'unit' => 'ea',
            'on_hand' => '4',
            'reorder_point' => '5',
            'target_max' => '15',
            'last_cost' => '2.50',
        ])->assertRedirect('/inventory?location_id=' . $locationId);

        $product = Product::query()->where('sku', 'SKU-1001')->first();
        $this->assertNotNull($product);

        $item = InventoryItem::query()->where('product_id', $product->id)->first();
        $this->assertNotNull($item);
        $this->assertSame('low', $item->status);
        $this->assertSame(250, $item->last_cost_cents);

        $this->withSession($this->managerSession())->post('/inventory/items/' . $item->id . '/order', [
            'location_id' => $locationId,
            'quantity_ordered' => '11',
            'order_date' => '2026-02-27',
            'expected_delivery_date' => '2026-03-01',
            'unit_cost' => '2.50',
        ])->assertRedirect('/inventory?location_id=' . $locationId);

        $order = PurchaseOrder::query()->where('product_id', $product->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('11.000', $order->quantity_ordered);
    }
}

