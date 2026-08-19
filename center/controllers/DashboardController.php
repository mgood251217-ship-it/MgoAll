<?php
class DashboardController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData() {
        $data = [];

        $data['totalCabang'] = $this->koneksi->query("SELECT COUNT(*) FROM stores")->fetch_row()[0] ?? 0;
        $data['totalUsers'] = $this->koneksi->query("SELECT COUNT(*) FROM users")->fetch_row()[0] ?? 0;
        $data['totalOrders'] = $this->koneksi->query("SELECT COUNT(*) FROM orders")->fetch_row()[0] ?? 0;

        $totalTransaksiQuery = $this->koneksi->query("SELECT SUM(nominal) FROM payment");
        $totalTransaksi = $totalTransaksiQuery->fetch_row()[0] ?? 0;
        $data['totalTransaksiFormatted'] = 'Rp ' . number_format($totalTransaksi, 0, ',', '.');

        $result = $this->koneksi->query("SELECT store_id, name, latitude, longitude FROM locations");
        $locations = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $locations[] = [
                    'store_id' => $row['store_id'],
                    'name' => $row['name'],
                    'latitude' => (float)$row['latitude'],
                    'longitude' => (float)$row['longitude']
                ];
            }
        }
        $data['locations'] = $locations;

        return $data;
    }
}