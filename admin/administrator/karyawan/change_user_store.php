<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $store_id = isset($_POST['store_id']) ? (int) $_POST['store_id'] : 0;

    if ($user_id > 0 && $store_id > 0) {
        $stmt = $koneksi->prepare("UPDATE users SET store_id = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $store_id, $user_id);

        if ($stmt->execute()) {
            echo 'OK';
        } else {
            echo 'Gagal update: ' . $stmt->error;
        }

        $stmt->close();
    } else {
        echo 'Data tidak valid';
    }
} else {
    echo 'Invalid request';
}
