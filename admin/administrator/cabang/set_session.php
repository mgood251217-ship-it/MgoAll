<?php 

session_start();
require_once "../../connect.php";
require_once BASE_PATH . '/functions/helpers.php';

$user_id = (int)$_POST['user_id'];
$sql = "SELECT user_id, username, name, password, store_id, initial, role, picture FROM users WHERE user_id = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$sesi = '';

while ($row = $result->fetch_assoc()) {
    $sesi = $row;
}

if ($sesi) {

    $stmt = $koneksi->prepare("SELECT name, logo, address FROM stores WHERE store_id = ?");
    $stmt->bind_param("i", $sesi['store_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $data = $result->fetch_assoc()) {
        $storeName = $data['name'];
        $storeAddress = $data['address'];
        $storeLogo = $data['logo'];
    }

    $stmt = $koneksi->prepare("SELECT mode FROM user_setting WHERE user_id = ?");
    $stmt->bind_param("i", $sesi['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $mode = (int)$row['mode'];
    }
    $stmt->close();

    $_SESSION['user'] = [
        'user_id' => startEnk('enk', $sesi['user_id']),
        'username' => startEnk('enk', $sesi['username']),
        'name' => startEnk('enk', $sesi['name']),
        'initial' => startEnk('enk', $sesi['initial']),
        'store_id' => startEnk('enk', $sesi['store_id']),
        'role' => startEnk('enk', $sesi['role']),
        'foto' => startEnk('enk', $sesi['picture']),
        'store_name'     => startEnk('enk', $storeName),
        'store_address'     => startEnk('enk', $storeAddress),
        'store_logo'     => startEnk('enk', $storeLogo),
        'mode'     => startEnk('enk', $mode),
        'access' => startEnk('enk', 'all')
    ];
    ?>
   
<?php
}
?>