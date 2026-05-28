<?php
require_once BASE_PATH . '/models/User.php';

class UserController{
    private $userModel;

    public function __construct($koneksi){
        $this->userModel = new User($koneksi);
    }

    private function requestData(){
        require_once '../session.php';

        $data = new stdClass();
        $data->id = (int)($_POST['user_id'] ?? 0);
        $data->name = strtoupper(trim($_POST['name'] ?? ''));
        $data->username = strtolower(trim($_POST['username'] ?? ''));
        $data->password = $_POST['password'] ?? '';
        $data->initial = strtoupper(trim($_POST['initial'] ?? ''));
        $data->role = strtoupper(trim($_POST['role'] ?? ''));
        $data->store_id = $store_id;
        $data->picture = $old_picture;
        $data->store_name = $storeName;
        $data->latitude = $_POST['latitude'];
        $data->longitude = $_POST['longitude'];
    }

    public function getByStore($id){
        return $this->userModel->getUsersByStoreId($id);
    }


}

?>