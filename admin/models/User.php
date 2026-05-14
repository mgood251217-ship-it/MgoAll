<?php

class User{
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }

    public function getUserByUserId($id){
        $stmt = $this->koneksi->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result();
    }
}

?>