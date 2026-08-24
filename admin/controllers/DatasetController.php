<?php
require_once BASE_PATH . '/functions/helpers.php';

class DatasetController {
    private $koneksi;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
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

        $dataset_path = BASE_PATH . '/temp/dataset/store_' . $store_id . '.json';
        $order_trigger_path = BASE_PATH . '/temp/orders/store_' . $store_id . '.json';

        $data = [];

        if (file_exists($dataset_path)) {
            $dataset_json = file_get_contents($dataset_path);
            $dataset_arr = json_decode($dataset_json, true);
            if (is_array($dataset_arr)) {
                $data = array_merge($data, $dataset_arr);
            }
        }

        if (file_exists($order_trigger_path)) {
            $order_json = file_get_contents($order_trigger_path);
            $order_arr = json_decode($order_json, true);
            if (is_array($order_arr)) {
                $data['order_trigger'] = $order_arr;
            }
        }

        send_json_response(true, 'success', $data);
    }
}
?>
