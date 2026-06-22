<?php
require_once BASE_PATH . '/models/Finance.php';

class FinanceController {
    private $koneksi;
    private $financeModel;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->financeModel = new Finance($koneksi);
        
    }

    public function createTf(){
        require_once BASE_PATH . '/global_functions.php';
        global $storeName;

        $order_id = $_POST['order_id'] ?? 0;
        $store_id = $_POST['store_id'] ?? 0;
        $date = date('Y-m-d H:i:s');
 
        $storeNames = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
        $uploadDir = BASE_PATH . "/assets/img/buktitf/$storeNames/";

        if ( !empty($_FILES['picture']['name']) && $_FILES['picture']['error'] === 0){
            $result = compress( $_FILES['picture'], $uploadDir );
            if ($result) {
                $pictureName = $result['file'];

                $data = (object)[
                    'order_id' => $order_id,
                    'store_id' => $store_id,
                    'pictureName' => $pictureName,
                    'date' => $date
                ];

                if ($this->financeModel->create_tf($data)) {
                    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil']);
                    exit;
                }else{
                    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database']);
                    exit;
                }

            }else{
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database']);
                exit;
            }
            
        }


    }

    public function deleteTf(){
        global $storeName;

        $transfer_id = (int)$_POST['transfer_id'];
        $row = $this->financeModel->getTfById($transfer_id);

        $storeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
        $imgPath = BASE_PATH . '/assets/img/buktitf/' . $storeName. "/" . $row['img'];

        $this->financeModel->deleteTf($transfer_id);

        if (file_exists($imgPath)) unlink($imgPath);

        echo json_encode(['success' => true]);
    }

}
?>