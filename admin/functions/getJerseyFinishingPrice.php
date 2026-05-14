<?php

function getJerseyFinishingPrice($finishing_jersey, int $store_id, mysqli $koneksi): int {
    $total = 0;

    if (empty($finishing_jersey)) {
        return 0;
    }

    // Jika dikirim sebagai JSON string
    if (is_string($finishing_jersey)) {
        $decoded = json_decode($finishing_jersey, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $finishing_jersey = $decoded;
        } else {
            // fallback: comma separated
            $finishing_jersey = explode(',', $finishing_jersey);
        }
    }

    if (!is_array($finishing_jersey)) {
        return 0;
    }

    foreach ($finishing_jersey as $fid) {
        if (is_numeric($fid)) {
            $total += getProductPrice((int)$fid, $store_id, $koneksi);
        }
    }

    return $total;
}

?>