<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $picture = $_POST['picture'] ?? '';
    echo $user_id . " - " . $store_id . " - " . $picture;
    // Cek jumlah user di store ini
    $check = $koneksi->prepare("SELECT COUNT(*) FROM users WHERE store_id = ?");
    $check->bind_param("i", $store_id);
    $check->execute();
    $check->bind_result($userCount);
    $check->fetch();
    $check->close();

    if ($userCount <= 1) {
        $_SESSION['flash_error'] = "Tidak bisa menghapus user terakhir.";
    } else {
        $stmt = $koneksi->prepare("DELETE FROM users WHERE user_id = ? AND store_id = ?");
        $stmt->bind_param("ii", $user_id, $store_id);
        if ($stmt->execute()) {
            // Hapus foto jika ada
            if (!empty($picture)) {
                 $filePath = BASE_PATH . "/assets/img/" . $picture;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $_SESSION['flash_success'] = "User berhasil dihapus.";
        } else {
            $_SESSION['flash_error'] = "Gagal menghapus user.";
        }
        $stmt->close();
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
