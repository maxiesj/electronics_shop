<?php
if (!function_exists('trashOperator')) {
    function trashOperator(): array
    {
        return [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'name' => (string)($_SESSION['fullname'] ?? $_SESSION['staff_name'] ?? 'System operator')
        ];
    }
}

if (!function_exists('trashAudit')) {
    function trashAudit(mysqli $conn, string $actionType, string $details): void
    {
        $operator = trashOperator();
        $stmt = $conn->prepare('INSERT INTO staff_logs (user_id, staff_name, action_type, action_details) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare trash audit record.');
        }
        $stmt->bind_param('isss', $operator['id'], $operator['name'], $actionType, $details);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to save trash audit record.');
        }
        $stmt->close();
    }
}

if (!function_exists('registerTrashEntry')) {
    function registerTrashEntry(mysqli $conn, string $entityType, int $entityId, string $label, array $metadata = []): int
    {
        $operator = trashOperator();
        $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt = $conn->prepare("INSERT INTO trash_entries (entity_type, entity_id, entity_label, deleted_by, deleted_by_name, metadata, status) VALUES (?, ?, ?, ?, ?, ?, 'trashed')");
        if (!$stmt) throw new RuntimeException('Unable to prepare trash entry.');
        $stmt->bind_param('sisiss', $entityType, $entityId, $label, $operator['id'], $operator['name'], $metadataJson);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to save trash entry.');
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('restoreTrashEntry')) {
    function restoreTrashEntry(mysqli $conn, int $trashEntryId): void
    {
        $operator = trashOperator();
        $stmt = $conn->prepare("UPDATE trash_entries SET status='restored', restored_by=?, restored_by_name=?, restored_at=NOW() WHERE id=? AND status='trashed'");
        if (!$stmt) throw new RuntimeException('Unable to prepare trash restoration audit.');
        $stmt->bind_param('isi', $operator['id'], $operator['name'], $trashEntryId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Trash entry is no longer available for restore.');
        }
        $stmt->close();
    }
}

if (!function_exists('softDeleteProduct')) {
    function softDeleteProduct(mysqli $conn, int $productId): void
    {
        $operator = trashOperator();
        $find = $conn->prepare('SELECT product_name, sku, image, is_deleted FROM products WHERE id=? FOR UPDATE');
        if (!$find) throw new RuntimeException('Unable to prepare product lookup.');
        $find->bind_param('i', $productId);
        if (!$find->execute()) { $find->close(); throw new RuntimeException('Unable to load product.'); }
        $product = $find->get_result()->fetch_assoc();
        $find->close();
        if (!$product) throw new RuntimeException('NOT_FOUND');
        if ((int)$product['is_deleted'] === 1) throw new RuntimeException('ALREADY_TRASHED');

        $update = $conn->prepare('UPDATE products SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND is_deleted=0');
        if (!$update) throw new RuntimeException('Unable to prepare product trash operation.');
        $update->bind_param('ii', $operator['id'], $productId);
        if (!$update->execute() || $update->affected_rows !== 1) {
            $update->close();
            throw new RuntimeException('Unable to move product to Trash.');
        }
        $update->close();

        registerTrashEntry($conn, 'product', $productId, (string)$product['product_name'], [
            'sku' => $product['sku'] ?? null,
            'image' => $product['image'] ?? null
        ]);
        trashAudit($conn, 'Inventory Trash', "Product #{$productId} ({$product['product_name']}) moved to Trash.");
    }
}

if (!function_exists('restoreProductFromTrash')) {
    function restoreProductFromTrash(mysqli $conn, int $trashEntryId, int $productId): void
    {
        $find = $conn->prepare("SELECT id, entity_label FROM trash_entries WHERE id=? AND entity_type='product' AND entity_id=? AND status='trashed' FOR UPDATE");
        if (!$find) throw new RuntimeException('Unable to prepare trash lookup.');
        $find->bind_param('ii', $trashEntryId, $productId);
        if (!$find->execute()) { $find->close(); throw new RuntimeException('Unable to load trash entry.'); }
        $entry = $find->get_result()->fetch_assoc();
        $find->close();
        if (!$entry) throw new RuntimeException('TRASH_NOT_FOUND');

        $restore = $conn->prepare('UPDATE products SET is_deleted=0, deleted_at=NULL, deleted_by=NULL WHERE id=? AND is_deleted=1');
        if (!$restore) throw new RuntimeException('Unable to prepare product restore.');
        $restore->bind_param('i', $productId);
        if (!$restore->execute() || $restore->affected_rows !== 1) {
            $restore->close();
            throw new RuntimeException('Product could not be restored.');
        }
        $restore->close();

        restoreTrashEntry($conn, $trashEntryId);
        trashAudit($conn, 'Inventory Restore', "Product #{$productId} ({$entry['entity_label']}) restored from Trash.");
    }
}
