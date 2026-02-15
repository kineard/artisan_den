<?php
// Inventory Management Section
// These variables should be set in index.php before including this file
if (!isset($inventoryItems)) $inventoryItems = [];
if (!isset($products)) $products = [];
if (!isset($vendors)) $vendors = [];
if (!isset($orders)) $orders = [];
if (!isset($inventorySort)) $inventorySort = 'status';
$dateArray = $dateArray ?? [];
$otherDates = isset($inventoryTableDates) && !empty($inventoryTableDates) ? $inventoryTableDates : $dateArray;
$snapshotsMap = $snapshotsMap ?? [];
$salesMap = $salesMap ?? [];
$purchasesMap = $purchasesMap ?? [];
$receivedMap = $receivedMap ?? [];
$manualPurchasesMap = $manualPurchasesMap ?? [];
$entryDate = $date ?? '';
$firstDate = !empty($dateArray) ? $dateArray[0] : '';
$lastDate = !empty($dateArray) ? $dateArray[count($dateArray) - 1] : '';
$dayBeforeFirst = $firstDate ? (new DateTime($firstDate))->modify('-1 day')->format('Y-m-d') : '';
$startDate = $startDate ?? $firstDate;
$endDate = $endDate ?? $lastDate;
$startingMap = [];
if ($dayBeforeFirst && isset($snapshotsMap)) {
    foreach ($snapshotsMap as $pid => $byDate) {
        if (isset($byDate[$dayBeforeFirst])) $startingMap[$pid] = $byDate[$dayBeforeFirst];
    }
}
$invColspan = 17 + (2 * count($otherDates));
?>

<!-- Inventory List -->
<div class="inventory-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3>Inventory & Reorder List</h3>
        <div class="inventory-tip-box" style="font-size: 12px; color: #000; padding: 8px 12px; background: #f0f0f0; border-radius: 4px; max-width: 500px;">
            <div style="margin-bottom: 5px;">
                💡 <strong>Daily:</strong> <strong>Sales</strong> = sales made that date. <strong>On Hand</strong> = qty left after subtracting sales (chain: each day uses the previous day&apos;s On Hand − today&apos;s sales; first day uses your product&apos;s current qty). Enter a sale and tab out — the page will recalc and reload so On Hand updates.
            </div>
            <div style="font-size: 11px; color: #000 !important; border-top: 1px solid #ddd; padding-top: 5px; margin-top: 5px;">
                <strong>Substitution Logic:</strong> When a product is OUT or unavailable, the system can suggest ordering a substitution product instead. Set this in the Edit Inventory modal. The substitution product should be a similar item that can temporarily replace the primary product when it's unavailable.
            </div>
            <div style="font-size: 11px; color: #000 !important; border-top: 1px solid #ddd; padding-top: 5px; margin-top: 5px;">
                <strong>Vendor rating:</strong> When adding a vendor, choose 1–5 stars in the form. To change a rating, click the vendor name under “Edit vendor” above, then update the rating dropdown and save.
            </div>
            <?php if (false): // Deferred post-Launch-1: POS integration lives outside locked scope ?>
            <div style="font-size: 11px; margin-top: 5px;">
                💡 <a href="#" onclick="alert('POS integration is deferred until after Launch 1 scope is complete.'); return false;">Connect Lightspeed POS</a> for automatic inventory sync
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="inventory-table-wrapper">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>SKU/Barcode</th>
                    <?php foreach ($otherDates as $d): $dateObj = new DateTime($d); ?>
                        <th class="inventory-date-col" colspan="2"><small><?php echo $dateObj->format('D m/d/y'); ?></small></th>
                    <?php endforeach; ?>
                    <th>Status</th>
                    <th>7-Day Avg Sales</th>
                    <th>30-Day Avg Sales</th>
                    <th>Days of Stock</th>
                    <th>ROP<br><small>(7-day)</small></th>
                    <th>Target (Max)<br><small>(7-day)</small></th>
                    <th>Suggested Order<br><small>(7-day)</small></th>
                    <th>Qty Ordered</th>
                    <th>Received</th>
                    <th>Vendor</th>
                    <th>Vendor Rating</th>
                    <th>Unit Cost<br><small>(Price)</small></th>
                    <th>Est Total</th>
                    <th>Substitution</th>
                    <th>Action</th>
                    <th>Notes</th>
                </tr>
                <tr>
                    <th></th>
                    <?php foreach ($otherDates as $d): ?>
                        <th class="inventory-date-col"><small>Sales</small></th>
                        <th class="inventory-date-col"><small>On Hand</small></th>
                    <?php endforeach; ?>
                    <?php for ($i = 0; $i < 16; $i++): ?><th></th><?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventoryItems)): ?>
                    <tr>
                        <td colspan="<?php echo $invColspan; ?>" style="text-align: center; padding: 40px; color: #999;">
                            No inventory items. <a href="#" onclick="showProductModal(); return false;">Add a product</a> first, then add it to inventory.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventoryItems as $item):
                        $pid = $item['product_id'];
                        $entryOnHand = ($entryDate && isset($snapshotsMap[$pid][$entryDate])) ? $snapshotsMap[$pid][$entryDate] : $item['on_hand'];
                        $status = calculateInventoryStatus($entryOnHand, $item['reorder_point']);
                        $daysOfStock = $item['days_of_stock'] ?? 7;
                        $leadTimeDays = $item['lead_time_days'] ?? 0;

                        // Get 7-day and 30-day averages (from getInventoryForStore)
                        $avg7Day = $item['avg_7day_sales'] ?? null;
                        $avg30Day = $item['avg_30day_sales'] ?? null;
                        $hasPendingOrder = $item['has_pending_order'] ?? false;
                        $pendingOrder = $item['pending_order'] ?? null;

                        // Use 7-day average for calculations if available, otherwise fall back to avg_daily_usage
                        $avgDailyUsage = $avg7Day !== null && $avg7Day > 0 ? $avg7Day : ($item['avg_daily_usage'] ?? 0);

                        // Auto-calculate ROP and Target using 7-day average if available (always recalc for accuracy)
                        $avgDailyUsageVal = (float)($item['avg_daily_usage'] ?? 0);
                        if ($avg7Day !== null && $avg7Day > 0) {
                            $item['reorder_point'] = calculateReorderPointForDays($avg7Day, $leadTimeDays, $daysOfStock);
                            $item['target_max'] = calculateTargetMaxForDays($avg7Day, $daysOfStock);
                        } elseif ($avgDailyUsageVal > 0) {
                            if (empty($item['reorder_point'])) {
                                $item['reorder_point'] = calculateReorderPointForDays($avgDailyUsageVal, $leadTimeDays, $daysOfStock);
                            }
                            if (empty($item['target_max'])) {
                                $item['target_max'] = calculateTargetMaxForDays($avgDailyUsageVal, $daysOfStock);
                            }
                        }
                        $item['reorder_point'] = $item['reorder_point'] ?? 0;
                        $item['target_max'] = $item['target_max'] ?? 0;

                        // Calculate suggested order using entry-date on hand - exclude if pending order exists
                        if ($hasPendingOrder) {
                            $suggestedOrder = 0;
                        } elseif ($avgDailyUsage > 0) {
                            $suggestedOrder = calculateSuggestedOrderForDays($entryOnHand, $avgDailyUsage, $leadTimeDays, $daysOfStock);
                        } else {
                            $suggestedOrder = calculateSuggestedOrder($entryOnHand, $item['target_max'], $status);
                        }

                        $estTotal = $suggestedOrder * $item['unit_cost'];
                    ?>
                        <tr class="status-<?php echo strtolower($status); ?>" data-inventory-id="<?php echo $item['id']; ?>" data-product-id="<?php echo $item['product_id']; ?>" data-reorder-point="<?php echo htmlspecialchars($item['reorder_point'] ?? 0); ?>">
                            <td class="inventory-td-sku">
                                <a href="?action=<?php echo htmlspecialchars($action ?? 'dashboard'); ?>&amp;store=<?php echo (int)($storeId ?? 0); ?>&amp;date=<?php echo htmlspecialchars($date ?? ''); ?>&amp;view=<?php echo htmlspecialchars($view ?? 'week'); ?>&amp;tab=inventory&amp;chart_product_id=<?php echo (int)$pid; ?>&amp;chart_days=<?php echo (int)$chartDays; ?>#inventory-chart" class="sku-chart-link" title="View this product in the chart below"><?php echo htmlspecialchars($item['sku']); ?></a>
                            </td>
                            <?php foreach ($otherDates as $d):
                                $salesVal = isset($salesMap[$pid][$d]) ? $salesMap[$pid][$d] : '';
                                $onHandVal = isset($snapshotsMap[$pid][$d]) ? $snapshotsMap[$pid][$d] : '';
                            ?>
                                <td class="inventory-date-col">
                                    <input type="number" class="daily-sale-input" data-store-id="<?php echo (int)$item['store_id']; ?>" data-product-id="<?php echo (int)$pid; ?>" data-date="<?php echo $d; ?>" step="1" min="0" value="<?php echo $salesVal !== '' ? (string)(int)round((float)$salesVal) : ''; ?>" placeholder="0" style="width: 52px; padding: 2px; border: 1px solid #ddd; border-radius: 4px; color: #000; background-color: #fff;" title="Sales">
                                </td>
                                <td class="inventory-date-col on-hand-computed" data-store-id="<?php echo (int)$item['store_id']; ?>" data-product-id="<?php echo (int)$pid; ?>" data-date="<?php echo $d; ?>">
                                    <span class="on-hand-value"><?php echo $onHandVal !== '' ? (string)(int)round((float)$onHandVal) : '—'; ?></span>
                                </td>
                            <?php endforeach; ?>
                            <td><span class="status-badge status-<?php echo strtolower($status); ?>"><?php echo $status; ?></span></td>
                            <td>
                                <?php if ($avg7Day !== null): ?>
                                    <?php echo number_format($avg7Day, 0); ?>/day
                                <?php else: ?>
                                    <span style="color: #999; font-size: 11px;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($avg30Day !== null): ?>
                                    <?php echo number_format($avg30Day, 0); ?>/day
                                <?php else: ?>
                                    <span style="color: #999; font-size: 11px;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $daysOfStock; ?> days</td>
                            <td>
                                <?php if ($avgDailyUsage > 0 && $item['reorder_point'] > 0): ?>
                                    <span title="Auto-calculated: (<?php echo $avgDailyUsage; ?> × <?php echo $leadTimeDays; ?> lead) + (<?php echo $avgDailyUsage; ?> × <?php echo $daysOfStock; ?> days)"><?php echo number_format((float)($item['reorder_point'] ?? 0), 0); ?></span>
                                <?php else: ?>
                                    <?php echo (int)round((float)($item['reorder_point'] ?? 0)); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($avgDailyUsage > 0 && $item['target_max'] > 0): ?>
                                    <span title="Auto-calculated: <?php echo $avgDailyUsage; ?> × <?php echo $daysOfStock; ?> days"><?php echo number_format($item['target_max'], 0); ?></span>
                                <?php else: ?>
                                    <?php echo (int)round((float)($item['target_max'] ?? 0)); ?>
                                <?php endif; ?>
                            </td>
                            <td class="btn-cell">
                                <strong style="color: <?php echo $suggestedOrder > 0 ? '#e74c3c' : '#27ae60'; ?>;">
                                    <?php echo $suggestedOrder > 0 ? number_format($suggestedOrder, 0) : '-'; ?>
                                </strong>
                                <?php if ($avgDailyUsage > 0 && $suggestedOrder > 0): ?>
                                    <br><small style="color: #000;" title="Enough for <?php echo $daysOfStock; ?> days + lead time">(<?php echo round($suggestedOrder / $avgDailyUsage); ?> days)</small>
                                <?php endif; ?>
                            </td>
                            <td class="btn-cell">
                                <?php if ($pendingOrder): 
                                    $orderDateStr = !empty($pendingOrder['order_date']) ? date('M j', strtotime($pendingOrder['order_date'])) : '';
                                ?>
                                    <span style="font-size: 11px; color: #f39c12;" title="Ordered on <?php echo htmlspecialchars($pendingOrder['order_date'] ?? ''); ?>">
                                        <?php echo number_format($pendingOrder['quantity'], 0); ?><?php echo $orderDateStr ? ' <small style="color: #000;">(' . $orderDateStr . ')</small>' : ''; ?>
                                    </span>
                                <?php else: ?>
                                    <button type="button" class="btn-action btn-action-primary order-row-btn" title="Create order for this product (opens same form as + Add Order)"
                                        data-product-id="<?php echo (int)$pid; ?>"
                                        data-vendor-id="<?php echo (int)($item['vendor_id'] ?? 0); ?>"
                                        data-unit-cost="<?php echo htmlspecialchars((string)($item['unit_cost'] ?? 0)); ?>"
                                        data-suggested-qty="<?php echo (int)$suggestedOrder; ?>">Order</button>
                                <?php endif; ?>
                            </td>
                            <td class="btn-cell">
                                <?php if ($pendingOrder): ?>
                                    <?php 
                                    $expectedDate = $pendingOrder['expected_delivery_date'] ?? null;
                                    $orderId = $pendingOrder['id'] ?? null;
                                    ?>
                                    <button type="button" class="btn-action btn-action-primary" onclick="openReceiveOrderModal(<?php echo (int)$orderId; ?>, <?php echo (int)$item['product_id']; ?>, <?php echo (int)$item['store_id']; ?>, <?php echo (int)round((float)$pendingOrder['quantity']); ?>)" title="Mark received (qty &amp; date). Exp: <?php echo $expectedDate ? htmlspecialchars($expectedDate) : 'N/A'; ?>">Rcvd</button>
                                    <?php if ($expectedDate): ?>
                                        <small style="color: #000; font-size: 10px;">Exp: <?php echo date('M j', strtotime($expectedDate)); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['vendor_name'] ?? '-'); ?></td>
                            <td>
                                <?php 
                                $vendorRating = $item['vendor_rating'] ?? 3;
                                echo str_repeat('⭐', $vendorRating) . ' (' . $vendorRating . ')';
                                ?>
                            </td>
                            <td><?php echo $item['unit_cost'] > 0 ? formatCurrency($item['unit_cost']) : '-'; ?></td>
                            <td><?php echo $estTotal > 0 ? formatCurrency($estTotal) : '-'; ?></td>
                            <td>
                                <?php if (!empty($item['substitution_sku'])): ?>
                                    <span title="Substitution Logic: When this product (<?php echo htmlspecialchars($item['sku']); ?>) is OUT or unavailable, the system will suggest ordering the substitution product (<?php echo htmlspecialchars($item['substitution_sku']); ?>) instead. The substitution product should be a similar item that can temporarily replace the primary product. Set this in the Edit Inventory modal.">
                                        <strong style="color: #3498db;"><?php echo htmlspecialchars($item['substitution_sku']); ?></strong>
                                        <br><small style="color: #000;"><?php echo htmlspecialchars($item['substitution_name'] ?? ''); ?></small>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;" title="No substitution set. Edit this inventory item to set a substitution product for when this item is unavailable.">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="btn-cell">
                                <button type="button" class="btn-action" onclick="editInventory(<?php echo (int)$item['id']; ?>)" title="Edit this inventory item">Edit</button>
                                <?php if ($hasPendingOrder): ?>
                                    <span class="order-hint" title="Order pending" style="color: #f39c12; font-size: 11px;">Ordered</span>
                                <?php endif; ?>
                            </td>
                            <td class="notes-cell">
                                <span class="notes-cell-text"><?php echo htmlspecialchars($item['notes'] ?? ''); ?></span>
                                <span class="notes-cell-hover"><?php echo htmlspecialchars($item['notes'] ?? ''); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals will be added via JavaScript -->
