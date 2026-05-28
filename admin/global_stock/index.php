<?php

require_once '../connect.php';
require_once BASE_PATH . '/session.php';

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$days_in_month = date('t', strtotime($selected_month . '-01'));

$available_stores = [];
$queryStores = mysqli_query($koneksi, "SELECT store_id, name FROM stores WHERE store_id != '$store_id' ORDER BY name ASC");
if ($queryStores) {
    while ($row = mysqli_fetch_assoc($queryStores)) {
        $available_stores[] = $row;
    }
}
$other_stores_stocks = [];
$qOtherStocks = mysqli_query($koneksi, "
    SELECT gs.id, gs.store_id, gs.name, gs.size, gsc.name as category_name
    FROM global_stocks gs
    JOIN global_stock_categories gsc ON gs.global_stock_category_id = gsc.id
    WHERE gs.store_id != '$store_id'
    ORDER BY gsc.name ASC, gs.name ASC
");
if ($qOtherStocks) {
    while ($row = mysqli_fetch_assoc($qOtherStocks)) {
        $other_stores_stocks[] = $row;
    }
}

$deliveries = [];
$queryDeliveries = mysqli_query($koneksi, "
    SELECT 
        d.id, d.qty, d.date, 
        s_from.name AS from_store, 
        s_to.name AS to_store, 
        gs_from.name AS item_name, 
        gs_from.size AS item_size,
        d.store_id AS sender_id
    FROM global_stock_deliveries d
    JOIN stores s_from ON d.store_id = s_from.store_id
    JOIN stores s_to ON d.to_store_id = s_to.store_id
    JOIN global_stocks gs_from ON d.global_stock_id = gs_from.id
    WHERE d.store_id = '$store_id' OR d.to_store_id = '$store_id'
    ORDER BY d.date DESC, d.id DESC
    LIMIT 100
");
if ($queryDeliveries) {
    while ($row = mysqli_fetch_assoc($queryDeliveries)) {
        $deliveries[] = $row;
    }
}

$categories = [];
$queryCategory = mysqli_query($koneksi, "
    SELECT *
    FROM global_stock_categories
    WHERE store_id = '$store_id'
    ORDER BY name ASC
");
while ($row = mysqli_fetch_assoc($queryCategory)) {
    $categories[] = $row;
}

$stocks = [];

$queryStocks = mysqli_query($koneksi, "
    SELECT
        gs.*,
        gsc.name AS category_name
    FROM global_stocks gs
    JOIN global_stock_categories gsc
        ON gsc.id = gs.global_stock_category_id
    WHERE gs.store_id = '$store_id'
    ORDER BY gsc.name ASC, gs.name ASC, gs.size ASC
");

while ($row = mysqli_fetch_assoc($queryStocks)) {
    $id = $row['id'];
    $stocks[$id] = $row;
    $stocks[$id]['sa_awal'] = 0; 
    $stocks[$id]['sa_akhir'] = 0; 
    $stocks[$id]['daily'] = [];
    
    for ($i = 1; $i <= 31; $i++) {
        $stocks[$id]['daily'][$i] = ['sm' => 0, 'sk' => 0];
    }
}

$queryAwal = mysqli_query($koneksi, "
    SELECT global_stock_id, initial_stock, final_stock
    FROM global_stock_monthly_values
    WHERE store_id = '$store_id' AND month_year = '$selected_month'
");
while ($row = mysqli_fetch_assoc($queryAwal)) {
    if (isset($stocks[$row['global_stock_id']])) {
        $stocks[$row['global_stock_id']]['sa_awal'] = $row['initial_stock'];
        $stocks[$row['global_stock_id']]['sa_akhir'] = $row['final_stock']; 
    }
}

$queryDaily = mysqli_query($koneksi, "
    SELECT global_stock_id, DAY(date) as day_date, stock_in, stock_out
    FROM global_stock_daily_values
    WHERE store_id = '$store_id' AND date LIKE '$selected_month%'
");
while ($row = mysqli_fetch_assoc($queryDaily)) {
    if (isset($stocks[$row['global_stock_id']])) {
        $day = (int)$row['day_date'];
        $stocks[$row['global_stock_id']]['daily'][$day]['sm'] += $row['stock_in'];
        $stocks[$row['global_stock_id']]['daily'][$day]['sk'] += $row['stock_out'];
    }
}

$grouped_stocks = [];
foreach ($stocks as $id => $s) {
    $cat_name = $s['category_name'];
    $item_name = $s['name'];
    
    if (!isset($grouped_stocks[$cat_name])) {
        $grouped_stocks[$cat_name] = [];
    }
    if (!isset($grouped_stocks[$cat_name][$item_name])) {
        $grouped_stocks[$cat_name][$item_name] = [];
    }
    $grouped_stocks[$cat_name][$item_name][$id] = $s;
}

$theme_colors = ['primary', 'success', 'danger', 'info', 'warning', 'secondary', 'dark'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Global Stock</title>
    <?php include BASE_PATH . '/header.php'; ?>

    <style>
        .table-excel th, .table-excel td {
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            padding: 5px;
            font-size: 13px;
        }
        .table-excel .text-start {
            text-align: left !important;
        }
        .col-sm-sk {
            min-width: 30px;
        }
        .update-cell {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .update-cell:hover {
            background-color: rgba(0, 0, 0, 0.1) !important;
            box-shadow: inset 0 0 5px rgba(0,0,0,0.2);
        }
        .dark-mode .update-cell:hover {
            background-color: rgba(255, 255, 255, 0.2) !important;
            box-shadow: inset 0 0 5px rgba(255,255,255,0.2);
        }
        .bg-awal { background-color: #fff3cd !important; font-weight: bold; }
        .bg-akhir { background-color: #d1e7dd !important; font-weight: bold; }
        .bg-total { background-color: #f8f9fa !important; font-weight: bold; }
        .dark-mode .bg-total { background-color: #2b2b2b !important; }
        .category-title {
            margin-top: 30px;
            margin-bottom: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .hover-container .edit-icon {
            opacity: 0;
            visibility: hidden;
            cursor: pointer;
            fill: #6c757d;
            margin-left: 5px;
            transition: opacity 0.2s ease, visibility 0.2s ease, fill 0.2s;
            vertical-align: middle;
        }
        .hover-container:hover .edit-icon {
            opacity: 1;
            visibility: visible;
        }
        .hover-container .edit-icon:hover {
            fill: #0d6efd;
        }

        /* LOADING SPINNER CSS */
        .global-loading {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .global-loading .loading-content {
            color: #fff;
            text-align: center;
        }
    </style>
</head>

<body>

<div id="global-loading" class="global-loading d-none">
    <div class="loading-content">
        <div class="spinner-border text-light" role="status"></div>
        <div class="mt-2">Loading...</div>
    </div>
</div>

<div id="main-wrapper" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include '../navbar.php'; ?>
    
    <div id="main-content" <?= ($mode === 1) ? 'class="dark-mode"' : '' ?>>
        <?php include '../sidebar.php'; ?>

        <div id="page-content-wrapper">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center gap-3">
                    <h1 class="mb-0">Global Stock</h1>
                    <form method="GET" class="mb-0">
                        <input type="month" name="month" class="form-control form-control-sm" value="<?= $selected_month ?>" onchange="this.form.submit()">
                    </form>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-secondary btn-sm text-white" data-bs-toggle="modal" data-bs-target="#historyDeliveryModal">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="vertical-align: sub;"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/><path d="M13 7h-2v6h6v-2h-4z"/></svg>
                        Riwayat
                    </button>
                    <button class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#sendStockModal">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="vertical-align: sub;"><path d="m21.426 11.095-17-8A.999.999 0 0 0 3.03 4.242L4.969 12 3.03 19.758a.998.998 0 0 0 1.396 1.147l17-8a1 1 0 0 0 0-1.81zM5.481 18.197l.839-3.357L12 12 6.32 9.16l-.839-3.357L18.651 12l-13.17 6.197z"/></svg> 
                        Kirim Barang
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">+ Kategori</button>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addStockModal">+ Barang</button>
                </div>
            </div>

            <?php 
                $color_index = 0;
                if (empty($grouped_stocks)): 
            ?>
                <div class="alert alert-info text-center">Tidak ada data stock</div>
            <?php 
                else:
                    foreach ($grouped_stocks as $cat_name => $items_group): 
                        $current_theme = $theme_colors[$color_index % count($theme_colors)];
                        $color_index++;
                        
                        $cat_id_for_edit = 0;
                        foreach ($categories as $c) {
                            if ($c['name'] === $cat_name) {
                                $cat_id_for_edit = $c['id'];
                                break;
                            }
                        }
            ?>
                <div class="d-flex align-items-center hover-container">
                    <h4 class="category-title text-<?= $current_theme ?> mb-2">KATEGORI: <?= htmlspecialchars($cat_name) ?></h4>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" class="edit-icon ms-2 mt-3" title="Edit Kategori" onclick="openEditCategory(<?= $cat_id_for_edit ?>, '<?= htmlspecialchars($cat_name) ?>')">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                </div>
                
                <div class="table-responsive mb-4 shadow-sm rounded">
                    <table class="table table-bordered table-striped table-hover table-excel mb-0">
                        <thead class="table-<?= $current_theme ?>">
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">Jenis Bahan</th>
                                <th rowspan="2">UK</th>
                                <th rowspan="2" class="bg-awal text-dark">SA (Awal)</th>
                                <?php for ($i = 1; $i <= $days_in_month; $i++): ?>
                                    <th colspan="2"><?= $i ?></th>
                                <?php endfor; ?>
                                <th colspan="2" class="bg-total text-dark">Total</th>
                                <th rowspan="2" class="bg-akhir text-dark">SA (Akhir)</th>
                            </tr>
                            <tr>
                                <?php for ($i = 1; $i <= $days_in_month; $i++): ?>
                                    <th class="col-sm-sk">SM</th>
                                    <th class="col-sm-sk">SK</th>
                                <?php endfor; ?>
                                <th class="col-sm-sk bg-total text-dark">SM</th>
                                <th class="col-sm-sk bg-total text-dark">SK</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $no = 1;
                        foreach ($items_group as $item_name => $sizes): 
                            $rowspan = count($sizes);
                            $is_first = true;
                            
                            foreach ($sizes as $id => $s): 
                                $total_sm = 0;
                                $total_sk = 0;
                                for ($d = 1; $d <= $days_in_month; $d++) {
                                    $total_sm += $s['daily'][$d]['sm'];
                                    $total_sk += $s['daily'][$d]['sk'];
                                }
                        ?>
                            <tr>
                                <?php if ($is_first): ?>
                                    <td rowspan="<?= $rowspan ?>"><?= $no++ ?></td>
                                    <td rowspan="<?= $rowspan ?>" class="text-start fw-bold"><?= htmlspecialchars($item_name) ?></td>
                                <?php endif; ?>
                                
                                <td class="hover-container">
                                    <?= htmlspecialchars($s['size']) ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" class="edit-icon" title="Edit Barang" onclick="openEditStock(<?= $id ?>, '<?= htmlspecialchars($s['name']) ?>', '<?= htmlspecialchars($s['size']) ?>', <?= $s['global_stock_category_id'] ?>)">
                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                    </svg>
                                </td>
                                
                                <td class="bg-awal text-dark">
                                    <?= floatval($s['sa_awal']) ?>
                                </td>

                                <?php 
                                for ($i = 1; $i <= $days_in_month; $i++) {
                                    $sm = $s['daily'][$i]['sm'];
                                    $sk = $s['daily'][$i]['sk'];
                                    $date_str = $selected_month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                                    
                                    echo "<td class='update-cell text-primary fw-bold' 
                                            data-id='{$id}' 
                                            data-name='" . htmlspecialchars($s['name']) . " (" . htmlspecialchars($s['size']) . ")' 
                                            data-date='{$date_str}' 
                                            data-type='sm' 
                                            data-sm='{$sm}' 
                                            data-sk='{$sk}' 
                                            title='Klik untuk isi SM tanggal {$i}'>" . 
                                            ($sm > 0 ? floatval($sm) : '') . 
                                         "</td>";

                                    echo "<td class='update-cell text-danger fw-bold' 
                                            data-id='{$id}' 
                                            data-name='" . htmlspecialchars($s['name']) . " (" . htmlspecialchars($s['size']) . ")' 
                                            data-date='{$date_str}' 
                                            data-type='sk' 
                                            data-sm='{$sm}' 
                                            data-sk='{$sk}'
                                            title='Klik untuk isi SK tanggal {$i}'>" . 
                                            ($sk > 0 ? floatval($sk) : '') . 
                                         "</td>";
                                }
                                ?>
                                
                                <td class="bg-total text-primary"><?= $total_sm > 0 ? floatval($total_sm) : '' ?></td>
                                <td class="bg-total text-danger"><?= $total_sk > 0 ? floatval($total_sk) : '' ?></td>

                                <td class="bg-akhir text-success">
                                    <?= floatval($s['sa_akhir']) ?>
                                </td>
                            </tr>
                        <?php 
                            $is_first = false;
                            endforeach; 
                        endforeach; 
                        ?>
                        </tbody>
                    </table>
                </div>
            <?php 
                endforeach; 
                endif; 
            ?>

        </div>
    </div>
    <?php include '../footer.php'; ?>
</div>

<div class="modal fade" id="historyDeliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Riwayat Pengiriman & Penerimaan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped table-hover mb-0" style="font-size: 14px;">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-3">Tanggal</th>
                            <th>Status</th>
                            <th>Toko Terkait</th>
                            <th>Barang (UK)</th>
                            <th class="text-center pe-3">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deliveries)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat pengiriman stok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($deliveries as $del): 
                                $is_outgoing = ($del['sender_id'] == $store_id); // Cek apakah toko ini yang mengirim
                            ?>
                                <tr>
                                    <td class="ps-3"><?= date('d-m-Y', strtotime($del['date'])) ?></td>
                                    <td>
                                        <?php if ($is_outgoing): ?>
                                            <span class="badge bg-danger">Kirim Keluar</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Terima Masuk</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_outgoing): ?>
                                            Ke: <strong><?= htmlspecialchars($del['to_store']) ?></strong>
                                        <?php else: ?>
                                            Dari: <strong><?= htmlspecialchars($del['from_store']) ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($del['item_name']) ?> (<?= htmlspecialchars($del['item_size']) ?>)</td>
                                    <td class="text-center pe-3 fw-bold"><?= floatval($del['qty']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sendStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <form action="global_stock_action.php" method="POST" class="async-form">
                <input type="hidden" name="action" value="send_stock">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Pengiriman Barang</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">1. Pilih Toko Tujuan</label>
                        <select name="to_store_id" id="to_store_select" class="form-select" required>
                            <option value="">-- Pilih Toko Tujuan --</option>
                            <?php foreach ($available_stores as $as): ?>
                                <option value="<?= htmlspecialchars($as['store_id']) ?>"><?= htmlspecialchars($as['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">2. Barang Yang Dikirim (Dari Toko Ini)</label>
                        <select name="global_stock_id" class="form-select" required>
                            <option value="">-- Pilih Barang Stok --</option>
                            <?php foreach ($stocks as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['category_name']) ?> - <?= htmlspecialchars($s['name']) ?> (UK: <?= htmlspecialchars($s['size']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">3. Diterima Sebagai Barang Apa di Tujuan?</label>
                        <select name="to_global_stock_id" id="to_global_stock_select" class="form-select" required>
                            <option value="">-- Pilih Toko Tujuan Terlebih Dahulu --</option>
                        </select>
                        <small class="text-muted d-block mt-1">Pilih barang tujuan yang sesuai agar stok tidak menduplikasi. Jika tidak ada, pilih Buat Baru.</small>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Tanggal Kirim</label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Jumlah (Qty)</label>
                            <input type="number" step="0.01" name="qty" class="form-control text-center" placeholder="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-info text-white px-4">Kirim Barang Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="global_stock_action.php" method="POST" class="async-form">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" name="name" class="form-control" placeholder="Nama kategori" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="global_stock_action.php" method="POST" class="async-form">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="id" id="edit_category_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Edit Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" name="name" id="edit_category_name" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-warning">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="global_stock_action.php" method="POST" class="async-form">
                <input type="hidden" name="action" value="add_stock">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Tambah Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Barang (Jenis Bahan)</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ukuran (UK)</label>
                        <input type="text" name="size" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="global_stock_action.php" method="POST" class="async-form">
                <input type="hidden" name="action" value="edit_stock">
                <input type="hidden" name="id" id="edit_stock_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Edit Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" id="edit_stock_category" class="form-select" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Barang</label>
                        <input type="text" name="name" id="edit_stock_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ukuran (UK)</label>
                        <input type="text" name="size" id="edit_stock_size" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-warning">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="dailyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <form action="global_stock_action.php" method="POST" class="async-form">
                <input type="hidden" name="action" value="update_daily_stock">
                <input type="hidden" name="global_stock_id" id="modal_stock_id">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modal_dynamic_title">Update Stock Harian</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    
                    <div class="alert alert-secondary py-2 mb-3">
                        <span id="modal_item_name" class="fw-bold d-block text-center fs-5"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="date" id="modal_date" class="form-control bg-light" readonly required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label text-primary fw-bold">Stock Masuk (SM)</label>
                            <input type="number" step="0.01" name="stock_in" id="modal_sm" class="form-control form-control-lg text-center" value="0">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label text-danger fw-bold">Stock Keluar (SK)</label>
                            <input type="number" step="0.01" name="stock_out" id="modal_sk" class="form-control form-control-lg text-center" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary px-4">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const otherStoresStocks = <?= json_encode($other_stores_stocks) ?>;

// JS Untuk Otomatisasi Dropdown Barang Tujuan di Form Kirim Barang
document.getElementById('to_store_select').addEventListener('change', function() {
    const selectedStoreId = this.value;
    const targetSelect = document.getElementById('to_global_stock_select');
    
    targetSelect.innerHTML = '<option value="">-- Pilih Barang --</option><option value="NEW" class="text-success fw-bold">+ BUAT SEBAGAI BARANG BARU (OTOMATIS)</option>';
    
    if (selectedStoreId) {
        const filteredStocks = otherStoresStocks.filter(stock => stock.store_id == selectedStoreId);
        
        if (filteredStocks.length > 0) {
            filteredStocks.forEach(stock => {
                const opt = document.createElement('option');
                opt.value = stock.id;
                opt.textContent = `${stock.category_name} - ${stock.name} (UK: ${stock.size})`;
                targetSelect.appendChild(opt);
            });
        }
    } else {
        targetSelect.innerHTML = '<option value="">-- Pilih Toko Tujuan Terlebih Dahulu --</option>';
    }
});


// FUNGSI UNTUK MEMBUKA MODAL EDIT KATEGORI & BARANG
function openEditCategory(id, name) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_name').value = name;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

function openEditStock(id, name, size, cat_id) {
    document.getElementById('edit_stock_id').value = id;
    document.getElementById('edit_stock_name').value = name;
    document.getElementById('edit_stock_size').value = size;
    document.getElementById('edit_stock_category').value = cat_id;
    new bootstrap.Modal(document.getElementById('editStockModal')).show();
}

// ASYNC FETCH & EFEK LOADING UNTUK SEMUA FORM
document.querySelectorAll('.async-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault(); 
        
        const loading = document.getElementById('global-loading');
        loading.classList.remove('d-none'); // Munculkan Animasi Loading

        const formData = new FormData(this);

        try {
            const response = await fetch('global_stock_action.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if(result.status === 'success') {
                window.location.reload(); 
            } else {
                alert(result.message || 'Terjadi kesalahan pada saat menyimpan data.');
                loading.classList.add('d-none'); // Sembunyikan Loading jika error
            }
        } catch (error) {
            alert('Terjadi kesalahan pada koneksi server.');
            loading.classList.add('d-none'); // Sembunyikan Loading jika error
        }
    });
});

// LOGIKA KLIK CELL HARIAN
document.addEventListener('DOMContentLoaded', function () {
    const updateCells = document.querySelectorAll('.update-cell');
    const dailyModalElement = document.getElementById('dailyModal');
    let bsModal = null;
    
    if(typeof bootstrap !== 'undefined') {
        bsModal = new bootstrap.Modal(dailyModalElement);
    }

    updateCells.forEach(cell => {
        cell.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const date = this.getAttribute('data-date');
            const type = this.getAttribute('data-type');
            const smVal = this.getAttribute('data-sm');
            const skVal = this.getAttribute('data-sk');

            document.getElementById('modal_stock_id').value = id;
            document.getElementById('modal_item_name').innerText = name;
            document.getElementById('modal_date').value = date;
            document.getElementById('modal_sm').value = smVal;
            document.getElementById('modal_sk').value = skVal;

            if (bsModal) bsModal.show();

            setTimeout(() => {
                const targetInput = type === 'sm' ? document.getElementById('modal_sm') : document.getElementById('modal_sk');
                targetInput.focus();
                targetInput.select(); 
            }, 500);
        });
    });
});
</script>

</body>
</html>