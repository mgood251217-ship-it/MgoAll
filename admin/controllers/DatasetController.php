<?php
require_once __DIR__ . '/../functions/helpers.php';

class DatasetController {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
    }

    private function readCache($path) {
        if (!file_exists($path)) return [];
        $file = fopen($path, 'r');
        if (!$file) return [];
        try {
            if (!flock($file, LOCK_SH)) return [];
            $data = json_decode(stream_get_contents($file), true);
            return is_array($data) ? $data : [];
        } finally { flock($file, LOCK_UN); fclose($file); }
    }

    public function checkUpdate() {
        global $store_id;

        if (!$store_id) {
            send_json_response(false, 'Unauthorized');
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $dataset_path = $_ENV['BASE_PATH_UPLOAD'] . '/temp/dataset/store_' . $store_id . '.json';
        $order_trigger_path = $_ENV['BASE_PATH_UPLOAD'] . '/temp/orders/store_' . $store_id . '.json';

        $data = [];

        $data = $this->readCache($dataset_path);
        $orders = $this->readCache($order_trigger_path);
        if ($orders) $data['order_trigger'] = $orders;

        send_json_response(true, 'success', $data);
    }
}
?>
