<?php
require_once __DIR__ . '/../models/ProductPriceTier.php';
require_once __DIR__ . '/ProductFinishingPriceController.php';
require_once __DIR__ . '/ProductLaserConversionController.php';
require_once __DIR__ . '/../models/ProductPromotion.php';
require_once __DIR__ . '/ProductVariantController.php';
require_once __DIR__ . '/ProductBundleController.php';

class ProductPriceTierController {
    private $db;
    private $tierModel;

    public function __construct($db) {
        $this->db = $db;
        $this->tierModel = new ProductPriceTier($db);
    }

    public function isAvailable() {
        return $this->tierModel->isAvailable();
    }

    public static function isWeightMaterial($product) {
        return strtoupper(trim($product['unit_type'] ?? '')) === 'PCS'
            && str_contains(strtoupper($product['name'] ?? ''), 'BAHAN');
    }

    public static function weightFromSize($size) {
        return preg_match('/^(\d+(?:\.\d+)?)\s*KG$/iD', trim((string)$size), $match) ? (float)$match[1] : 0;
    }

    public static function usesTime($product) {
        return strtoupper(trim($product['category'] ?? '')) === 'JASA'
            && in_array(strtoupper(trim($product['name'] ?? '')), ['SETTING', 'POTONG AKRILIK'], true);
    }

    public static function measure($product) {
        $category = strtoupper(trim($product['category'] ?? ''));
        if (self::usesTime($product)) {
            return null;
        }
        $unit = strtoupper(trim($product['unit_type'] ?? ''));
        if (in_array($unit, ['PCS', '~'], true)) {
            return 'pcs';
        }
        if ($unit === 'M2' && !in_array($category, ['DTF', 'DTF UV'], true)) {
            return 'm2';
        }
        if ($unit === 'CM2') {
            return 'cm2';
        }
        return null;
    }

    public static function unitLabel($measure) {
        return ['m2' => 'm²', 'cm2' => 'cm²', 'pcs' => 'pcs'][$measure] ?? 'pcs';
    }

    public static function minimumAmount($product) {
        return strtoupper(trim($product['category'] ?? '')) === 'AKRILIK'
            && strtoupper(trim($product['name'] ?? '')) === 'PRINT UV' ? 7500 : 0;
    }

    public function hasVariantTiers() {
        return $this->tierModel->hasVariantTiers();
    }

    public function rules($store, $product, $variant = 0) {
        return $this->tierModel->rules($store, $product, $variant);
    }

    public function rulesForProducts($store, $productIds) {
        return $this->tierModel->rulesForProducts($store, $productIds);
    }

    public function rulesForVariants($store, $product) {
        return $this->tierModel->rulesForVariants($store, $product);
    }

    public static function hasPromotion($row) {
        return !empty(self::snapshot($row)['promotion']);
    }

    public static function choose($rules, $quantity) {
        $selected = null;
        $quantity = round($quantity, 8);
        foreach ($rules as $rule) {
            if ($quantity > (float)$rule['quantity_above']
                && ($selected === null || (float)$rule['quantity_above'] > (float)$selected['quantity_above'])) $selected = $rule;
        }
        return $selected;
    }

    public static function stock($item) {
        if (isset($item['bundle_stock'])) {
            return $item['bundle_stock'];
        }
        $qty = max(1, (int)$item['quantity']);
        $stock = ['products' => [], 'finishings' => []];
        if ($item['product']['unit_type'] !== '~') {
            $stock['products'][(int)$item['product_id']] = round((float)$item['stok_butuh'] / $qty, 8);
        }
        foreach ($item['finishing_to_reduce'] as $f) {
            $stock['finishings'][(int)$f['finishing_id']] = round((float)$f['qty'] / $qty, 8);
        }
        return $stock;
    }

    public function variantMigrationData($row, $target, $variant, $discount) {
        $saved = self::snapshot($row);
        $data = $saved ?? ['rules' => [], 'measure' => self::measure($target), 'factor' => 1,
            'discount' => $discount, 'stock' => $this->legacyStock($row)];
        // Migrasi mengikuti satuan tujuan; nominal pada nota tetap dipertahankan.
        $targetMeasure = self::measure($target);
        if (!$saved || ($data['measure'] ?? null) !== $targetMeasure) {
            $data['measure'] = $targetMeasure;
            $data['factor'] = 1;
            if (in_array($targetMeasure, ['m2', 'cm2'], true) && preg_match('/^([\d.]+)x([\d.]+)$/i', $row['size'], $size)) {
                $data['factor'] = round((float)$size[1] * (float)$size[2], 8);
            }
        }
        if (self::isWeightMaterial($target) && ($weight = self::weightFromSize($row['size'])) > 0) {
            $data['factor'] = $weight;
        }
        $data['variant'] = (int)$variant['id'];
        unset($data['variant_tiers']);
        $data['normal'] = (float)$row['unit'];
        $data['base'] = (float)$variant['price'];
        $sources = $data['migration_product_ids'] ?? [];
        $sources[] = (int)$row['product_id'];
        $data['migration_product_ids'] = array_values(array_unique($sources));
        unset($data['stock']['products'][$row['product_id']]);
        if ($target['unit_type'] !== '~') {
            $targetRow = array_replace($row, ['product_id' => (int)$target['product_id']]);
            $data['stock']['products'][(int)$target['product_id']] = $this->legacyStock($targetRow)['products'][$target['product_id']] ?? 1;
        }
        return $data;
    }

    // Aturan disalin ke nota. Perubahan katalog tidak mengganti aturan nota yang sudah dimulai.
    public function preview(&$item, $product, $store, $input, $discount, $special = false) {
        $measure = self::measure($product);
        if (!$this->isAvailable()) {
            return;
        }
        $weightBased = self::isWeightMaterial($product) && (float)($input['kiloan'] ?? 0) > 0;
        $rows = $this->items($store, $item['order_id'], $item['product_id']);
        $migrated = null;
        $migrationSources = [];
        foreach ($rows as $row) if ((int)$row['order_item_id'] === (int)($input['order_item_id'] ?? 0)) {
            $saved = self::snapshot($row);
            if (!empty($saved['migration_product_ids'])) {
                $migrationSources = $saved['migration_product_ids'];
                if ((int)($row['product_variant_id'] ?? 0) === (int)($item['product_variant_id'] ?? 0)) {
                    $migrated = ['row' => $row, 'data' => $saved];
                    $discount = max(0, (float)($input['diskon'] ?? $saved['discount'] ?? 0));
                }
            }
        }
        $rows = array_values(array_filter($rows, function ($row) use ($item) {
            return (int)($row['product_variant_id'] ?? 0) === (int)($item['product_variant_id'] ?? 0);
        }));
        $first = $rows ? self::snapshot($rows[0]) : null;
        // Aturan pertama produk dalam nota dipertahankan, termasuk saat promo dihapus.
        $promotion = $migrated ? ($migrated['data']['promotion'] ?? null)
            : ($first ? ($first['promotion'] ?? null) : (new ProductPromotion($this->db))->rule($store, $item['product_id']));
        if ($promotion && ProductPromotion::eligible($product) && !$weightBased) {
            $data = ['rules' => [], 'promotion' => $promotion, 'measure' => 'pcs', 'factor' => 1,
                'normal' => round($item['unit'], 2), 'base' => (float)$product['price'],
                'discount' => (float)$discount, 'stock' => self::stock($item)];
            if ($minimum = self::minimumAmount($product)) {
                $data['minimum_amount'] = $minimum;
            }
            if ($migrationSources) {
                $data['migration_product_ids'] = $migrationSources;
            }
            foreach ($rows as $row) if ((int)$row['order_item_id'] === (int)($input['order_item_id'] ?? 0)
                && $row['size'] === $item['size'] && self::finishingKey($row['finishing']) === self::finishingKey($item['finishing_str'])
                && ($row['laser_product_id'] ?? null) == ($item['laser_product_id'] ?? null)
                && (int)($row['product_variant_id'] ?? 0) === (int)($item['product_variant_id'] ?? 0)
                && ($row['product_finishing_price_id'] ?? null) == ($item['product_finishing_price_id'] ?? null)) {
                $saved = self::snapshot($row);
                $data['base'] = $saved['base'];
                $data['normal'] = round(max(0, $saved['normal'] + ($saved['discount'] ?? 0) - $discount), 2);
            }
            $item['tier_price_data'] = json_encode($data, JSON_THROW_ON_ERROR);
            $item['unit'] = $data['normal'];
            $item['amount'] = self::amount($data, $item['unit'], $item['quantity']);
            $item['promotion'] = ProductPromotion::calculate($promotion, $item['quantity']);
            return;
        }
        $variantRules = !empty($item['variant']) ? ($first ? ($first['rules'] ?? []) : $this->rules($store, $item['product_id'], (int)$item['variant']['id'])) : null;
        $useVariantTiers = $variantRules && (!$first || !empty($first['variant_tiers'])) && $measure && !$special && !$weightBased;
        if (!empty($item['variant']) && !$useVariantTiers) {
            $data = ['rules' => [], 'variant' => (int)$item['variant']['id'], 'measure' => $measure, 'factor' => $weightBased ? (float)$input['kiloan'] : 1,
                'normal' => round($item['unit'], 2), 'base' => (float)$product['price'],
                'discount' => (float)$discount, 'stock' => self::stock($item)];
            if ($minimum = self::minimumAmount($product)) {
                $data['minimum_amount'] = $minimum;
            }
            if ($migrationSources) {
                $data['migration_product_ids'] = $migrationSources;
            }
            if ($migrated) {
                $old = $migrated['row'];
                $saved = $migrated['data'];
                if ($old['size'] === $item['size'] && self::finishingKey($old['finishing']) === self::finishingKey($item['finishing_str'])
                    && ($old['laser_product_id'] ?? null) == ($item['laser_product_id'] ?? null)
                    && ($old['product_finishing_price_id'] ?? null) == ($item['product_finishing_price_id'] ?? null)) {
                    $factor = $weightBased ? self::weightFromSize($old['size']) : (float)($saved['factor'] ?? 1);
                    $data['normal'] = round(max(0, $saved['normal'] + $factor * (($saved['discount'] ?? 0) - $discount)), 2);
                    $item['unit'] = $data['normal'];
                    $item['amount'] = self::amount($data, $item['unit'], $item['quantity']);
                }
            }
            $item['tier_price_data'] = json_encode($data, JSON_THROW_ON_ERROR);
            return;
        }
        if (!$measure || $special || $weightBased || ($first && !empty($first['promotion']))) {
            return;
        }
        $factor = in_array($measure, ['m2', 'cm2'], true) ? round((float)($input['panjang'] ?? 0) * (float)($input['lebar'] ?? 0), 8) : 1;
        if ($factor <= 0) {
            return;
        }
        $rules = $variantRules ?? ($first ? $first['rules'] : $this->rules($store, $item['product_id']));
        if (!$rules) {
            return;
        }
        $data = ['rules' => $rules, 'measure' => $measure, 'factor' => $factor,
            'normal' => round($item['unit'], 2), 'base' => (float)$product['price'],
            'discount' => (float)$discount, 'stock' => self::stock($item)];
        if ($useVariantTiers) { $data['variant'] = (int)$item['variant']['id']; $data['variant_tiers'] = true; }
        if ($migrationSources) {
            $data['migration_product_ids'] = $migrationSources;
        }
        if ($minimum = self::minimumAmount($product)) {
            $data['minimum_amount'] = $minimum;
        }
        $total = $factor * $item['quantity'];
        $editing = (int)($input['order_item_id'] ?? 0);
        foreach ($rows as $row) {
            $saved = self::snapshot($row);
            if ((int)$row['order_item_id'] === $editing) {
                if ($row['size'] === $item['size'] && self::finishingKey($row['finishing']) === self::finishingKey($item['finishing_str']) && abs($saved['factor'] - $factor) < 0.00001) {
                    $data['base'] = $saved['base'];
                    $data['normal'] = $saved['normal'] + $factor * (($saved['discount'] ?? 0) - $discount);
                }
            } else $total += (float)$saved['factor'] * (int)$row['quantity'];
        }
        $rule = self::choose($rules, $total);
        $item['tier_price_data'] = json_encode($data, JSON_THROW_ON_ERROR);
        $item['unit'] = self::unit($data, $rule);
        $item['amount'] = self::amount($data, $item['unit'], $item['quantity']);
        $item['tier_pricing'] = ['quantity' => round($total, 8), 'measure' => $measure, 'unit_label' => self::unitLabel($measure),
            'quantity_above' => $rule['quantity_above'] ?? null, 'price' => $rule['price'] ?? null];
    }

    public function handles($store, $input) {
        if (!$this->isAvailable()) {
            return false;
        }
        $product = (int)($input['product_id'] ?? 0);
        $order = (int)($input['order_id'] ?? 0);
        return (bool)(new ProductBundleController($this->db))->find($store, $product) || (bool)$this->rules($store, $product) || (bool)$this->items($store, $order, $product)
            || (bool)(new ProductPromotion($this->db))->rule($store, $product)
            || !empty($input['product_variant_id']) || (bool)(new ProductVariantController($this->db))->getByProduct($store, $product);
    }

    public function saveItem($store, $input, $prepare, $finish) {
        if (!$this->isAvailable()) {
            throw new InvalidArgumentException('Jalankan SQL product_price_tiers.sql terlebih dahulu.');
        }
        $order = (int)($input['order_id'] ?? 0);
        $id = (int)($input['order_item_id'] ?? 0);
        $driver = new mysqli_driver();
        $mode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->tierModel->beginTransaction();
        try {
            $this->lockOrder($store, $order);
            $old = $id ? $this->getItem($store, $order, $id) : null;
            $item = $prepare();
            if (isset($item['error'])) {
                throw new InvalidArgumentException($item['error']);
            }
            if ($item['product_id'] < 1 || trim($item['judul']) === '') {
                throw new InvalidArgumentException('Data item tidak lengkap.');
            }
            // Promo dihitung per baris. Dua input terpisah tidak digabung untuk mendapat bonus.
            if (!$old && !empty($item['tier_price_data']) && empty(self::snapshot($item)['promotion'])) {
                $data = self::snapshot($item);
                foreach ($this->items($store, $order, $item['product_id']) as $row) {
                    if ($row['judul'] === $item['judul'] && $row['size'] === $item['size']
                        && ($row['product_variant_data'] ?? null) === ($item['product_variant_data'] ?? null)
                        && self::finishingKey($row['finishing']) === self::finishingKey($item['finishing_str'])
                        && self::snapshot($row) == $data) {
                        $old = $row;
                        $id = (int)$row['order_item_id'];
                        $previousQuantity = $item['quantity'];
                        $item['quantity'] += (int)$row['quantity'];
                        $item['stok_butuh'] = $item['stok_butuh'] / $previousQuantity * $item['quantity'];
                        foreach ($item['finishing_to_reduce'] as &$finishing) {
                            $finishing['qty'] = $finishing['qty'] / $previousQuantity * $item['quantity'];
                        }
                        unset($finishing);
                        $item['amount'] = self::amount($data, $item['unit'], $item['quantity']);
                        break;
                    }
                }
            }
            $this->adjustStock($store, $old, $item);
            $itemData = $item;
            $itemData['unit'] = round($item['unit'], 2);
            $itemData['amount'] = round($item['amount'], 2);
            $this->tierModel->saveItem($store, $order, $id, $itemData, (bool)$old);
            $this->refresh($store, $order);
            $finish($order);
            $this->tierModel->commit();
        } catch (Throwable $e) {
            $this->tierModel->rollback();
            throw $e;
        } finally {
            mysqli_report($mode);
        }
        $this->notify($store, $order);
    }

    public function deleteItem($store, $row, $finish) {
        $driver = new mysqli_driver();
        $mode = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->tierModel->beginTransaction();
        try {
            $this->lockOrder($store, $row['order_id']);
            $old = $this->getItem($store, $row['order_id'], $row['order_item_id']);
            $this->adjustStock($store, $old, null);
            $this->tierModel->deleteItem($store, $old['order_item_id']);
            $this->refresh($store, $old['order_id']);
            $finish($old['order_id']);
            $this->tierModel->commit();
        } catch (Throwable $e) {
            $this->tierModel->rollback();
            throw $e;
        } finally {
            mysqli_report($mode);
        }
        $this->notify($store, $old['order_id']);
    }

    public static function describe($items) {
        $totals = [];
        foreach ($items as $row) if (($s = self::snapshot($row)) && !empty($s['rules']) && (empty($s['variant']) || !empty($s['variant_tiers']))) {
            $group = self::tierGroup($row, $s); $totals[$group] = ($totals[$group] ?? 0) + $s['factor'] * (int)$row['quantity'];
        }
        foreach ($items as &$row) {
            $row['bundle'] = !empty($row['tier_price_data']) ? (self::snapshot($row)['bundle'] ?? null) : null;
            $row['tier_pricing'] = null;
            $row['promotion'] = null;
            if ($s = self::snapshot($row)) {
                $row['diskon'] = $s['discount'] ?? 0;
                if (!empty($s['promotion'])) {
                    $row['promotion'] = ProductPromotion::calculate($s['promotion'], (int)$row['quantity']);
                }
                elseif (!empty($s['rules']) && (empty($s['variant']) || !empty($s['variant_tiers']))) {
                    $qty = round($totals[self::tierGroup($row, $s)], 8); $rule = self::choose($s['rules'], $qty);
                    $row['tier_pricing'] = ['quantity' => $qty, 'measure' => $s['measure'], 'unit_label' => self::unitLabel($s['measure']), 'quantity_above' => $rule['quantity_above'] ?? null, 'price' => $rule['price'] ?? null];
                }
            }
            unset($row['tier_price_data']);
        }
        unset($row);
        return ProductVariantController::describe($items);
    }

    private function items($store, $order, $product = 0) {
        return $this->tierModel->getItems($store, $order, $product);
    }

    private static function snapshot($row) {
        if (empty($row['tier_price_data'])) {
            return null;
        }
        $data = json_decode($row['tier_price_data'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || (empty($data['rules']) && empty($data['promotion']) && empty($data['variant']) && empty($data['bundle'])) || !isset($data['factor'], $data['normal'], $data['base'], $data['stock'])) {
            throw new RuntimeException('Data aturan harga pada item tidak lengkap.');
        }
        return $data;
    }

    private static function unit($data, $rule) {
        return round(max(0, (float)$data['normal'] + ($rule ? (float)$data['factor'] * ((float)$rule['price'] - (float)$data['base']) : 0)), 2);
    }

    private static function amount($data, $unit, $quantity) {
        if (!empty($data['promotion'])) {
            $quantity = ProductPromotion::calculate($data['promotion'], $quantity)['paid_quantity'];
        }
        return round(max($unit * $quantity, (float)($data['minimum_amount'] ?? 0)), 2);
    }

    private static function finishingKey($value) {
        $ids = ProductLaserConversionController::finishingIds($value);
        return implode(',', $ids);
    }

    private function lockOrder($store, $order) {
        if (!$this->tierModel->getOrder($store, $order)) {
            throw new InvalidArgumentException('Nota tidak ditemukan pada toko ini.');
        }
    }

    private function getItem($store, $order, $id) {
        $row = $this->tierModel->getItem($store, $order, $id);
        if (!$row) {
            throw new InvalidArgumentException('Item tidak ditemukan pada nota ini.');
        }

        return $row;
    }

    private function legacyStock($row) {
        $p = $this->tierModel->getProduct($row['store_id'], $row['product_id']);
        $stock = ['products' => [], 'finishings' => []];
        if (!$p) {
            return $stock;
        }
        $area = $length = 0;
        if (strtoupper(trim($p['category'] ?? '')) !== 'ANEKA CETAK' && preg_match('/^([\d.]+)x([\d.]+)$/i', $row['size'], $m)) {
            $length = (float)$m[1];
            $area = $length * (float)$m[2];
        }
        $package = !empty($row['product_finishing_price_id']);
        $factor = $area > 0 ? $area : 1;
        if ($package && !ProductFinishingPriceController::requiresDimensions($p)) {
            $factor = 1;
        }
        elseif (in_array(strtoupper($p['category'] ?? ''), ['DTF', 'DTF UV'], true) && $length > 0) $factor = $length;
        elseif (preg_match('/^([\d.]+) KG$/i', $row['size'], $m)) $factor = (float)$m[1];
        if ($p['unit_type'] !== '~') {
            $stock['products'][(int)$row['product_id']] = $factor;
        }
        foreach (ProductLaserConversionController::finishingIds($row['finishing']) as $id) {
            $f = $this->tierModel->getFinishing($row['store_id'], $id);
            if ($f && $f['unit_type'] !== '~') {
                $stock['finishings'][$id] = $package ? 1 : ($area > 0 ? $area : 1);
            }
        }
        return $stock;
    }

    private function adjustStock($store, $old, $item) {
        $delta = ['products' => [], 'finishings' => []];
        if ($old) {
            $saved = self::snapshot($old);
            $stock = $saved ? $saved['stock'] : $this->legacyStock($old);
            foreach (['products', 'finishings'] as $table) {
                foreach ($stock[$table] ?? [] as $id => $factor) {
                    $delta[$table][$id] = $factor * (int)$old['quantity'];
                }
            }
        }
        if ($item) {
            foreach (self::stock($item) as $table => $entries) {
                foreach ($entries as $id => $factor) {
                    $delta[$table][$id] = ($delta[$table][$id] ?? 0) - $factor * $item['quantity'];
                }
            }
        }
        foreach ($delta as $table => $entries) {
            ksort($entries, SORT_NUMERIC);
            foreach ($entries as $id => $value) {
                if (abs($value) < 0.000001) {
                    continue;
                }
                $row = $this->tierModel->getStock($store, $table, $id);
                if (!$row && $value > 0) {
                    continue;
                }
                if (!$row || ($value < 0 && (float)$row['stock'] + $value < -0.00001)) {
                    throw new InvalidArgumentException('Stok bahan atau finishing tidak mencukupi.');
                }
                $this->tierModel->updateStock($store, $table, $id, $value);
            }
        }
    }

    private function refresh($store, $order) {
        $groups = [];
        foreach ($this->items($store, $order) as $row) {
            $data = self::snapshot($row);
            if (!empty($data['promotion']) || (!empty($data['variant']) && empty($data['variant_tiers']))) {
                $amount = self::amount($data, $row['unit'], (int)$row['quantity']);
                $this->tierModel->updateItemAmount($store, $row['order_item_id'], $amount);
            } else $groups[self::tierGroup($row, $data)][] = $row;
        }
        foreach ($groups as $rows) {
            $total = 0;
            foreach ($rows as $row) {
                $total += self::snapshot($row)['factor'] * (int)$row['quantity'];
            }
            $rule = self::choose(self::snapshot($rows[0])['rules'], $total);
            foreach ($rows as $row) {
                $unit = self::unit(self::snapshot($row), $rule);
                $amount = self::amount(self::snapshot($row), $unit, (int)$row['quantity']);
                $this->tierModel->updateItemPricing($store, $row['order_item_id'], $unit, $amount);
            }
        }
    }

    private function notify($store, $order) {
        try {
            foreach (['orders', 'products', 'finishings'] as $module) {
                updateStoreCache($store, $module);
            }
            updateOrderTrigger($store, $order);
        } catch (Throwable $e) {
            error_log('Tier cache: ' . $e->getMessage());
        }
    }

    private static function tierGroup($row, $data) {
        return $row['product_id'] . ':' . (!empty($data['variant_tiers']) ? (int)($row['product_variant_id'] ?? $data['variant']) : 0);
    }

}
