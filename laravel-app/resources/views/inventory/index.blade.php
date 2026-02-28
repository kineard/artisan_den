@extends('layouts.app')

@section('title', 'Artisan Den - Inventory')

@section('content')
    <div class="card">
        <div class="row" style="align-items:center;">
            <div style="flex:1 1 auto;">
                <h1 style="margin:0;">Inventory & Reorder</h1>
                <p class="muted" style="margin:6px 0 0 0;">Tenant: {{ $tenant->name }} · Location: {{ $location->name }}</p>
            </div>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('inventory.index') }}">
            <div class="row">
                <div>
                    <label for="location_id">Location</label>
                    <select id="location_id" name="location_id">
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected($loc->id === $location->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:0 0 160px;">
                    <label>&nbsp;</label>
                    <button type="submit">Load</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Add Product to Inventory</h2>
        <form method="POST" action="{{ route('inventory.products.store') }}">
            @csrf
            <input type="hidden" name="location_id" value="{{ $location->id }}">
            <div class="row">
                <div><label>SKU</label><input name="sku" required></div>
                <div><label>Name</label><input name="name" required></div>
                <div><label>Unit</label><input name="unit" value="ea"></div>
                <div><label>On Hand</label><input name="on_hand" type="number" step="0.001" value="0" required></div>
                <div><label>ROP</label><input name="reorder_point" type="number" step="0.001" value="0" required></div>
                <div><label>Target Max</label><input name="target_max" type="number" step="0.001" value="0" required></div>
                <div><label>Last Cost ($)</label><input name="last_cost" value="0.00"></div>
                <div style="flex:0 0 160px;"><label>&nbsp;</label><button type="submit">Save Product</button></div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Current Inventory</h2>
        <table>
            <thead>
            <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Status</th>
                <th>On Hand</th>
                <th>ROP</th>
                <th>Target</th>
                <th>Pending</th>
                <th>Suggested</th>
                <th>Last Cost</th>
                <th class="actions">Update</th>
                <th class="actions">Order</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($inventoryItems as $item)
                <tr>
                    <td>{{ $item['sku'] }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td>
                        <span class="tag {{ $item['status'] === 'out' ? 'tag-out' : ($item['status'] === 'low' ? 'tag-low' : 'tag-ok') }}">
                            {{ strtoupper($item['status']) }}
                        </span>
                    </td>
                    <td>{{ number_format($item['on_hand'], 3) }}</td>
                    <td>{{ number_format($item['reorder_point'], 3) }}</td>
                    <td>{{ number_format($item['target_max'], 3) }}</td>
                    <td>{{ number_format($item['pending_qty'], 3) }}</td>
                    <td>{{ number_format($item['suggested_order_qty'], 3) }}</td>
                    <td>${{ number_format($item['last_cost_cents'] / 100, 2) }}</td>
                    <td class="actions">
                        <form method="POST" action="{{ route('inventory.items.update', $item['id']) }}">
                            @csrf
                            <input type="hidden" name="location_id" value="{{ $location->id }}">
                            <div class="row">
                                <div><input name="on_hand" type="number" step="0.001" value="{{ number_format($item['on_hand'], 3, '.', '') }}"></div>
                                <div><input name="reorder_point" type="number" step="0.001" value="{{ number_format($item['reorder_point'], 3, '.', '') }}"></div>
                                <div><input name="target_max" type="number" step="0.001" value="{{ number_format($item['target_max'], 3, '.', '') }}"></div>
                                <div><input name="last_cost" value="{{ number_format($item['last_cost_cents'] / 100, 2, '.', '') }}"></div>
                                <div style="flex:0 0 110px;"><button type="submit">Update</button></div>
                            </div>
                        </form>
                    </td>
                    <td class="actions">
                        <form method="POST" action="{{ route('inventory.items.order', $item['id']) }}">
                            @csrf
                            <input type="hidden" name="location_id" value="{{ $location->id }}">
                            <div class="row">
                                <div><input name="quantity_ordered" type="number" step="0.001" value="{{ number_format($item['suggested_order_qty'], 3, '.', '') }}"></div>
                                <div><input name="order_date" type="date" value="{{ now()->toDateString() }}"></div>
                                <div><input name="expected_delivery_date" type="date" value=""></div>
                                <div>
                                    <select name="vendor_id">
                                        <option value="">Vendor</option>
                                        @foreach ($vendors as $vendor)
                                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div><input name="unit_cost" value="{{ number_format($item['last_cost_cents'] / 100, 2, '.', '') }}" placeholder="Unit Cost"></div>
                                <div style="flex:0 0 110px;"><button type="submit">Order</button></div>
                            </div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="muted">No inventory yet. Add a product above.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

