<?php

function startEnk($enkdek, $enkvalue){
    $enkkey = "kunci-rahasia-sangat-aman";
    $enkmethod = "aes-256-cbc";
    if ($enkdek == 'enk') {
        return enkripsiSession($enkkey, $enkmethod, $enkvalue);
    } elseif ($enkdek == 'dek') {
        return dekripsiSession($enkkey, $enkmethod, $enkvalue);
    }
}
    
function enkripsiSession($enkkey, $enkmethod, $enkvalue){
    $iv_length = openssl_cipher_iv_length($enkmethod);
    $iv = openssl_random_pseudo_bytes($iv_length);

    $encrypted = openssl_encrypt(
        $enkvalue,
        $enkmethod,
        $enkkey,
        OPENSSL_RAW_DATA, 
        $iv
    );

    return base64_encode($iv . $encrypted);
}

function dekripsiSession($enkkey, $enkmethod, $enkvalue){
    $data = base64_decode($enkvalue);
    $iv_length = openssl_cipher_iv_length($enkmethod);

    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);

    return openssl_decrypt(
        $ciphertext,
        $enkmethod,
        $enkkey,
        OPENSSL_RAW_DATA, 
        $iv
    );
}

?>