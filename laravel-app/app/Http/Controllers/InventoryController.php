<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\AppContextResolver;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        [$tenant, $location, $locations] = $this->resolveContext($request);

        $pendingByProduct = PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->where('status', 'pending')
            ->selectRaw('product_id, SUM(quantity_ordered - quantity_received) AS pending_qty')
            ->groupBy('product_id')
            ->pluck('pending_qty', 'product_id');

        $inventoryItems = InventoryItem::query()
            ->with('product')
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->orderByDesc('status')
            ->orderBy('id')
            ->get()
            ->map(function (InventoryItem $item) use ($pendingByProduct) {
                $pendingQty = (float)($pendingByProduct[$item->product_id] ?? 0);
                $suggested = max(0, (float)$item->target_max - (float)$item->on_hand - $pendingQty);

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'sku' => (string)($item->product->sku ?? ''),
                    'name' => (string)($item->product->name ?? ''),
                    'unit' => (string)($item->product->unit ?? 'ea'),
                    'on_hand' => (float)$item->on_hand,
                    'reorder_point' => (float)$item->reorder_point,
                    'target_max' => (float)$item->target_max,
                    'status' => (string)$item->status,
                    'last_cost_cents' => (int)$item->last_cost_cents,
                    'pending_qty' => $pendingQty,
                    'suggested_order_qty' => $suggested,
                ];
            });

        $vendors = Vendor::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return view('inventory.index', [
            'tenant' => $tenant,
            'location' => $location,
            'locations' => $locations,
            'inventoryItems' => $inventoryItems,
            'vendors' => $vendors,
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        [$tenant, $location] = $this->resolveContext($request);

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'on_hand' => ['required', 'numeric', 'min:0'],
            'reorder_point' => ['required', 'numeric', 'min:0'],
            'target_max' => ['required', 'numeric', 'min:0'],
            'last_cost' => ['nullable', 'string'],
        ]);

        $product = Product::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => trim((string)$validated['sku'])],
            [
                'name' => trim((string)$validated['name']),
                'unit' => trim((string)($validated['unit'] ?? 'ea')) ?: 'ea',
                'is_active' => true,
            ]
        );

        $status = $this->calculateInventoryStatus((float)$validated['on_hand'], (float)$validated['reorder_point']);

        InventoryItem::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'product_id' => $product->id,
            ],
            [
                'on_hand' => (float)$validated['on_hand'],
                'reorder_point' => (float)$validated['reorder_point'],
                'target_max' => (float)$validated['target_max'],
                'last_cost_cents' => $this->moneyToCents((string)($validated['last_cost'] ?? '0')),
                'status' => $status,
            ]
        );
        app(AuditLogger::class)->log($request, 'inventory.product_saved', [
            'product_id' => $product->id,
            'location_id' => $location->id,
        ]);

        return redirect()
            ->route('inventory.index', ['location_id' => $location->id])
            ->with('status', 'Product saved to inventory.');
    }

    public function updateInventory(Request $request, InventoryItem $item): RedirectResponse
    {
        [$tenant, $location] = $this->resolveContext($request);
        if ($item->tenant_id !== $tenant->id || $item->location_id !== $location->id) {
            abort(404);
        }

        $validated = $request->validate([
            'on_hand' => ['required', 'numeric', 'min:0'],
            'reorder_point' => ['required', 'numeric', 'min:0'],
            'target_max' => ['required', 'numeric', 'min:0'],
            'last_cost' => ['nullable', 'string'],
        ]);

        $item->update([
            'on_hand' => (float)$validated['on_hand'],
            'reorder_point' => (float)$validated['reorder_point'],
            'target_max' => (float)$validated['target_max'],
            'last_cost_cents' => $this->moneyToCents((string)($validated['last_cost'] ?? '0')),
            'status' => $this->calculateInventoryStatus((float)$validated['on_hand'], (float)$validated['reorder_point']),
        ]);
        app(AuditLogger::class)->log($request, 'inventory.item_updated', [
            'inventory_item_id' => $item->id,
            'product_id' => $item->product_id,
            'location_id' => $location->id,
        ]);

        return redirect()
            ->route('inventory.index', ['location_id' => $location->id])
            ->with('status', 'Inventory updated.');
    }

    public function placeOrder(Request $request, InventoryItem $item): RedirectResponse
    {
        [$tenant, $location] = $this->resolveContext($request);
        if ($item->tenant_id !== $tenant->id || $item->location_id !== $location->id) {
            abort(404);
        }

        $validated = $request->validate([
            'quantity_ordered' => ['required', 'numeric', 'gt:0'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'vendor_id' => ['nullable', 'integer'],
            'unit_cost' => ['nullable', 'string'],
        ]);

        $existingPending = PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->where('product_id', $item->product_id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            return redirect()
                ->route('inventory.index', ['location_id' => $location->id])
                ->withErrors(['order' => 'Pending order already exists for this product.']);
        }

        PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'product_id' => $item->product_id,
            'vendor_id' => (int)($validated['vendor_id'] ?? 0) > 0 ? (int)$validated['vendor_id'] : null,
            'quantity_ordered' => (float)$validated['quantity_ordered'],
            'quantity_received' => 0,
            'unit_cost_cents' => $this->moneyToCents((string)($validated['unit_cost'] ?? '0')),
            'order_date' => $validated['order_date'],
            'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
            'received_date' => null,
            'status' => 'pending',
        ]);
        app(AuditLogger::class)->log($request, 'inventory.order_placed', [
            'product_id' => $item->product_id,
            'location_id' => $location->id,
            'quantity_ordered' => (float)$validated['quantity_ordered'],
        ]);

        return redirect()
            ->route('inventory.index', ['location_id' => $location->id])
            ->with('status', 'Order placed.');
    }

    /**
     * @return array{0: Tenant, 1: Location, 2?: Collection<int, Location>}
     */
    private function resolveContext(Request $request): array
    {
        $tenant = $request->attributes->get('tenant');
        $location = $request->attributes->get('location');
        $locations = $request->attributes->get('locations');
        if ($tenant !== null && $location !== null && $locations !== null) {
            return [$tenant, $location, $locations];
        }

        return app(AppContextResolver::class)->resolve($request);
    }

    private function calculateInventoryStatus(float $onHand, float $reorderPoint): string
    {
        if ($onHand <= 0) {
            return 'out';
        }
        if ($onHand <= $reorderPoint) {
            return 'low';
        }
        return 'ok';
    }

    private function moneyToCents(string $raw): int
    {
        $normalized = str_replace([',', '$', ' '], '', trim($raw));
        if ($normalized === '') {
            return 0;
        }
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $normalized)) {
            abort(422, 'Invalid money value: ' . $raw);
        }

        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '0');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = ((int)$whole * 100) + (int)$fraction;
        return $negative ? -$cents : $cents;
    }
}
