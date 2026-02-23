<!-- Product Modal -->
<div id="productModal" class="modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="productModalTitle" aria-hidden="true">
    <div class="modal-content" tabindex="-1">
        <div class="modal-header">
            <h3 id="productModalTitle">Add Product</h3>
            <button type="button" class="modal-close" onclick="closeProductModal()">&times;</button>
        </div>
        <form method="POST" action="" id="productForm">
            <input type="hidden" name="product_id" id="product_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="sku">SKU/Barcode *</label>
                    <input type="text" id="sku" name="sku" required>
                </div>
                <div class="form-group">
                    <label for="product_name">Product Name *</label>
                    <input type="text" id="product_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="product_description">Description</label>
                    <textarea id="product_description" name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="unit_type">Unit Type</label>
                    <select id="unit_type" name="unit_type">
                        <option value="unit">Unit</option>
                        <option value="gram">Gram</option>
                        <option value="box">Box</option>
                        <option value="case">Case</option>
                        <option value="lb">Pound</option>
                        <option value="oz">Ounce</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeProductModal()">Cancel</button>
                <button type="submit" name="save_product" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Vendor Modal -->
<div id="vendorModal" class="modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="vendorModalTitle" aria-hidden="true">
    <div class="modal-content modal-large" tabindex="-1">
        <div class="modal-header">
            <h3 id="vendorModalTitle">Add Vendor</h3>
            <button type="button" class="modal-close" onclick="closeVendorModal()">&times;</button>
        </div>
        <form method="POST" action="" id="vendorForm">
            <input type="hidden" name="inventory_limit" value="<?php echo htmlspecialchars(isset($inventoryLimit) ? $inventoryLimit : '10'); ?>">
            <input type="hidden" name="inventory_days" value="<?php echo (int)($effectiveInventoryDays ?? 7); ?>">
            <input type="hidden" name="inventory_sort" value="<?php echo htmlspecialchars($inventorySort ?? 'status'); ?>">
            <input type="hidden" name="vendor_id" id="vendor_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="vendor_name">Vendor Name *</label>
                        <input type="text" id="vendor_name" name="vendor_name" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_name">Contact Name</label>
                        <input type="text" id="contact_name" name="contact_name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="order_method">Order Method</label>
                        <select id="order_method" name="order_method">
                            <option value="">Select...</option>
                            <option value="text">Text</option>
                            <option value="call">Call</option>
                            <option value="site">Website</option>
                            <option value="text/call">Text/Call</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cutoff_time">Cutoff Time</label>
                        <input type="text" id="cutoff_time" name="cutoff_time" placeholder="e.g., WEDNESDAY 1PM">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="typical_lead_time">Typical Lead Time</label>
                        <input type="text" id="typical_lead_time" name="typical_lead_time" placeholder="e.g., 2 DAYS">
                    </div>
                    <div class="form-group">
                        <label for="free_ship_threshold">Free Ship Threshold</label>
                        <input type="number" id="free_ship_threshold" name="free_ship_threshold" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div class="form-group">
                    <label for="shipping_speed_notes">Shipping Speed Notes</label>
                    <textarea id="shipping_speed_notes" name="shipping_speed_notes" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="account_info">Account # / Login Owner</label>
                        <input type="text" id="account_info" name="account_info">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="text" id="password" name="password">
                    </div>
                </div>
                <div class="form-group">
                    <label for="vendor_notes">Notes</label>
                    <textarea id="vendor_notes" name="vendor_notes" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="vendor_rating">Rating (1-5 stars)</label>
                        <select id="vendor_rating" name="rating">
                            <option value="1">⭐ (1 star)</option>
                            <option value="2">⭐⭐ (2 stars)</option>
                            <option value="3" selected>⭐⭐⭐ (3 stars)</option>
                            <option value="4">⭐⭐⭐⭐ (4 stars)</option>
                            <option value="5">⭐⭐⭐⭐⭐ (5 stars)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_preferred" id="is_preferred"> Preferred Vendor
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" id="is_active" checked> Active
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeVendorModal()">Cancel</button>
                <button type="submit" name="save_vendor" class="btn btn-primary">Save Vendor</button>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Edit Modal -->
<div id="inventoryModal" class="modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="inventory-modal-title" aria-hidden="true">
    <div class="modal-content modal-large" tabindex="-1">
        <div class="modal-header">
            <h3 id="inventory-modal-title">Edit Inventory</h3>
            <button type="button" class="modal-close" onclick="closeInventoryModal()">&times;</button>
        </div>
        <form method="POST" action="" id="inventoryForm">
            <input type="hidden" name="store_id" value="<?php echo (int)$storeId; ?>">
            <input type="hidden" name="inventory_id" id="inventory_id">
            <input type="hidden" name="inventory_limit" value="<?php echo htmlspecialchars($inventoryLimit ?? '10'); ?>">
            <div class="form-group" id="inventory-product-selector" style="display: none;">
                <label for="inventory_product_id_select">Product</label>
                <select id="inventory_product_id_select">
                    <option value="">Select Product...</option>
                    <?php 
                    // Get products not already in inventory for this store
                    $existingProductIds = [];
                    if (isset($inventoryItems) && !empty($inventoryItems)) {
                        $existingProductIds = array_column($inventoryItems, 'product_id');
                    }
                    foreach ($products ?? [] as $product): 
                        if (in_array($product['id'], $existingProductIds)) continue; // Skip products already in inventory
                    ?>
                        <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['sku'] . ' - ' . $product['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #000;">Only products not yet in inventory for this store are shown</small>
            </div>
            <input type="hidden" name="product_id" id="inventory_product_id">
            <div class="modal-body">
                <div class="form-group" id="inventory-edit-sku-row">
                    <label for="product_sku">SKU/Barcode</label>
                    <input type="text" id="product_sku" name="product_sku" maxlength="100" placeholder="Unique product ID" data-original-sku="">
                    <small style="color: #666;">Must be unique. Changing it renames this product everywhere. You’ll be asked to confirm before saving.</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="on_hand">Initial quantity (on hand)</label>
                        <input type="number" id="on_hand" name="on_hand" step="1" min="0" value="0">
                        <small style="color: #000;">Set when adding the product; used as the baseline for daily On Hand.</small>
                    </div>
                    <div class="form-group">
                        <label for="avg_daily_usage">Avg Daily Usage</label>
                        <input type="number" id="avg_daily_usage" name="avg_daily_usage" step="0.001" value="0" onchange="calculateInventoryTargets()">
                        <small style="color: #000;">Units sold per day (for auto-calculation)</small>
                    </div>
                    <div class="form-group">
                        <label for="days_of_stock">Days of Stock (Target)</label>
                        <input type="number" id="days_of_stock" name="days_of_stock" step="1" value="7" min="1" max="30" onchange="calculateInventoryTargets()">
                        <small style="color: #000;">Default: 7 days</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="reorder_point">Reorder Point (ROP)</label>
                        <input type="number" id="reorder_point" name="reorder_point" step="1" min="0" value="0">
                        <small style="color: #000;">Auto-calculated if daily usage is set</small>
                    </div>
                    <div class="form-group">
                        <label for="target_max">Target (Max)</label>
                        <input type="number" id="target_max" name="target_max" step="0.001" value="0">
                        <small style="color: #000;">Auto-calculated for 7-day stock</small>
                    </div>
                    <div class="form-group">
                        <label for="substitution_product_id">Substitution Product</label>
                        <select id="substitution_product_id" name="substitution_product_id">
                            <option value="">None</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['sku'] . ' - ' . $product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display: block; margin-top: 5px; color: #000; font-size: 11px;">
                            <strong>How it works:</strong> When this product is OUT or unavailable, the system will suggest ordering the selected substitution product instead. Choose a similar product that can temporarily replace this one. The current product cannot be selected as its own substitute.
                        </small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="inventory_vendor_id">Vendor</label>
                        <select id="inventory_vendor_id" name="vendor_id">
                            <option value="">Select Vendor...</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="vendor_sku">Vendor SKU</label>
                        <input type="text" id="vendor_sku" name="vendor_sku" placeholder="Vendor's catalog or product code">
                        <small style="color: #666;">Vendor’s product code when ordering (may differ from your SKU/Barcode above)</small>
                    </div>
                    <div class="form-group">
                        <label for="lead_time_days">Lead Time (days)</label>
                        <input type="number" id="lead_time_days" name="lead_time_days" step="1" min="0" value="0" onchange="calculateInventoryTargets()">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="vendor_link">Vendor Link</label>
                        <input type="url" id="vendor_link" name="vendor_link">
                    </div>
                    <div class="form-group">
                        <label for="unit_cost">Unit Cost</label>
                        <input type="number" id="unit_cost" name="unit_cost" step="0.01" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label for="inventory_notes">Notes</label>
                    <textarea id="inventory_notes" name="inventory_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeInventoryModal()">Cancel</button>
                <button type="submit" name="save_inventory" class="btn btn-primary">Save Inventory</button>
            </div>
        </form>
    </div>
</div>

<!-- Order Modal -->
<div id="orderModal" class="modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle" aria-hidden="true">
    <div class="modal-content" tabindex="-1">
        <div class="modal-header">
            <h3 id="orderModalTitle">Create Order</h3>
            <button type="button" class="modal-close" onclick="closeOrderModal()">&times;</button>
        </div>
        <form method="POST" action="" id="orderForm">
            <input type="hidden" name="inventory_limit" value="<?php echo htmlspecialchars(isset($inventoryLimit) ? $inventoryLimit : '10'); ?>">
            <input type="hidden" name="inventory_days" value="<?php echo (int)($effectiveInventoryDays ?? 7); ?>">
            <input type="hidden" name="inventory_sort" value="<?php echo htmlspecialchars($inventorySort ?? 'status'); ?>">
            <input type="hidden" name="store_id" value="<?php echo $storeId; ?>">
            <input type="hidden" name="order_id" id="order_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="order_product_id">Product *</label>
                    <select id="order_product_id" name="product_id" required style="color: #000; background: #fff;">
                        <option value="">— Select product —</option>
                        <?php foreach ($products ?? [] as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['sku'] . ' – ' . $p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="order_vendor_id">Vendor *</label>
                    <select id="order_vendor_id" name="vendor_id" required style="color: #000; background: #fff;">
                        <option value="">— Select vendor —</option>
                        <?php foreach ($vendors ?? [] as $v): ?>
                        <option value="<?php echo (int)$v['id']; ?>"><?php echo htmlspecialchars($v['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="order_quantity">Quantity</label>
                    <input type="number" id="order_quantity" name="quantity" step="0.001" required>
                </div>
                <div class="form-group">
                    <label for="order_unit_cost">Unit Cost</label>
                    <input type="number" id="order_unit_cost" name="unit_cost" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="order_status">Status</label>
                    <select id="order_status" name="order_status">
                        <option value="REQUESTED">REQUESTED</option>
                        <option value="ORDERED">ORDERED</option>
                        <option value="RECEIVED">RECEIVED</option>
                        <option value="STOCKED">STOCKED</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="order_date">Order Date</label>
                    <input type="date" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>">
                    <small style="display:block;margin-top:4px;color: #000;">When you placed the order.</small>
                </div>
                <div class="form-group">
                    <label for="received_date">Received Date</label>
                    <input type="date" id="received_date" name="received_date">
                    <small style="display:block;margin-top:4px;color: #000;">When stock arrived (inventory went up). Set this when marking RECEIVED.</small>
                </div>
                <div class="form-group">
                    <label for="order_notes">Notes</label>
                    <textarea id="order_notes" name="order_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeOrderModal()">Cancel</button>
                <button type="submit" name="save_order" class="btn btn-primary">Save Order</button>
            </div>
        </form>
    </div>
</div>

<!-- Receive Order Modal (confirm qty and date) -->
<div id="receiveOrderModal" class="modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="receiveOrderModalTitle" aria-hidden="true">
    <div class="modal-content" tabindex="-1">
        <div class="modal-header">
            <h3 id="receiveOrderModalTitle">Mark order received</h3>
            <button type="button" class="modal-close" onclick="closeReceiveOrderModal()">&times;</button>
        </div>
        <form id="receiveOrderForm">
            <input type="hidden" name="mark_received" value="1">
            <input type="hidden" id="receive_order_id" name="order_id">
            <input type="hidden" id="receive_product_id" name="product_id">
            <input type="hidden" id="receive_store_id" name="store_id">
            <div class="modal-body">
                <p style="color: #000; margin-bottom: 12px;">Adjust quantity if you did not receive the full order (e.g. partial shipment).</p>
                <p style="color: #000; margin: 0 0 10px; font-size: 12px;">
                    Ordered qty: <strong id="receive_order_qty_label">0</strong>
                </p>
                <div class="form-group">
                    <label for="receive_quantity">Quantity received</label>
                    <input type="number" id="receive_quantity" name="quantity" step="1" min="0" required style="color: #000;">
                </div>
                <div class="form-group">
                    <label for="receive_date">Date received</label>
                    <input type="date" id="receive_date" name="received_date" value="<?php echo date('Y-m-d'); ?>" style="color: #000;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeReceiveOrderModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Mark received</button>
            </div>
        </form>
    </div>
</div>
