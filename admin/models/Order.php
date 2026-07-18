<?php

use LDAP\Result;
class Order {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
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
        $stmt->bind_param("iisssisissis", $order['order_id'], $order['store_id'], $order['nomorator'], $order['nomor'], $order['customer_name'], $order['total'], $order['deadline'], $order['user_id'], $order['system'], $order['date'], $administrator_id, $date);
        $stmt->execute();
        $stmt->close();
    }

    public function archiveOrderItems($order_id) {
        $stmtItems = $this->koneksi->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmtItems->bind_param("i", $order_id);
        $stmtItems->execute();
        $resultItems = $stmtItems->get_result();
        $stmtItems->close();

        $stmtInsert = $this->koneksi->prepare("INSERT INTO deleted_order_items (order_item_id, store_id, order_id, product_id, judul, finishing, size, quantity, unit, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        while ($item = $resultItems->fetch_assoc()) {
            $stmtInsert->bind_param("iiiisssiii", $item['order_item_id'], $item['store_id'], $item['order_id'], $item['product_id'], $item['judul'], $item['finishing'], $item['size'], $item['quantity'], $item['unit'], $item['amount']);
            $stmtInsert->execute();
        }
        $stmtInsert->close();
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
        $stmt = $this->koneksi->prepare("
            SELECT 
                oi.*, 
                p.name AS product_name, 
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

    public function cekOrderItem($order_id, $judul, $finishing, $size){
        $stmt = $this->koneksi->prepare("SELECT order_item_id, quantity, unit, amount FROM order_items WHERE order_id = ? AND judul = ? AND finishing = ? AND size = ?");
        $stmt->bind_param("isss", $order_id, $judul, $finishing, $size);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function createOrderItem($data){
        $stmt = $this->koneksi->prepare("INSERT INTO order_items (store_id, order_id, product_id, judul, size, quantity, unit, amount, finishing) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssiiis", $data->store_id, $data->order_id, $data->product_id, $data->judul, $data->size, $data->quantity, $data->unit, $data->amount, $data->finishing_str);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateOrderItem($data) {
        $stmt = $this->koneksi->prepare("UPDATE order_items SET quantity = ?, unit = ?, amount = ? WHERE order_item_id = ?");
        $stmt->bind_param("iddi", $data->quantity, $data->unit, $data->amount, $data->id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function updateOrderTotal($id, $value) {
        $stmt = $this->koneksi->prepare("UPDATE orders SET total = ? WHERE order_id = ?");
        $stmt->bind_param("ii", $value, $id);
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
        $stmt->bind_param("iii", $value, $order_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }

    public function createDiscount( $order_id, $product_id, $value ) {
        $stmt = $this->koneksi->prepare("INSERT INTO diskon_order_items (order_id, product_id, diskon) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $order_id, $product_id, $value);
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

    public function getFilteredOrders($is_all_access, $search_text, $store_id, $customerLimit, $start_date, $end_date, $system) {
        if ($is_all_access) {
            if ($search_text !== '') {
                $query = "SELECT * FROM orders WHERE store_id = ? AND (customer_name LIKE ? OR nomorator LIKE ?) AND date BETWEEN ? AND ? ORDER BY order_id DESC";
                $params = [$store_id, "%$search_text%", "%$search_text%", $start_date, $end_date];
                $types = "issss";
            } else {
                if ($customerLimit > 0) {
                    $query = "(SELECT * FROM orders WHERE store_id = ? AND system = 'OFFLINE' AND date BETWEEN ? AND ? ORDER BY order_id DESC LIMIT ?)
                              UNION ALL
                              (SELECT * FROM orders WHERE store_id = ? AND system = 'ONLINE' AND date BETWEEN ? AND ? ORDER BY order_id DESC LIMIT ?)";
                    $params = [$store_id, $start_date, $end_date, $customerLimit, $store_id, $start_date, $end_date, $customerLimit];
                    $types = "issiissi";
                } else {
                    $query = "SELECT * FROM orders WHERE store_id = ? AND date BETWEEN ? AND ? ORDER BY order_id DESC";
                    $params = [$store_id, $start_date, $end_date];
                    $types = "iss";
                }
            }
        } else {
            if ($search_text !== '') {
                $query = "SELECT * FROM orders WHERE store_id = ? AND system = ? AND (customer_name LIKE ? OR nomorator LIKE ?) AND date BETWEEN ? AND ? ORDER BY order_id DESC";
                $params = [$store_id, $system, "%$search_text%", "%$search_text%", $start_date, $end_date];
                $types = "isssss";
            } else {
                if ($customerLimit > 0) {
                    $query = "SELECT * FROM orders WHERE store_id = ? AND system = ? AND date BETWEEN ? AND ? ORDER BY order_id DESC LIMIT ?";
                    $params = [$store_id, $system, $start_date, $end_date, $customerLimit];
                    $types = "isssi"; 
                } else {
                    $query = "SELECT * FROM orders WHERE store_id = ? AND system = ? AND date BETWEEN ? AND ? ORDER BY order_id DESC";
                    $params = [$store_id, $system, $start_date, $end_date];
                    $types = "isss";
                }
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
        $stmt = $this->koneksi->prepare("SELECT i.*, o.nomorator, o.customer_name, o.date, o.order_id, p.price, p.name AS product_name,
                COALESCE(
                    (SELECT GROUP_CONCAT(f.name SEPARATOR ' ') 
                     FROM finishings f
                     WHERE FIND_IN_SET(f.finishing_id, REPLACE(i.finishing, ' ', '')) > 0
                    ), '-'
                ) AS finishing_names
                FROM order_items i
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