<?php
require_once __DIR__ . '/ProductFinishingPrice.php';
require_once __DIR__ . '/ProductPriceTier.php';
require_once __DIR__ . '/ProductLaserConversion.php';

use LDAP\Result;
class Order {
    private $koneksi;
    private $packagePrices;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->packagePrices = new ProductFinishingPrice($koneksi);
    }
 
    public function createOrder($data) {
        $stmt = $this->koneksi->prepare("INSERT INTO orders (store_id, nomorator, customer_name, nomor, total, deadline, user_id, system, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdssss", $data->store_id, $data->nomorator, $data->customer_name, $data->nomor, $data->total, $data->deadline, $data->user_id, $data->system, $data->date);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateOrder($data) {
        $stmt = $this->koneksi->prepare("UPDATE orders SET customer_name = ?, nomor = ?, deadline = ?, user_id = ?, store_id = ?, date = ?, system = ? WHERE order_id = ?");
        $stmt->bind_param("sssiissi", $data->customer_name, $data->nomor, $data->deadline, $data->user_id, $data->store_id, $data->date, $data->system, $data->order_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function deleteOrderDependencies($order_id) {
        $queries = [
            "DELETE FROM note_orders WHERE order_id = ?",
            "DELETE FROM diskon_order_items WHERE order_id = ?"
        ];
        foreach ($queries as $sql) {
            $stmt = $this->koneksi->prepare($sql);
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function archiveOrder($order, $administrator_id, $date) {
        $stmt = $this->koneksi->prepare("INSERT INTO deleted_orders (order_id, store_id, nomorator, nomor, customer_name, total, deadline, user_id, system, date, deleted_by, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssissis", $order['order_id'], $order['store_id'], $order['nomorator'], $order['nomor'], $order['customer_name'], $order['total'], $order['deadline'], $order['user_id'], $order['system'], $order['date'], $administrator_id, $date);
        $stmt->execute();
        $stmt->close();
    }

    private function itemColumns($table) {
        $stmt = $this->koneksi->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->bind_param('s', $table); $stmt->execute();
        $columns = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME'); $stmt->close();
        return $columns;
    }

    public function archiveOrderItems($order_id, $store = 0) {
        $fields = ['order_item_id', 'store_id', 'order_id', 'product_id', 'judul', 'finishing', 'size', 'quantity', 'unit', 'amount'];
        $source = $this->itemColumns('order_items');
        foreach (['maklun', 'product_variant_id', 'product_variant_data', 'tier_price_data', 'product_finishing_price_id', 'laser_product_id'] as $field) {
            if (in_array($field, $source, true)) $fields[] = $field;
        }
        if (array_diff($fields, $this->itemColumns('deleted_order_items'))) {
            throw new RuntimeException('Skema arsip item belum lengkap. Jalankan SQL archive_order_item_details.sql sebelum menghapus order.');
        }
        $columns = implode(', ', array_map(function ($field) { return "`$field`"; }, $fields));
        $where = 'order_id = ?' . ($store ? ' AND store_id = ?' : '');
        $stmt = $this->koneksi->prepare("INSERT INTO deleted_order_items ($columns) SELECT $columns FROM order_items WHERE $where");
        if ($store) $stmt->bind_param('ii', $order_id, $store);
        else $stmt->bind_param('i', $order_id);
        $stmt->execute(); $stmt->close();
    }

    public function getArchivedItems($store, $ids) {
        if (!$ids) return [];
        $columns = $this->itemColumns('deleted_order_items');
        $fields = ''; $joins = '';
        if (in_array('product_variant_id', $columns, true) && (new ProductVariant($this->koneksi))->isAvailable()) {
            $fields .= ', ' . ProductVariant::orderFields();
            $joins .= ProductVariant::orderJoins('i');
        }
        if (in_array('laser_product_id', $columns, true)) {
            $fields .= ', laser.name AS laser_material_name';
            $joins .= ' LEFT JOIN products laser ON laser.product_id = i.laser_product_id AND laser.store_id = i.store_id';
        } else $fields .= ', NULL AS laser_material_name';
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $order = in_array('deleted_order_item_id', $columns, true) ? 'i.deleted_order_item_id' : 'i.order_item_id';
        $stmt = $this->koneksi->prepare("SELECT i.*, i.judul AS product_name $fields,
            (SELECT GROUP_CONCAT(f.name ORDER BY f.finishing_id SEPARATOR ', ') FROM finishings f
                WHERE f.store_id = ? AND FIND_IN_SET(f.finishing_id, REPLACE(i.finishing, ' ', ''))) AS finishing_names
            FROM deleted_order_items i $joins WHERE i.store_id = ? AND i.order_id IN ($marks) ORDER BY $order");
        $params = array_merge([$store, $store], array_map('intval', $ids));
        $stmt->bind_param(str_repeat('i', count($params)), ...$params); $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    public function deleteOrderAndItems($order_id) {
        $stmt1 = $this->koneksi->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt1->bind_param("i", $order_id);
        $stmt1->execute();
        $stmt1->close();
        $stmt2 = $this->koneksi->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt2->bind_param("i", $order_id);
        $stmt2->execute();
        $stmt2->close();
    }

    public function getOrderById($id) {
        $stmt = $this->koneksi->prepare("SELECT o.*, u.initial AS operator_initial 
                                            FROM orders o
                                            JOIN users u ON o.user_id = u.user_id
                                            WHERE o.order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getOneValue($id, $column){
        $columnName = ['store_id', 'nomorator', 'nomor', 'customer_name', 'total', 'deadline', 'user_id', 'system', 'date'];
        if (!in_array($column, $columnName)) {
            return ''; 
        }
        $stmt = $this->koneksi->prepare("SELECT `{$column}` FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result[$column] : '';
    }

    public function getNoteOrder($data) {
        $stmt = $this->koneksi->prepare("SELECT * FROM note_orders WHERE order_id = ? AND note_for = ? ORDER BY note_order_id DESC LIMIT 1");
        $stmt->bind_param("is", $data->order_id, $data->note_for);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getHistoryNameAndNomor($data) {
        $keyword = "%" . $data->name . "%";
        $stmt = $this->koneksi->prepare("SELECT DISTINCT customer_name AS name, nomor FROM orders WHERE store_id = ? AND customer_name LIKE ? LIMIT 10");
        $stmt->bind_param("is", $data->store_id, $keyword);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getLatestCustomerNote($order_id) {
        $stmt = $this->koneksi->prepare("SELECT * FROM note_orders WHERE order_id = ? AND note_for = 'CTM' ORDER BY note_order_id DESC LIMIT 1");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ?? [];
    }

    public function updateNote($note_order_id, $note) {
        $stmt = $this->koneksi->prepare("UPDATE note_orders SET note = ? WHERE note_order_id = ?");
        $stmt->bind_param("si", $note, $note_order_id);
        return $stmt->execute();
    }

    public function updateNoteAndSession($note_order_id, $session, $note) {
        $stmt = $this->koneksi->prepare("UPDATE note_orders SET note = ?, session = ? WHERE note_order_id = ?");
        $stmt->bind_param("sii", $note, $session, $note_order_id);
        return $stmt->execute();
    }

    public function createNote($order_id, $note, $note_for) {
        $stmt = $this->koneksi->prepare("INSERT INTO note_orders (order_id, note, note_for) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $order_id, $note, $note_for);
        return $stmt->execute();
    }

    public function getOrderItem($order_item_id, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT * FROM order_items WHERE order_item_id = ? AND store_id = ?");
        $stmt->bind_param("ii", $order_item_id, $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function deleteOrderItem($order_item_id, $store_id) {
        $stmt = $this->koneksi->prepare("DELETE FROM order_items WHERE order_item_id = ? AND store_id = ?");
        $stmt->bind_param("ii", $order_item_id, $store_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateMaklun($data){
        $stmt = $this->koneksi->prepare("UPDATE order_items SET maklun = ? WHERE order_item_id = ?");
        $stmt->bind_param("ii", $data->store_id_maklun, $data->order_item_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getOrderItemsWithDetails($order_id) {
        $variants = new ProductVariant($this->koneksi);
        $variantFields = $variants->isAvailable() ? ', ' . ProductVariant::orderFields() : '';
        $variantJoins = $variants->isAvailable() ? ProductVariant::orderJoins() : '';
        $hasMaterial = (new ProductLaserConversion($this->koneksi))->hasOrderMaterials();
        $materialName = $hasMaterial ? 'laser.name AS laser_material_name' : 'NULL AS laser_material_name';
        $materialJoin = $hasMaterial ? 'LEFT JOIN products laser ON laser.product_id = oi.laser_product_id AND laser.store_id = oi.store_id' : '';
        $stmt = $this->koneksi->prepare("
            SELECT 
                oi.*, $materialName$variantFields,
                COALESCE(p.name, oi.judul) AS product_name,
                p.category_id,
                c.name AS category, 
                p.unit_type, 
                p.price, 
                UPPER(COALESCE(c.name, '')) AS category,
                COALESCE(doi.diskon, 0) AS diskon,
                COALESCE(s.name, '') AS maklun_store,
                COALESCE(
                    (SELECT GROUP_CONCAT(f.name SEPARATOR ' ') 
                     FROM finishings f
                     WHERE FIND_IN_SET(f.finishing_id, REPLACE(oi.finishing, ' ', '')) > 0
                    ), '-'
                ) AS finishing_names
            FROM order_items oi
            $materialJoin $variantJoins
            LEFT JOIN stores s ON oi.maklun = s.store_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN diskon_order_items doi ON doi.order_id = oi.order_id AND doi.product_id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function cekOrderItem($order_id, $judul, $finishing, $size, $package_id = null, $product_id = 0, $unit = 0, $match_price = false, $laser_product_id = null){
        $query = "SELECT order_item_id, quantity, unit, amount FROM order_items WHERE order_id = ? AND judul = ? AND finishing = ? AND size = ?";
        if ((new ProductPriceTier($this->koneksi))->isAvailable()) $query .= ' AND tier_price_data IS NULL';
        $types = 'isss';
        $params = [$order_id, $judul, $finishing, $size];
        if ($package_id) {
            // Harga paket yang sudah tersimpan tidak ditimpa saat daftar harga berubah.
            $query .= " AND product_finishing_price_id = ? AND product_id = ? AND unit = ?";
            $types .= 'iid';
            array_push($params, $package_id, $product_id, round($unit, 2));
        } else {
            if ($this->packagePrices->isAvailable()) $query .= " AND product_finishing_price_id IS NULL";
            if ($match_price) {
                $query .= ' AND product_id = ? AND unit = ?';
                $types .= 'id';
                array_push($params, $product_id, round($unit, 2));
            }
        }
        if ((new ProductLaserConversion($this->koneksi))->hasOrderMaterials()) {
            // Bahan berbeda selalu menjadi baris terpisah, termasuk bila harganya sama.
            $query .= ' AND laser_product_id <=> ?';
            $types .= 'i';
            $params[] = $laser_product_id;
        }
        $stmt = $this->koneksi->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function createOrderItem($data){
        if (!empty($data->laser_product_id) && (new ProductLaserConversion($this->koneksi))->hasOrderMaterials()) {
            $stmt = $this->koneksi->prepare("INSERT INTO order_items (store_id, order_id, product_id, judul, size, quantity, unit, amount, finishing, laser_product_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiississsi', $data->store_id, $data->order_id, $data->product_id, $data->judul, $data->size, $data->quantity, $data->unit, $data->amount, $data->finishing_str, $data->laser_product_id);
        } elseif (!empty($data->product_finishing_price_id)) {
            $stmt = $this->koneksi->prepare("INSERT INTO order_items (store_id, order_id, product_id, judul, size, quantity, unit, amount, finishing, product_finishing_price_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiississsi", $data->store_id, $data->order_id, $data->product_id, $data->judul, $data->size, $data->quantity, $data->unit, $data->amount, $data->finishing_str, $data->product_finishing_price_id);
        } else {
            $stmt = $this->koneksi->prepare("INSERT INTO order_items (store_id, order_id, product_id, judul, size, quantity, unit, amount, finishing) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $types = 'iiississs';
            $stmt->bind_param($types, $data->store_id, $data->order_id, $data->product_id, $data->judul, $data->size, $data->quantity, $data->unit, $data->amount, $data->finishing_str);
        }
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateOrderItem($data) {
        $stmt = $this->koneksi->prepare("UPDATE order_items SET quantity = ?, unit = ?, amount = ? WHERE order_item_id = ?");
        $stmt->bind_param("issi", $data->quantity, $data->unit, $data->amount, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateOrderTotal($id, $value) {
        $stmt = $this->koneksi->prepare("UPDATE orders SET total = ? WHERE order_id = ?");
        $stmt->bind_param("si", $value, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function checkDiscount($order_id, $product_id) {
        $stmt = $this->koneksi->prepare("SELECT 1 FROM diskon_order_items WHERE order_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $order_id, $product_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
    public function updateDiscount($order_id, $product_id, $value) {
        $stmt = $this->koneksi->prepare("UPDATE diskon_order_items SET diskon = ? WHERE order_id = ? AND product_id = ?");
        $stmt->bind_param("sii", $value, $order_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }

    public function createDiscount( $order_id, $product_id, $value ) {
        $stmt = $this->koneksi->prepare("INSERT INTO diskon_order_items (order_id, product_id, diskon) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $order_id, $product_id, $value);
        $stmt->execute();
        $stmt->close();
    }

    public function getDiscount($order_id, $product_id) {
        $stmt = $this->koneksi->prepare("SELECT diskon FROM diskon_order_items WHERE order_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $order_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['diskon'] : 0;
    }

    public function getFilteredOrders($is_all_access, $search_text, $store_id, $customerLimit, $start_date, $end_date, $system, $categories = []) {
        $where = 'store_id = ? AND date BETWEEN ? AND ?';
        $params = [$store_id, $start_date, $end_date];
        $types = 'iss';

        if (!$is_all_access) {
            $where .= ' AND system = ?';
            $params[] = $system;
            $types .= 's';
        }
        if ($search_text !== '') {
            $where .= ' AND (customer_name LIKE ? OR nomorator LIKE ?)';
            $params[] = "%$search_text%";
            $params[] = "%$search_text%";
            $types .= 'ss';
        }
        if ($categories) {
            $marks = implode(',', array_fill(0, count($categories), '?'));
            // Pilih order yang memiliki minimal satu item cocok; detail item tetap utuh.
            $where .= " AND EXISTS (
                SELECT 1 FROM order_items oi
                JOIN products p ON p.product_id = oi.product_id AND p.store_id = oi.store_id
                JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id
                WHERE oi.order_id = orders.order_id AND oi.store_id = orders.store_id
                    AND UPPER(TRIM(c.name)) IN ($marks)
            )";
            $params = array_merge($params, $categories);
            $types .= str_repeat('s', count($categories));
        }

        $query = "SELECT * FROM orders WHERE $where ORDER BY order_id DESC";
        if ($search_text === '' && $customerLimit > 0) {
            if ($is_all_access) {
                $query = "(SELECT * FROM orders WHERE $where AND system = 'OFFLINE' ORDER BY order_id DESC LIMIT ?)
                          UNION ALL
                          (SELECT * FROM orders WHERE $where AND system = 'ONLINE' ORDER BY order_id DESC LIMIT ?)";
                $params = array_merge($params, [$customerLimit], $params, [$customerLimit]);
                $types = $types . 'i' . $types . 'i';
            } else {
                $query .= ' LIMIT ?';
                $params[] = $customerLimit;
                $types .= 'i';
            }
        }

        $stmt = $this->koneksi->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getDetailedOrderByIntervalDate($store_id, $start_date, $end_date){
        $variants = new ProductVariant($this->koneksi);
        $variantFields = $variants->isAvailable() ? ', ' . ProductVariant::orderFields() : '';
        $variantJoins = $variants->isAvailable() ? ProductVariant::orderJoins('i') : '';
        $hasMaterial = (new ProductLaserConversion($this->koneksi))->hasOrderMaterials();
        $materialName = $hasMaterial ? 'laser.name AS laser_material_name' : 'NULL AS laser_material_name';
        $materialJoin = $hasMaterial ? 'LEFT JOIN products laser ON laser.product_id = i.laser_product_id AND laser.store_id = i.store_id' : '';
        $stmt = $this->koneksi->prepare("SELECT i.*, $materialName$variantFields, o.nomorator, o.customer_name, o.date, o.order_id, p.price, COALESCE(p.name, i.judul) AS product_name,
                COALESCE(
                    (SELECT GROUP_CONCAT(f.name SEPARATOR ' ') 
                     FROM finishings f
                     WHERE FIND_IN_SET(f.finishing_id, REPLACE(i.finishing, ' ', '')) > 0
                    ), '-'
                ) AS finishing_names
                FROM order_items i
                $materialJoin $variantJoins
                INNER JOIN orders o ON i.order_id = o.order_id
                LEFT JOIN products p ON i.product_id = p.product_id
                WHERE o.store_id = ? AND o.date BETWEEN ? AND ?
                ORDER BY o.customer_name DESC");
        $stmt->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result ?? [];
    }

    public function getOrderArchive($store_id, $start_date, $end_date) {
        $stmt = $this->koneksi->prepare("
                SELECT
                    do.*,
                    doi.*,
                    a.name AS deleted_by_name,
                    COALESCE(
                        (
                            SELECT GROUP_CONCAT(f.name SEPARATOR ' ')
                            FROM finishings f
                            WHERE FIND_IN_SET(f.finishing_id, REPLACE(doi.finishing, ' ', '')) > 0
                        ),
                        '-'
                    ) AS finishing_names
                FROM deleted_orders do
                LEFT JOIN deleted_order_items doi
                    ON doi.order_id = do.order_id
                LEFT JOIN administrator a
                    ON a.administrator_id = do.deleted_by
                WHERE do.store_id = ?
                AND do.deleted_at BETWEEN ? AND ?
                ORDER BY do.deleted_at DESC, doi.deleted_order_item_id;");
        $stmt->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result ?? [];
    }

    public function getOrderIdsByIntervalDate($store_id, $start_date, $end_date){
        $stmt = $this->koneksi->prepare("SELECT order_id FROM orders WHERE store_id = ? AND date BETWEEN ? AND ?");
        $stmt->bind_param("iss", $store_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $order_ids = array_column($result, 'order_id');
        return $order_ids;
    }
}
