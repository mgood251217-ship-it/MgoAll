<?php

function compress($source, $destination, $quality) {

    $info = getimagesize($source);

    if ($info['mime'] == 'image/jpeg') 
        $image = imagecreatefromjpeg($source);

    elseif ($info['mime'] == 'image/gif') 
        $image = imagecreatefromgif($source);

    elseif ($info['mime'] == 'image/png') 
        $image = imagecreatefrompng($source);

    imagejpeg($image, $destination, $quality);

    return $destination;
}

function refreshFinance($store_id, $date) {
    global $koneksi;

    $start = $date . ' 00:00:00';
    $end   = $date . ' 23:59:59';

    $omset_offline = 0;
    $omset_online  = 0;
    $cash          = 0;
    $transfer      = 0;
    $pengeluaran   = 0;

    /* ===============================
       1. HITUNG OMSET & CASH
    =============================== */
    $stmt = $koneksi->prepare("
        SELECT payment_method, nominal, order_id
        FROM payment
        WHERE date BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orderIds = explode(',', $row['order_id']);
        $perOrder = $row['nominal'] / max(count($orderIds), 1);

        foreach ($orderIds as $oid) {
            $oid = trim($oid);
            $q = $koneksi->query("
                SELECT store_id, system
                FROM orders
                WHERE order_id = '$oid'
            ");
            if ($q && $o = $q->fetch_assoc()) {
                if ((int)$o['store_id'] === (int)$store_id) {

                    if ($o['system'] === 'OFFLINE') {
                        $omset_offline += $perOrder;
                    } else {
                        $omset_online += $perOrder;
                    }

                    if ($row['payment_method'] === 'CASH') {
                        $cash += $perOrder;
                    } else {
                        $transfer += $perOrder;
                    }
                }
            }
        }
    }
    $stmt->close();

    /* ===============================
       2. AMBIL SALDO KEMARIN
    =============================== */
    $prevDate  = date('Y-m-d', strtotime($date . ' -1 day'));
    $saldoPrev = 0;

    $stmt = $koneksi->prepare("
        SELECT saldo
        FROM finance
        WHERE store_id = ? AND date = ?
        LIMIT 1
    ");
    $stmt->bind_param("is", $store_id, $prevDate);
    $stmt->execute();
    $stmt->bind_result($saldoPrev);
    $stmt->fetch();
    $stmt->close();

    $saldoPrev = $saldoPrev ?? 0;

    /* ===============================
       3. SALDO OTOMATIS (UPDATE / INSERT)
    =============================== */
    $infoSaldo = "INPUT SALDO OTOMATIS " . $date;

    // cek apakah saldo otomatis sudah ada
    $stmt = $koneksi->prepare("
        SELECT income_id, nominal
        FROM income
        WHERE store_id = ?
          AND information = ?
          AND DATE(date) = ?
        LIMIT 1
    ");
    $stmt->bind_param("iss", $store_id, $infoSaldo, $date);
    $stmt->execute();
    $stmt->bind_result($income_id, $nominalOld);
    $exists = $stmt->fetch();
    $stmt->close();

    if ($exists) {
        // UPDATE saldo otomatis
        $stmt = $koneksi->prepare("
            UPDATE income
            SET nominal = ?
            WHERE income_id = ?
        ");
        $stmt->bind_param("ii", $saldoPrev, $income_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // INSERT saldo otomatis
        $stmt = $koneksi->prepare("
            INSERT INTO income (store_id, information, nominal, date)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isis", $store_id, $infoSaldo, $saldoPrev, $date);
        $stmt->execute();
        $stmt->close();
    }

    /* ===============================
       4. PEMASUKAN LAIN
    =============================== */
    $pemasukan_lain = 0;
    $stmt = $koneksi->prepare("
        SELECT IFNULL(SUM(nominal),0)
        FROM income
        WHERE store_id = ?
          AND DATE(date) = ?
          AND information NOT LIKE 'INPUT SALDO OTOMATIS%'
    ");
    $stmt->bind_param("is", $store_id, $date);
    $stmt->execute();
    $stmt->bind_result($pemasukan_lain);
    $stmt->fetch();
    $stmt->close();

    /* ===============================
       5. PENGELUARAN
    =============================== */
    $stmt = $koneksi->prepare("
        SELECT IFNULL(SUM(nominal),0)
        FROM expenditures
        WHERE store_id = ? AND DATE(date) = ?
    ");
    $stmt->bind_param("is", $store_id, $date);
    $stmt->execute();
    $stmt->bind_result($pengeluaran);
    $stmt->fetch();
    $stmt->close();

    /* ===============================
       6. HITUNG SALDO FINAL
    =============================== */
    $saldo = $saldoPrev + $cash + $pemasukan_lain - $pengeluaran;

    /* ===============================
       7. UPDATE / INSERT FINANCE
    =============================== */
    $stmt = $koneksi->prepare("
        SELECT COUNT(*)
        FROM finance
        WHERE store_id = ? AND date = ?
    ");
    $stmt->bind_param("is", $store_id, $date);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $stmt = $koneksi->prepare("
            UPDATE finance SET
                omset_offline = ?,
                omset_online  = ?,
                saldo         = ?,
                transfer      = ?,
                expenditure   = ?
            WHERE store_id = ? AND date = ?
        ");
        $stmt->bind_param(
            "iiiiiss",
            $omset_offline,
            $omset_online,
            $saldo,
            $transfer,
            $pengeluaran,
            $store_id,
            $date
        );
    } else {
        $stmt = $koneksi->prepare("
            INSERT INTO finance
            (store_id, omset_offline, omset_online, saldo, transfer, expenditure, date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiiiiss",
            $store_id,
            $omset_offline,
            $omset_online,
            $saldo,
            $transfer,
            $pengeluaran,
            $date
        );
    }

    $stmt->execute();
    $stmt->close();
}

?>