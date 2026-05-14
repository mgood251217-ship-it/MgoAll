<?php
require_once '../connect.php';

$order_ids = $_POST['order_ids'] ?? '';
$status    = $_POST['status'] ?? '';
$user_id   = $_POST['user_initial'] ?? 0;

if ($order_ids && $status) {
    // Convert ke array jika dikirim dalam format string koma
    if (!is_array($order_ids)) {
        $order_ids = explode(',', $order_ids);
    }

    // Waktu sekarang
    date_default_timezone_set('Asia/Jakarta');
    $tanggalSekarang = date('Y-m-d H:i:s');

    // Siapkan statement untuk mendapatkan status terakhir
    $stmtStatus = $koneksi->prepare("SELECT status FROM projects WHERE order_id = ? ORDER BY date DESC LIMIT 1");

    // Siapkan statement untuk insert ke projects
    // $stmtInsert = $koneksi->prepare("INSERT INTO projects (order_id, status, process, user_id, date) VALUES (?, ?, ?, ?, ?)");
    $stmtInsert = $koneksi->prepare("UPDATE projects SET status = ?, process = ?, user_id = ?, date = ? WHERE order_id = ?");

    foreach ($order_ids as $order_id) {
        $order_id = (int)$order_id;

        // Ambil status terakhir
        $status_terakhir = '';
        $stmtStatus->bind_param("i", $order_id);
        $stmtStatus->execute();
        $stmtStatus->bind_result($status_terakhir);
        $stmtStatus->fetch();
        $stmtStatus->reset();

        // Insert status baru
        $stmtInsert->bind_param("ssisi", $status_terakhir, $status, $user_id, $tanggalSekarang, $order_id);
        $stmtInsert->execute();
    }

    $stmtStatus->close();
    $stmtInsert->close();
}

// Redirect kembali ke halaman utama
header("Location: customer.php");
exit;
?>
