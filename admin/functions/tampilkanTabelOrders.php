<?php
function formatKeInternasional($nomor) {
    $nomor = preg_replace('/[^0-9]/', '', $nomor);

    if (strpos($nomor, '0') === 0) {
        $nomor = '62' . substr($nomor, 1);
    } 
    return '+' . $nomor;
}

function tampilkanTabelOrders(array $orders, $koneksi, array $usersInitial, string $role, string $sistim): void {

    if (empty($orders)) {
        echo '<div class="alert alert-warning">Data orders kosong.</div>';
        return;
    }

    $orderIds = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));

    $sqlPay = "
        SELECT 
            order_id,
            SUM(CASE WHEN status = 'DP' THEN nominal ELSE 0 END) as total_dp,
            MAX(CASE WHEN status = 'LUNAS' THEN 1 ELSE 0 END) as is_lunas,
            MAX(CASE WHEN status = 'LUNAS' THEN payment_method ELSE NULL END) as lunas_method,
            COALESCE(SUM(nominal),0) as total_paid
        FROM payment
        WHERE order_id IN ($placeholders)
        GROUP BY order_id
    ";

    $stmt = $koneksi->prepare($sqlPay);
    $stmt->bind_param($types, ...$orderIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $paymentData = [];
    while ($row = $result->fetch_assoc()) {
        $paymentData[$row['order_id']] = $row;
    }
    $stmt->close();

    $sqlProj = "
        SELECT p1.order_id, p1.status, p1.process, p1.user_id
        FROM projects p1
        INNER JOIN (
            SELECT order_id, MAX(date) as max_date
            FROM projects
            WHERE order_id IN ($placeholders)
            GROUP BY order_id
        ) p2 
        ON p1.order_id = p2.order_id 
        AND p1.date = p2.max_date
    ";

    $stmtProj = $koneksi->prepare($sqlProj);
    $stmtProj->bind_param($types, ...$orderIds);
    $stmtProj->execute();
    $resultProj = $stmtProj->get_result();

    $projectData = [];
    $userIds = [];

    while ($row = $resultProj->fetch_assoc()) {
        $projectData[$row['order_id']] = $row;
        if (!empty($row['user_id'])) {
            $userIds[] = $row['user_id'];
        }
    }
    $stmtProj->close();

    $userIds = array_unique($userIds);
    

    ?>

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-smaller order-table <?= $sistim == 'OFFLINE' ? 'table-prim' : 'table-dan' ?>">

            <thead class="<?= $sistim == 'OFFLINE' ? 'table-primary' : 'table-danger' ?>">
                <tr>
                    <th style="width:50px;" onclick="sortTable(this, 0)">No <span class="sort-icon">▲▼</span></th>

                    <th onclick="sortTable(this, 1)">INV <span class="sort-icon">▲▼</span></th>

                    <th onclick="sortTable(this, 2)">Nama Customer <span class="sort-icon">▲▼</span></th>

                    <?php if ($role == 'ADMIN' || $role == 'MANAGER'){ ?>
                        <th onclick="sortTable(this, 3)">Nomor HP <span class="sort-icon">▲▼</span></th>
                    <?php } ?>

                    <th onclick="sortTable(this, <?= ($role == 'ADMIN' || $role == 'MANAGER') ? 4 : 3 ?>)">
                        Total <span class="sort-icon">▲▼</span>
                    </th>

                    <th onclick="sortTable(this, <?= ($role == 'ADMIN' || $role == 'MANAGER') ? 5 : 4 ?>)">
                        Deadline <span class="sort-icon">▲▼</span>
                    </th>

                    <th onclick="sortTable(this, <?= ($role == 'ADMIN' || $role == 'MANAGER') ? 6 : 5 ?>)">
                        OP <span class="sort-icon">▲▼</span>
                    </th>

                    <?php if ($role == 'ADMIN' || $role == 'MANAGER' || $role == 'ONLINE') { ?>
                        <th style="width:200px;">Aksi</th>
                    <?php } ?>

                    <th>Bayar</th>
                    <th>Keterangan</th>

                    <th class="position-relative p-0" style="width:30px;">
                        <input 
                            type="checkbox"
                            class="check-all order-checkbox form-check-input position-absolute top-0 start-0 w-100 h-100 m-0"
                        >
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $no => $order):

                    $orderId = $order['order_id'];
                    $orderEnk = startEnk('enk', $orderId);

                    $pay = $paymentData[$orderId] ?? [];
                    $totalPaid = $pay['total_paid'] ?? 0;
                    $totalDP = $pay['total_dp'] ?? 0;
                    $isLunasStatus = $pay['is_lunas'] ?? 0;
                    $lunas_method = $pay['lunas_method'] ?? '';

                    $isLunas = ($order['total'] <= $totalPaid);

                    $proj = $projectData[$orderId] ?? [];
                    $projectStatus = $proj['status'] ?? '';
                    $projectProcess = $proj['process'] ?? '';
                    $projectUser = $proj['user_id'] ?? 0;

                    $initial = $usersInitial[$projectUser] ?? '';
                ?>

                <tr class="order-row" data-order-id="<?= $orderId ?>" data-id="<?= $orderEnk ?>">

                    <td><?= $no + 1 ?></td>
                    <td><?= htmlspecialchars($order['nomorator']) ?></td>
                    <td><?= htmlspecialchars(ucwords(strtolower($order['customer_name']))) ?></td>

                    <?php if ($role == 'ADMIN' || $role == 'MANAGER'){ ?>
                        <td><?= htmlspecialchars(formatKeInternasional($order['nomor']) ?? '-') ?></td>
                    <?php } ?>

                    <td><?= number_format($order['total'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($order['deadline']))) ?></td>
                    <td><?= $usersInitial[$order['user_id']] ?? '-' ?></td>

                    <?php if ($role == 'ADMIN' || $role == 'MANAGER' || $role == 'ONLINE') { ?>
                    <td class="aksi-cell">
                        <div class="btn-group-row">

                            <?php
                            $mobile = strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false;
                            if ($mobile){ ?>
                                <form action="nota.php" method="post">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Buka</button>
                                </form>
                            <?php } ?>

                            <button class="btn btn-sm btn-primary btn-edit" data-id="<?= $orderId ?>">Edit</button>

                            <?php if (!$isLunas): ?>
                                <button class="btn btn-sm btn-danger btn-pay" data-order-id="<?= $orderId ?>">Bayar</button>
                            <?php endif; ?>

                            <div class="btn-group">
                                <button class="btn btn-sm btn-warning dropdown-toggle" data-bs-toggle="dropdown">
                                    Print Struk
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" onclick="printStruk(<?= $orderId ?>)">
                                            Print langsung
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" onclick="printStrukPDF(<?= $orderId ?>)">
                                            Print PDF
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <?php if ($role == 'ADMIN' || $role == 'MANAGER'){ ?>
                                <a href="../laporan/transaksi_detil?scrl_id=<?= $orderId ?>&start_date=<?= date('Y-m-d', strtotime($order['date'])) ?>&end_date=<?= date('Y-m-d', strtotime($order['date'])) ?>" target="_blank" class="btn btn-sm btn-success">Cek</a>
                            <?php } ?>

                        </div>
                    </td>
                    <?php } ?>

                    <!-- BAYAR -->
                    <td>
                        <?php
                        if ($isLunasStatus) {
                            echo "LUNAS " . $lunas_method;
                        } elseif ($totalDP > 0) {
                            echo "<div style='font-size:12px'>DP: " . number_format($totalDP, 0, ',', '.') .
                                 "<br>Sisa: " . number_format($order['total'] - $totalDP, 0, ',', '.') . "</div>";
                        } elseif (!empty($projectStatus)) {
                            echo htmlspecialchars($projectStatus);
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>

                    <td>
                        <?php
                            $projectProcess = ucwords(strtolower($projectProcess));
                            $projectStatus = ucwords(strtolower($projectStatus));
                        if ($projectProcess == 'DIAMBIL') {
                            echo $projectProcess . ' ' . $initial;
                        } elseif (!empty($projectProcess)) {
                            echo $projectProcess;
                        } elseif (!empty($projectStatus)) {
                            echo $projectStatus;
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>

                    <td class="position-relative p-0" style="width:30px;">
                        <input 
                            type="checkbox"
                            class="order-checkbox form-check-input position-absolute top-0 start-0 w-100 h-100 m-0"
                            value="<?= $orderId ?>"
                            style="cursor:pointer;"
                        >
                    </td>

                </tr>

                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button id="btn-proses-massal" class="btn btn-success" style="position: fixed; bottom: 90px; right: 20px; display:none;">
        Update Proses Terpilih
    </button>

<?php
}