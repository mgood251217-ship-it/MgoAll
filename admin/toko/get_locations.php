<?php
require_once '../connect.php';

$result = $koneksi->query("SELECT * FROM locations");
$locations = [];

while ($row = $result->fetch_assoc()) {
  $locations[] = [
    'store_id' => $row['store_id'],
    'name' => $row['name'],
    'latitude' => (float)$row['latitude'],
    'longitude' => (float)$row['longitude']
  ];
}

header('Content-Type: application/json');
echo json_encode($locations);
