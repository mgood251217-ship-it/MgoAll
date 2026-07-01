<?php
require_once BASE_PATH . '/functions/helpers.php';
session_start();

if (isset($_SESSION['admin_logged_in'])) {
  $administrator_id = startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']);
  $username = startEnk('dek', $_SESSION['admin_logged_in']['username']);
  $access = startEnk('dek', $_SESSION['admin_logged_in']['access']);
} elseif (
    isset($_COOKIE['admin_administrator_id']) &&
    isset($_COOKIE['admin_username']) &&
    isset($_COOKIE['admin_access'])
) {
    $administrator_id = startEnk('dek', $_COOKIE['admin_administrator_id']);
    $username         = startEnk('dek', $_COOKIE['admin_username']);
    $access           = startEnk('dek', $_COOKIE['admin_access']);

    // Validasi hasil dekripsi
    if ($administrator_id && $username && $access) {
        $_SESSION['admin_logged_in'] = [
            'administrator_id' => $_COOKIE['admin_administrator_id'],
            'username'         => $_COOKIE['admin_username'],
            'access'           => $_COOKIE['admin_access']
        ];
        return;
    }
} elseif (!isset($_SESSION['admin_logged_in'])) {
  header("Location: " . BASE_URL . "/administrator/login.php");
  exit;
}

// $administrator_id = startEnk('dek', $_SESSION['admin_logged_in']['administrator_id']);
// $username = startEnk('dek', $_SESSION['admin_logged_in']['username']);
// $access = startEnk('dek', $_SESSION['admin_logged_in']['access']);

$stmt = $koneksi->prepare("SELECT administrator_id FROM administrator WHERE administrator_id = ?");
$stmt->bind_param("i", $administrator_id);
$stmt->execute();
$resultValidation = $stmt->get_result();

if ($resultValidation->num_rows !== 1) {
    session_start();
    session_destroy();
    header("Location: " . BASE_URL . "/administrator/login.php");
    exit;
}

?>