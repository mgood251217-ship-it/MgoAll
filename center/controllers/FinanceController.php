<?php
class FinanceController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData($access, $startDateF = null, $endDateF = null) {
        $startDateF = $startDateF ?: date('Y-m-d');
        $endDateF = $endDateF ?: date('Y-m-d');

        $startDate = $startDateF . ' 00:00:00';
        $endDate = $endDateF . ' 23:59:59';

        if ($access == 'ALL') {
            $stmt = $this->koneksi->prepare("
                SELECT f.*, s.name AS store_name, s.branch 
                FROM finance f 
                JOIN stores s ON f.store_id = s.store_id 
                WHERE f.date BETWEEN ? AND ?
                ORDER BY f.date DESC
            ");
            $stmt->bind_param("ss", $startDate, $endDate);
        } else {
            $stmt = $this->koneksi->prepare("
                SELECT f.*, s.name AS store_name, s.branch 
                FROM finance f 
                JOIN stores s ON f.store_id = s.store_id 
                WHERE f.date BETWEEN ? AND ? AND s.administrator = ?
                ORDER BY f.date DESC
            ");
            $stmt->bind_param("sss", $startDate, $endDate, $access);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $finances = [];
        $totalOffline = 0;
        $totalOnline = 0;
        $totalSaldo = 0;
        $totalTransfer = 0;
        $totalExpenditure = 0;

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $finances[] = $row;
                $totalOffline += (float)$row['omset_offline'];
                $totalOnline += (float)$row['omset_online'];
                $totalSaldo += (float)$row['saldo'];
                $totalTransfer += (float)$row['transfer'];
                $totalExpenditure += (float)$row['expenditure'];
            }
        }

        return [
            'startDate' => $startDateF,
            'endDate' => $endDateF,
            'finances' => $finances,
            'totals' => [
                'offline' => $totalOffline,
                'online' => $totalOnline,
                'saldo' => $totalSaldo,
                'transfer' => $totalTransfer,
                'expenditure' => $totalExpenditure
            ]
        ];
    }
}