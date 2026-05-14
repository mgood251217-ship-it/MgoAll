<?php
// ================ FILE: edit_user.php ================
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
$errors = [];

$user_id = (int)($_POST['user_id'] ?? 0);
$name = strtoupper(trim($_POST['name'] ?? ''));
$username = strtolower(trim($_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$initial = strtoupper(trim($_POST['initial'] ?? ''));
$old_picture = $_POST['old_picture'] ?? '';
$new_picture_name = $old_picture;

// Validasi wajib
if (!$user_id || !$name || !$username || !$role || !$initial) {
    $errors[] = "Semua field (kecuali password dan foto) harus diisi.";
}

// Cek duplikat username (selain miliknya sendiri)
$stmt = $koneksi->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
$stmt->bind_param("si", $username, $user_id);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count > 0) {
    $errors[] = "Username sudah digunakan oleh user lain.";
}

// Jika ada upload gambar baru
if (!empty($_FILES['picture']['name'])) {
    $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($ext, $allowed)) {
        $errors[] = "Format foto tidak didukung.";
    } else {
        $new_picture_name = uniqid("user_", true) . '.' . $ext;
        $upload_path = BASE_PATH . "/assets/img/user/" . $new_picture_name;

        // Pindahkan file
        if (move_uploaded_file($_FILES['picture']['tmp_name'], $upload_path)) {
            // Hapus gambar lama jika ada
            if ($old_picture && file_exists(BASE_PATH . "/assets/img/user/" . $old_picture)) {
                unlink(BASE_PATH . "/assets/img/user/" . $old_picture);
            }
        } else {
            $errors[] = "Gagal mengupload gambar.";
        }
    }
}

if ($errors) {
    $_SESSION['flash_errors'] = $errors;
    header("Location: toko.php");
    exit;
}

// Siapkan update
if (!empty($password)) {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $koneksi->prepare("UPDATE users SET name = ?, username = ?, password = ?, role = ?, initial = ?, picture = ? WHERE user_id = ? AND store_id = ?");
    $stmt->bind_param("ssssssii", $name, $username, $passwordHash, $role, $initial, $new_picture_name, $user_id, $store_id);
} else {
    $stmt = $koneksi->prepare("UPDATE users SET name = ?, username = ?, role = ?, initial = ?, picture = ? WHERE user_id = ? AND store_id = ?");
    $stmt->bind_param("ssssssi", $name, $username, $role, $initial, $new_picture_name, $user_id, $store_id);
}

if ($stmt->execute()) {
    $_SESSION['flash_success'] = "User berhasil diperbarui.";
} else {
    $_SESSION['flash_errors'] = ["Gagal memperbarui user."];
}
$stmt->close();

header("Location: toko.php");
exit;
