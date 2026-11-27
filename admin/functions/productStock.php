<?php
function productStockValue($value) {
    if (!is_scalar($value) || is_bool($value)) return null;
    $value = trim((string)$value);
    if ($value === '') $value = '0';
    return preg_match('/^-?[0-9]{1,11}(?:\.[0-9]{1,4})?$/D', $value) ? $value : null;
}

// M dan CM adalah nama baru satuan luas; rumus M2/CM2 tetap dipakai.
function productUnitType($product) {
    $unit = strtoupper(trim($product['unit_type'] ?? ''));
    return ['M' => 'M2', 'CM' => 'CM2'][$unit] ?? $unit;
}

// Nota finishing lama pernah menyimpan panjang tunggal, misalnya "2 M".
function finishingUsesLegacyLength($finishing, $size) {
    return in_array(strtoupper(trim($finishing['unit_type'] ?? '')), ['M', 'CM'], true)
        && preg_match('/^\d+(?:\.\d+)?\s*(?:M|CM)$/iD', trim((string)$size)) === 1;
}

// Satuan menentukan perhitungan; track_stock menentukan pemeriksaan dan mutasi stok.
function tracksStock($row) {
    return isset($row['track_stock']) ? (int)$row['track_stock'] === 1 : ($row['unit_type'] ?? '') !== '~';
}

function hasStockTracking($db, $table) {
    if (!in_array($table, ['products', 'finishings'], true)) throw new InvalidArgumentException('Tabel stok tidak valid.');
    $stmt = $db->prepare("SELECT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'track_stock') AS ready");
    $stmt->bind_param('s', $table); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return (bool)$row['ready'];
}

function stockTrackingSql($db, $table, $alias = '') {
    $prefix = $alias ? $alias . '.' : '';
    return hasStockTracking($db, $table) ? $prefix . 'track_stock = 1' : $prefix . "unit_type <> '~'";
}

function saveStockTracking($db, $table, $id, $input = []) {
    if (!hasStockTracking($db, $table)) return;
    $key = $table === 'products' ? 'product_id' : 'finishing_id';
    $stmt = $db->prepare("SELECT unit_type, track_stock FROM `$table` WHERE `$key` = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) return;
    $tracked = array_key_exists('track_stock', $input) ? (int)((string)$input['track_stock'] === '1') : (int)tracksStock($row);
    if ($row['unit_type'] === '~') $tracked = 0;
    $stmt = $db->prepare("UPDATE `$table` SET track_stock = ?, unit_type = IF(unit_type = '~', 'PCS', unit_type) WHERE `$key` = ?");
    $stmt->bind_param('ii', $tracked, $id); $stmt->execute(); $stmt->close();
}
