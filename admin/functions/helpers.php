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

function send_json_response($status, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => $status,
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