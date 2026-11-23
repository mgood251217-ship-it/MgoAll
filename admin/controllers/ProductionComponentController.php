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

    // Resep dan nota diarahkan langsung ke komponen tujuan dalam transaksi pemanggil.
    public function renameOrMerge($store, $id, $name) {
        $name = self::name($name);
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
        $this->rewriteReferences($store, function ($variant, $component) use ($id, $targetId) {
            if ((int)($component['component_id'] ?? 0) === (int)$id) $component['component_id'] = $targetId;
            return $component;
        });
        $this->componentModel->delete($store, $id);

        return ['id' => $targetId, 'name' => $target['name']];
    }

    public function getByStore($store) {
        return $this->componentModel->getByStore($store);
    }

    public static function legacyKey($snapshot, $name) {
        return hash('sha256', json_encode(
            [(int)$snapshot['id'], $snapshot['_legacy_options'] ?? $snapshot['options'], $name],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    public function link($store, $product, $snapshot, $name, $component, $onlyMissing = false) {
        $key = self::legacyKey($snapshot, $name);
        $this->rewriteReferences($store, function ($variant, $entry) use ($key, $component, $onlyMissing) {
            if ((!$onlyMissing || empty($entry['component_id'])) && self::legacyKey($variant, $entry['name']) === $key) {
                $entry['component_id'] = (int)$component;
            }
            return $entry;
        }, $product);
    }

    public static function rewriteData($data, $replace, $context = []) {
        if (!is_array($data)) return $data;
        if (isset($data['components']) && is_array($data['components'])) {
            $variant = array_replace($context, $data);
            $variant['id'] = (int)($data['id'] ?? $context['product_variant_id'] ?? 0);
            $variant['options'] = $data['legacy_options'] ?? $data['options'] ?? $context['options'] ?? [];
            foreach ($data['components'] as &$component) $component = $replace($variant, $component);
            unset($component);
        }
        foreach ($data as $key => &$value) if ($key !== 'components' && is_array($value)) {
            $value = self::rewriteData($value, $replace, $context);
        }
        unset($value);
        return $data;
    }

    public function rewriteReferences($store, $replace, $product = 0) {
        return $this->componentModel->rewriteSnapshots($store, function ($data, $row) use ($replace) {
            return self::rewriteData($data, $replace, $row);
        }, $product);
    }
}
