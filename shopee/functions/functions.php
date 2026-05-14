<?php
require_once '../connect.php';

function updateTotalMeteran($product_id, $date) {
    global $koneksi;
    $stmtSum = $koneksi->prepare("
        SELECT SUM(value) AS total_value
        FROM list_meters lm
        JOIN meters m ON lm.meter_id = m.meter_id
        WHERE m.product_id = ? AND m.date = ?");
    $stmtSum->bind_param("is", $product_id, $date);
    $stmtSum->execute();
    $resultSum = $stmtSum->get_result()->fetch_assoc();
    $totalValue = $resultSum['total_value'] ?? 0;

    $stmtUpdateTotal = $koneksi->prepare("
        UPDATE meters
        SET total = ?
        WHERE product_id = ? AND date = ?");
    $stmtUpdateTotal->bind_param("dis", $totalValue, $product_id, $date);
    $stmtUpdateTotal->execute();
    $stmtUpdateTotal->close();
    //kembalikan nilaitotal terbaru
    return $totalValue;
}

function refreshFinanceOmset($user_id, $month) {
    global $koneksi;
    $start_month = $month;
    $end_month = date('Y-m-t', strtotime($start_month));

    $stmtExpenditure = $koneksi->prepare("
        SELECT SUM(nominal) AS total_expenditure
        FROM expenditure
        WHERE user_id = ? AND date BETWEEN ? AND ?");
    $stmtExpenditure->bind_param("iss", $user_id, $start_month, $end_month);
    $stmtExpenditure->execute();
    $resultExpenditure = $stmtExpenditure->get_result()->fetch_assoc();
    $totalExpenditure = $resultExpenditure['total_expenditure'] ?? 0;
    $stmtExpenditure->close();

    $omset = 0;
    $stmtOmsetCheck = $koneksi->prepare("SELECT omset FROM finance WHERE user_id = ? AND month = ?");
    $stmtOmsetCheck->bind_param("is", $user_id, $month);
    $stmtOmsetCheck->execute();
    $resultOmset = $stmtOmsetCheck->get_result()->fetch_assoc();
    if ($resultOmset) {
        $omset = $resultOmset['omset'];

        $stmtUpdateOmset = $koneksi->prepare("UPDATE finance SET omset = ? WHERE user_id = ? AND month = ?");
        $stmtUpdateOmset->bind_param("dis", $omset, $user_id, $start_month);
        $stmtUpdateOmset->execute();
    }else {

        $stmtInsertOmset = $koneksi->prepare("INSERT INTO finance (user_id, omset, month) VALUES (?, ?, ?)");
        $stmtInsertOmset->bind_param("ids", $user_id, $omset, $start_month);
        $stmtInsertOmset->execute();
    }
    $stmtOmsetCheck->close();


}

?>