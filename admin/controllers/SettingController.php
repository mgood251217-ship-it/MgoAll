<?php
require_once BASE_PATH . '/models/Setting.php';
require_once BASE_PATH . '/functions/helpers.php';

class SettingController {
    private $settingModel;

    public function __construct($koneksi) {
        $this->settingModel = new Setting($koneksi);
    }

    private function requestData() {
        global $user_id;

        $data = new stdClass();
        $data->customer_limit = $_POST['limit'] ?? 0;
        $data->user_id = $user_id;

        return $data;
    }

    public function limit() {
        $data = $this->requestData();
        if ($this->settingModel->cekUserSetting($data->user_id)) {
            $this->settingModel->updateOneValue($data->user_id, 'customer_limit', $data->customer_limit);
            send_json_response(true, 'Berhasil diperbarui.');
        } else {
            $this->settingModel->create($data);
            send_json_response(true, 'Berhasil diperbarui.');
        }
    }

    public function update() {

    }

}
?>