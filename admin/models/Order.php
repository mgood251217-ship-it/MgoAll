<?php
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
        $stmt = $this->koneksi->prepare("UPDATE orders SET nomorator = ?, customer_name = ?, nomor = ?, deadline = ?, user_id = ?, store_id = ?, date = ?, system = ? WHERE order_id = ?");
        $stmt->bind_param("ssssiissi", $data->nomorator, $data->customer_name, $data->nomor, $data->deadline, $data->user_id, $data->store_id, $data->date, $data->system, $data->order_id);
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
        $stmt = $this->koneksi->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function getTotalById($id) {
        $stmt = $this->koneksi->prepare("SELECT total FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['total'] : '';
    }

    public function getNoteOrder($data) {
        $stmt = $this->koneksi->prepare("SELECT note FROM note_orders WHERE order_id = ? AND note_for = ? ORDER BY note_order_id DESC LIMIT 1");
        $stmt->bind_param("is", $data->order_id, $data->note_for);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getHistoryNameAndNomor($data) {
        $keyword = "%" . $data->name . "%";
        $stmt = $this->koneksi->prepare("SELECT DISTINCT customer_name AS name, nomor FROM orders WHERE store_id = ? AND customer_name LIKE ? LIMIT 10");
        $stmt->bind_param("is", $data->store_id, $keyword);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getLatestCustomerNote($order_id) {
        $stmt = $this->koneksi->prepare("SELECT note_order_id FROM note_orders WHERE order_id = ? AND note_for = 'CTM' ORDER BY note_order_id DESC LIMIT 1");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function updateNote($note_order_id, $note) {
        $stmt = $this->koneksi->prepare("UPDATE note_orders SET note = ? WHERE note_order_id = ?");
        $stmt->bind_param("si", $note, $note_order_id);
        return $stmt->execute();
    }

    public function createNote($order_id, $note) {
        $stmt = $this->koneksi->prepare("INSERT INTO note_orders (order_id, note, note_for) VALUES (?, ?, 'CTM')");
        $stmt->bind_param("is", $order_id, $note);
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
}