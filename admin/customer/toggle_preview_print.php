<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_preview_print'])) {
    // Cek data lama
    $stmt = $koneksi->prepare("SELECT preview_print FROM user_setting WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($old_pp);

    if ($stmt->fetch()) {
        $old_pp = (int)$old_pp;
        $stmt->close();
        $new_pp = $old_pp === 1 ? 0 : 1;

        $update = $koneksi->prepare("UPDATE user_setting SET preview_print = ? WHERE user_id = ?");
        $update->bind_param("ii", $new_pp, $user_id);
        $update->execute();
        $update->close();
    } else {
        $stmt->close();
        // Insert record baru dengan preview_print=1 karena toggle aktif
        $insert = $koneksi->prepare("INSERT INTO user_setting (user_id, preview_print) VALUES (?, 1)");
        $insert->bind_param("i", $user_id);
        $insert->execute();
        $insert->close();
    }

    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $redirect_url");
    exit;
}

header("Location: customer.php");
exit;
