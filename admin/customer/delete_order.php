<?php
require_once '../connect.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/session.php';

header('Content-Type: application/json');

$date = date("Y-m-d H:i:s");

if (!isset($_POST['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID order tidak ditemukan.']);
    exit;
}elseif (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Kesalahan Login Administrator']);
    exit;
}

$administrator_id = startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']);



$order_id = (int) $_POST['order_id'];
$keterangan = isset($_POST['keterangan_hapus']) ? trim($_POST['keterangan_hapus']) : '';


// Mulai transaksi
$koneksi->begin_transaction();



try {

    // Hapus pembayaran (payment)
    $stmt_payment = $koneksi->prepare("
        DELETE p FROM payment p
        JOIN orders o ON p.order_id = o.order_id
        WHERE p.order_id = ? AND o.store_id = ?
    ");
    $stmt_payment->bind_param("ii", $order_id, $store_id);
    $stmt_payment->execute();
    $stmt_payment->close();

    // Hapus project (projects)
    $stmt_projects = $koneksi->prepare("
        DELETE pr FROM projects pr
        JOIN orders o ON pr.order_id = o.order_id
        WHERE pr.order_id = ? AND o.store_id = ?
    ");
    $stmt_projects->bind_param("ii", $order_id, $store_id);
    $stmt_projects->execute();
    $stmt_projects->close();

    // Hapus notes (note)
    $stmt_projects = $koneksi->prepare("
        DELETE no FROM note_orders no
        JOIN orders o ON no.order_id = o.order_id
        WHERE no.order_id = ? AND o.store_id = ?
    ");
    $stmt_projects->bind_param("ii", $order_id, $store_id);
    $stmt_projects->execute();
    $stmt_projects->close();

    // Hapus diskon (orders)
    $stmt_projects = $koneksi->prepare("
        DELETE nd FROM diskon_order_items nd
        JOIN orders o ON nd.order_id = o.order_id
        WHERE nd.order_id = ? AND o.store_id = ?
    ");
    $stmt_projects->bind_param("ii", $order_id, $store_id);
    $stmt_projects->execute();
    $stmt_projects->close();

    $stmtOrder = $koneksi->prepare("SELECT customer_name, nomorator FROM orders WHERE order_id = ?");
    $stmtOrder->bind_param("i", $order_id);
    $stmtOrder->execute();
    $resultOrder = $stmtOrder->get_result();
    $order = $resultOrder->fetch_assoc();
    $orderName = $order['customer_name'];
    $orderNomorator = $order['nomorator'];

    $title = "HAPUS ORDER";
    $message = "HAPUS ORDERAN DENGAN NAMA " . $orderName . " NOMORATOR " . $orderNomorator;
    $done = 0;

    $insert = $koneksi->prepare("
                        INSERT INTO activity
                        (store_id, title, message, information, date, order_id, done, administrator_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param("issssiii", $store_id, $title, $message, $keterangan, $date, $order_id, $done, $administrator_id);
    $insert->execute();
    $insert->close();

    $stmtOrder = $koneksi->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
    $stmtOrder->bind_param("i", $order_id);
    $stmtOrder->execute();
    $resultOrder = $stmtOrder->get_result();
    $orderd = $resultOrder->fetch_assoc();
    $tanggalAja = date('Y-m-d', strtotime($orderd['date']));
    $o_order_id = $orderd['order_id'];
    $o_store_id = $orderd['store_id'];
    $o_nomorator = $orderd['nomorator'];
    $o_nomor = $orderd['nomor'];
    $o_customer_name = $orderd['customer_name'];
    $o_total = $orderd['total'];
    $o_deadline = $orderd['deadline'];
    $o_user_id = $orderd['user_id'];
    $o_system = $orderd['system'];
    $o_date = $orderd['date'];

    refreshFinance($store_id, $tanggalAja);


    $stmt = $koneksi->prepare("
        INSERT INTO deleted_orders
        (order_id, store_id, nomorator, nomor, customer_name, total, deadline, user_id, system, date, deleted_by, deleted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisssisissis", $o_order_id, $o_store_id, $o_nomorator, $o_nomor, $o_customer_name, $o_total, $o_deadline, $o_user_id, $o_system, $o_date, $administrator_id, $date);
    $stmt->execute();
    $stmt->close();

    $stmt = $koneksi->prepare("
        SELECT * FROM order_items
        WHERE order_id = ? AND store_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $store_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($item = $result->fetch_assoc()) {

        $stmt_insert = $koneksi->prepare("
            INSERT INTO deleted_order_items
            (order_item_id, store_id, order_id, product_id, judul, finishing, size, quantity, unit, amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt_insert->bind_param(
            "iiiisssiii",
            $item['order_item_id'],
            $item['store_id'],
            $item['order_id'],
            $item['product_id'],
            $item['judul'],
            $item['finishing'],
            $item['size'],
            $item['quantity'],
            $item['unit'],
            $item['amount']
        );

        $stmt_insert->execute();
        $stmt_insert->close();
    }
    $stmt->close();

    // Hapus semua item order
    $stmt_items = $koneksi->prepare("
        DELETE oi FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.order_id = ? AND o.store_id = ?
    ");
    $stmt_items->bind_param("ii", $order_id, $store_id);
    $stmt_items->execute();
    $stmt_items->close();

    // Hapus order utama
    $stmt_order = $koneksi->prepare("
        DELETE FROM orders WHERE order_id = ? AND store_id = ?
    ");
    $stmt_order->bind_param("ii", $order_id, $store_id);
    $stmt_order->execute();
    $stmt_order->close();

    // Commit jika semua berhasil
    $koneksi->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $koneksi->rollback();
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus order.']);
}
