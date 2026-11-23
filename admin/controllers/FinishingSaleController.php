<?php
require_once __DIR__ . '/../models/ProductFinishing.php';
require_once __DIR__ . '/../models/Order.php';

class FinishingSaleController {
    private $db;
    public function __construct($db) { $this->db = $db; }

    public function prepare($store, $input) {
        $ready = $this->db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='product_id'")->fetch_assoc();
        if (($ready['IS_NULLABLE'] ?? '') !== 'YES') throw new InvalidArgumentException('Jalankan SQL standalone_finishing_sales.sql terlebih dahulu.');
        $id = filter_var($input['finishing_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qty = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if (!$id || !$qty) throw new InvalidArgumentException('Pilih finishing dan isi jumlah dengan benar.');
        $rows = (new ProductFinishing($this->db))->finishingsByIds($store, [$id]);
        if (!$rows) throw new InvalidArgumentException('Finishing tidak tersedia pada toko ini.');
        $finishing = $rows[0];
        $unitType = strtoupper(trim($finishing['unit_type']));
        $factor = 1; $size = '-';
        $length = (float)($input['panjang'] ?? 0); $width = (float)($input['lebar'] ?? 0);
        if (in_array($unitType, ['M2', 'CM2'], true)) {
            if (!is_finite($length) || !is_finite($width) || $length <= 0 || $width <= 0) throw new InvalidArgumentException('Isi panjang dan lebar lebih dari 0.');
            $factor = $length * $width; $size = $length . 'x' . $width;
        } elseif (in_array($unitType, ['M', 'CM'], true)) {
            if (!is_finite($length) || $length <= 0) throw new InvalidArgumentException('Isi panjang lebih dari 0.');
            $factor = $length; $size = $length . ' ' . $unitType;
        } elseif (!in_array($unitType, ['PCS', '~'], true)) {
            throw new InvalidArgumentException('Satuan finishing ini belum mendukung penjualan terpisah.');
        }
        $price = (float)$finishing['price'];
        $old = !empty($input['order_item_id']) ? (new Order($this->db))->getOrderItem((int)$input['order_item_id'], $store) : null;
        if ($old && (int)$old['order_id'] === (int)($input['order_id'] ?? 0) && $old['size'] === $size) {
            $saved = json_decode($old['tier_price_data'] ?? '', true);
            $previous = $saved['standalone_finishing'] ?? [];
            if ((int)($previous['id'] ?? 0) === $id && ($previous['unit_type'] ?? '') === $unitType) $price = (float)$previous['price'];
        }
        $discount = moneyInputDecimal(($input['diskon'] ?? '') === '' ? 0 : ($input['diskon'] ?? 0));
        if ($discount === null || (float)$discount < 0) throw new InvalidArgumentException('Diskon tidak valid.');
        $unit = round(max(0, $price * $factor - (float)$discount), 2);
        $amount = round($unit * $qty, 2);
        if (!is_finite($amount) || $amount > 9999999999999.99) throw new InvalidArgumentException('Jumlah atau ukuran terlalu besar.');
        $snapshot = ['rules' => [], 'factor' => $factor, 'normal' => $unit, 'base' => $price, 'discount' => (float)$discount,
            'stock' => ['products' => [], 'finishings' => $unitType === '~' ? [] : [$id => $factor]],
            'standalone_finishing' => ['id' => $id, 'name' => $finishing['name'], 'unit_type' => $unitType, 'price' => $price]];
        return ['item_type' => 'finishing', 'order_id' => (int)($input['order_id'] ?? 0), 'product_id' => null,
            'product' => ['unit_type' => '~'], 'judul' => $finishing['name'], 'size' => $size, 'quantity' => $qty,
            'stok_butuh' => 0, 'finishing_str' => (string)$id,
            'finishing_to_reduce' => $unitType === '~' ? [] : [['finishing_id' => $id, 'qty' => $factor * $qty]],
            'unit' => $unit, 'amount' => $amount, 'product_finishing_price_id' => null,
            'laser_product_id' => null, 'tier_price_data' => json_encode($snapshot, JSON_THROW_ON_ERROR)];
    }
}
