<?php

function getFinishingPrice(string $finishing_ids, int $store_id, mysqli $koneksi): int {
    $total = 0;
    if ($finishing_ids === '-' || empty($finishing_ids)) return 0;

    $ids = explode(',', $finishing_ids);
    foreach ($ids as $fid) {
        $fid = trim($fid);
        if (is_numeric($fid)) {
            $total += getProductPrice((int)$fid, $store_id, $koneksi);
        }
    }
    return $total;
}

?>