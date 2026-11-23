<?php
require_once __DIR__ . '/../models/FinishingMigration.php';
require_once __DIR__ . '/ProductLaserConversionController.php';
require_once __DIR__ . '/ProductFinishingController.php';

class FinishingMigrationController {
    private $db;
    private $model;
    private const REFERENCES = [
        'product_package' => ['finishing_id', null],
        'product_finishing_prices' => ['finishing_id', null],
        'product_laser_conversion' => ['finishing_ids', null],
        'order_items' => ['finishing', 'tier_price_data'],
        'deleted_order_items' => ['finishing', 'tier_price_data'],
        'failure' => ['finishing', 'tier_price_data']
    ];

    public function __construct($db) { $this->db = $db; $this->model = new FinishingMigration($db); }

    private static function replaceIds($value, $source, $target) {
        $ids = ProductLaserConversionController::finishingIds($value);
        if (!in_array($source, $ids, true)) return $value;
        $ids = array_values(array_unique(array_map(function ($id) use ($source, $target) { return $id === $source ? $target : $id; }, $ids)));
        sort($ids);
        return implode(',', $ids);
    }

    private static function replaceSnapshot($node, $source, $target, $parent = '') {
        if (!is_array($node)) return $node;
        foreach ($node as $key => &$value) {
            if ($parent === 'stock' && $key === 'finishings' && is_array($value) && array_key_exists($source, $value)) {
                $value[$target] = ($value[$target] ?? 0) + $value[$source];
                unset($value[$source]);
            } elseif ($parent === 'standalone_finishing' && $key === 'id' && (int)$value === $source) {
                $value = $target;
            } elseif ($key === 'finishing_id' && (int)$value === $source) {
                $value = $target;
            } elseif (in_array($key, ['finishing', 'finishing_ids', 'finishing_str'], true) && is_scalar($value)) {
                $value = self::replaceIds((string)$value, $source, $target);
            } elseif (is_array($value)) {
                $value = self::replaceSnapshot($value, $source, $target, (string)$key);
            }
        }
        unset($value);
        return $node;
    }

    private function changes($row, $csv, $snapshot, $source, $target, $activeItem = false) {
        $values = [];
        $updated = self::replaceIds((string)$row[$csv], $source, $target);
        if ((string)$updated !== (string)$row[$csv]) $values[$csv] = $updated;
        if ($activeItem && $target > 0) {
            $ids = ProductLaserConversionController::finishingIds($row[$csv]);
            if (in_array($source, $ids, true) && in_array($target, $ids, true)) {
                $saved = !empty($row[$snapshot ?? '']) ? json_decode($row[$snapshot], true) : null;
                if (!isset($saved['stock']['finishings'][$source])) {
                    throw new InvalidArgumentException('Item #' . $row['order_item_id'] . ' memakai finishing asal dan tujuan sekaligus tanpa snapshot stok. Periksa item tersebut sebelum migrasi.');
                }
            }
        }
        if ($snapshot && !empty($row[$snapshot])) {
            try { $data = json_decode($row[$snapshot], true, 512, JSON_THROW_ON_ERROR); }
            catch (JsonException $e) { throw new InvalidArgumentException('Snapshot baris #' . ($row['order_item_id'] ?? $row['failure_id'] ?? $row['id'] ?? '-') . ' tidak valid. Periksa datanya sebelum migrasi.'); }
            $updated = self::replaceSnapshot($data, $source, $target);
            if ($updated !== $data) $values[$snapshot] = json_encode($updated, JSON_THROW_ON_ERROR);
        }
        return $values;
    }

    public function hasReferences($store, $source) {
        if ($this->model->linked($store, $source)) return true;
        foreach (self::REFERENCES as $table => [$csv, $snapshot]) {
            foreach ($this->model->rows($table, $store, $source, $csv, $snapshot) as $row) {
                if ($this->changes($row, $csv, $snapshot, $source, -1)) return true;
            }
        }
        return false;
    }

    public function migrate($store, $source, $target) {
        if ($source <= 0 || $target <= 0 || $source === $target) throw new InvalidArgumentException('Pilih finishing pengganti yang berbeda.');
        $this->db->begin_transaction();
        try {
            if (!$this->model->lockStore($store)) throw new InvalidArgumentException('Toko tidak ditemukan.');
            $rows = $this->model->lockFinishings($store, [$source, $target]);
            if (count($rows) !== 2) throw new InvalidArgumentException('Finishing asal dan tujuan harus tersedia pada toko yang sama.');
            if (strtoupper(trim($rows[$source]['unit_type'])) !== strtoupper(trim($rows[$target]['unit_type']))) {
                throw new InvalidArgumentException('Satuan finishing asal dan tujuan harus sama agar pemakaian stok tetap sesuai.');
            }
            $crossCategory = (int)$rows[$source]['category_id'] !== (int)$rows[$target]['category_id'];
            if ($crossCategory) {
                foreach (['product_package', 'product_finishing_prices'] as $table) {
                    if ($this->model->rows($table, $store, $source, 'finishing_id')) throw new InvalidArgumentException('Finishing ini digunakan harga paket. Pilih pengganti pada kategori yang sama.');
                }
            }
            $counts = [];
            foreach (self::REFERENCES as $table => [$csv, $snapshot]) {
                foreach ($this->model->rows($table, $store, $source, $csv, $snapshot) as $row) {
                    $values = $this->changes($row, $csv, $snapshot, $source, $target, $table === 'order_items');
                    if ($values) { $this->model->updateRow($table, $row, $values); $counts[$table] = ($counts[$table] ?? 0) + 1; }
                }
            }
            $this->model->moveLinks($store, $source, $target);
            if ($this->hasReferences($store, $source)) throw new RuntimeException('Masih ada referensi finishing asal.');
            $this->db->commit();
            return $counts;
        } catch (Throwable $e) {
            $this->db->rollback();
            if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
                throw new InvalidArgumentException('Aturan ' . (($table ?? '') === 'product_laser_conversion' ? 'konversi laser' : 'harga paket')
                    . ' #' . ($row['id'] ?? '-') . ' bertabrakan dengan aturan tujuan. Rapikan aturan tersebut sebelum migrasi.');
            }
            throw $e;
        }
    }

    public function delete($store, $id) {
        $this->db->begin_transaction();
        try {
            $this->model->lockStore($store);
            if (!$this->model->lockFinishings($store, [$id])) throw new InvalidArgumentException('Finishing tidak ditemukan.');
            if ($this->hasReferences($store, $id)) throw new InvalidArgumentException('Finishing masih digunakan. Migrasikan ke finishing pengganti sebelum dihapus.');
            $this->model->query('DELETE FROM finishings WHERE store_id = ? AND finishing_id = ?', [$store, $id])->close();
            $this->db->commit();
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }
}
