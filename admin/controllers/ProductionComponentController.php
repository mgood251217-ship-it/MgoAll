<?php
require_once __DIR__ . '/../models/ProductionComponent.php';
require_once __DIR__ . '/ProductVariantController.php';

class ProductionComponentController {
    private $componentModel;

    public function __construct($db) {
        $this->componentModel = new ProductionComponent($db);
    }

    public function isAvailable() {
        return $this->componentModel->isAvailable();
    }

    public static function name($value) {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Nama komponen tidak valid.');
        }
        $value = preg_replace('/\s+/u', ' ', trim((string)$value));
        if ($value === null || !preg_match('/^.{1,150}$/us', $value)) {
            throw new InvalidArgumentException('Isi nama komponen, maksimal 150 karakter.');
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    // Dipanggil dalam transaksi pengaturan varian setelah baris toko dikunci.
    public function findOrCreate($store, $name) {
        $name = self::name($name);
        $row = $this->componentModel->findByName($store, $name);
        if ($row) {
            return ['id' => (int)$row['id'], 'name' => $row['name']];
        }
        $id = $this->componentModel->create($store, $name);

        return ['id' => $id, 'name' => $name];
    }

    public static function resolveId($id, $links) {
        $id = (int)$id;
        $seen = [];
        while ($id && isset($links['component:' . $id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id = (int)$links['component:' . $id];
        }

        return $id;
    }

    // Penggabungan menyimpan ID lama sebagai alias untuk nota yang sudah dibuat.
    // Pemanggil memegang transaksi dan kunci toko yang sama dengan penyimpanan varian.
    public function renameOrMerge($store, $id, $name) {
        $name = self::name($name);
        $id = self::resolveId($id, $this->links($store));
        $source = $this->componentModel->findById($store, $id);
        if (!$source) {
            throw new InvalidArgumentException('Nama meteran sudah berubah. Muat ulang halaman.');
        }
        $target = $this->componentModel->findByName($store, $name);
        if (!$target || (int)$target['id'] === $id) {
            $this->componentModel->rename($store, $id, $name);
            return ['id' => $id, 'name' => $name];
        }
        $targetId = (int)$target['id'];
        $this->componentModel->updateVariantComponents($store, $id, $targetId);
        $this->componentModel->updateLinks($store, $id, $targetId);
        $this->componentModel->saveAlias($store, 'component:' . $id, $source['name'], $targetId);
        $this->componentModel->delete($store, $id);

        return ['id' => $targetId, 'name' => $target['name']];
    }

    public function getByStore($store) {
        return $this->componentModel->getByStore($store);
    }

    public function links($store) {
        return array_column($this->componentModel->getLinks($store), 'component_id', 'legacy_key');
    }

    public static function legacyKey($snapshot, $name) {
        return hash('sha256', json_encode(
            [(int)$snapshot['id'], $snapshot['options'], $name],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    public function link($store, $product, $snapshot, $name, $component, $onlyMissing = false) {
        $key = self::legacyKey($snapshot, $name);
        $variant = (int)$snapshot['id'];
        $this->componentModel->saveLink($store, $product, $variant, $key, $name, $component, $onlyMissing);
    }

    public function legacyGroups($store, $product = 0) {
        if (!$this->isAvailable()) {
            return [];
        }
        $links = $this->links($store);
        $result = $this->componentModel->getLegacyRows($store, $product);
        $groups = [];
        while ($row = $result->fetch_assoc()) {
            $saved = ProductVariantController::snapshot($row);
            foreach ($saved['components'] as $component) {
                if (!empty($component['component_id'])) {
                    continue;
                }
                $key = self::legacyKey($saved, $component['name']);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'key' => $key,
                        'product_id' => (int)$row['product_id'],
                        'snapshot' => ['id' => (int)$saved['id'], 'options' => $saved['options']],
                        'product_name' => $saved['product_name'],
                        'variant_label' => $saved['label'],
                        'name' => $component['name'],
                        'quantity' => 0,
                        'item_count' => 0,
                        'component_id' => (int)($links[$key] ?? 0)
                    ];
                }
                $groups[$key]['quantity'] = round(
                    $groups[$key]['quantity'] + (float)$component['quantity'] * (int)$row['quantity'],
                    4
                );
                $groups[$key]['item_count']++;
            }
        }

        return $groups;
    }
}
