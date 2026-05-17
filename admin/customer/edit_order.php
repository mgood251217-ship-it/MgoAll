<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $nomorator = trim($_POST['nomorator'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $nomor = trim(($_POST['nomor'] ?? 0));
    $date = trim($_POST['date']);
    $deadline_input = $_POST['deadline'] ?? '';
    $deadline = date('Y-m-d H:i:s', strtotime($deadline_input));
    $user_id = (int)($_POST['user_id'] ?? 0);
    $system = trim($_POST['sistem'] ?? '');

    if ($order_id === 0 || $store_id === 0 || $user_id === 0 || empty($nomorator) || empty($customer_name)) {
        $_SESSION['error'] = "Data tidak lengkap.";
        header("Location: customer.php");
        exit;
    }

    // Validasi apakah user_id milik store_id yang sama
    $stmt = $koneksi->prepare("SELECT user_id FROM users WHERE user_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $user_id, $store_id);
    $stmt->execute();
    $validUser = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$validUser) {
        $_SESSION['error'] = "Operator tidak valid.";
        header("Location: customer.php");
        exit;
    }

    // Update data order
    $stmt = $koneksi->prepare("UPDATE orders SET nomorator = ?, customer_name = ?, nomor = ?, deadline = ?, user_id = ?, store_id = ?, date = ?, system = ? WHERE order_id = ?");
    $stmt->bind_param("ssssiissi", $nomorator, $customer_name, $nomor, $deadline, $user_id, $store_id, $date, $system, $order_id);

    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Order berhasil diperbarui.";
    } else {
        $_SESSION['error'] = "Gagal memperbarui order: " . $stmt->error;
    }

    $stmt->close();
    header("Location: customer.php");
    exit;
} else {
    header("Location: customer.php");
    exit;
}
?>