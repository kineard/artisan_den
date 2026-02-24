<?php

require_once __DIR__ . '/lightspeedx-client.php';

function lightspeedxSyncRunStart($entityName, $modeName = 'manual', $startedBy = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO integration_sync_runs
            (provider, entity_name, mode_name, status, records_seen, records_upserted, records_failed, message, started_at, started_by)
        VALUES
            ('lightspeed_x', ?, ?, 'RUNNING', 0, 0, 0, NULL, CURRENT_TIMESTAMP, ?)
        RETURNING id
    ");
    $stmt->execute([(string)$entityName, (string)$modeName, $startedBy !== null ? (string)$startedBy : null]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['id'] ?? 0);
}

function lightspeedxSyncRunFinish($runId, $status, $seen, $upserted, $failed, $message = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        UPDATE integration_sync_runs
        SET
            status = ?,
            records_seen = ?,
            records_upserted = ?,
            records_failed = ?,
            message = ?,
            ended_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([
        (string)$status,
        (int)$seen,
        (int)$upserted,
        (int)$failed,
        $message !== null ? (string)$message : null,
        (int)$runId,
    ]);
}

function lightspeedxSyncLogError($runId, $externalId, $errorCode, $errorMessage, $payload = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO integration_sync_errors
            (sync_run_id, external_id, error_code, error_message, payload_json, created_at)
        VALUES
            (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $payloadJson = null;
    if ($payload !== null) {
        $payloadJson = json_encode($payload);
    }
    $stmt->execute([
        (int)$runId,
        $externalId !== null ? (string)$externalId : null,
        $errorCode !== null ? (string)$errorCode : null,
        (string)$errorMessage,
        $payloadJson,
    ]);
}

function lightspeedxGetCheckpoint($entityName) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT checkpoint_value
        FROM integration_sync_checkpoints
        WHERE provider = 'lightspeed_x' AND entity_name = ?
        LIMIT 1
    ");
    $stmt->execute([(string)$entityName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)($row['checkpoint_value'] ?? '') : '';
}

function lightspeedxUpsertCheckpoint($entityName, $checkpointValue) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO integration_sync_checkpoints (provider, entity_name, checkpoint_value, updated_at)
        VALUES ('lightspeed_x', ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (provider, entity_name)
        DO UPDATE SET checkpoint_value = EXCLUDED.checkpoint_value, updated_at = CURRENT_TIMESTAMP
    ");
    return $stmt->execute([(string)$entityName, $checkpointValue !== null ? (string)$checkpointValue : null]);
}

function lightspeedxNormalizeCategoryRow(array $row) {
    $externalId = trim((string)($row['id'] ?? $row['category_id'] ?? ''));
    $name = trim((string)($row['name'] ?? $row['category_name'] ?? ''));
    if ($name === '' && !empty($row['attributes']) && is_array($row['attributes'])) {
        $name = trim((string)($row['attributes']['name'] ?? ''));
    }
    $isActive = true;
    if (array_key_exists('active', $row)) {
        $val = $row['active'];
        $isActive = ($val === true || $val === 1 || $val === '1' || $val === 'true');
    } elseif (!empty($row['deleted_at'])) {
        $isActive = false;
    }
    return [
        'external_id' => $externalId,
        'name' => $name,
        'is_active' => $isActive,
        'raw' => $row,
        'version' => isset($row['version']) ? (string)$row['version'] : '',
    ];
}

function lightspeedxNormalizeProductRow(array $row) {
    $externalId = trim((string)($row['id'] ?? $row['product_id'] ?? ''));
    $name = trim((string)($row['name'] ?? $row['full_name'] ?? $row['title'] ?? ''));
    $sku = trim((string)($row['sku'] ?? $row['handle'] ?? $row['code'] ?? ''));
    if ($sku === '' && $externalId !== '') {
        $sku = 'LSX-' . $externalId;
    }
    if ($name === '' && $sku !== '') {
        $name = $sku;
    }
    $desc = trim((string)($row['description'] ?? $row['description_html'] ?? ''));
    $unitType = trim((string)($row['unit_type'] ?? 'unit'));
    if ($unitType === '') {
        $unitType = 'unit';
    }
    $categoryIds = [];
    if (!empty($row['product_category_id'])) {
        $categoryIds[] = (string)$row['product_category_id'];
    }
    if (!empty($row['product_category']) && is_array($row['product_category']) && !empty($row['product_category']['id'])) {
        $categoryIds[] = (string)$row['product_category']['id'];
    }
    if (!empty($row['categories']) && is_array($row['categories'])) {
        foreach ($row['categories'] as $cat) {
            if (is_array($cat) && !empty($cat['id'])) {
                $categoryIds[] = (string)$cat['id'];
            } elseif (is_string($cat) || is_numeric($cat)) {
                $categoryIds[] = (string)$cat;
            }
        }
    }
    $categoryIds = array_values(array_unique(array_filter(array_map('trim', $categoryIds), function ($v) {
        return $v !== '';
    })));
    return [
        'external_id' => $externalId,
        'sku' => $sku,
        'name' => $name,
        'description' => $desc !== '' ? $desc : null,
        'unit_type' => $unitType,
        'category_external_ids' => $categoryIds,
        'raw' => $row,
        'version' => isset($row['version']) ? (string)$row['version'] : '',
    ];
}

function lightspeedxNormalizeSupplierRow(array $row) {
    $externalId = trim((string)($row['id'] ?? $row['supplier_id'] ?? ''));
    $name = trim((string)($row['name'] ?? $row['supplier_name'] ?? ''));
    if ($name === '' && !empty($row['attributes']) && is_array($row['attributes'])) {
        $name = trim((string)($row['attributes']['name'] ?? ''));
    }
    $contactRaw = $row['contact_name'] ?? $row['contact'] ?? '';
    if (is_array($contactRaw)) {
        $contactName = trim((string)($contactRaw['name'] ?? $contactRaw['full_name'] ?? ''));
    } else {
        $contactName = trim((string)$contactRaw);
    }
    $phone = trim((string)($row['phone'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));
    return [
        'external_id' => $externalId,
        'name' => $name,
        'contact_name' => ($contactName !== '' ? $contactName : null),
        'phone' => ($phone !== '' ? $phone : null),
        'email' => ($email !== '' ? $email : null),
        'raw' => $row,
    ];
}

function lightspeedxNormalizeInventoryRow(array $row) {
    $externalProductId = trim((string)($row['product_id'] ?? $row['id'] ?? ''));
    if ($externalProductId === '' && !empty($row['product']) && is_array($row['product'])) {
        $externalProductId = trim((string)($row['product']['id'] ?? ''));
    }
    $externalOutletId = trim((string)($row['outlet_id'] ?? $row['outlet']['id'] ?? ''));
    $qtyCandidates = [
        $row['count'] ?? null,
        $row['on_hand'] ?? null,
        $row['inventory_level'] ?? null,
        $row['current_amount'] ?? null,
        $row['quantity'] ?? null,
    ];
    $onHand = 0.0;
    foreach ($qtyCandidates as $candidate) {
        if ($candidate !== null && $candidate !== '') {
            $onHand = (float)$candidate;
            break;
        }
    }
    $unitCost = null;
    $costCandidates = [
        $row['supply_price'] ?? null,
        $row['cost'] ?? null,
        $row['unit_cost'] ?? null,
    ];
    foreach ($costCandidates as $candidate) {
        if ($candidate !== null && $candidate !== '') {
            $unitCost = (float)$candidate;
            break;
        }
    }
    return [
        'external_product_id' => $externalProductId,
        'external_outlet_id' => $externalOutletId,
        'on_hand' => $onHand,
        'unit_cost' => $unitCost,
        'raw' => $row,
    ];
}

function lightspeedxNormalizeOutletRow(array $row) {
    $externalId = trim((string)($row['id'] ?? $row['outlet_id'] ?? ''));
    $name = trim((string)($row['name'] ?? $row['outlet_name'] ?? ''));
    if ($name === '' && !empty($row['attributes']) && is_array($row['attributes'])) {
        $name = trim((string)($row['attributes']['name'] ?? ''));
    }
    return [
        'external_outlet_id' => $externalId,
        'outlet_name' => $name,
        'raw' => $row,
    ];
}

function lightspeedxUpsertCategory(array $normalized) {
    $externalId = (string)($normalized['external_id'] ?? '');
    $name = (string)($normalized['name'] ?? '');
    if ($externalId === '' || $name === '') {
        return ['success' => false, 'message' => 'Category external id and name are required.'];
    }
    $isActive = !empty($normalized['is_active']);
    $rawJson = json_encode($normalized['raw'] ?? null);
    $pdo = getDB();

    $stmt = $pdo->prepare("
        INSERT INTO lightspeed_categories (external_id, name, is_active, raw_payload_json, updated_at)
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (external_id)
        DO UPDATE SET
            name = EXCLUDED.name,
            is_active = EXCLUDED.is_active,
            raw_payload_json = EXCLUDED.raw_payload_json,
            updated_at = CURRENT_TIMESTAMP
        RETURNING id
    ");
    $stmt->execute([$externalId, $name, $isActive ? 't' : 'f', $rawJson]);
    $lightspeedRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $lightspeedCategoryId = (int)($lightspeedRow['id'] ?? 0);

    $legacyStmt = $pdo->prepare("
        INSERT INTO product_categories (name, external_id, provider, created_at, updated_at)
        VALUES (?, ?, 'lightspeed_x', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ON CONFLICT (name)
        DO UPDATE SET
            external_id = EXCLUDED.external_id,
            provider = 'lightspeed_x',
            updated_at = CURRENT_TIMESTAMP
        RETURNING id
    ");
    $legacyStmt->execute([$name, $externalId]);
    $legacyRow = $legacyStmt->fetch(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'lightspeed_category_id' => $lightspeedCategoryId,
        'category_id' => (int)($legacyRow['id'] ?? 0),
    ];
}

function lightspeedxResolveCategoryIdsByExternalId($externalId) {
    $externalId = trim((string)$externalId);
    if ($externalId === '') {
        return [0, 0];
    }
    $pdo = getDB();
    $legacyId = 0;
    $lsId = 0;

    $stmtLegacy = $pdo->prepare("SELECT id FROM product_categories WHERE external_id = ? LIMIT 1");
    $stmtLegacy->execute([$externalId]);
    $rowLegacy = $stmtLegacy->fetch(PDO::FETCH_ASSOC);
    $legacyId = (int)($rowLegacy['id'] ?? 0);

    $stmtLs = $pdo->prepare("SELECT id FROM lightspeed_categories WHERE external_id = ? LIMIT 1");
    $stmtLs->execute([$externalId]);
    $rowLs = $stmtLs->fetch(PDO::FETCH_ASSOC);
    $lsId = (int)($rowLs['id'] ?? 0);

    return [$legacyId, $lsId];
}

function lightspeedxUpsertProduct(array $normalized) {
    $externalId = (string)($normalized['external_id'] ?? '');
    $sku = trim((string)($normalized['sku'] ?? ''));
    $name = trim((string)($normalized['name'] ?? ''));
    if ($externalId === '' || $sku === '' || $name === '') {
        return ['success' => false, 'message' => 'Product external id, sku, and name are required.'];
    }
    $desc = $normalized['description'] ?? null;
    $unitType = trim((string)($normalized['unit_type'] ?? 'unit'));
    if ($unitType === '') {
        $unitType = 'unit';
    }
    $rawJson = json_encode($normalized['raw'] ?? null);
    $pdo = getDB();

    $ownsTx = !$pdo->inTransaction();
    if ($ownsTx) {
        $pdo->beginTransaction();
    }
    try {
        $existingStmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
        $existingStmt->execute([$sku]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $productId = (int)$existing['id'];
            $upStmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, unit_type = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upStmt->execute([$name, $desc, $unitType, $productId]);
        } else {
            $insStmt = $pdo->prepare("
                INSERT INTO products (sku, name, description, unit_type, created_at, updated_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id
            ");
            $insStmt->execute([$sku, $name, $desc, $unitType]);
            $insRow = $insStmt->fetch(PDO::FETCH_ASSOC);
            $productId = (int)($insRow['id'] ?? 0);
        }
        if ($productId <= 0) {
            throw new RuntimeException('Unable to upsert product.');
        }

        $mapStmt = $pdo->prepare("
            INSERT INTO lightspeed_product_map (external_id, product_id, raw_payload_json, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (external_id)
            DO UPDATE SET
                product_id = EXCLUDED.product_id,
                raw_payload_json = EXCLUDED.raw_payload_json,
                updated_at = CURRENT_TIMESTAMP
        ");
        $mapStmt->execute([$externalId, $productId, $rawJson]);

        if (!empty($normalized['category_external_ids']) && is_array($normalized['category_external_ids'])) {
            $legacyLinkStmt = $pdo->prepare("
                INSERT INTO product_category_map (product_id, category_id)
                VALUES (?, ?)
                ON CONFLICT (product_id, category_id) DO NOTHING
            ");
            $lsLinkStmt = $pdo->prepare("
                INSERT INTO lightspeed_product_category_map (product_id, lightspeed_category_id)
                VALUES (?, ?)
                ON CONFLICT (product_id, lightspeed_category_id) DO NOTHING
            ");
            foreach ($normalized['category_external_ids'] as $catExternalId) {
                [$legacyCatId, $lsCatId] = lightspeedxResolveCategoryIdsByExternalId($catExternalId);
                if ($legacyCatId > 0) {
                    $legacyLinkStmt->execute([$productId, $legacyCatId]);
                }
                if ($lsCatId > 0) {
                    $lsLinkStmt->execute([$productId, $lsCatId]);
                }
            }
        }

        if ($ownsTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return ['success' => true, 'product_id' => $productId];
    } catch (Throwable $e) {
        if ($ownsTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function lightspeedxResolveVendorIdByExternalId($externalId) {
    $externalId = trim((string)$externalId);
    if ($externalId === '') {
        return 0;
    }
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT vendor_id FROM lightspeed_supplier_map WHERE external_id = ? LIMIT 1");
    $stmt->execute([$externalId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['vendor_id'] ?? 0);
}

function lightspeedxUpsertSupplier(array $normalized) {
    $externalId = trim((string)($normalized['external_id'] ?? ''));
    $name = trim((string)($normalized['name'] ?? ''));
    if ($externalId === '' || $name === '') {
        return ['success' => false, 'message' => 'Supplier external id and name are required.'];
    }
    $pdo = getDB();
    $ownsTx = !$pdo->inTransaction();
    if ($ownsTx) {
        $pdo->beginTransaction();
    }
    try {
        $vendorId = lightspeedxResolveVendorIdByExternalId($externalId);
        if ($vendorId <= 0) {
            $findByName = $pdo->prepare("SELECT id FROM vendors WHERE name = ? LIMIT 1");
            $findByName->execute([$name]);
            $row = $findByName->fetch(PDO::FETCH_ASSOC);
            $vendorId = (int)($row['id'] ?? 0);
        }
        if ($vendorId > 0) {
            $upVendor = $pdo->prepare("
                UPDATE vendors
                SET name = ?, contact_name = ?, phone = ?, email = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $upVendor->execute([
                $name,
                $normalized['contact_name'] ?? null,
                $normalized['phone'] ?? null,
                $normalized['email'] ?? null,
                $vendorId,
            ]);
        } else {
            $insVendor = $pdo->prepare("
                INSERT INTO vendors (name, contact_name, phone, email, created_at, updated_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id
            ");
            $insVendor->execute([
                $name,
                $normalized['contact_name'] ?? null,
                $normalized['phone'] ?? null,
                $normalized['email'] ?? null,
            ]);
            $vendorRow = $insVendor->fetch(PDO::FETCH_ASSOC);
            $vendorId = (int)($vendorRow['id'] ?? 0);
        }
        if ($vendorId <= 0) {
            throw new RuntimeException('Unable to resolve vendor id.');
        }
        $upMap = $pdo->prepare("
            INSERT INTO lightspeed_supplier_map (external_id, vendor_id, raw_payload_json, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (external_id)
            DO UPDATE SET
                vendor_id = EXCLUDED.vendor_id,
                raw_payload_json = EXCLUDED.raw_payload_json,
                updated_at = CURRENT_TIMESTAMP
        ");
        $upMap->execute([$externalId, $vendorId, json_encode($normalized['raw'] ?? null)]);

        if ($ownsTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return ['success' => true, 'vendor_id' => $vendorId];
    } catch (Throwable $e) {
        if ($ownsTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function lightspeedxResolveLocalStoreId($externalOutletId = '') {
    $configured = (int)getAppEnv('LIGHTSPEED_X_LOCAL_STORE_ID', 0);
    if ($configured > 0) {
        return $configured;
    }
    $externalOutletId = trim((string)$externalOutletId);
    $pdo = getDB();
    if ($externalOutletId !== '') {
        $stmtMap = $pdo->prepare("SELECT store_id FROM lightspeed_outlet_map WHERE external_outlet_id = ? LIMIT 1");
        $stmtMap->execute([$externalOutletId]);
        $rowMap = $stmtMap->fetch(PDO::FETCH_ASSOC);
        $mappedStoreId = (int)($rowMap['store_id'] ?? 0);
        if ($mappedStoreId > 0) {
            return $mappedStoreId;
        }
    }
    $stmtAny = $pdo->query("SELECT id FROM stores ORDER BY id ASC LIMIT 1");
    $rowAny = $stmtAny->fetch(PDO::FETCH_ASSOC);
    return (int)($rowAny['id'] ?? 0);
}

function lightspeedxResolveMappedStoreIdOnly($externalOutletId = '') {
    $externalOutletId = trim((string)$externalOutletId);
    if ($externalOutletId === '') {
        return 0;
    }
    $pdo = getDB();
    $stmtMap = $pdo->prepare("SELECT store_id FROM lightspeed_outlet_map WHERE external_outlet_id = ? LIMIT 1");
    $stmtMap->execute([$externalOutletId]);
    $rowMap = $stmtMap->fetch(PDO::FETCH_ASSOC);
    return (int)($rowMap['store_id'] ?? 0);
}

function lightspeedxResolveProductIdByExternalId($externalProductId) {
    $externalProductId = trim((string)$externalProductId);
    if ($externalProductId === '') {
        return 0;
    }
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT product_id FROM lightspeed_product_map WHERE external_id = ? LIMIT 1");
    $stmt->execute([$externalProductId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['product_id'] ?? 0);
}

function lightspeedxUpsertInventoryRecord(array $normalized) {
    $productId = lightspeedxResolveProductIdByExternalId($normalized['external_product_id'] ?? '');
    if ($productId <= 0) {
        return ['success' => false, 'message' => 'No local product map for external product id.'];
    }
    $storeId = lightspeedxResolveLocalStoreId((string)($normalized['external_outlet_id'] ?? ''));
    if ($storeId <= 0) {
        return ['success' => false, 'message' => 'No local store mapping available.'];
    }
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO inventory (store_id, product_id, on_hand, unit_cost, created_at, updated_at)
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ON CONFLICT (store_id, product_id)
        DO UPDATE SET
            on_hand = EXCLUDED.on_hand,
            unit_cost = COALESCE(EXCLUDED.unit_cost, inventory.unit_cost),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $storeId,
        $productId,
        (float)($normalized['on_hand'] ?? 0),
        $normalized['unit_cost'] !== null ? (float)$normalized['unit_cost'] : null,
    ]);
    return ['success' => true];
}

function lightspeedxUpsertOutletRecord(array $normalized) {
    $externalOutletId = trim((string)($normalized['external_outlet_id'] ?? ''));
    $outletName = trim((string)($normalized['outlet_name'] ?? ''));
    if ($externalOutletId === '') {
        return ['success' => false, 'message' => 'Outlet external id is required.'];
    }
    $mappedStoreId = lightspeedxResolveMappedStoreIdOnly($externalOutletId);
    if ($mappedStoreId <= 0) {
        $configured = (int)getAppEnv('LIGHTSPEED_X_LOCAL_STORE_ID', 0);
        if ($configured > 0) {
            $mappedStoreId = $configured;
        }
    }
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO lightspeed_outlet_map (external_outlet_id, store_id, outlet_name, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (external_outlet_id)
        DO UPDATE SET
            store_id = COALESCE(EXCLUDED.store_id, lightspeed_outlet_map.store_id),
            outlet_name = EXCLUDED.outlet_name,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $externalOutletId,
        $mappedStoreId > 0 ? $mappedStoreId : null,
        $outletName !== '' ? $outletName : null,
    ]);
    return ['success' => true, 'store_id' => $mappedStoreId];
}

function lightspeedxSyncCategories(array $options = []) {
    $mode = (string)($options['mode'] ?? 'manual');
    $startedBy = isset($options['started_by']) ? (string)$options['started_by'] : null;
    $runId = lightspeedxSyncRunStart('product_categories', $mode, $startedBy);
    $seen = 0;
    $upserted = 0;
    $failed = 0;
    $checkpointMax = null;
    try {
        $query = [];
        if (($options['mode'] ?? '') === 'incremental') {
            $checkpoint = lightspeedxGetCheckpoint('product_categories');
            if ($checkpoint !== '') {
                $query['after'] = $checkpoint;
            }
        }
        $resp = lightspeedxGetCollection('/api/2.0/product_categories', [
            'limit' => (int)($options['limit'] ?? 200),
            'max_pages' => (int)($options['max_pages'] ?? 50),
            'query' => $query,
        ]);
        if (empty($resp['success'])) {
            $failed++;
            lightspeedxSyncLogError($runId, null, 'API_ERROR', (string)($resp['message'] ?? 'API call failed'), ['url' => (string)($resp['url'] ?? '')]);
            lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed, (string)($resp['message'] ?? 'Category sync failed.'));
            return ['success' => false, 'run_id' => $runId, 'message' => (string)($resp['message'] ?? 'Category sync failed.')];
        }
        foreach ((array)$resp['rows'] as $row) {
            $seen++;
            try {
                $normalized = lightspeedxNormalizeCategoryRow((array)$row);
                $up = lightspeedxUpsertCategory($normalized);
                if (!empty($up['success'])) {
                    $upserted++;
                } else {
                    $failed++;
                    lightspeedxSyncLogError($runId, (string)($normalized['external_id'] ?? ''), 'UPSERT_FAILED', (string)($up['message'] ?? 'Category upsert failed'), $row);
                }
                if (isset($row['version']) && is_numeric($row['version'])) {
                    $v = (string)$row['version'];
                    if ($checkpointMax === null || (int)$v > (int)$checkpointMax) {
                        $checkpointMax = $v;
                    }
                }
            } catch (Throwable $inner) {
                $failed++;
                lightspeedxSyncLogError($runId, (string)($row['id'] ?? ''), 'EXCEPTION', $inner->getMessage(), $row);
            }
        }
        if ($checkpointMax !== null) {
            lightspeedxUpsertCheckpoint('product_categories', $checkpointMax);
        }
        lightspeedxSyncRunFinish($runId, $failed > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED', $seen, $upserted, $failed, 'Category sync complete.');
        return ['success' => true, 'run_id' => $runId, 'seen' => $seen, 'upserted' => $upserted, 'failed' => $failed];
    } catch (Throwable $e) {
        lightspeedxSyncLogError($runId, null, 'FATAL', $e->getMessage(), null);
        lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed + 1, $e->getMessage());
        return ['success' => false, 'run_id' => $runId, 'message' => 'Category sync failed: ' . $e->getMessage()];
    }
}

function lightspeedxSyncProducts(array $options = []) {
    $mode = (string)($options['mode'] ?? 'manual');
    $startedBy = isset($options['started_by']) ? (string)$options['started_by'] : null;
    $runId = lightspeedxSyncRunStart('products', $mode, $startedBy);
    $seen = 0;
    $upserted = 0;
    $failed = 0;
    $checkpointMax = null;
    try {
        $query = [];
        if (($options['mode'] ?? '') === 'incremental') {
            $checkpoint = lightspeedxGetCheckpoint('products');
            if ($checkpoint !== '') {
                $query['after'] = $checkpoint;
            }
        }
        $resp = lightspeedxGetCollection('/api/2.0/products', [
            'limit' => (int)($options['limit'] ?? 200),
            'max_pages' => (int)($options['max_pages'] ?? 50),
            'query' => $query,
        ]);
        if (empty($resp['success'])) {
            $failed++;
            lightspeedxSyncLogError($runId, null, 'API_ERROR', (string)($resp['message'] ?? 'API call failed'), ['url' => (string)($resp['url'] ?? '')]);
            lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed, (string)($resp['message'] ?? 'Product sync failed.'));
            return ['success' => false, 'run_id' => $runId, 'message' => (string)($resp['message'] ?? 'Product sync failed.')];
        }
        foreach ((array)$resp['rows'] as $row) {
            $seen++;
            try {
                $normalized = lightspeedxNormalizeProductRow((array)$row);
                $up = lightspeedxUpsertProduct($normalized);
                if (!empty($up['success'])) {
                    $upserted++;
                } else {
                    $failed++;
                    lightspeedxSyncLogError($runId, (string)($normalized['external_id'] ?? ''), 'UPSERT_FAILED', (string)($up['message'] ?? 'Product upsert failed'), $row);
                }
                if (!empty($normalized['version']) && is_numeric($normalized['version'])) {
                    $v = (string)$normalized['version'];
                    if ($checkpointMax === null || (int)$v > (int)$checkpointMax) {
                        $checkpointMax = $v;
                    }
                }
            } catch (Throwable $inner) {
                $failed++;
                lightspeedxSyncLogError($runId, (string)($row['id'] ?? ''), 'EXCEPTION', $inner->getMessage(), $row);
            }
        }
        if ($checkpointMax !== null) {
            lightspeedxUpsertCheckpoint('products', $checkpointMax);
        }
        lightspeedxSyncRunFinish($runId, $failed > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED', $seen, $upserted, $failed, 'Product sync complete.');
        return ['success' => true, 'run_id' => $runId, 'seen' => $seen, 'upserted' => $upserted, 'failed' => $failed];
    } catch (Throwable $e) {
        lightspeedxSyncLogError($runId, null, 'FATAL', $e->getMessage(), null);
        lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed + 1, $e->getMessage());
        return ['success' => false, 'run_id' => $runId, 'message' => 'Product sync failed: ' . $e->getMessage()];
    }
}

function lightspeedxSyncSuppliers(array $options = []) {
    $mode = (string)($options['mode'] ?? 'manual');
    $startedBy = isset($options['started_by']) ? (string)$options['started_by'] : null;
    $runId = lightspeedxSyncRunStart('suppliers', $mode, $startedBy);
    $seen = 0;
    $upserted = 0;
    $failed = 0;
    try {
        $query = [];
        $resp = lightspeedxGetCollection('/api/2.0/suppliers', [
            'limit' => (int)($options['limit'] ?? 200),
            'max_pages' => (int)($options['max_pages'] ?? 50),
            'query' => $query,
        ]);
        if (empty($resp['success'])) {
            $failed++;
            lightspeedxSyncLogError($runId, null, 'API_ERROR', (string)($resp['message'] ?? 'API call failed'), ['url' => (string)($resp['url'] ?? '')]);
            lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed, (string)($resp['message'] ?? 'Supplier sync failed.'));
            return ['success' => false, 'run_id' => $runId, 'message' => (string)($resp['message'] ?? 'Supplier sync failed.')];
        }
        foreach ((array)$resp['rows'] as $row) {
            $seen++;
            try {
                $normalized = lightspeedxNormalizeSupplierRow((array)$row);
                $up = lightspeedxUpsertSupplier($normalized);
                if (!empty($up['success'])) {
                    $upserted++;
                } else {
                    $failed++;
                    lightspeedxSyncLogError($runId, (string)($normalized['external_id'] ?? ''), 'UPSERT_FAILED', (string)($up['message'] ?? 'Supplier upsert failed'), $row);
                }
            } catch (Throwable $inner) {
                $failed++;
                lightspeedxSyncLogError($runId, (string)($row['id'] ?? ''), 'EXCEPTION', $inner->getMessage(), $row);
            }
        }
        lightspeedxSyncRunFinish($runId, $failed > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED', $seen, $upserted, $failed, 'Supplier sync complete.');
        return ['success' => true, 'run_id' => $runId, 'seen' => $seen, 'upserted' => $upserted, 'failed' => $failed];
    } catch (Throwable $e) {
        lightspeedxSyncLogError($runId, null, 'FATAL', $e->getMessage(), null);
        lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed + 1, $e->getMessage());
        return ['success' => false, 'run_id' => $runId, 'message' => 'Supplier sync failed: ' . $e->getMessage()];
    }
}

function lightspeedxSyncInventory(array $options = []) {
    $mode = (string)($options['mode'] ?? 'manual');
    $startedBy = isset($options['started_by']) ? (string)$options['started_by'] : null;
    $runId = lightspeedxSyncRunStart('inventory', $mode, $startedBy);
    $seen = 0;
    $upserted = 0;
    $failed = 0;
    try {
        $query = [];
        if (!empty($options['outlet_id'])) {
            $query['outlet_id'] = (string)$options['outlet_id'];
        }
        $resp = lightspeedxGetCollection('/api/2.0/inventory', [
            'limit' => (int)($options['limit'] ?? 200),
            'max_pages' => (int)($options['max_pages'] ?? 50),
            'query' => $query,
        ]);
        if (empty($resp['success'])) {
            $failed++;
            lightspeedxSyncLogError($runId, null, 'API_ERROR', (string)($resp['message'] ?? 'API call failed'), ['url' => (string)($resp['url'] ?? '')]);
            lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed, (string)($resp['message'] ?? 'Inventory sync failed.'));
            return ['success' => false, 'run_id' => $runId, 'message' => (string)($resp['message'] ?? 'Inventory sync failed.')];
        }
        foreach ((array)$resp['rows'] as $row) {
            $seen++;
            try {
                $normalized = lightspeedxNormalizeInventoryRow((array)$row);
                // Ensure outlet is registered in mapping table before inventory assignment.
                $outletSeed = lightspeedxUpsertOutletRecord([
                    'external_outlet_id' => (string)($normalized['external_outlet_id'] ?? ''),
                    'outlet_name' => '',
                    'raw' => $row,
                ]);
                if (empty($outletSeed['success'])) {
                    $failed++;
                    lightspeedxSyncLogError($runId, (string)($normalized['external_outlet_id'] ?? ''), 'OUTLET_MAP_FAILED', (string)($outletSeed['message'] ?? 'Outlet seed failed'), $row);
                    continue;
                }
                $up = lightspeedxUpsertInventoryRecord($normalized);
                if (!empty($up['success'])) {
                    $upserted++;
                } else {
                    $failed++;
                    lightspeedxSyncLogError($runId, (string)($normalized['external_product_id'] ?? ''), 'UPSERT_FAILED', (string)($up['message'] ?? 'Inventory upsert failed'), $row);
                }
            } catch (Throwable $inner) {
                $failed++;
                lightspeedxSyncLogError($runId, (string)($row['product_id'] ?? $row['id'] ?? ''), 'EXCEPTION', $inner->getMessage(), $row);
            }
        }
        lightspeedxSyncRunFinish($runId, $failed > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED', $seen, $upserted, $failed, 'Inventory sync complete.');
        return ['success' => true, 'run_id' => $runId, 'seen' => $seen, 'upserted' => $upserted, 'failed' => $failed];
    } catch (Throwable $e) {
        lightspeedxSyncLogError($runId, null, 'FATAL', $e->getMessage(), null);
        lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed + 1, $e->getMessage());
        return ['success' => false, 'run_id' => $runId, 'message' => 'Inventory sync failed: ' . $e->getMessage()];
    }
}

function lightspeedxSyncOutlets(array $options = []) {
    $mode = (string)($options['mode'] ?? 'manual');
    $startedBy = isset($options['started_by']) ? (string)$options['started_by'] : null;
    $runId = lightspeedxSyncRunStart('outlets', $mode, $startedBy);
    $seen = 0;
    $upserted = 0;
    $failed = 0;
    try {
        $resp = lightspeedxGetCollection('/api/2.0/outlets', [
            'limit' => (int)($options['limit'] ?? 200),
            'max_pages' => (int)($options['max_pages'] ?? 50),
        ]);
        if (empty($resp['success'])) {
            $failed++;
            lightspeedxSyncLogError($runId, null, 'API_ERROR', (string)($resp['message'] ?? 'API call failed'), ['url' => (string)($resp['url'] ?? '')]);
            lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed, (string)($resp['message'] ?? 'Outlets sync failed.'));
            return ['success' => false, 'run_id' => $runId, 'message' => (string)($resp['message'] ?? 'Outlets sync failed.')];
        }
        foreach ((array)$resp['rows'] as $row) {
            $seen++;
            try {
                $normalized = lightspeedxNormalizeOutletRow((array)$row);
                $up = lightspeedxUpsertOutletRecord($normalized);
                if (!empty($up['success'])) {
                    $upserted++;
                } else {
                    $failed++;
                    lightspeedxSyncLogError($runId, (string)($normalized['external_outlet_id'] ?? ''), 'UPSERT_FAILED', (string)($up['message'] ?? 'Outlet upsert failed'), $row);
                }
            } catch (Throwable $inner) {
                $failed++;
                lightspeedxSyncLogError($runId, (string)($row['id'] ?? ''), 'EXCEPTION', $inner->getMessage(), $row);
            }
        }
        lightspeedxSyncRunFinish($runId, $failed > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED', $seen, $upserted, $failed, 'Outlets sync complete.');
        return ['success' => true, 'run_id' => $runId, 'seen' => $seen, 'upserted' => $upserted, 'failed' => $failed];
    } catch (Throwable $e) {
        lightspeedxSyncLogError($runId, null, 'FATAL', $e->getMessage(), null);
        lightspeedxSyncRunFinish($runId, 'FAILED', $seen, $upserted, $failed + 1, $e->getMessage());
        return ['success' => false, 'run_id' => $runId, 'message' => 'Outlets sync failed: ' . $e->getMessage()];
    }
}

function lightspeedxSyncAll(array $options = []) {
    $out = [
        'success' => true,
        'steps' => [],
    ];
    $cat = lightspeedxSyncCategories($options);
    $out['steps']['categories'] = $cat;
    if (empty($cat['success'])) {
        $out['success'] = false;
        return $out;
    }
    $prd = lightspeedxSyncProducts($options);
    $out['steps']['products'] = $prd;
    if (empty($prd['success'])) {
        $out['success'] = false;
    }
    $sup = lightspeedxSyncSuppliers($options);
    $out['steps']['suppliers'] = $sup;
    if (empty($sup['success'])) {
        $out['success'] = false;
    }
    $outlets = lightspeedxSyncOutlets($options);
    $out['steps']['outlets'] = $outlets;
    if (empty($outlets['success'])) {
        $out['success'] = false;
    }
    $inv = lightspeedxSyncInventory($options);
    $out['steps']['inventory'] = $inv;
    if (empty($inv['success'])) {
        $out['success'] = false;
    }
    return $out;
}
