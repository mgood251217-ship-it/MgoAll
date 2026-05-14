<?php

function getProductPrice(int $product_id, int $store_id, mysqli $koneksi): int {
    $price = 0;
    $stmt = $koneksi->prepare("SELECT price FROM products WHERE product_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $product_id, $store_id);
    $stmt->execute();
    $stmt->bind_result($price);
    $stmt->fetch();
    $stmt->close();
    return $price;
}

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

function addFinishing($name, $store_id, $finishing_type, &$koneksi, &$finishing_ids, &$finishing_additional_price, $panjang = 0, $lebar = 0, $product_type = '') {
    $stmt = $koneksi->prepare("SELECT product_id, price FROM products WHERE type = ? AND name = ? AND store_id = ?");
    $stmt->bind_param("ssi", $finishing_type, $name, $store_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $finishing_ids[] = $result['product_id'];
        $price = (float)$result['price'];
        // Khusus untuk INDOOR dan finishing CUT, harga dikali luas
        if ($product_type === 'INDOOR' && $name === 'KISS CUT') {
            $price *= $panjang * $lebar;
        }
        $finishing_additional_price += $price;
    }
    $stmt->close();
}

function getAvailableProductIdByPrefix($koneksi, $store_id, $judul) {
    $prefixes = ['XBANNER', 'ROLLUP', 'MINIBANNER', 'KN'];
    foreach ($prefixes as $prefix) {
        if (stripos($judul, $prefix) === 0) {
            $sql = $koneksi->prepare("SELECT p.product_id FROM products p JOIN stock s ON p.product_id = s.product_id WHERE p.store_id = ? AND p.name LIKE ? AND s.quantity > 0 ORDER BY s.quantity DESC LIMIT 1");
            $likeName = $prefix . '%';
            $sql->bind_param("is", $store_id, $likeName);
            $sql->execute();
            $result = $sql->get_result();
            if ($row = $result->fetch_assoc()) {
                $sql->close();
                return (int)$row['product_id'];
            }
            $sql->close();
        }
    }
    return null;
}

function handleDiskonOrderItem(mysqli $koneksi, int $order_id, int $product_id, int $diskonInput): int {
    if ($diskonInput > 0) {
        // Cek apakah sudah ada record
        $cekSql = "SELECT 1 FROM diskon_order_items WHERE order_id = ? AND product_id = ?";
        $cekStmt = $koneksi->prepare($cekSql);
        $cekStmt->bind_param("ii", $order_id, $product_id);
        $cekStmt->execute();
        $cekStmt->store_result();

        if ($cekStmt->num_rows > 0) {
            // Update
            $updateSql = "UPDATE diskon_order_items SET diskon = ? WHERE order_id = ? AND product_id = ?";
            $updateStmt = $koneksi->prepare($updateSql);
            $updateStmt->bind_param("iii", $diskonInput, $order_id, $product_id);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert
            $insertSql = "INSERT INTO diskon_order_items (order_id, product_id, diskon) VALUES (?, ?, ?)";
            $insertStmt = $koneksi->prepare($insertSql);
            $insertStmt->bind_param("iii", $order_id, $product_id, $diskonInput);
            $insertStmt->execute();
            $insertStmt->close();
        }

        $cekStmt->close();
    }

    // Ambil kembali diskon dari database (jika ada)
    $cekDiskonSql = "SELECT diskon FROM diskon_order_items WHERE order_id = ? AND product_id = ?";
    $cekDiskonStmt = $koneksi->prepare($cekDiskonSql);
    $cekDiskonStmt->bind_param("ii", $order_id, $product_id);
    $cekDiskonStmt->execute();
    $cekDiskonStmt->bind_result($diskonDb);
    if ($cekDiskonStmt->fetch()) {
        $diskonInput = (int)$diskonDb;
    }
    $cekDiskonStmt->close();

    return $diskonInput;
}

function cekStokProdukUtama(mysqli $koneksi, int $product_id, int $store_id, float $lebar, float $panjang, int $quantity): array {
    // Ambil type dan unit_type produk
    $stmt = $koneksi->prepare("SELECT type, unit_type, name FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($product_type, $unit_type, $name);
    $stmt->fetch();
    $stmt->close();

    // Hitung stok yang dibutuhkan
    $stok_butuh = $quantity;
    if ($unit_type === 'M2') {
        $stok_butuh = $panjang * $lebar * $quantity;
    } elseif ($unit_type === 'CM2') {
        $stok_butuh = round(($panjang / 100) * ($lebar / 100) * $quantity, 4);
    }

    // Cek stok jika unit_type bukan '~'
    if ($unit_type !== '~') {
        $sqlStok = $koneksi->prepare("SELECT quantity FROM stock WHERE product_id = ? AND store_id = ?");
        $sqlStok->bind_param("ii", $product_id, $store_id);
        $sqlStok->execute();
        $sqlStok->bind_result($stok_tersedia);
        $stok_ada = $sqlStok->fetch();
        $sqlStok->close();

        if (!$stok_ada || $stok_tersedia === null || $stok_tersedia < $stok_butuh) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Stok produk tidak mencukupi atau belum tersedia.']);
            exit;
        }
    }

    // Return semua informasi
    return [
        'type' => $product_type,
        'unit_type' => $unit_type,
        'stok_butuh' => $stok_butuh,
        'name' => $name
    ];
}

function cekDanKurangiStokFinishing(mysqli $koneksi, int $store_id, int $quantity, float $panjang, float $lebar, $finishing): bool {
    $finishing_ids = [];
    if ($finishing !== '-' && is_numeric($finishing)) {
        $finishing_ids[] = (int)$finishing;
    }

    // Cek ketersediaan stok
    foreach ($finishing_ids as $fid) {
        $stok_finishing = 0;
        $stmtCek = $koneksi->prepare("
            SELECT p.unit_type, s.quantity 
            FROM products p 
            JOIN stock s ON p.product_id = s.product_id 
            WHERE p.product_id = ? AND p.store_id = ? AND s.store_id = ?
        ");
        $stmtCek->bind_param("iii", $fid, $store_id, $store_id);
        $stmtCek->execute();
        $stmtCek->bind_result($unit_type_finishing, $stok_finishing);

        if ($stmtCek->fetch()) {
            if ($unit_type_finishing !== '~') {
                if ($stok_finishing === null || $stok_finishing < 1) {
                    $stmtCek->close();
                    return false;
                }
            }
        }
        $stmtCek->close();
    }

    // Lakukan pengurangan stok
    foreach ($finishing_ids as $fid) {
        $stmtCekUnit = $koneksi->prepare("SELECT unit_type, name, type FROM products WHERE product_id = ? AND store_id = ?");
        $stmtCekUnit->bind_param("ii", $fid, $store_id);
        $stmtCekUnit->execute();
        $stmtCekUnit->bind_result($unit_type_finishing, $name_finishing, $product_type_finishing);
        $stmtCekUnit->fetch();
        $stmtCekUnit->close();

        if ($unit_type_finishing !== '~') {
            if (
                ($name_finishing === 'DOFF' || $name_finishing === 'GLOSSY')
                && $product_type_finishing === 'FINISHING LASER A3'
            ) {
                $qty_per_item = 0.1536;
            } elseif (
                ($name_finishing === 'DOFF' || $name_finishing === 'GLOSSY')
                && $product_type_finishing === 'FINISHING INDOOR'
            ) {
                $qty_per_item = $panjang * $lebar;
            } else {
                $qty_per_item = 1;
            }

            $qty_to_reduce = $qty_per_item * $quantity;

            $stmtFinishingStok = $koneksi->prepare("UPDATE stock SET quantity = quantity - ? WHERE product_id = ? AND store_id = ?");
            $stmtFinishingStok->bind_param("dii", $qty_to_reduce, $fid, $store_id);
            $stmtFinishingStok->execute();
            $stmtFinishingStok->close();
        }
    }

    return true;
}

function updateStatusPembayaranTerbaru(mysqli $koneksi, int $order_id): void {
    // Cek total pembayaran dari tabel payment
    $stmtPayment = $koneksi->prepare("SELECT SUM(nominal) as total_bayar FROM payment WHERE order_id = ?");
    $stmtPayment->bind_param("i", $order_id);
    $stmtPayment->execute();
    $resultPayment = $stmtPayment->get_result();
    $total_bayar = 0;
    if ($rowPayment = $resultPayment->fetch_assoc()) {
        $total_bayar = (float)$rowPayment['total_bayar'];
    }
    $stmtPayment->close();

    // Ambil total harga order dari tabel orders
    $stmtOrder = $koneksi->prepare("SELECT total FROM orders WHERE order_id = ?");
    $stmtOrder->bind_param("i", $order_id);
    $stmtOrder->execute();
    $stmtOrder->bind_result($total_order);
    $stmtOrder->fetch();
    $stmtOrder->close();

    // Tentukan status pembayaran
    $status_bayar = ($total_bayar >= $total_order) ? 'LUNAS' : 'DP';

    // Update status pembayaran pada entry terbaru
    $stmtUpdatePayment = $koneksi->prepare("
        UPDATE payment 
        SET status = ? 
        WHERE payment_id = (
            SELECT payment_id FROM (
                SELECT payment_id FROM payment WHERE order_id = ? ORDER BY date DESC LIMIT 1
            ) AS subquery
        )
    ");
    $stmtUpdatePayment->bind_param("si", $status_bayar, $order_id);
    $stmtUpdatePayment->execute();
    $stmtUpdatePayment->close();
}

function updateOrderTotal(int $order_id, mysqli $koneksi): bool {
    $sql = "
        SELECT 
            oi.product_id,
            oi.quantity,
            oi.size,
            oi.unit,
            oi.judul,
            p.price,
            p.name,
            UPPER(COALESCE(p.type, '')) AS type,
            COALESCE(doi.diskon, 0) AS diskon
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id AND p.store_id = oi.store_id
        LEFT JOIN diskon_order_items doi ON doi.order_id = oi.order_id AND doi.product_id = oi.product_id
        WHERE oi.order_id = ?
    ";

    $stmt = $koneksi->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $outdoorGroups = [];
    $grand_total = 0;

    while ($row = $result->fetch_assoc()) {
        $product_id = $row['product_id'];
        $product_name = $row['name'];
        $type = $row['type'];
        $quantity = (int)$row['quantity'];
        $size_str = $row['size'];
        $judul = strtoupper(trim($row['judul']));
        $unit = $row['unit'];
        $price = (int)$row['price'];
        $diskon = (int)$row['diskon'];

        $is_manual = empty($product_id);

        if ($is_manual) {
            $type = '';
            $product_id = 'manual_' . $judul;
        }

        if (($type === 'OUTDOOR') && $product_name != 'ONEWAY') {
            if (!isset($outdoorGroups[$product_id])) {
                $outdoorGroups[$product_id] = [
                    'price' => $price,
                    'diskon' => $diskon,
                    'total_size' => 0,
                ];
            }

            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $size_str, $matches)) {
                $panjang = floatval($matches[1]);
                $lebar = floatval($matches[2]);
                $luas = $panjang * $lebar;
                $outdoorGroups[$product_id]['total_size'] += $luas * $quantity;
            } else {
                $outdoorGroups[$product_id]['total_size'] += 0;
            }
        } else {
            // Non-OUTDOOR → ambil harga dari unit
            $harga_satuan = (int)$unit;
            $amount = $harga_satuan * $quantity;
            $grand_total += $amount;
        }
    }

    $stmt->close();

    // Hitung total untuk OUTDOOR
    foreach ($outdoorGroups as $group) {
        $harga_per_meter = max($group['price'] - $group['diskon'], 0);
        $total_size = $group['total_size'];
        $amount = ($total_size < 1) ? $harga_per_meter : ($total_size * $harga_per_meter);
        $grand_total += $amount;
    }

    // Bulatkan ke bawah kelipatan 500
    $grand_total = floor($grand_total / 500) * 500;

    $stmt = $koneksi->prepare("UPDATE orders SET total = ? WHERE order_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $grand_total, $order_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function generateNomorator(mysqli $koneksi, int $store_id, string $sys): string {

    if ($sys == 'OFFLINE') {
        $maxNomorator = 199999;
        $defaultStart = 100001;
    }elseif ($sys == 'ONLINE'){
        $defaultStart = 200001;
        $maxNomorator = 299999;
    }

    $stmt = $koneksi->prepare("
        SELECT session, last_nomorator 
        FROM nomorator_sessions 
        WHERE store_id = ? AND system = ?
        ORDER BY session DESC LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $koneksi->error);
    }
    $stmt->bind_param("is", $store_id, $sys);
    $stmt->execute();
    $stmt->bind_result($session, $lastNomorator);
    $found = $stmt->fetch();
    $stmt->close();

    if ($found) {
        if ($lastNomorator >= $maxNomorator) {
            $session += 1;
            $nextNomorator = $defaultStart;

            $stmtInsert = $koneksi->prepare("
                INSERT INTO nomorator_sessions (store_id, system, session, last_nomorator) 
                VALUES (?, ?, ?, ?)
            ");
            $stmtInsert->bind_param("isii", $store_id, $sys, $session, $nextNomorator);
            $stmtInsert->execute();
            $stmtInsert->close();
        } else {
            $nextNomorator = $lastNomorator + 1;

            $stmtUpdate = $koneksi->prepare("
                UPDATE nomorator_sessions 
                SET last_nomorator = ? 
                WHERE store_id = ? AND system = ? AND session = ?
            ");
            $stmtUpdate->bind_param("iisi", $nextNomorator, $store_id, $sys, $session);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
    } else {
        $session = 1;
        $nextNomorator = $defaultStart;

        $stmtInsert = $koneksi->prepare("
            INSERT INTO nomorator_sessions (store_id, system, session, last_nomorator) 
            VALUES (?, ?, ?, ?)
        ");
        $stmtInsert->bind_param("isii", $store_id, $sys, $session, $nextNomorator);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    return str_pad($nextNomorator, 6, '0', STR_PAD_LEFT);
}

function tampilkanTabelOrders(array $orders, $koneksi, array $users, string $role, string $sistim): void {
    if (empty($orders)) {
        echo '<div class="alert alert-warning">Data orders kosong.</div>';
        return;
    }

    // Ambil total bayar per order_id sekaligus (bisa dengan 1 query)
    $orderIds = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    $sql = "SELECT order_id, COALESCE(SUM(nominal),0) as total_paid FROM payment WHERE order_id IN ($placeholders) GROUP BY order_id";
    $stmtPay = $koneksi->prepare($sql);

    // bind_param dinamis sesuai jumlah order_id
    $types = str_repeat('i', count($orderIds));
    $stmtPay->bind_param($types, ...$orderIds);
    $stmtPay->execute();
    $resultPay = $stmtPay->get_result();

    $payments = [];
    while ($row = $resultPay->fetch_assoc()) {
        $payments[$row['order_id']] = (int)$row['total_paid'];
    }
    $stmtPay->close();

    ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-smaller <?php if ($sistim == 'OFFLINE'){ echo('table-prim'); } else{echo('table-dan');} ?>">
            
            <thead class="<?php if ($sistim == 'OFFLINE'){ echo('table-primary'); } else{echo('table-danger');} ?>">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th style="width: 100px;">Nomorator</th>
                    <th>Nama Customer</th>
                    <?php if ($role == 'ADMIN' || $role == 'MANAGER'){ ?><th style="width: 160px;">Nomor HP</th><?php }?>
                    <th style="width: 140px;">Total</th>
                    <th style="width: 160px;">Deadline</th>
                    <th style="width: 80px;">OP</th>
                    <?php if ($role == 'ADMIN' || $role == 'MANAGER' || $role == 'ONLINE') { ?><th class="aksi-cell" style="width: 200px;">Aksi</th> <?php }?>
                    <th style="width: 140px;">Bayar</th>
                    <th style="width: 150px;">Keterangan</th>
                    <th style="width: 30px;" class="position-relative p-0">
                        <input type="checkbox" class="form-check-input check-all order-checkbox position-absolute top-0 start-0 w-100 h-100 m-0"    >
                    </th>

                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $no => $order): 
                    $orderId = $order['order_id'];
                    $totalPaid = $payments[$orderId] ?? 0;
                    $isLunas = ($order['total'] <= $totalPaid);
                ?>
                <tr class="order-row" data-order-id="<?= $orderId ?>" data-store-id="<?= $order['store_id'] ?>">
                    <td><?= $no + 1 ?></td>
                    <td><?= htmlspecialchars($order['nomorator']) ?></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <?php if ($role == 'ADMIN' || $role == 'MANAGER'){ ?><td><?= htmlspecialchars($order['nomor'] ?? '-') ?></td><?php }?>
                    <td><?= number_format($order['total'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($order['deadline']) ?></td>
                    <td><?= $users[$order['user_id']] ?? '-' ?></td>
                    <?php if ($role == 'ADMIN' || $role == 'MANAGER' || $role == 'ONLINE') { ?>
                    <td class="aksi-cell">
                        
                            

                        <div class="btn-group-row">
                                <?php
                                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                                $mobile = false;
                                if (strpos($userAgent, 'Mobile') !== false) {
                                $mobile = true;
                                }
                                
                                if ($mobile){ ?>
                                <form action="nota.php" method="post">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <button type="submit" class="btn btn-sm btn-success" data-order-id="<?= $orderId ?>">Buka</button>
                                </form>
                                <?php } ?>
                                <button class="btn btn-sm btn-primary btn-edit" 
                                        data-id="<?= $orderId ?>" >
                                    Edit
                                </button>

                                <?php if (!$isLunas): ?>
                                <button type="button" class="btn btn-sm btn-danger btn-pay" data-order-id="<?= $orderId ?>">Bayar</button>
                                <?php endif; ?>

                                <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-warning dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    Print Struk
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                    <a class="dropdown-item" onclick="printStruk(<?= $order['order_id'] ?>, <?= $order['store_id'] ?>)" >
                                        Print Struk (Print langsung)
                                    </a>
                                    </li>
                                    <li>
                                    <a class="dropdown-item" onclick="printStrukPDF(<?= $order['order_id'] ?>, <?= $order['store_id'] ?>)">
                                        Print PDF
                                    </a>
                                    </li>
                                </ul>
                                </div>
                                <?php if ($role == 'ADMIN' || $role == 'MANAGER'){ ?>
                                <a href="../laporan/transaksi_detil?scrl_id=<?= htmlspecialchars($order['order_id']) ?>&start_date=<?= date('Y-m-d', strtotime($order['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($order['date'])) ?>" target="_black" class="btn btn-sm btn-success">Cek</a>
                                <?php }?>
                                

                        </div>
                    </td>
                    <?php } ?>
                    <td>
                        <?php
                        $projectStatus = '';
                        $projectProcess = '';
                        $projectUser = 0;

                        $stmtPay = $koneksi->prepare("
                            SELECT status, SUM(nominal) as total, payment_method
                            FROM payment 
                            WHERE order_id = ? 
                            GROUP BY status
                        ");
                        $stmtPay->bind_param("i", $orderId);
                        $stmtPay->execute();
                        $result = $stmtPay->get_result();

                        $totalDP = 0;
                        $isLunasStatus = false;
                        $lunas_method = '';

                        while ($row = $result->fetch_assoc()) {
                            if ($row['status'] === 'DP') {
                                $totalDP = (int)$row['total'];
                            } elseif ($row['status'] === 'LUNAS') {
                                $isLunasStatus = true;
                                $lunas_method = $row['payment_method'];
                            }
                        }
                        $stmtPay->close();

                        $stmtProj = $koneksi->prepare("SELECT status, process, user_id FROM projects WHERE order_id = ? ORDER BY date DESC LIMIT 1");
                        $stmtProj->bind_param("i", $orderId);
                        $stmtProj->execute();
                        $stmtProj->bind_result($projectStatus, $projectProcess, $projectUser);
                        $stmtProj->fetch();
                        $stmtProj->close();

                        if ($isLunasStatus) {
                            echo "<div>" . "LUNAS " . $lunas_method . "</div>";
                        } elseif ($totalDP > 0) {
                            echo "<div style='font-size: 12px; line-height: 12px;'>" . "DP: " . number_format($totalDP, 0, ',', '.') . " <br> Sisa : " . number_format($order['total'] - $totalDP, 0, ',', '.') . "</div>";
                        } elseif (!empty($projectStatus)) {
                            echo htmlspecialchars($projectStatus);
                        } else {
                            echo '-';
                        }
                        ?>

                    </td>
                    <td>
                    <?php 
                        if ($projectProcess == 'DIAMBIL') {
                            $initial = '';
                            $stmtUser = $koneksi->prepare("SELECT initial FROM users WHERE user_id = ? LIMIT 1");
                            $stmtUser->bind_param('i', $projectUser);
                            $stmtUser->execute();
                            $stmtUser->bind_result($initial);
                            $stmtUser->fetch();
                            $stmtUser->close();
                            echo htmlspecialchars($projectProcess) . ' ' . $initial;
                        } elseif ($isLunasStatus) {
                            echo htmlspecialchars($projectProcess);
                        } elseif ($totalDP > 0) {
                            echo htmlspecialchars($projectProcess);
                        } elseif (!empty($projectStatus)) {
                            echo htmlspecialchars($projectStatus);
                        } else {
                            echo '-';
                        }
                    ?>
                    </td>
                    <?php if ($role == 'ADMIN' || $role == 'MANAGER' || $role == 'ONLINE') { ?>
                    
                    <?php }?>
                    <td class="aksi-cell position-relative p-0">
                    <input 
                        class="order-checkbox position-absolute top-0 start-0 w-100 h-100 m-0" 
                        type="checkbox" 
                        value="<?= $orderId ?>" 
                        style="cursor: pointer;"
                    >
                    </td>


                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
    <button id="btn-proses-massal" class="btn btn-success" style="
        position: fixed;
        bottom: 90px;
        right: 20px;
        display: none;
        z-index: 9999;
    ">
        Update Proses Terpilih
    </button>

    <?php
}

?>
