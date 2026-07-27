<?php
class AuthMiddleware {
    private $koneksi;

    public function __construct($koneksi){
        $this->koneksi = $koneksi;
    }
}

?>