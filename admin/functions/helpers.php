<?php
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function formatKeInternasional($nomor) {
    $nomor = preg_replace('/[^0-9]/', '', $nomor);

    if (strpos($nomor, '0') === 0) {
        $nomor = '62' . substr($nomor, 1);
    } 
    return '+' . $nomor;
}

function format_rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

function title_case($teks) {
    return ucwords(strtolower($teks));
}

function format_tanggal_id($tanggal) {
    if (empty($tanggal)) return '-';
    
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($tanggal);
    $tgl = date('j', $timestamp);
    $bln = (int)date('n', $timestamp);
    $thn = date('Y', $timestamp);
    
    return $tgl . ' ' . $bulan[$bln] . ' ' . $thn;
} 

function limit_text($text, $limit = 100) {
    if (strlen($text) > $limit) {
        return substr($text, 0, $limit) . '...';
    }
    return $text;
}

function get_versioned_file($filepath) {
    if (file_exists($filepath)) {
        return $filepath . '?v=' . filemtime($filepath);
    }
    return $filepath;
}

function get_json_input() {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    return is_array($data) ? $data : [];
}

function send_json_response($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success'  => $success,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

function is_active_page($page_name) {
    $current_page = isset($_GET['page']) ? $_GET['page'] : '';
    return ($current_page === $page_name) ? 'active' : '';
}

function make_slug($string) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
}

function hitungDeadline($deadline_str) {
    date_default_timezone_set('Asia/Jakarta');

    $sekarang = new DateTime();
    $deadline = new DateTime($deadline_str);

    if ($deadline < $sekarang) {
        return "Sudah Terlewat";
    }

    $tgl_sekarang = new DateTime($sekarang->format('Y-m-d'));
    $tgl_deadline = new DateTime($deadline->format('Y-m-d'));
    $selisih = $tgl_sekarang->diff($tgl_deadline);
    $jumlah_hari = $selisih->days;

    $jam = (int)$deadline->format('H');
    $menit = $deadline->format('i');
    
    $jam_12 = $jam % 12;
    if ($jam_12 === 0) {
        $jam_12 = 12;
    }

    if ($jam >= 0 && $jam < 4) {
        $ket_waktu = "Dini Hari";
    } elseif ($jam >= 4 && $jam < 10) {
        $ket_waktu = "Pagi";
    } elseif ($jam >= 10 && $jam < 15) {
        $ket_waktu = "Siang";
    } elseif ($jam >= 15 && $jam < 18) {
        $ket_waktu = "Sore";
    } else {
        $ket_waktu = "Malam";
    }

    $format_jam = "Jam " . $jam_12 . " " . $ket_waktu;

    if ($jumlah_hari === 0) {
        return $format_jam;
    } elseif ($jumlah_hari === 1) {
        return $format_jam . " Besok";
    } else {
        return $jumlah_hari . " hari lagi";
    }
}

function redirect($url){
    header("Location: $url");
    exit;
}

if (!function_exists('startEnk')) {
    function startEnk($enkdek, $enkvalue){
        $enkkey = "kunci-rahasia-sangat-aman";
        $enkmethod = "aes-256-cbc";
        $iv_length = openssl_cipher_iv_length($enkmethod);

        if ($enkdek == 'enk') {
            $iv = openssl_random_pseudo_bytes($iv_length);
            $encrypted = openssl_encrypt($enkvalue, $enkmethod, $enkkey, OPENSSL_RAW_DATA,  $iv);

            return base64_encode($iv . $encrypted);
        } elseif ($enkdek == 'dek') {
            $data = base64_decode($enkvalue);
            $iv = substr($data, 0, $iv_length);
            $ciphertext = substr($data, $iv_length);

            return openssl_decrypt( $ciphertext, $enkmethod, $enkkey, OPENSSL_RAW_DATA, $iv );
        }
    }
}