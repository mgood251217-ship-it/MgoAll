<?php

require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/controllers/GlobalStockController.php';

$globalStockController = new GlobalStockController($koneksi);
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$data = $globalStockController->index();

$days_in_month = $data['days_in_month'];
$available_stores = $data['available_stores'];
$other_stores_stocks = $data['other_stores_stocks'];
$deliveries = $data['deliveries'];
$categories = $data['categories'];
$stocks_list = $data['stocks_list'];
$awal_list = $data['awal_list'];
$daily_list = $data['daily_list'];
$grouped_stocks = $data['grouped_stocks'];

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

        /* Tambahkan di bawah .hover-container .edit-icon:hover */
        .hover-container .delete-icon {
            opacity: 0;
            visibility: hidden;
            cursor: pointer;
            fill: #dc3545; /* Warna Merah */
            margin-left: 5px;
            transition: opacity 0.2s ease, visibility 0.2s ease, fill 0.2s;
            vertical-align: middle;
        }
        .hover-container:hover .delete-icon {
            opacity: 1;
            visibility: visible;
        }
        .hover-container .delete-icon:hover {
            fill: #a11a27; /* Merah gelap saat di-hover */
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

<div id="main-wrapper">
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
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] == true): ?>
                        <form class="d-inline m-0" method="POST" action="../routes/?action=export_csv_global_stock">
                            <input type="hidden" name="action" value="export_csv_global_stock">
                            <button type="submit" class="btn btn-dark btn-sm text-white fw-bold" title="Download Template Master Barang">
                                ↓ Export CSV
                            </button>
                        </form>
                        <button class="btn btn-outline-dark btn-sm fw-bold bg-white text-dark" data-bs-toggle="modal" data-bs-target="#importCsvModal" title="Upload Template Master Barang">
                            ↑ Import CSV
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-warning btn-sm text-dark fw-bold" id="btnExportExcel">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="vertical-align: sub;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM15 15H9v-2h6v2zm-2 4H9v-2h4v2z"/></svg> 
                        Export Excel
                    </button>
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
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] == true): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" class="delete-icon ms-1 mt-3" title="Hapus Kategori" onclick="deleteCategory(<?= $cat_id_for_edit ?>, '<?= htmlspecialchars($cat_name) ?>')">
                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                    <?php endif; ?>
                </div>
                
                <div class="table-responsive mb-4 shadow-sm rounded">
                    <table class="table table-bordered table-striped table-hover table-excel mb-0">
                        <thead class="table-<?= $current_theme ?>">
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">Jenis Bahan</th>
                                <th rowspan="2">UK</th>
                                <th rowspan="2">Harga</th> <th rowspan="2" class="bg-awal text-dark">SA (Awal)</th>
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
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" class="edit-icon" title="Edit Barang" onclick="openEditStock(<?= $id ?>, '<?= htmlspecialchars($s['name']) ?>', '<?= htmlspecialchars($s['size']) ?>', <?= $s['global_stock_category_id'] ?>, <?= floatval($s['price']) ?>)">
                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                    </svg>
                                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] == true): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" class="delete-icon" title="Hapus Barang" onclick="deleteStock(<?= $id ?>, '<?= htmlspecialchars($s['name']) ?>')">
                                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                        </svg>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end text-success fw-bold">
                                    <?= number_format((float)$s['price'], 0, ',', '.') ?>
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
                                $is_outgoing = ($del['sender_id'] == $store_id);
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
            <form class="async-form">
                <input type="hidden" name="action" value="send_global_stock">
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
                            <?php foreach ($stocks_list as $s): ?>
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
            <form class="async-form">
                <input type="hidden" name="action" value="create_category_global_stock">
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
            <form class="async-form">
                <input type="hidden" name="action" value="update_category_global_stock">
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
            <form class="async-form">
                <input type="hidden" name="action" value="create_global_stock">
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
                    <div class="mb-3">
                        <label class="form-label">Harga Dasar</label>
                        <input type="number" name="price" class="form-control" value="0" required>
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
            <form class="async-form">
                <input type="hidden" name="action" value="update_global_stock">
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
                    <div class="mb-3">
                        <label class="form-label">Harga Dasar</label>
                        <input type="number" name="price" id="edit_stock_price" class="form-control" required>
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
            <form class="async-form">
                <input type="hidden" name="action" value="update_daily_global_stock">
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

<?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] == true): ?>
<div class="modal fade" id="importCsvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <form enctype="multipart/form-data" class="async-form">
                <input type="hidden" name="action" value="import_csv_global_stock">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Import Data Stok (CSV)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3" style="font-size: 13px;">
                        <strong>Penting:</strong> Import ini digunakan untuk memindahkan <em>Database Kategori & Barang</em> dari toko lain tanpa membawa ID Toko (cocok untuk cabang baru).
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih File CSV</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark px-4">Mulai Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/exceljs.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/FileSaver.min.js"></script>

<script>

const otherStoresStocks = <?= json_encode($other_stores_stocks) ?>;
const groupedStocks = <?= json_encode($grouped_stocks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const daysInMonth = <?= $days_in_month ?>;
const selectedMonthStr = '<?= $selected_month ?>';

const toko = "<?= addslashes($storeName) ?>";
const alamat = "<?= addslashes($storeAddress) ?>";

document.getElementById('btnExportExcel').addEventListener('click', async () => {
    if (typeof ExcelJS === 'undefined') {
        alert('Library ExcelJS belum termuat sempurna.');
        return;
    }

    const loading = document.getElementById('global-loading');
    loading.classList.remove('d-none');

    try {
        const workbook = new ExcelJS.Workbook();
        
        const sheet = workbook.addWorksheet('Stok ' + selectedMonthStr, {
            pageSetup: { paperSize: 9, orientation: 'landscape' }
        });

        // PERUBAHAN EXCEL: maxCols ditambah 1 karena ada kolom Harga
        const maxCols = 5 + (daysInMonth * 2) + 3; 
        
        // Judul toko (merge dan center)
        sheet.mergeCells(1, 1, 1, maxCols);
        const cellToko = sheet.getCell(1, 1);
        cellToko.value = toko;
        cellToko.font = { bold: true, size: 16 };
        cellToko.alignment = { horizontal: "center", vertical: "middle" };
        sheet.getRow(1).height = 25;

        // Alamat toko (merge dan center)
        sheet.mergeCells(2, 1, 2, maxCols);
        const cellAlamat = sheet.getCell(2, 1);
        cellAlamat.value = alamat;
        cellAlamat.alignment = { horizontal: "center", vertical: "middle" };

        sheet.addRow([]);

        // Judul laporan
        sheet.mergeCells(4, 1, 4, maxCols);
        const cellJudul = sheet.getCell(4, 1);
        cellJudul.value = "LAPORAN STOK BAHAN GLOBAL - " + selectedMonthStr;
        cellJudul.font = { bold: true, size: 14 };
        cellJudul.alignment = { horizontal: "center", vertical: "middle" };

        sheet.addRow([]);

        let currentRow = 6; 
        const borderStyle = {
            top: {style:'thin'}, left: {style:'thin'}, 
            bottom: {style:'thin'}, right: {style:'thin'}
        };
        const alignCenter = { horizontal: 'center', vertical: 'middle' };

        for (const catName in groupedStocks) {
            const itemsGroup = groupedStocks[catName];

            // Tulis Nama Kategori (Tanpa Background)
            sheet.mergeCells(currentRow, 1, currentRow, maxCols);
            const titleCell = sheet.getCell(currentRow, 1);
            titleCell.value = 'KATEGORI: ' + catName.toUpperCase();
            titleCell.font = { bold: true, size: 12 };
            currentRow++;

            // Render Header Kolom Tabel (Row 1 dan Row 2)
            const h1 = sheet.getRow(currentRow);
            const h2 = sheet.getRow(currentRow + 1);

            h1.getCell(1).value = 'No'; sheet.mergeCells(currentRow, 1, currentRow + 1, 1);
            h1.getCell(2).value = 'Jenis Bahan'; sheet.mergeCells(currentRow, 2, currentRow + 1, 2);
            h1.getCell(3).value = 'UK'; sheet.mergeCells(currentRow, 3, currentRow + 1, 3);
            
            // PERUBAHAN EXCEL: Kolom Header Harga dan Geser SA Awal
            h1.getCell(4).value = 'Harga'; sheet.mergeCells(currentRow, 4, currentRow + 1, 4);
            h1.getCell(5).value = 'SA (Awal)'; sheet.mergeCells(currentRow, 5, currentRow + 1, 5);

            let colIndex = 6; // Mulai dari 6 karena 4 dan 5 sudah terpakai
            for (let d = 1; d <= daysInMonth; d++) {
                h1.getCell(colIndex).value = d;
                sheet.mergeCells(currentRow, colIndex, currentRow, colIndex + 1);
                
                h2.getCell(colIndex).value = 'SM';
                h2.getCell(colIndex + 1).value = 'SK';
                colIndex += 2;
            }

            h1.getCell(colIndex).value = 'Total';
            sheet.mergeCells(currentRow, colIndex, currentRow, colIndex + 1);
            h2.getCell(colIndex).value = 'SM';
            h2.getCell(colIndex + 1).value = 'SK';
            colIndex += 2;

            h1.getCell(colIndex).value = 'SA (Akhir)';
            sheet.mergeCells(currentRow, colIndex, currentRow + 1, colIndex);

            // Terapkan Styling Header Biru (Format Sama Persis Seperti Permintaan Anda)
            for(let rowNum = currentRow; rowNum <= currentRow+1; rowNum++){
                const hr = sheet.getRow(rowNum);
                for(let c = 1; c <= maxCols; c++){
                    const cell = hr.getCell(c);
                    cell.border = borderStyle;
                    cell.alignment = alignCenter;
                    cell.font = { bold: true };
                    cell.fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'FFCCE5FF' } // Biru Muda
                    };
                }
            }

            currentRow += 2; 

            let no = 1;
            for (const itemName in itemsGroup) {
                const sizes = itemsGroup[itemName];
                const rowSpanCount = Object.keys(sizes).length;
                const startRow = currentRow;

                for (const id in sizes) {
                    const s = sizes[id];
                    const r = sheet.getRow(currentRow);

                    if (currentRow === startRow) {
                        r.getCell(1).value = no++;
                        r.getCell(2).value = itemName;
                    }

                    r.getCell(3).value = s.size;
                    
                    // PERUBAHAN EXCEL: Kolom Harga diekspor
                    r.getCell(4).value = parseFloat(s.price) || 0;
                    r.getCell(4).numFmt = '#,##0';
                    
                    r.getCell(5).value = parseFloat(s.sa_awal) || 0;
                    r.getCell(5).numFmt = '#,##0';

                    let cIdx = 6;
                    let totalSm = 0;
                    let totalSk = 0;

                    for (let d = 1; d <= daysInMonth; d++) {
                        const sm = parseFloat(s.daily[d].sm);
                        const sk = parseFloat(s.daily[d].sk);
                        
                        const cellSm = r.getCell(cIdx);
                        const cellSk = r.getCell(cIdx + 1);
                        
                        cellSm.value = sm > 0 ? sm : '';
                        cellSk.value = sk > 0 ? sk : '';
                        
                        cellSm.numFmt = '#,##0';
                        cellSk.numFmt = '#,##0';
                        
                        totalSm += sm || 0;
                        totalSk += sk || 0;
                        cIdx += 2;
                    }

                    const cellTotalSm = r.getCell(cIdx);
                    const cellTotalSk = r.getCell(cIdx + 1);
                    const cellSaAkhir = r.getCell(cIdx + 2);

                    cellTotalSm.value = totalSm > 0 ? totalSm : '';
                    cellTotalSk.value = totalSk > 0 ? totalSk : '';
                    cellSaAkhir.value = parseFloat(s.sa_akhir) || 0;

                    cellTotalSm.numFmt = '#,##0';
                    cellTotalSk.numFmt = '#,##0';
                    cellSaAkhir.numFmt = '#,##0';

                    // Beri Border ke Seluruh Kolom & Atur Posisi Text
                    for(let c = 1; c <= maxCols; c++){
                        const cell = r.getCell(c);
                        cell.border = borderStyle;
                        if(c === 1 || c === 3) {
                            cell.alignment = alignCenter; // No & UK di tengah
                        } else if (c === 2) {
                            cell.alignment = { horizontal: 'left', vertical: 'middle' };
                        } else {
                            // Seluruh cell angka ke rata kanan seperti di laporan piutang
                            cell.alignment = { horizontal: 'right', vertical: 'middle' };
                        }
                    }

                    currentRow++;
                }

                if (rowSpanCount > 1) {
                    sheet.mergeCells(startRow, 1, currentRow - 1, 1);
                    sheet.mergeCells(startRow, 2, currentRow - 1, 2);
                }
            }

            currentRow += 2; 
        }

        sheet.getColumn(1).width = 6;
        sheet.getColumn(2).width = 30;
        sheet.getColumn(3).width = 12;
        sheet.getColumn(4).width = 15; // PERUBAHAN: Lebar kolom harga
        sheet.getColumn(5).width = 12; // SA Awal

        // Lebar kolom tanggal
        for (let i = 6; i <= maxCols; i++) {
             sheet.getColumn(i).width = 6;
        }
        
        // Perlebar kolom Total dan Akhir
        sheet.getColumn(maxCols - 2).width = 10;
        sheet.getColumn(maxCols - 1).width = 10;
        sheet.getColumn(maxCols).width = 12;

        // =======================================================
        // SHEET 2: PENGIRIMAN & PENERIMAAN BARANG
        // =======================================================
        const sheet2 = workbook.addWorksheet('Pengiriman & Penerimaan', {
            pageSetup: { paperSize: 9, orientation: 'portrait' }
        });

        // Ambil data dari PHP
        const deliveriesJS = <?= json_encode($deliveries) ?>;
        const availableStoresJS = <?= json_encode($available_stores) ?>;

        // 1. Judul toko (merge dan center) persis seperti sheet 1
        sheet2.mergeCells(1, 1, 1, 7);
        const cellToko2 = sheet2.getCell(1, 1);
        cellToko2.value = toko;
        cellToko2.font = { bold: true, size: 16 };
        cellToko2.alignment = { horizontal: "center", vertical: "middle" };
        sheet2.getRow(1).height = 25;

        // 2. Alamat toko
        sheet2.mergeCells(2, 1, 2, 7);
        const cellAlamat2 = sheet2.getCell(2, 1);
        cellAlamat2.value = alamat;
        cellAlamat2.alignment = { horizontal: "center", vertical: "middle" };

        sheet2.addRow([]);

        // 3. Judul Laporan Sheet 2
        sheet2.mergeCells(4, 1, 4, 7);
        const title2 = sheet2.getCell(4, 1);
        title2.value = "LAPORAN PENGIRIMAN & PENERIMAAN BARANG - " + selectedMonthStr;
        title2.font = { bold: true, size: 14 };
        title2.alignment = { horizontal: "center", vertical: "middle" };
        
        sheet2.addRow([]);

        let r2 = 6; // Mulai tabel dari baris ke-6 biar rapi dan sejajar dengan Sheet 1


        // Fungsi Cetak Tabel Otomatis (Mencegah pengulangan kode)
        function createDeliveryTable(tableName, dataArray) {
            // Judul Tabel per Toko
            sheet2.mergeCells(r2, 1, r2, 7);
            const tblTitle = sheet2.getCell(r2, 1);
            tblTitle.value = tableName;
            tblTitle.font = { bold: true, size: 11, color: { argb: 'FFFFFFFF' } };
            tblTitle.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF6C757D' } }; // Header abu-abu tua
            tblTitle.alignment = { vertical: 'middle', indent: 1 };
            r2++;

            // Header Tabel
            const headers = ['No', 'Tanggal', 'Nama Barang', 'Ukuran', 'Qty', 'Harga Satuan', 'Total Harga'];
            const hr = sheet2.getRow(r2);
            headers.forEach((h, i) => {
                const cell = hr.getCell(i + 1);
                cell.value = h;
                cell.font = { bold: true };
                cell.alignment = { horizontal: 'center', vertical: 'middle' };
                cell.border = borderStyle; // Menggunakan border dari Sheet 1
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCCE5FF' } };
            });
            r2++;

            if (dataArray.length === 0) {
                sheet2.mergeCells(r2, 1, r2, 7);
                const emptyCell = sheet2.getCell(r2, 1);
                emptyCell.value = "Tidak ada transaksi pengiriman/penerimaan di bulan ini.";
                emptyCell.alignment = { horizontal: 'center', vertical: 'middle' };
                emptyCell.border = borderStyle;
                emptyCell.font = { italic: true, color: { argb: 'FF888888' } };
                r2++;
            } else {
                let no = 1;
                let grandTotal = 0;
                dataArray.forEach(d => {
                    const row = sheet2.getRow(r2);
                    const qty = parseFloat(d.qty) || 0;
                    const price = parseFloat(d.price) || 0;
                    const total = qty * price;
                    grandTotal += total;

                    // Perbaikan format tanggal aman (Cegah error beda browser)
                    let tgl = d.date;
                    if (tgl.includes(' ')) tgl = tgl.split(' ')[0]; 
                    const parts = tgl.split('-');
                    if (parts.length === 3) tgl = `${parts[2]}-${parts[1]}-${parts[0]}`;

                    row.getCell(1).value = no++;
                    row.getCell(2).value = tgl; 
                    row.getCell(3).value = d.item_name;
                    row.getCell(4).value = d.item_size;
                    row.getCell(5).value = qty;
                    row.getCell(6).value = price;
                    row.getCell(7).value = total;

                    // Formatting rata letak
                    row.getCell(1).alignment = { horizontal: 'center' };
                    row.getCell(2).alignment = { horizontal: 'center' };
                    row.getCell(5).alignment = { horizontal: 'center' };
                    
                    // Formatting format ribuan rupiah
                    row.getCell(5).numFmt = '#,##0.##';
                    row.getCell(6).numFmt = '#,##0';
                    row.getCell(7).numFmt = '#,##0';

                    for(let c=1; c<=7; c++) row.getCell(c).border = borderStyle;
                    r2++;
                });

                // Baris Grand Total Bawah
                sheet2.mergeCells(r2, 1, r2, 6);
                const gtLabel = sheet2.getCell(r2, 1);
                gtLabel.value = "TOTAL KESELURUHAN";
                gtLabel.font = { bold: true };
                gtLabel.alignment = { horizontal: 'right', vertical: 'middle' };
                gtLabel.border = borderStyle;

                const gtValue = sheet2.getCell(r2, 7);
                gtValue.value = grandTotal;
                gtValue.font = { bold: true };
                gtValue.numFmt = '#,##0';
                gtValue.border = borderStyle;
                r2++;
            }
            r2 += 2; // Memberi jarak 2 baris antar tabel
        }

        // Generate tabel HANYA untuk toko yang memiliki riwayat transaksi
        let hasAnyTransaction = false;

        availableStoresJS.forEach(store => {
            const incoming = deliveriesJS.filter(d => d.sender_id == store.store_id);
            const outgoing = deliveriesJS.filter(d => d.to_store_id == store.store_id);

            // Jika ada stok masuk dari toko ini, buat tabelnya
            if (incoming.length > 0) {
                createDeliveryTable(`📦 STOK MASUK DARI: ${store.name.toUpperCase()}`, incoming);
                hasAnyTransaction = true;
            }

            // Jika ada stok keluar ke toko ini, buat tabelnya
            if (outgoing.length > 0) {
                createDeliveryTable(`🚀 STOK KELUAR KE: ${store.name.toUpperCase()}`, outgoing);
                hasAnyTransaction = true;
            }
        });

        // Jika bulan ini benar-benar tidak ada mutasi sama sekali dari semua toko
        if (!hasAnyTransaction) {
            sheet2.mergeCells(r2, 1, r2 + 2, 7);
            const emptyCell = sheet2.getCell(r2, 1);
            emptyCell.value = "Belum ada aktivitas pengiriman atau penerimaan barang di bulan ini.";
            emptyCell.font = { italic: true, size: 12, color: { argb: 'FF888888' } };
            emptyCell.alignment = { horizontal: 'center', vertical: 'middle' };
            
            // Beri border putus-putus atau biasa agar terlihat rapi
            for(let rowNum = r2; rowNum <= r2+2; rowNum++){
                for(let colNum = 1; colNum <= 7; colNum++){
                    sheet2.getCell(rowNum, colNum).border = borderStyle;
                }
            }
        }

        // Set lebar kolom agar proporsional dan rapi
        sheet2.getColumn(1).width = 6;
        sheet2.getColumn(2).width = 15;
        sheet2.getColumn(3).width = 35;
        sheet2.getColumn(4).width = 15;
        sheet2.getColumn(5).width = 8;
        sheet2.getColumn(6).width = 15;
        sheet2.getColumn(7).width = 18;
        // =======================================================

        const buffer = await workbook.xlsx.writeBuffer();
        const today = new Date().toISOString().slice(0, 10);
        saveAs(new Blob([buffer]), `Laporan_Stok_Bahan_${today}.xlsx`);

    } catch (error) {
        console.error(error);
        alert('Gagal mengekspor file Excel.');
    } finally {
        loading.classList.add('d-none');
    }
});


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

function openEditCategory(id, name) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_name').value = name;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

// PERUBAHAN: JS Parameter harga ditambah
function openEditStock(id, name, size, cat_id, price) {
    document.getElementById('edit_stock_id').value = id;
    document.getElementById('edit_stock_name').value = name;
    document.getElementById('edit_stock_size').value = size;
    document.getElementById('edit_stock_category').value = cat_id;
    document.getElementById('edit_stock_price').value = price; // Load data harga ke form
    new bootstrap.Modal(document.getElementById('editStockModal')).show();
}

document.querySelectorAll('.async-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault(); 
        
        const loading = document.getElementById('global-loading');
        loading.classList.remove('d-none');

        const formData = new FormData(this);
        const action = formData.get('action')

        try {
            const response = await fetch(`../routes/?action=${action}`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if(result.success) {
                window.location.reload(); 
            } else {
                alert(result.message || 'Terjadi kesalahan pada saat menyimpan data.');
                loading.classList.add('d-none');
            }
        } catch (error) {
            alert('Terjadi kesalahan pada koneksi server.');
            loading.classList.add('d-none');
        }
    });
});

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

// FUNGSI HAPUS KATEGORI & BARANG (ADMIN ONLY)
async function deleteCategory(id, name) {
    if (confirm(`PERINGATAN: Anda yakin ingin menghapus Kategori "${name}" beserta SELURUH BARANG di dalamnya?\n\nTindakan ini akan menghapus riwayat stoknya juga dan tidak dapat dibatalkan.`)) {
        sendDeleteRequest('delete_category_global_stock', id);
    }
}

async function deleteStock(id, name) {
    if (confirm(`Anda yakin ingin menghapus Barang "${name}"?\n\nRiwayat stok masuk dan keluar untuk barang ini akan ikut terhapus.`)) {
        sendDeleteRequest('delete_global_stock', id);
    }
}

async function sendDeleteRequest(action, id) {
    const loading = document.getElementById('global-loading');
    loading.classList.remove('d-none');
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('id', id);

    try {
        const response = await fetch(`../routes/?action=${id}`, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            window.location.reload(); 
        } else {
            alert(result.message || 'Gagal menghapus data.');
            loading.classList.add('d-none');
        }
    } catch (error) {
        alert('Terjadi kesalahan koneksi saat menghapus.');
        loading.classList.add('d-none');
    }
}
</script>

</body>
</html>