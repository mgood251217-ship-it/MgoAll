<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../connect.php';
require_once BASE_PATH . '/global_functions.php';

$start_date_f = $_GET['start_date'] ?? date('Y-m-d');
$end_date_f = $_GET['end_date'] ?? date('Y-m-d');

$start_date = "$start_date_f 00:00:00";
$end_date = "$end_date_f 23:59:59";

$toko_list = [];
if ($access == 'ALL') {
  $result = $koneksi->query("SELECT * FROM stores");
}else {
  $stmtStore = $koneksi->prepare("SELECT * FROM stores WHERE administrator = ?");
  $stmtStore->bind_param("s", $access);
  $stmtStore->execute();
  $result = $stmtStore->get_result();
}
while ($row = $result->fetch_assoc()) {
    $toko_list[$row['store_id']] = $row['name'];
}

$kategori_list = ['OUTDOOR', 'INDOOR', 'DTF', 'DTF UV', 'LASER A3', 'LASER A3 Lainnya'];
$produksi = [];
$detail_produk = [];
foreach ($kategori_list as $kategori) {
    $produksi[$kategori] = array_fill_keys(array_keys($toko_list), 0);
    $detail_produk[$kategori] = [];
}

foreach ($toko_list as $store_id => $store_name) {
    $order_ids = [];
    $order_res = $koneksi->prepare("SELECT order_id FROM orders WHERE store_id = ? AND date BETWEEN ? AND ?");
    $order_res->bind_param("iss", $store_id, $start_date, $end_date);
    $order_res->execute();
    $order_result = $order_res->get_result();
    while ($order_row = $order_result->fetch_assoc()) {
        $order_ids[] = $order_row['order_id'];
    }
    $order_res->close();

    if (empty($order_ids)) continue;

    $order_placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $order_types = str_repeat('i', count($order_ids) + 1);

    // --- OUTDOOR ---
    $stmt = $koneksi->prepare("SELECT product_id, name, type FROM products WHERE (type = 'OUTDOOR' OR type = 'PAKET INDOOR OUTDOOR') AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $produk_result = $stmt->get_result();
    $produk_all = $produk_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $outdoor_products = array_filter($produk_all, fn($p) => $p['type'] === 'OUTDOOR');
    $paket_products = array_filter($produk_all, fn($p) => $p['type'] === 'PAKET INDOOR OUTDOOR');

    $products = $outdoor_products;
    foreach ($paket_products as $paket) {
        foreach ($outdoor_products as $outdoor) {
            if (stripos($paket['name'], $outdoor['name']) !== false) {
                $products[] = ['product_id' => $paket['product_id'], 'name' => $outdoor['name']];
                break;
            }
        }
    }

    foreach ($products as $product) {
        $pid = $product['product_id'];
        $params = array_merge([$pid], $order_ids);
        $queryStr = "SELECT size, quantity FROM order_items WHERE product_id = ? AND order_id IN ($order_placeholders)";
        $query = $koneksi->prepare($queryStr);
        $query->bind_param($order_types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $total_m2 = 0;
        while ($row = $res->fetch_assoc()) {
            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $row['size'], $m)) {
                $total_m2 += (float)$m[1] * (float)$m[2] * (int)$row['quantity'];
            }
        }
        $query->close();

        $produksi['OUTDOOR'][$store_id] += $total_m2;

        $nama_asli = $product['name'];
        foreach ($outdoor_products as $main) {
            if ($product['product_id'] !== $main['product_id'] && stripos($product['name'], $main['name']) !== false) {
                $nama_asli = $main['name'];
                break;
            }
        }

        if (!isset($detail_produk['OUTDOOR'][$nama_asli])) {
            $detail_produk['OUTDOOR'][$nama_asli] = array_fill_keys(array_keys($toko_list), 0);
        }
        $detail_produk['OUTDOOR'][$nama_asli][$store_id] += $total_m2;
    }

    // --- INDOOR ---
    $stmt = $koneksi->prepare("SELECT product_id, name, type FROM products WHERE (type = 'INDOOR' OR type = 'PAKET INDOOR OUTDOOR') AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $produk_result = $stmt->get_result();
    $produk_all = $produk_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $indoor_products = array_filter($produk_all, fn($p) => $p['type'] === 'INDOOR');
    $paket_indoor = array_filter($produk_all, fn($p) => $p['type'] === 'PAKET INDOOR OUTDOOR');

    $products = $indoor_products;
    foreach ($paket_indoor as $paket) {
        foreach ($indoor_products as $indoor) {
            if (stripos($paket['name'], $indoor['name']) !== false) {
                $products[] = ['product_id' => $paket['product_id'], 'name' => $indoor['name']];
                break;
            }
        }
    }

    foreach ($products as $product) {
        $pid = $product['product_id'];
        $params = array_merge([$pid], $order_ids);
        $queryStr = "SELECT size, quantity FROM order_items WHERE product_id = ? AND order_id IN ($order_placeholders)";
        $query = $koneksi->prepare($queryStr);
        $query->bind_param($order_types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $total_m2 = 0;
        while ($row = $res->fetch_assoc()) {
            if (preg_match('/^([\d.]+)[xX]([\d.]+)$/', $row['size'], $m)) {
                $total_m2 += (float)$m[1] * (float)$m[2] * (int)$row['quantity'];
            }
        }
        $query->close();

        $produksi['INDOOR'][$store_id] += $total_m2;

        $nama_asli = $product['name'];
        foreach ($indoor_products as $main) {
            if ($product['product_id'] !== $main['product_id'] && stripos($product['name'], $main['name']) !== false) {
                $nama_asli = $main['name'];
                break;
            }
        }

        if (!isset($detail_produk['INDOOR'][$nama_asli])) {
            $detail_produk['INDOOR'][$nama_asli] = array_fill_keys(array_keys($toko_list), 0);
        }
        $detail_produk['INDOOR'][$nama_asli][$store_id] += $total_m2;
    }
    // --- DTF dan DTF UV ---
    // Ambil produk DTF (type DTF), nanti dipisah berdasarkan name mengandung UV atau tidak
    $stmt = $koneksi->prepare("SELECT product_id, name, type FROM products WHERE type = 'DTF' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $produk_result = $stmt->get_result();
    $dtf_products = $produk_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Pisahkan DTF UV dan DTF biasa
    $dtf_uv_products = array_filter($dtf_products, fn($p) => stripos($p['name'], 'UV') !== false);
    $dtf_plain_products = array_filter($dtf_products, fn($p) => stripos($p['name'], 'UV') === false);

    // Fungsi helper untuk hitung DTF (dan DTF UV)
    $process_dtf = function($products, $kategori_label) use ($koneksi, $order_ids, $order_placeholders, $order_types, $store_id, $toko_list, &$produksi, &$detail_produk) {
        foreach ($products as $product) {
            $pid = $product['product_id'];
            $params = array_merge([$pid], $order_ids);
            $queryStr = "SELECT size, quantity, unit FROM order_items WHERE product_id = ? AND order_id IN ($order_placeholders)";
            $query = $koneksi->prepare($queryStr);
            $query->bind_param($order_types, ...$params);
            $query->execute();
            $res = $query->get_result();

            $total_meter = 0;
            while ($row = $res->fetch_assoc()) {
                $qty = (int)$row['quantity'];
                $unit = strtoupper(trim($row['unit']));
                $size = $row['size'];

            if (preg_match('/^([\d.]+)[xX]/', $size, $m)) {
                $panjang = (float)$m[1];
                $total_meter += $panjang * $qty;
            } elseif (stripos($product['name'], 'A3') !== false) {
                $total_meter += $qty * 0.3;
            } else {
                $total_meter += $qty;
            }



            }
            $query->close();

            $produksi[$kategori_label][$store_id] += $total_meter;

            $nama_asli = $product['name'];

            if (!isset($detail_produk[$kategori_label][$nama_asli])) {
                $detail_produk[$kategori_label][$nama_asli] = array_fill_keys(array_keys($toko_list), 0);
            }
            $detail_produk[$kategori_label][$nama_asli][$store_id] += $total_meter;
        }
    };

    // Proses DTF biasa
    $process_dtf($dtf_plain_products, 'DTF');
    // Proses DTF UV
    $process_dtf($dtf_uv_products, 'DTF UV');

    // --- LASER A3 ---
    $stmt = $koneksi->prepare("SELECT product_id, name FROM products WHERE type = 'LASER A3' AND store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $laser_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($laser_products as $product) {
        $pid = $product['product_id'];
        $params = array_merge([$pid], $order_ids);
        $queryStr = "SELECT quantity FROM order_items WHERE product_id = ? AND order_id IN ($order_placeholders)";
        $query = $koneksi->prepare($queryStr);
        $query->bind_param($order_types, ...$params);
        $query->execute();
        $res = $query->get_result();

        $total_qty = 0;
        while ($row = $res->fetch_assoc()) {
            $total_qty += (int)$row['quantity'];
        }
        $query->close();

        $produksi['LASER A3'][$store_id] += $total_qty;

        $nama_asli = $product['name'];
        if (!isset($detail_produk['LASER A3'][$nama_asli])) {
            $detail_produk['LASER A3'][$nama_asli] = array_fill_keys(array_keys($toko_list), 0);
        }
        $detail_produk['LASER A3'][$nama_asli][$store_id] += $total_qty;
    }

  // --- LASER A3 Lainnya (STAMP, KN, KN BB, MERCHANDISE) ---
  $stmt = $koneksi->prepare("
      SELECT product_id, name, type FROM products 
      WHERE store_id = ? AND (
          type = 'STAMP' OR 
          type = 'KARTU NAMA' OR 
          type = 'MERCENDISE'
      )
  ");
  $stmt->bind_param("i", $store_id);
  $stmt->execute();
  $lainnya_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  foreach ($lainnya_products as $product) {
      $pid = $product['product_id'];
      $product_name = strtoupper($product['name']);
      $product_type = strtoupper($product['type']);

      $params = array_merge([$pid], $order_ids);
      $queryStr = "SELECT quantity FROM order_items WHERE product_id = ? AND order_id IN ($order_placeholders)";
      $query = $koneksi->prepare($queryStr);
      $query->bind_param($order_types, ...$params);
      $query->execute();
      $res = $query->get_result();

      $total_qty = 0;
      while ($row = $res->fetch_assoc()) {
          $total_qty += (int)$row['quantity'];
      }
      $query->close();

      // Tambahkan ke total produksi LASER A3 Lainnya
      $produksi['LASER A3 Lainnya'][$store_id] += $total_qty;

      // Tentukan label berdasarkan nama dan tipe produk
      if (str_starts_with($product_name, 'KN BB')) {
          $label = 'KN BB';
      } elseif (str_starts_with($product_name, 'KN')) {
          $label = 'KN';
      } elseif (str_starts_with($product_name, 'STAMP')) {
          $label = 'STAMP';
      } elseif (
          $product_type === 'MERCENDISE' &&
          (str_contains($product_name, 'ID CARD') ||
          str_contains($product_name, 'THUMBLER') ||
          str_contains($product_name, 'JAM') ||
          str_contains($product_name, 'GANCI') ||
          str_contains($product_name, 'PIN'))
      ) {
          $label = $product['name']; // Pakai nama asli untuk tampilan
      } else {
          continue; // Skip produk lainnya (tidak dimasukkan ke detail)
      }

      if (!isset($detail_produk['LASER A3 Lainnya'][$label])) {
          $detail_produk['LASER A3 Lainnya'][$label] = array_fill_keys(array_keys($toko_list), 0);
      }

      $detail_produk['LASER A3 Lainnya'][$label][$store_id] += $total_qty;
  }



}

?>



<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Produksi & Mesin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/administrator/assets/css/content.css">
</head>
<body>

<?php include BASE_PATH . '/administrator/navbar.php'; ?>

<div id="mainWrapper">
  <?php include BASE_PATH . '/administrator/sidebar.php'; ?>

  <div id="contentWrapper">
    <main id="mainContent">
      <div class="container-fluid">
        <h1 class="mb-4">Produksi & Mesin</h1>
        <form method="get" class="row g-2 mb-3">
          <div class="col-md-3">
            <label for="start_date" class="form-label">Dari Tanggal</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date_f) ?>" />
          </div>
          <div class="col-md-3">
            <label for="end_date" class="form-label">Sampai Tanggal</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date_f) ?>" />
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-filter"></i> Filter Produksi
            </button>
          </div>
        </form>

        <div class="table-responsive mb-4">
            <table class="table table-modern table-bordered text-center align-middle">
              <thead>
                <tr>
                  <th style="min-width: 160px;">Toko</th>
                  <?php foreach ($kategori_list as $kategori): ?>
                    <th style="min-width: 120px;"><?= htmlspecialchars($kategori) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($toko_list as $store_id => $nama_toko): ?>
                  <tr>
                    <td class="text-start"><?= htmlspecialchars($nama_toko) ?></td>
                    <?php foreach ($kategori_list as $kategori): ?>
                      <?php
                        $value = $produksi[$kategori][$store_id] ?? 0;
                        $collapseId = "detail-{$store_id}-" . md5($kategori);
                      ?>
                      <td class="text-end" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>">
                        <?php
                          if (in_array($kategori, ['OUTDOOR', 'INDOOR'])) {
                            echo number_format($value, 2) . ' m²';
                          } elseif (in_array($kategori, ['DTF', 'DTF UV'])) {
                            echo number_format($value, 2) . ' m';
                          } elseif ($kategori === 'LASER A3') {
                            echo number_format($value, 0) . ' lbr';
                          } else {
                            echo number_format($value, 0) . ' pcs';
                          }
                        ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>

                  <?php foreach ($kategori_list as $kategori): 
                    $collapseId = "detail-{$store_id}-" . md5($kategori);
                    $produkDetail = $detail_produk[$kategori] ?? [];
                  ?>
                  <tr class="collapse bg-light" id="<?= $collapseId ?>">
                    <td colspan="<?= count($kategori_list) + 1 ?>" class="p-0">
                      <div class="table-responsive">
                        <table class="table table-sm table-bordered text-start mb-0 table-detail">
                          <thead>
                            <tr>
                              <th style="min-width: 160px;">Nama Produk</th>
                              <th style="min-width: 120px;">Jumlah</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php
                            // Tampilkan produk yang punya data untuk toko dan kategori ini
                            foreach ($produkDetail as $nama_produk => $per_toko) {
                              $value = $per_toko[$store_id] ?? 0;

                              // Jangan konversi ulang ke 0.3, anggap sudah meter
                              if (in_array($kategori, ['OUTDOOR', 'INDOOR'])) {
                                $display = number_format($value, 2) . ' m²';
                              } elseif (in_array($kategori, ['DTF', 'DTF UV'])) {
                                $display = number_format($value, 2) . ' m';
                              } elseif ($kategori === 'LASER A3') {
                                $display = number_format($value, 0) . ' lbr';
                              } else {
                                $display = number_format($value, 0) . ' pcs';
                              }

                              if ($value > 0) {
                                echo "<tr>";
                                echo "<td class='ps-4'>" . htmlspecialchars($nama_produk) . "</td>";
                                echo "<td class='text-end'>{$display}</td>";
                                echo "</tr>";
                              }
                            }

                            ?>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>

                <?php endforeach; ?>
              </tbody>
            </table>
        </div>

      </div>
    </main>

    <?php include BASE_PATH . '/administrator/footer.php'; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
