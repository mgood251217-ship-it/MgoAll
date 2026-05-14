<?php

class Store{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function getStoreByStoreId($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM stores WHERE store_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result();
    }

    
}

?>