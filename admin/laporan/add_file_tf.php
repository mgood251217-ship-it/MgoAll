<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/global_functions.php';

if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $_SESSION['error'] = "Ukuran file terlalu besar hingga melampaui batas server.";
    header("Location:index");
    exit;
}

$order_id = $_POST['order_id'] ?? 0;
$store_id = $_POST['store_id'] ?? 0;
$date = date('Y-m-d H:i:s');

$storeNames = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
$uploadDir = BASE_PATH . "/assets/img/buktitf/$storeNames/";

if ( !empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0){
    $result = compress( $_FILES['picture'], $uploadDir );
    if ($result) {
        $pictureName = $result['file'];
        $insert = $koneksi->prepare("
                            INSERT INTO transfers
                            (order_id, store_id, img, date)
                            VALUES (?, ?, ?, ?)");
        $insert->bind_param("iiss", $order_id, $store_id, $pictureName, $date);
        $insert->execute();
        $insert->close();
    }
    
}

header("Location:index");
exit;