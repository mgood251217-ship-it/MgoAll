<?php

function updateStatusPembayaranTerbaru(mysqli $koneksi, int $order_id): void {
    // Cek total pembayaran dari tabel payment
    $stmtPayment = $koneksi->prepare("SELECT SUM(nominal) as total_bayar FROM payment WHERE order_id = ?");
    $stmtPayment->bind_param("i", $order_id);
    $stmtPayment->execute();
    $resultPayment = $stmtPayment->get_result();
    $total_bayar = 0;
    if ($rowPayment = $resultPayment->fetch_assoc()) {
        $total_bayar = (float)$rowPayment['total_bayar'];
    }
    $stmtPayment->close();

    // Ambil total harga order dari tabel orders
    $stmtOrder = $koneksi->prepare("SELECT total FROM orders WHERE order_id = ?");
    $stmtOrder->bind_param("i", $order_id);
    $stmtOrder->execute();
    $stmtOrder->bind_result($total_order);
    $stmtOrder->fetch();
    $stmtOrder->close();

    // Tentukan status pembayaran
    $status_bayar = ($total_bayar >= $total_order) ? 'LUNAS' : 'DP';

    // Update status pembayaran pada entry terbaru
    $stmtUpdatePayment = $koneksi->prepare("
        UPDATE payment 
        SET status = ? 
        WHERE payment_id = (
            SELECT payment_id FROM (
                SELECT payment_id FROM payment WHERE order_id = ? ORDER BY date DESC LIMIT 1
            ) AS subquery
        )
    ");
    $stmtUpdatePayment->bind_param("si", $status_bayar, $order_id);
    $stmtUpdatePayment->execute();
    $stmtUpdatePayment->close();
}

?>