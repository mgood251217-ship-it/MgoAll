<?php
session_start();
require_once BASE_PATH . '/global_functions.php';

if (isset($_SESSION['user']['user_id']) &&
    isset($_SESSION['user']['store_id']) &&
    isset($_SESSION['user']['role']) &&
    isset($_SESSION['user']['username']) &&
    isset($_SESSION['user']['initial']) &&
    isset($_SESSION['user']['name']) &&
    isset($_SESSION['user']['foto']) &&
    isset($_SESSION['user']['store_name']) &&
    isset($_SESSION['user']['store_address']) &&
    isset($_SESSION['user']['store_logo']) 
    // isset($_SESSION['user']['mode']) 
) {
    $user_id = startEnk('dek', $_SESSION['user']['user_id']);
    $store_id = startEnk('dek', $_SESSION['user']['store_id']);
    $role = startEnk('dek', $_SESSION['user']['role']);
    $username = startEnk('dek', $_SESSION['user']['username']);
    $initial = startEnk('dek', $_SESSION['user']['initial']);
    $name = startEnk('dek', $_SESSION['user']['name']);
    $foto = startEnk('dek', $_SESSION['user']['foto']);
    $storeName = startEnk('dek', $_SESSION['user']['store_name']);
    $storeAddress = startEnk('dek', $_SESSION['user']['store_address']);
    $storeLogo = startEnk('dek', $_SESSION['user']['store_logo']);
    // $mode = (int)startEnk('dek', $_SESSION['user']['mode']);
} elseif (
    isset($_COOKIE['user_user_id']) &&
    isset($_COOKIE['user_username']) &&
    isset($_COOKIE['user_name']) &&
    isset($_COOKIE['user_initial']) &&
    isset($_COOKIE['user_store_id']) &&
    isset($_COOKIE['user_role']) &&
    isset($_COOKIE['store_name']) &&
    isset($_COOKIE['store_logo']) &&
    isset($_COOKIE['store_address']) &&
    isset($_COOKIE['user_mode'])
) {
    $user_id = startEnk('dek', $_COOKIE['user_user_id']);
    $store_id = startEnk('dek', $_COOKIE['user_store_id']);
    $role = startEnk('dek', $_COOKIE['user_role']);
    $username = startEnk('dek', $_COOKIE['user_username']);
    $initial = startEnk('dek', $_COOKIE['user_initial']);
    $name = startEnk('dek', $_COOKIE['user_name']);
    $foto = startEnk('dek', $_COOKIE['user_foto']);
    $storeName = startEnk('dek', $_COOKIE['store_name']);
    $storeAddress = startEnk('dek', $_COOKIE['store_address']);
    $storeLogo = startEnk('dek', $_COOKIE['store_logo']);
    // $mode = startEnk('dek', $_COOKIE['user_mode']);

    // Validasi hasil dekripsi
    if ($user_id && $store_id && $role && $username && $initial && $name) {
        $_SESSION['user'] = [
            'user_id' => $_COOKIE['user_user_id'],
            'store_id'          => $_COOKIE['user_store_id'],
            'role'              => $_COOKIE['user_role'],
            'username'          => $_COOKIE['user_username'],
            'initial'           => $_COOKIE['user_initial'],
            'name'              => $_COOKIE['user_name'],
            'foto'              => $_COOKIE['user_foto'],
            'store_name'=> $_COOKIE['store_name'],
            'store_address'=> $_COOKIE['store_address'],
            'store_logo'=> $_COOKIE['store_logo']
            // 'mode'=> $_COOKIE['user_mode'],
        ];
        return;
    }
}else{
    session_destroy();
    header("Location: " . BASE_URL . "/login");
    exit;
}


$stmt = $koneksi->prepare("SELECT user_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultValidation = $stmt->get_result();

if ($resultValidation->num_rows !== 1) {
    session_start();
    session_destroy();
    header("Location: " . BASE_URL . "/login");
    exit;
}

date_default_timezone_set('Asia/Jakarta');
$date = date("Y-m-d H:i:s");

?>