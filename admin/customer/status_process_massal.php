<?php
require_once '../connect.php';
require_once BASE_PATH . '/models/Project.php';
date_default_timezone_set('Asia/Jakarta');

$projectModel = new Project($koneksi);
$order_ids = $_POST['order_ids'] ?? '';

if ($order_ids) {
    if (!is_array($order_ids)) {
        $order_ids = explode(',', $order_ids);
    }

    foreach ($order_ids as $order_id) {
        $data = new stdClass();
        $data->id = $order_id;

        $status_terakhir = $projectModel->getLastProjectStatusByOrderId($data->id);
        $data->process = $_POST['status'] ?? '';
        $data->status = $status_terakhir;
        $data->user_id = $_POST['user_initial'] ?? 0;
        $data->order_id = $order_id;
        $data->date = date('Y-m-d H:i:s');
        $projectModel->updateProject($data);
    }
}

header("Location: index");
exit;
?>
