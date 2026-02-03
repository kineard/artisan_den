<?php
$snapshotsMap = $snapshotsMap ?? [];
$dateArray = $dateArray ?? [];
$inventoryItems = $inventoryItems ?? [];
// Use dailyOnHandItems if available (sorted by 7-day avg), otherwise fall back to inventoryItems
$gridItems = $dailyOnHandItems ?? $inventoryItems ?? [];
$storeId = $storeId ?? null;
?>
<div class="daily-onhand-section" id="daily-onhand-section" data-store-id="<?php echo (int)$storeId; ?>">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h3>Daily On-Hand</h3>
        <div style="font-size: 12px; color: #000;">
            <span id="product-count"><?php echo count($gridItems); ?> products</span>
            <button type="button" class="btn-small" onclick="showAddProductToGridModal()" style="margin-left: 10px;">+ Add Product</button>
        </div>
    </div>
    <p class="daily-onhand-note">Track on-hand per product per day. Changes save automatically. Fri–Sat–Sun are typically entered on Monday. The latest value you enter per product updates <strong>On Hand</strong> in the Inventory &amp; Reorder list above (refresh the page to see it). Products are sorted by 7-day average sales (top sellers first).</p>
    <div class="spreadsheet-container">
        <div class="spreadsheet-wrapper">
            <table class="spreadsheet-table daily-onhand-table">
                <thead class="spreadsheet-header">
                    <tr>
                        <th class="frozen-label-col">Product</th>
                        <?php foreach ($dateArray as $d):
                            $dateObj = new DateTime($d);
                            $dayName = $dateObj->format('D');
                            $dayNum = $dateObj->format('j');
                        ?>
                            <th class="data-cell"><div class="label-text"><?php echo $dayName; ?> <?php echo $dayNum; ?></div></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($gridItems)): ?>
                        <tr><td colspan="<?php echo count($dateArray) + 1; ?>" style="text-align:center;padding:24px;color:#999;">No inventory items. Add products and assign them to this store first.</td></tr>
                    <?php else: ?>
                        <?php foreach ($gridItems as $item):
                            $pid = $item['product_id'];
                            $vals = $snapshotsMap[$pid] ?? [];
                        ?>
                            <tr>
                                <td class="frozen-label-col metric-label">
                                    <div class="label-text"><?php echo htmlspecialchars($item['sku']); ?></div>
                                </td>
                                <?php foreach ($dateArray as $d):
                                    $v = $vals[$d] ?? '';
                                ?>
                                    <td class="data-cell">
                                        <input type="number"
                                               class="onhand-cell-input spreadsheet-input"
                                               data-store-id="<?php echo (int)$storeId; ?>"
                                               data-product-id="<?php echo (int)$pid; ?>"
                                               data-date="<?php echo $d; ?>"
                                               step="0.001"
                                               min="0"
                                               value="<?php echo $v !== '' ? htmlspecialchars($v) : ''; ?>"
                                               placeholder="—">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
