<?php

class ProductPromotion {
    private $db;
    private $ready;

    public function __construct($db) { $this->db = $db; }

    public function isAvailable() {
        if ($this->ready === null) {
            $row = $this->db->query("SELECT EXISTS(SELECT 1 FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_promotions') AS ready")->fetch_assoc();
            $this->ready = (bool)$row['ready'];
        }
        return $this->ready;
    }

    public static function eligible($product) {
        $timeBased = strtoupper(trim($product['category'] ?? '')) === 'JASA'
            && in_array(strtoupper(trim($product['name'] ?? '')), ['SETTING', 'POTONG AKRILIK'], true);
        return !$timeBased && in_array(strtoupper(trim($product['unit_type'] ?? '')), ['PCS', '~'], true);
    }

    public function rule($store, $product) {
        if (!$this->isAvailable()) return null;
        $stmt = $this->db->prepare('SELECT buy_quantity, free_quantity FROM product_promotions WHERE store_id = ? AND product_id = ?');
        $stmt->bind_param('ii', $store, $product); $stmt->execute();
        $rule = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $rule ?: null;
    }

    // Quantity adalah semua barang yang diterima, termasuk bonus. Sisa bonus pada
    // kelompok terakhir juga gratis: beli 10 gratis 2, input 11 berarti bayar 10.
    public static function calculate($rule, $quantity) {
        $buy = (int)($rule['buy_quantity'] ?? 0); $free = (int)($rule['free_quantity'] ?? 0);
        $quantity = (int)$quantity;
        if ($buy < 1 || $free < 1 || $buy > 1000000 || $free > 1000000 || $quantity < 1) throw new InvalidArgumentException('Aturan atau jumlah promo tidak valid.');
        $group = $buy + $free;
        $bonus = intdiv($quantity, $group) * $free + max(0, $quantity % $group - $buy);
        return ['buy_quantity' => $buy, 'free_quantity' => $free, 'quantity' => $quantity,
            'paid_quantity' => $quantity - $bonus, 'bonus_quantity' => $bonus];
    }
}
