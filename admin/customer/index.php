<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/global_functions.php';
require_once BASE_PATH . '/models/Setting.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/controllers/OrderController.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/functions/helpers.php';
require_once BASE_PATH . '/components/Modal.php';
require_once BASE_PATH . '/components/Alert.php';
require_once BASE_PATH . '/functions/helpers.php';

$settingModel = new Setting($koneksi);
$userModel = new User($koneksi);
$orderController = new OrderController($koneksi);

$is_admin_like = in_array($role, ['SETTING']);
$is_all_access = in_array($role, ['PRODUKSI', 'MANAGER', 'ADMIN']);

if ($is_admin_like || $is_all_access) {
    $system = 'OFFLINE';
} else {
    $system = 'ONLINE';
}

$search_text = trim($_GET['search'] ?? '');
$start_date = ($_GET['start_date'] ?? date('Y-m-d')) . ' 00:00:00';
$end_date = ($_GET['end_date'] ?? date('Y-m-d')) . ' 23:59:59';

$customerLimit = (float)$settingModel->getOneValue($user_id, 'customer_limit');
$preview_print = (float)$settingModel->getOneValue($user_id, 'preview_print');

$usersInitial = $userModel->getUsersInitial($store_id);
$dataOrder = $orderController->index();
$ordersOnline = $dataOrder['online'];
$ordersOffline = $dataOrder['offline'];

?>


<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer - Mgood</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer.css">
<style>
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

<?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
    .dark-mode .table-primary tr th {
      background-color:rgb(192, 22, 155) !important;
      color: white !important;
    }
    .dark-mode .table-danger tr th {
      background-color:rgb(192, 22, 155) !important;
      color: white !important;
    }
    .dark-mode .table-prim > tbody > .order-row:nth-child(even) > td{
      background-color: #ffcce5 !important;
      color: rgb(115, 0, 90) !important;
    }
    .dark-mode .table-prim > tbody > .order-row:nth-child(odd) > td{
      background-color:rgb(255, 244, 249) !important;
      color: rgb(115, 0, 90) !important;
    }
    .dark-mode .table-dan > tbody > .order-row:nth-child(even) > td{
      background-color: #ffcce5 !important;
      color: rgb(115, 0, 90) !important;
    }
    .dark-mode .table-dan > tbody > .order-row:nth-child(odd) > td{
      background-color: rgb(255, 244, 249) !important;
      color: rgb(115, 0, 90) !important;
    }
    .dark-mode .table-prim > tbody > .order-row.row-selected > td,
    .dark-mode .table-dan > tbody > .order-row.row-selected > td {
        background-color: rgb(255, 151, 232) !important;
        color: white !important;
    }
<?php } ?>

</style>
</head>
<body>
  <div id="main-wrapper">
    <?php include BASE_PATH . '/navbar.php'; ?>
    <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
      <?php include BASE_PATH . '/sidebar.php'; ?>
      <div id="page-content-wrapper">
        <div class="row align-items-end mb-4">
          <div class="col-md-auto">
            <h1 class="mb-0" style="font-size:1.7rem;">Data Order</h1>
          </div>
          <div class="col">
            <form method="post" action="order_action.php?order=preview_print" style="display:inline;">
              <button type="submit" name="toggle_preview_print" value="1" style="border:none; background:none; padding:0; cursor:pointer;" title="Toggle Preview Print">
                <img src="<?= BASE_URL ?>/assets/img/prt.svg" alt="Print Preview" style="width:30px; height:30px; filter: invert(1) sepia(1) saturate(5) hue-rotate(180deg);  opacity: <?= ($preview_print === 1) ? '1' : '0.5' ?>;  transition: opacity 0.3s ease;">
              </button>
            </form>
          </div>
          <div class="col">
          <form method="post" action="order_action.php?order=limit" class="row g-2 " id="limitForm" style="margin-bottom:0;">
            <div class="col-auto">
                <select class="form-select" aria-label="Default select example"
                name="limit"
                id="limit"
                onchange="this.form.submit()
                ">
                  <option selected value="0">Limit Order</option>
                  <option value="1">1</option>
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="15">15</option>
                  <option value="0">Unlimited</option>
                </select>
            </div>
          </form>
          </div>
          <div class="col">
            <?php
            $showSearch = true;
            include BASE_PATH . '/interval_date.php'; ?>
          </div>
        </div>

        <?php

        foreach ($ordersOffline as &$row) {
            $row['order_enk'] = startEnk('enk', $row['order_id']);
        }
        unset($row);

        foreach ($ordersOnline as &$row) {
            $row['order_enk'] = startEnk('enk', $row['order_id']);
        }
        unset($row);

        $columns = [
            [
                'header' => 'No',
                'type' => 'number'
            ],
            [
                'header' => 'INV',
                'render' => function($row) {
                    return sanitize($row['nomorator']);
                }
            ],
            [
                'header' => 'Nama Customer',
                'render' => function($row) {
                    return sanitize(title_case($row['customer_name']));
                }
            ],
            [
                'header' => 'Nomor HP',
                'visible' => ($role == 'ADMIN' || $role == 'MANAGER'),
                'render' => function($row) {
                    return sanitize(formatKeInternasional($row['nomor']) ?? '-');
                }
            ],
            [
                'header' => 'Total',
                'type' => 'currency',
                'field' => 'total'
            ],
            [
                'header' => 'Deadline',
                'render' => function($row) {
                    return hitungDeadline($row['deadline']);
                }
            ],
            [
                'header' => 'OP',
                'render' => function($row) {
                    return $row['op_initial'];
                }
            ],
            [
                'header' => 'Aksi',
                'visible' => ($role == 'ADMIN' || $role == 'MANAGER' || $role == 'ONLINE'),
                'render' => function($row) use ($mobile, $role) {
                    $html = '<div class="btn-group-row">';
                    if ($mobile) {
                        $html .= '<form action="nota.php" method="post" style="display:inline;">
                                    <input type="hidden" name="order_id" value="' . $row['order_id'] . '">
                                    <button type="submit" class="btn btn-sm btn-success">Buka</button>
                                  </form> ';
                    }
                    $html .= '<button class="btn btn-sm btn-primary btn-edit" data-id="' . $row['order_id'] . '" data-enk="' . $row['order_enk'] . '" data-paid="' . $row['total_paid'] . '">Edit</button> ';
                    if (!$row['is_lunas']) {
                        $html .= '<button class="btn btn-sm btn-danger btn-pay" data-order-id="' . $row['order_id'] . '">Bayar</button> ';
                    }
                    $html .= '<div class="btn-group">
                                <button class="btn btn-sm btn-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Print</button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" onclick="printStruk(' . $row['order_id'] . ')">Print Struk</a></li>
                                    <li><a class="dropdown-item" onclick="printStrukPDF(' . $row['order_id'] . ')">Print PDF</a></li>
                                </ul>
                              </div> ';
                    if ($role == 'ADMIN' || $role == 'MANAGER') {
                        $formattedDate = date('Y-m-d', strtotime($row['date']));
                        $html .= '<a href="../laporan/transaksi_detil?scrl_id=' . $row['order_id'] . '&start_date=' . $formattedDate . '&end_date=' . $formattedDate . '" target="_blank" class="btn btn-sm btn-success">Cek</a>';
                    }
                    $html .= '</div>';
                    return $html;
                }
            ],
            [
                'header' => 'Bayar',
                'render' => function($row) {
                    if ($row['is_lunas_status']) {
                        return title_case("LUNAS " . $row['lunas_method']);
                    } elseif ($row['total_dp'] > 0) {
                        return "<div style='font-size:12px'>DP: " . format_rupiah($row['total_dp']) .
                              "<br>Sisa: " . format_rupiah($row['total'] - $row['total_dp']) . "</div>";
                    } elseif (!empty($row['project_status'])) {
                        return sanitize(title_case($row['project_status']));
                    }
                    return '-';
                }
            ],
            [
                'header' => 'Keterangan',
                'render' => function($row) {
                    $proc = title_case($row['project_process']);
                    $stat = title_case($row['project_status']);
                    if (strtoupper($proc) == 'DIAMBIL') {
                        return $proc . ' ' . $row['project_initial'];
                    } elseif (!empty($proc)) {
                        return $proc;
                    } elseif (!empty($stat)) {
                        return $stat;
                    }
                    return '-';
                }
            ],
            [
                'header' => '<input type="checkbox" class="check-all order-checkbox form-check-input m-0">',
                'render' => function($row) {
                    $checkboxBg = '';
                    switch (strtoupper($row['project_process'])) {
                        case 'DIAMBIL':
                            $checkboxBg = 'bg-success';
                            break;
                        case 'DIPROSES':
                            $checkboxBg = 'bg-secondary';
                            break;
                        case 'BELUM DIPROSES':
                            $checkboxBg = 'bg-warning';
                            break;
                        case 'BELUM BAYAR':
                            $checkboxBg = 'bg-danger';
                            break;
                        case 'MENUNGGU KONFIRMASI':
                            $checkboxBg = 'bg-info';
                            break;
                    }
                    return '<input type="checkbox" class="order-checkbox form-check-input m-0 ' . $checkboxBg . '" value="' . $row['order_id'] . '" style="cursor:pointer;">';
                }
            ]
        ];

        if ($is_all_access): ?>
            <h5 id="order_offline">Order Offline</h5>
            <?= renderTable([
                'data' => $ordersOffline,
                'empty_message' => 'Data orders kosong.',
                'table_class' => 'table table-striped table-bordered table-smaller order-table table-prim',
                'thead_class' => 'table-primary',
                'tbody_tr_class' => 'order-row',
                'columns' => $columns,
                'row_attributes' => function($row) {
                    return 'data-order-id="' . $row['order_id'] . '" data-id="' . $row['order_id'] . '"';
                }
            ]); ?>

            <h5 class="mt-4" id="order_online">Order Online</h5>
            <?= renderTable([
                'data' => $ordersOnline,
                'empty_message' => 'Data orders kosong.',
                'table_class' => 'table table-striped table-bordered table-smaller order-table table-dan',
                'thead_class' => 'table-danger',
                'tbody_tr_class' => 'order-row',
                'columns' => $columns,
                'row_attributes' => function($row) {
                    return 'data-order-id="' . $row['order_id'] . '" data-id="' . $row['order_id'] . '"';
                }
            ]); ?>
        <?php else: ?>
            <h5 id="order_section">Data <?= sanitize($system) ?></h5>
            <?php
            $orders = ($system === 'ONLINE') ? $ordersOnline : $ordersOffline;
            $tClass = ($system === 'ONLINE') ? 'table-dan' : 'table-prim';
            $thClass = ($system === 'ONLINE') ? 'table-danger' : 'table-primary';
            echo renderTable([
                'data' => $orders,
                'empty_message' => 'Data orders kosong.',
                'table_class' => 'table table-striped table-bordered table-smaller order-table ' . $tClass,
                'thead_class' => $thClass,
                'tbody_tr_class' => 'order-row',
                'columns' => $columns,
                'row_attributes' => function($row) {
                    return 'data-order-id="' . $row['order_id'] . '" data-id="' . $row['order_id'] . '"';
                }
            ]);
            ?>
        <?php endif; ?>

        <button id="btn-proses-massal" class="btn btn-success" style="position: fixed; bottom: 90px; right: 20px; display:none;">
            Update Proses Terpilih
        </button>

        <?php if ($role !== 'PRODUKSI'): ?>
          <div style="position: fixed; bottom: 50px; right: 20px; z-index: 999;">
            <button class="btn btn-primary" id="btnShowAddOrderModal">+ Tambah Order Baru</button>
          </div>
        <?php endif; ?>
        <?php

        $operatorOptions = [];
        foreach ($usersInitial as $id => $initial) {
            $operatorOptions[$id] = sanitize($initial);
        }

        $addOrderInputs = [
            [
                'type'          => 'text',
                'name'          => 'customer_name',
                'id'            => 'customerNameInput',
                'label'         => 'Nama Customer',
                'required'      => true,
                'wrapper_class' => 'mb-3 position-relative" id="customerInputWrapper',
                'custom_attr'   => 'autocomplete="off" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();"'
            ],
            [
                'type'        => 'text',
                'name'        => 'nomor',
                'id'          => 'nomorInput',
                'label'       => 'Nomor',
                'required'    => true,
                'custom_attr' => 'placeholder="0812xxxx"'
            ],
            [
                'type'        => 'datetime-local',
                'name'        => 'deadline',
                'label'       => 'Deadline',
                'required'    => true,
                'value'       => date('Y-m-d\TH:00', strtotime('+1 hour')),
                'custom_attr' => 'step="60"'
            ]
        ];

        if ($role === 'ONLINE') {
            $addOrderInputs[] = [
                'type'  => 'hidden',
                'name'  => 'user_id',
                'value' => $user_id
            ];
            $addOrderInputs[] = [
                'type'        => 'text',
                'label'       => 'Operator',
                'value'       => sanitize($usersInitial[$user_id] ?? ''),
                'custom_attr' => 'readonly'
            ];
        } else {
            $addOrderInputs[] = [
                'type'     => 'select',
                'name'     => 'user_id',
                'label'    => 'Operator',
                'options'  => $operatorOptions,
                'required' => true
            ];
        }

        $htmlModalAddOrder = renderModal([
            'id'            => 'addOrderModal',
            'form_id'       => 'addOrderForm',
            'title'         => 'Tambah Order Baru',
            'layout'        => 'horizontal',
            'label_width'   => 'col-sm-4',
            'input_width'   => 'col-sm-8',
            'btn_color'     => 'primary" id="addOrderBtn',
            'btn_text'      => 'Tambah Order',
            'inputs'        => $addOrderInputs
        ]);

        $htmlModalEditOrder = renderModal([
            'id'        => 'editOrderModal',
            'title'     => 'Edit Order',
            'action'    => 'order_action.php?order=update',
            'layout'        => 'horizontal',
            'label_width'   => 'col-sm-4',
            'input_width'   => 'col-sm-8',
            'btn_color' => 'success',
            'btn_text'  => 'Simpan Perubahan',
            'inputs'    => [
                [
                    'type' => 'hidden',
                    'name' => 'order_id',
                    'id'   => 'edit-id'
                ],
                [
                    'type'        => 'number',
                    'name'        => 'nomorator',
                    'id'          => 'edit-nomorator',
                    'label'       => 'Nomorator',
                    'required'    => true,
                    'custom_attr' => 'readonly'
                ],
                [
                    'type'        => 'text',
                    'name'        => 'customer_name',
                    'id'          => 'edit-customer_name',
                    'label'       => 'Nama Customer',
                    'required'    => true,
                    'custom_attr' => 'style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();"'
                ],
                [
                    'type'     => 'datetime-local',
                    'name'     => 'deadline',
                    'id'       => 'edit-deadline',
                    'label'    => 'Deadline',
                    'required' => true
                ],
                [
                    'type'        => 'text',
                    'name'        => 'nomor',
                    'id'          => 'edit-nomor',
                    'label'       => 'Nomor',
                    'required'    => true,
                    'custom_attr' => 'style="text-transform:uppercase"'
                ],
                [
                    'type'  => 'datetime-local',
                    'name'  => 'date',
                    'id'    => 'edit-date',
                    'label' => 'Tanggal'
                ],
                [
                    'type'     => 'select',
                    'name'     => 'user_id',
                    'id'       => 'edit-user_id',
                    'label'    => 'Operator',
                    'options'  => $operatorOptions,
                    'required' => true
                ],
                [
                    'type'              => 'select',
                    'name'              => 'sistem',
                    'id'                => 'edit-sistem',
                    'label'             => 'Sistem',
                    'options'           => ['OFFLINE' => 'OFFLINE', 'ONLINE' => 'ONLINE'],
                    'required'          => true,
                    'no_default_option' => true
                ]
            ]
        ]);

        $htmlModalPayment = renderModal([
            'id'            => 'paymentModal',
            'form_id'       => 'paymentForm',
            'title'         => 'Pembayaran Order',
            'btn_color'     => 'primary d-none',
            'custom_bottom' => '<div class="modal-body pt-0"><div class="mb-3"><label class="form-label">Bayar Sebagian</label><br><div class="d-flex"><button type="button" class="btn btn-primary w-50" data-method="TF" data-lunas="false">Transfer</button><button type="button" class="btn btn-success w-50 ms-2" data-method="CASH" data-lunas="false">Cash</button></div></div><div class="mb-3"><label class="form-label">Lunas Langsung</label><br><div class="d-flex"><button type="button" class="btn btn-primary w-50" data-method="TF" data-lunas="true">Lunas Transfer</button><button type="button" class="btn btn-success w-50 ms-2" data-method="CASH" data-lunas="true">Lunas Cash</button></div></div><div id="paymentFeedback" class="text-danger"></div></div><style>#paymentModal .modal-footer { display: none !important; }</style>',
            'inputs'        => [
                [
                    'type' => 'hidden',
                    'name' => 'order_id',
                    'id'   => 'payment-order-id'
                ],
                [
                    'type'  => 'text',
                    'name'  => 'nominal_formatted',
                    'id'    => 'payment-nominal',
                    'label' => 'Nominal'
                ],
                [
                    'type' => 'hidden',
                    'name' => 'nominal',
                    'id'   => 'payment-nominal-raw'
                ]
            ]
        ]);

        $htmlModalProsesMassal = renderModal([
          'id'            => 'modalProsesMassal',
          'form_id'       => 'formProsesMassal',
            'action'        => 'order_action.php?order=process',
            'title'         => '🛠 Proses Status Order Massal',
            'header_class'  => 'bg-primary text-white',
            'btn_color'     => 'success w-100" id="submitBtnMassal" disabled="disabled',
            'btn_text'      => '💾 Simpan',
            'inputs'        => [
                ['type' => 'hidden', 'name' => 'order_ids', 'id' => 'massOrderIds'],
                ['type' => 'hidden', 'name' => 'status', 'id' => 'statusInputMassal']
            ],
            'custom_bottom' => <<<HTML
                <div class="modal-body pt-0">
                    <label class="form-label fw-bold">Status Order:</label>
                    <div class="btn-group w-100 mb-3" role="group">
                        <button type="button" class="btn btn-outline-primary status-btn" data-status="BELUM_DIPROSES">BELUM DIPROSES</button>
                        <button type="button" class="btn btn-outline-primary status-btn" data-status="DIPROSES">DIPROSES</button>
                        <button type="button" class="btn btn-outline-primary status-btn" data-status="SELESAI">SELESAI</button>
                        <button type="button" class="btn btn-outline-primary status-btn" data-status="DIAMBIL">DIAMBIL</button>
                        <button type="button" class="btn btn-outline-primary status-btn" data-status="LAINNYA">LAINNYA</button>
                    </div>
                    <div class="mb-3 d-none" id="customStatusWrapperMassal">
                        <label for="customStatusMassal" class="form-label">Isi Status Manual</label>
                        <input type="text" id="customStatusMassal" class="form-control" placeholder="Contoh: REVISI, DALAM PENGIRIMAN">
                    </div>
                    <div id="storeUserSelectorMassal" class="mb-3 d-none">
                        <label for="userInitialMassal" class="form-label fw-bold">Pilih User Initial</label>
                        <select name="user_id" id="userInitialMassal" class="form-select"></select>
                    </div>
                </div>
                <style>#modalProsesMassal .modal-footer .btn-secondary { display: none !important; }</style>
        HTML
        ]);

        $htmlModalConfirmDelete = renderModal([
            'id'           => 'confirmDeleteModal',
            'title'        => 'Konfirmasi Hapus Orderan',
            'header_class' => 'bg-danger text-white',
            'size'         => 'modal-dialog-centered',
            'btn_color'    => 'danger" id="confirmDeleteBtn',
            'btn_text'     => 'Hapus',
            'custom_bottom' => <<<HTML
                <div class="modal-body pt-0">
                    Apakah Anda yakin ingin menghapus Orderan ini?
                    <br><br>
                    <div class="mb-1">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan_hapus" placeholder="Keterangan" required></textarea>
                    </div>
                </div>
                <style>#confirmDeleteModal .btn-secondary { order: -1; }</style>
        HTML
        ]);

        echo $htmlModalAddOrder;
        echo $htmlModalEditOrder;
        echo $htmlModalPayment;
        echo $htmlModalProsesMassal;
        echo $htmlModalConfirmDelete;

        ?>

        <div id="global-loading" class="global-loading d-none">
          <div class="loading-content">
            <div class="spinner-border text-light" role="status"></div>
            <div class="mt-2">Loading...</div>
          </div>
        </div>


      </div>
    </div>
    <?php include BASE_PATH . '/footer.php'; ?>
  </div>
<div id="hover-tooltip" style="
  display: none;
  position: absolute;
  z-index: 1000;
  background: #fff;
  border: 1px solid #ccc;
  padding: 8px 12px;
  font-size: 13px;
  max-width: 300px;
  white-space: pre-wrap;
  box-shadow: 0 4px 12px hsla(0, 0%, 0%, 0.15);
  border-radius: 6px;
  pointer-events: none;
"></div>
<?php if (isset($username) && ($username == 'zannia' || $username == 'vikialvian')) { ?>
  <img src="https://mgood.my.id/assets/img/output-onlinegiftools.gif" alt="" srcset="" style="position: fixed; bottom: 0; left: 0; height: 200px; z-index: 999999;">
<?php }elseif (isset($username) && ($username == 'nada' )){ ?>
  <img src="https://media.tenor.com/d2j7YdyhtmsAAAAj/shikanoko-dance-shikanoko-meme.gif" alt="" srcset="" style="position: fixed; bottom: 0; left: 0; height: 200px; z-index: 999999;">
<?php } ?>

<script src="<?= BASE_URL ?>/assets/js/customer.js"></script>
<iframe id="cetak-loader" style="position: absolute; width: 0; height: 0; border: none; left: -9999px;"></iframe>

<script>
let userList = <?php echo json_encode($usersInitial) ?>;
function showGlobalLoading() {
  document.getElementById('global-loading').classList.remove('d-none');
}

function hideGlobalLoading() {
  document.getElementById('global-loading').classList.add('d-none');
}


document.addEventListener('DOMContentLoaded', function () {
  const statusButtons = document.querySelectorAll('.status-btn');
  const statusInput = document.getElementById('statusInput');
  const submitBtn = document.getElementById('submitBtn');
  const customStatusWrapper = document.getElementById('customStatusWrapper');
  const customStatusInput = document.getElementById('customStatus');

  statusButtons.forEach(button => {
    button.addEventListener('click', () => {
      statusButtons.forEach(btn => btn.classList.remove('active'));
      button.classList.add('active');
      const status = button.getAttribute('data-status');
      if (status === 'LAINNYA') {
        customStatusWrapper.classList.remove('d-none');
        customStatusInput.focus();
        customStatusInput.addEventListener('input', () => {
          statusInput.value = customStatusInput.value.trim();
          submitBtn.disabled = statusInput.value === '';
        });
        statusInput.value = customStatusInput.value.trim();
      } else {
        customStatusWrapper.classList.add('d-none');
        statusInput.value = status;
        submitBtn.disabled = false;
      }
    });
  });
});

function debouncedSubmit() {
  clearTimeout(window.debounceTimer);
  window.debounceTimer = setTimeout(() => {
    document.getElementById('filterForm').submit();
  }, 1000);
}

const tooltip = document.getElementById('hover-tooltip');

document.querySelectorAll('.order-row').forEach(row => {
  const orderId = row.dataset.orderId;
  let timeout;

  row.addEventListener('mouseenter', (e) => {
    
    if (e.target.closest('.aksi-cell')) return;

    
    tooltip.innerText = 'Memuat...';
    tooltip.style.display = 'block';

    timeout = setTimeout(() => {
      fetch(`order_action.php?order=get_order_items&order_id=${orderId}`)
        .then(res => res.json())
        .then(res => {
          if (res.data.items.length === 0) {
            tooltip.innerText = 'Tidak ada item';
            return;
          }

          tooltip.innerText = res.data.items.map(item => {
            const size = item.size && item.size !== '-' ? ` (${item.size})` : '';
            const finishing_names = item.finishing_names && item.finishing_names !== '-' ? ` + ${item.finishing_names}` : '';
            return `• ${item.judul}${size}${finishing_names} x${item.quantity}`;
          }).join('\n');
        });
    }, 1000);
  });

  row.addEventListener('mousemove', (e) => {
    if (e.target.closest('.aksi-cell')) {
      tooltip.style.display = 'none'; 
      return;
    }

    tooltip.style.left = (e.pageX + 15) + 'px';
    tooltip.style.top = (e.pageY + 15) + 'px';

    if (tooltip.style.display === 'none') {
      tooltip.style.display = 'block';  
    }
  });

  row.addEventListener('mouseleave', () => {
    clearTimeout(timeout);
    tooltip.style.display = 'none';
  });

});

document.addEventListener('click', function (e) {
  const row = e.target.closest('.order-row');
  if (!row) return;

  if (window.selectedRow) {
    window.selectedRow.classList.remove("row-selected");
  }
  window.selectedRow = row;
  row.classList.add("row-selected");
});

document.addEventListener('dblclick', function (e) {
  const row = e.target.closest('.order-row');
  if (!row) return;

  if (e.target.closest('button') || e.target.closest('form') || e.target.closest('input')) {
    return;
  }

  const editBtn = row.querySelector('.btn-edit');
  if (!editBtn) return;

  const encryptedId = editBtn.dataset.enk;
  const isPaid = editBtn.dataset.paid;
  const access = '<?= $access ?>';

  if (isPaid > 0 && access !== 'all') {
    showAlert('error', 'Tidak dapat membuka nota');
  }else{
    const form = document.createElement('form');
    form.method = 'GET';
    form.action = 'nota';
    form.target = '_self';
    form.innerHTML = `<input type="hidden" name="id" value="${encryptedId}">`;
    document.body.appendChild(form);
    form.submit();
  }
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Delete' && window.selectedRow) {
    let access = '<?= $access ?>';
    if (access == 'all') {
      
      const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
      modal.show();

    }else{
      Swal.fire({
        icon: "error",
        title: "Tidak ada akses, Hubungi Administrator",
        theme: '<?= ($mode === 1) ? 'dark' : '' ?>'
      });
    }
  }
});

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
  const orderId = window.selectedRow.dataset.orderId;
  let keteranganHapus = document.querySelector("[name='keterangan_hapus']").value;
  fetch('order_action.php?order=delete', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `order_id=${encodeURIComponent(orderId)}&keterangan_hapus=${keteranganHapus}`
  }).then(res => res.json()).then(data => {
    if (data.success) {
      window.selectedRow.remove();
      window.selectedRow = null;
      const modalEl = document.getElementById('confirmDeleteModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      modal.hide();
    } else {
      alert('Gagal menghapus order: ' + data.message);
    }
  }).catch(err => {
    alert('Terjadi kesalahan: ' + err);
  });
});

document.getElementById('btnShowAddOrderModal')?.addEventListener('click', () => {
  const modalEl = document.getElementById('addOrderModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();

  
  modalEl.addEventListener('shown.bs.modal', () => {
    document.getElementById('customerNameInput')?.focus();
  }, { once: true });
});

const addOrderForm = document.getElementById("addOrderForm");
const addOrderBtn = document.getElementById("addOrderBtn");

addOrderBtn.addEventListener('click', (e) => {
    if (!addOrderForm.checkValidity()) {
        addOrderForm.reportValidity();
        return;
    }
    
    e.preventDefault();
    showGlobalLoading();
    
    const formData = new FormData(addOrderForm);
    
    fetch('order_action.php?order=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = 'nota';

            const id = document.createElement('input');
            id.type = 'hidden';
            id.name = 'id';
            id.value = data.id;
            
            form.appendChild(id);
            document.body.appendChild(form);
            form.submit();
        } else {
            if (typeof hideGlobalLoading === 'function') {
                hideGlobalLoading();
            }
            alert(data.message);
        }
    })
    .catch(error => {
        if (typeof hideGlobalLoading === 'function') {
            hideGlobalLoading();
        }
        alert('Terjadi kesalahan sistem.');
    });
});
const previewPrintSetting = <?= $preview_print ?>;
function printStruk(order_id) {
  const url = `print_struk?order_id=${order_id}`;
  window.open(url, "_blank");
}
function printStrukPDF(order_id) {
  const url = `print_struk_pdf?order_id=${order_id}`;
  
  if (previewPrintSetting === 0) {
    document.getElementById('cetak-loader').src = url;
  } else {
    window.open(url, "_blank");
  }
}

document.querySelectorAll('.btn-edit').forEach(button => {
  button.addEventListener('click', function () {
    document.getElementById('edit-id').value = this.dataset.id;
 
    fetch('order_action.php?order=get_order&order_id=' + this.dataset.id)
    .then(response => response.json())
    .then(data => {
      document.getElementById('edit-nomorator').value = data.nomorator;
      document.getElementById('edit-customer_name').value = data.customer_name;
      document.getElementById('edit-nomor').value = data.nomor;
      document.getElementById('edit-deadline').value = data.deadline;
      const rawDate = data.date;
      const formattedDate = rawDate.replace(' ', 'T').slice(0, 16);
      document.getElementById('edit-date').value = formattedDate;
      document.getElementById('edit-user_id').value = data.user_id;
      document.getElementById('edit-sistem').value = data.system;

      new bootstrap.Modal(document.getElementById('editOrderModal')).show();
    })

  });
});

document.querySelectorAll('.btn-pay').forEach(button => {
  button.addEventListener('click', () => {
    document.getElementById('payment-order-id').value = button.getAttribute('data-order-id');
    document.getElementById('payment-nominal').value = '';
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
  });
});

document.getElementById('btn-proses-massal').addEventListener('click', function () {
  const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
  if (checkedBoxes.length === 0) {
    alert('Pilih minimal satu order terlebih dahulu.');
    return;
  }

  const orderIds = Array.from(checkedBoxes).map(cb => cb.value).join(',');
  document.getElementById('massOrderIds').value = orderIds;

  
  document.getElementById('statusInputMassal').value = '';
  document.getElementById('submitBtnMassal').disabled = true;
  document.getElementById('storeUserSelectorMassal').classList.add('d-none');
  document.getElementById('userInitialMassal').innerHTML = '';
  document.getElementById('customStatusWrapperMassal').classList.add('d-none');
  document.getElementById('customStatusMassal').value = '';

  new bootstrap.Modal(document.getElementById('modalProsesMassal')).show();
});


document.querySelectorAll('.status-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const status = btn.getAttribute('data-status');
    const isMassal = btn.closest('#modalProsesMassal') !== null;

    const statusInput = document.getElementById(isMassal ? 'statusInputMassal' : 'statusInput');
    const submitBtn = document.getElementById(isMassal ? 'submitBtnMassal' : 'submitBtn');
    const customStatusWrapper = document.getElementById(isMassal ? 'customStatusWrapperMassal' : 'customStatusWrapper');
    const customStatusInput = document.getElementById(isMassal ? 'customStatusMassal' : 'customStatus');
    const storeUserSelector = document.getElementById(isMassal ? 'storeUserSelectorMassal' : 'storeUserSelector');
    const userSelect = document.getElementById(isMassal ? 'userInitialMassal' : 'userInitial');

    
    statusInput.value = status;
    submitBtn.disabled = false;

    if (status === 'LAINNYA') {
      customStatusWrapper.classList.remove('d-none');
      customStatusInput.focus();

      customStatusInput.addEventListener('input', () => {
        statusInput.value = customStatusInput.value.trim();
        submitBtn.disabled = statusInput.value === '';
      });

      statusInput.value = customStatusInput.value.trim();
    } else {
      customStatusWrapper.classList.add('d-none');
    }

    
    btn.closest('.btn-group').querySelectorAll('.status-btn').forEach(b => {
      b.classList.remove('btn-primary');
      b.classList.add('btn-outline-primary');
    });
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-primary');

    userSelect.innerHTML = '';
    if (status === 'DIAMBIL') {
      const keys = Object.keys(userList);
      if (keys.length > 0) {
        keys.forEach(userId => {
          const opt = document.createElement('option');
          opt.value = userId;
          opt.textContent = userList[userId];
          userSelect.appendChild(opt);
        });
      } else {
        const opt = document.createElement('option');
        opt.textContent = 'Tidak ada user';
        opt.disabled = true;
        userSelect.appendChild(opt);
      }
      storeUserSelector.classList.remove('d-none');
    } else {
      storeUserSelector.classList.add('d-none');
    }
  });
});

const paymentForm = document.getElementById('paymentForm');
const nominalInput = document.getElementById('payment-nominal');
const nominalRaw = document.getElementById('payment-nominal-raw');
const feedback = document.getElementById('paymentFeedback');

function formatRupiah(angka) {
  let numberString = angka.replace(/[^,\d]/g, '').toString();
  let split = numberString.split(',');
  let sisa = split[0].length % 3;
  let rupiah = split[0].substr(0, sisa);
  let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

  if (ribuan) {
    let separator = sisa ? '.' : '';
    rupiah += separator + ribuan.join('.');
  }

  rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
  return rupiah ? 'Rp ' + rupiah : '';
}

nominalInput.addEventListener('input', function(e) {
  let cursorPos = nominalInput.selectionStart;
  let originalLength = nominalInput.value.length;

  let numericValue = nominalInput.value.replace(/[^,\d]/g, '');
  nominalRaw.value = numericValue.replace(/\./g, '');

  nominalInput.value = formatRupiah(numericValue);

  let updatedLength = nominalInput.value.length;
  cursorPos = cursorPos + (updatedLength - originalLength);
  nominalInput.setSelectionRange(cursorPos, cursorPos);
});

  // Submit via AJAX
  paymentForm.querySelectorAll('button[data-method]').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();

      feedback.textContent = '';
      const orderId = document.getElementById('payment-order-id').value;
      const nominal = nominalRaw.value;
      const method = this.getAttribute('data-method');
      const isLunas = this.dataset.lunas === "true";
      

      if (!orderId) {
        feedback.textContent = 'Order ID tidak valid';
        return;
      }

      if (!isLunas && (!nominal || parseInt(nominal) <= 0)) {
        feedback.textContent = 'Nominal harus diisi dan lebih dari 0';
        return;
      }

      // Siapkan data form
      const formData = new FormData();
      formData.append('order_id', orderId);
      formData.append(isLunas ? 'lunas_method' : 'payment_method', method);
      if (!isLunas) {
        formData.append('nominal', nominal);
      }

      showGlobalLoading();

      fetch('order_action.php?order=payment', {
        method: 'POST',
        body: formData,
      })
      .then(resp => resp.json())
      .then(res => {
      if (res.success) {
        feedback.style.color = 'green';
        feedback.textContent = res.message || 'Pembayaran berhasil';
        hideGlobalLoading();

        const orderId = document.getElementById('payment-order-id').value;
        const row = document.querySelector(`tr.order-row[data-order-id="${orderId}"]`);
        if (row) {
          const role = '<?= $role ?>' ;
          let bayarCell = row.cells[8];
          let keteranganCell = row.cells[9];
          let aksiCell = row.cells[7];
          if (role != 'MANAGER' && role != 'ADMIN') {
            bayarCell = row.cells[7];
            keteranganCell = row.cells[8];
            aksiCell = row.cells[6];
          }
          if (bayarCell) {
            bayarCell.innerHTML = res.data.bayar || '';
          }
          if (keteranganCell) {
            keteranganCell.textContent = res.data.keterangan || '';
          }
          if (aksiCell && res.data.isLunas) {
            const bayarBtn = aksiCell.querySelector('button.btn-pay'); 
            if (bayarBtn) {
              bayarBtn.remove();
            }
          }
          nominal.innerHTML = '';
        }

          const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
          modal.hide();
          feedback.textContent = '';

        showAlert('success', 'Pembayaran Berhasil');

      } else {
        feedback.style.color = 'red';
        feedback.textContent = res.message || 'Gagal melakukan pembayaran';
        showAlert('error', 'Pembayaran Gagal');
        hideGlobalLoading();
      }

      })
      .catch(err => {
        feedback.style.color = 'red';
        feedback.textContent = 'Kesalahan jaringan atau server';
      });
    });
  });

  const customerInput = document.getElementById('customerNameInput');
  const nomorInput = document.getElementById('nomorInput');
    customerInput.insertAdjacentHTML(
      "afterend", 
      '<div id="customerDropdown" class="list-group position-absolute" style="z-index:1050; top:100%; left: 500px; max-height:150px; overflow-y:auto; display:none;"></div>'
    );
  const dropdown = document.getElementById('customerDropdown');
  let timerDebounce;
  let history = {};
  customerInput?.addEventListener('input', function () {
    const val = customerInput.value.toUpperCase();
    clearTimeout(timerDebounce);
    timerDebounce = setTimeout(() => {
      fetch('order_action.php?order=get_history&name='+ val)
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        history = data;
        dropdown.innerHTML = '';
        if (val.length === 0) return dropdown.style.display = 'none';

        const filtered = history.filter(item => item.name.toUpperCase());
        if (filtered.length === 0) return dropdown.style.display = 'none';

        filtered.forEach(item => {
          const div = document.createElement('a');
          div.className = 'list-group-item list-group-item-action';
          div.style.cursor = 'pointer';
          div.textContent = `${item.name} - ${item.nomor}`;
          div.onclick = () => {
            customerInput.value = item.name;
            nomorInput.value = item.nomor;
            dropdown.style.display = 'none';
          };
          dropdown.appendChild(div);
        });

        dropdown.style.display = 'block';
        
      }).catch(error => {
        console.error('Error fetching data:', error);
      })
    }, 500);

  });

document.addEventListener('DOMContentLoaded', () => {
  const btnProses = document.getElementById('btn-proses-massal');

  function isVisible(element) {
    return element.offsetParent !== null;
  }

  function toggleButton() {
    const allCheckboxes = Array.from(document.querySelectorAll('.order-checkbox')).filter(isVisible);
    const anyChecked = allCheckboxes.some(cb => cb.checked);
    btnProses.style.display = anyChecked ? 'block' : 'none';
  }

  document.querySelectorAll('table').forEach(table => {
    const checkAll = table.querySelector('.check-all');
    const checkboxes = Array.from(table.querySelectorAll('.order-checkbox')).filter(isVisible);

    if (!checkAll || checkboxes.length === 0) return;

    checkAll.addEventListener('change', () => {
      checkboxes.forEach(cb => {
        if (isVisible(cb)) {
          cb.checked = checkAll.checked;
        }
      });
      toggleButton();
    });

    checkboxes.forEach(cb => {
      cb.addEventListener('change', () => {
        const allChecked = checkboxes.every(cb2 => cb2.checked);
        checkAll.checked = allChecked;
        toggleButton();
      });
    });

    const allCheckedInit = checkboxes.length > 0 && checkboxes.every(cb => cb.checked);
    checkAll.checked = allCheckedInit;
  });

  toggleButton();
});

document.addEventListener('DOMContentLoaded', function () {
  const nominalInput = document.getElementById('payment-nominal');
  const lunasButtons = document.querySelectorAll('[data-lunas="true"]');
  const modal = document.getElementById('paymentModal');

  function updateLunasButtonState() {
    const rawValue = nominalInput.value.replace(/[^\d]/g, '');
    const isPartial = rawValue && parseInt(rawValue) > 0;

    lunasButtons.forEach(button => {
      button.disabled = isPartial;
    });
  }

  modal.addEventListener('shown.bs.modal', function () {
    updateLunasButtonState();
  });

  nominalInput.addEventListener('input', updateLunasButtonState);
});

</script>

</body>
</html>