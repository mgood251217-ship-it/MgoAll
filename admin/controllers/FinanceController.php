<?php
require_once BASE_PATH . '/models/Finance.php';

class FinanceController {
    private $koneksi;
    private $financeModel;

    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->financeModel = new Finance($koneksi);
        
    }


}
?>