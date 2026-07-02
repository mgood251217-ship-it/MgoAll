<?php

require_once '../connect.php';
require_once '../global_functions.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/controllers/FinanceController.php';
require_once BASE_PATH . '/controllers/PaymentController.php';
require_once BASE_PATH . '/components/Alert.php';
require_once BASE_PATH . '/functions/helpers.php';

$paymentController = new PaymentController($koneksi);
$financeController = new FinanceController($koneksi);
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$data = $financeController->finance($store_id, $start_date, $end_date);
$dataFinance = $data['finance'];
$dataPengeluaran = $data['expenditure'];
$dataPemasukan = $data['income'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Keuangan</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <?php include BASE_PATH . '/export_libraries.php'; ?>
<style>
.img-thumb:hover {
  opacity: 0.5;
  transition: opacity 0.2s ease;
}
</style>

</head>

<body>
<div id="main-wrapper">
  <?php include BASE_PATH . '/navbar.php'; ?>

  <div id="main-content" <?= (isset($mode) && $mode === 1) ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">

      <?php require 'summary_cards.php'; ?>

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-3">
        <h1 class="mb-3 mb-md-0">Keuangan</h1>
        <div class="d-flex flex-wrap justify-content-end align-items-end gap-2">
          <?php $showExport = true; include BASE_PATH . '/interval_date.php'; ?>
        </div>
      </div>

      <div class="card mb-4" <?= ($mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>>
        <div class="card-header bg-primary text-white">
          Keuangan Terkini (<?= format_tanggal_id($start_date) ?> s.d <?= format_tanggal_id($end_date) ?>)
        </div>

        <div class="card-body">

          <?php
            echo renderTable([
                'id'          => 'tableKeuangan',
                'data'        => $dataFinance,
                'table_class' => 'table table-bordered table-striped text-center',
                'thead_class' => 'table-info',
                'columns'     => [
                    [
                        'header' => 'No',
                        'type'   => 'number'
                    ],
                    [
                        'header' => 'Omset Offline',
                        'type'   => 'currency',
                        'field'  => 'omset_offline'
                    ],
                    [
                        'header' => 'Omset Online',
                        'type'   => 'currency',
                        'field'  => 'omset_online'
                    ],
                    [
                        'header' => 'Total Omset',
                        'type'   => 'currency',
                        'field'  => 'total_omset'
                    ],
                    [
                        'header' => 'Transfer Masuk',
                        'type'   => 'currency',
                        'field'  => 'transfer'
                    ],
                    [
                        'header' => 'Cash Masuk',
                        'type'   => 'currency',
                        'field'  => 'cash_masuk'
                    ],
                    [
                        'header' => 'Pengeluaran',
                        'type'   => 'currency',
                        'field'  => 'expenditure'
                    ],
                    [
                        'header' => 'Saldo Kas',
                        'type'   => 'currency',
                        'field'  => 'saldo'
                    ],
                    [
                        'header' => 'Periode',
                        'field'  => 'date',
                        'render' => function($row) {
                            return format_tanggal_id($row['date']);
                        }
                    ]
                ]
            ]);
          ?>

          <div class="mt-3 text-end">
            <form class="d-inline" id="refresForm">
              <input type="hidden" name="start_date" value="<?= sanitize($start_date) ?>">
              <input type="hidden" name="end_date" value="<?= sanitize($end_date) ?>">
              <button type="submit" name="refresh_finance" class="btn btn-success px-4">Refresh</button>
            </form>
          </div>

        </div>

        <div class="row card-body">
          <!-- Tabel Pengeluaran -->
          <div class="col-md-6">
            <h5 class="mt-4">Data Pengeluaran Bulan Ini</h5>
            <?php
              $pengeluaranColumns = [
                  [
                      'header' => 'No',
                      'type'   => 'number'
                  ],
                  [
                      'header' => 'Keterangan',
                      'field'  => 'information'
                  ],
                  [
                      'header' => 'Nominal',
                      'type'   => 'currency',
                      'field'  => 'nominal'
                  ],
                  [
                      'header' => 'Foto',
                      'render' => function($row) use ($storeName) {
                          $stName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName ?? 'Toko');
                          $fDate  = date('Y/m/d', strtotime($row['date']));
                          $imgUrl = BASE_URL . "/assets/img/bukti/{$stName}/{$fDate}/" . sanitize($row['img']);
                          
                          return empty($row['img']) 
                              ? '<img src="'.BASE_URL.'/assets/img/noproof.png" style="height:30px;">' 
                              : '<img src="'.$imgUrl.'" class="img-thumb" onclick="showImageModal(\''.$imgUrl.'\')" style="width:50px;height:50px;object-fit:cover;cursor:pointer;">';
                      }
                  ],
                  [
                      'header' => 'Tanggal',
                      'render' => fn($row) => format_tanggal_id($row['date'])
                  ]
              ];

              if ($administrator) {
                  $pengeluaranColumns[] = [
                      'header' => 'Aksi',
                      'type'   => 'action_buttons',
                      'buttons' => [
                          [
                              'text'            => 'Edit',
                              'color'           => 'warning',
                              'modal'           => 'editExpenditureModal',
                              'data_attributes' => ['id' => 'expenditure_id', 'type' => 'expenditures', 'info' => 'information', 'nominal' => 'nominal']
                          ],
                          [
                              'text'            => 'Hapus',
                              'color'           => 'danger',
                              'modal'           => 'deleteModal',
                              'data_attributes' => ['id' => 'expenditure_id', 'type' => 'expenditures']
                          ]
                      ]
                  ];
              }

              echo renderTable([
                  'id'          => 'tablePengeluaran',
                  'data'        => $dataPengeluaran,
                  'table_class' => 'table table-bordered table-striped',
                  'thead_class' => 'table-danger',
                  'columns'     => $pengeluaranColumns
              ]);
            ?>
          </div>

          <!-- Tabel Pemasukan -->
          <div class="col-md-6">
            <h5 class="mt-4">Data Pemasukan Bulan Ini</h5>
            <div class="table-responsive">
              <?php
                $pemasukanColumns = [
                    [
                        'header' => 'No',
                        'type'   => 'number'
                    ],
                    [
                        'header' => 'Keterangan',
                        'field'  => 'information'
                    ],
                    [
                        'header' => 'Nominal',
                        'type'   => 'currency',
                        'field'  => 'nominal'
                    ],
                    [
                        'header' => 'Tanggal',
                        'render' => function($row) {
                            return format_tanggal_id($row['date']);
                        }
                    ]
                ];

                if ($administrator) {
                    $pemasukanColumns[] = [
                        'header' => 'Aksi',
                        'type'   => 'action_buttons',
                        'buttons' => [
                            [
                                'text'            => 'Edit',
                                'color'           => 'warning',
                                'modal'           => 'editIncomeModal',
                                'data_attributes' => [
                                    'id'      => 'income_id', 
                                    'type'    => 'income', 
                                    'info'    => 'information', 
                                    'nominal' => 'nominal'
                                ],
                                'visible'         => function($row) { 
                                    return strpos($row['information'], 'INPUT SALDO OTOMATIS') === false; 
                                }
                            ],
                            [
                                'text'            => 'Hapus',
                                'color'           => 'danger',
                                'modal'           => 'deleteModal',
                                'data_attributes' => [
                                    'id'   => 'income_id', 
                                    'type' => 'income'
                                ],
                                'visible'         => function($row) { 
                                    return strpos($row['information'], 'INPUT SALDO OTOMATIS') === false; 
                                }
                            ]
                        ]
                    ];
                }

                echo renderTable([
                    'id'          => 'tablePemasukan',
                    'data'        => $dataPemasukan,
                    'table_class' => 'table table-bordered table-striped',
                    'thead_class' => 'table-success',
                    'columns'     => $pemasukanColumns
                ]);
              ?>
            </div>
          </div>
        </div>
        <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background:transparent; border:none;">
              <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2" data-bs-dismiss="modal"></button>
              <img id="modalImage" src="" alt="Preview Bukti" 
                style=" max-height:80vh; object-fit:contain; display:block; margin:auto; border-radius:10px;">
            </div>
          </div>
        </div>
        <!-- Modal Tambah Pengeluaran -->
        <div class="modal fade" id="addExpenditure" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <form method="post" class="modal-content" enctype="multipart/form-data" id="expenditureForm">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Pengeluaran Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="date" value="<?= $start_date ?>">
                <input type="hidden" name="tambah_pengeluaran" value="1">
                
                <div class="mb-3">
                  <label class="form-label">Keterangan</label>
                  <input type="text" name="information" class="form-control" required placeholder="Misal: BELI KERTAS" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
                </div>
                
                <div class="mb-3">
                  <label class="form-label">Nominal</label>
                  <input type="number" name="nominal" class="form-control" required placeholder="Contoh: 100000">
                </div>
                
                <div class="mb-3">
                  <label class="form-label">Foto Bukti</label>
                  <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
                </div>
                
                <input type="hidden" name="tanggal" value="<?= date('Y-m-d') ?>">
              </div>
              
              <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Tambah</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Tambah Pemasukan -->
        <div class="modal fade" id="addIncome" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <form method="post" class="modal-content" id="incomeForm">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Pemasukan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="date" value="<?= $start_date ?>">
                <input type="hidden" name="tambah_pemasukan" value="1">
                <div class="mb-3">
                  <label class="form-label">Keterangan</label>
                  <input type="text" name="information" class="form-control" required placeholder="Misal: PENJUALAN ONLINE" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase();">
                </div>
                <div class="mb-3">
                  <label class="form-label">Nominal</label>
                  <input type="number" name="nominal" class="form-control" required placeholder="Contoh: 200000">
                </div>
                <input type="hidden" name="tanggal" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Tambah</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Edit Pengeluaran -->
        <div class="modal fade" id="editExpenditureModal" tabindex="-1" aria-labelledby="editExpenditureModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="updateExpenditureForm">
              <div class="modal-header">
                <h5 class="modal-title" id="editExpenditureModalLabel">Edit Data Pengeluaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="expenditure_id" id="edit-expenditure-id">
                <input type="hidden" name="type" id="edit-expenditure-type" value="expenditures">
                <input type="hidden" name="date" value="<?= $start_date ?>">

                <div class="mb-3">
                  <label class="form-label">Keterangan</label>
                  <input type="text" class="form-control" name="information" id="edit-info" required>
                </div>

                <div class="mb-3">
                  <label class="form-label">Nominal</label>
                  <input type="number" class="form-control" name="nominal" id="edit-nominal" required>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Edit Pemasukan -->
        <div class="modal fade" id="editIncomeModal" tabindex="-1" aria-labelledby="editIncomeModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="updateIncomeForm">
              <div class="modal-header">
                <h5 class="modal-title" id="editIncomeModalLabel">Edit Data Pemasukan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="income_id" id="edit-income-id">
                <input type="hidden" name="type" id="edit-income-type" value="income">
                <input type="hidden" name="date" value="<?= $start_date ?>">

                <div class="mb-3">
                  <label class="form-label">Keterangan</label>
                  <input type="text" class="form-control" name="information" id="edit-income-info" required>
                </div>

                <div class="mb-3">
                  <label class="form-label">Nominal</label>
                  <input type="number" class="form-control" name="nominal" id="edit-income-nominal" required>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Hapus -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formDeleteFinance">
              
              <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="start_date_hapus" value="<?= $start_date ?>">
                <input type="hidden" name="end_date_hapus" value="<?= $end_date ?>">
                <input type="hidden" name="id" id="delete-id">
                <input type="hidden" name="type" id="delete-type">
                <p>Apakah Anda yakin ingin menghapus data ini?</p>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              </div>
            </form>
          </div>
        </div>


        <div class="mt-3 mb-4 text-center">
          <button class="btn btn-danger me-2" id="btnAddExpenditure" data-bs-toggle="modal" data-bs-target="#addExpenditure">+ Pengeluaran</button>
          <button class="btn btn-success" id="btnAddIncome" data-bs-toggle="modal" data-bs-target="#addIncome">+ Pemasukan</button>
        </div>

      </div>

    </div>
  </div>

  <?php include BASE_PATH . '/footer.php'; ?>
</div>

<script>

document.addEventListener('DOMContentLoaded', () => {
  const expenditureForm = document.getElementById('expenditureForm');
  expenditureForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('finance_action?action=create_expenditure', {
      method : 'POST',
      body : formData
    }).then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text) });
        }
        return response.json();
    }).then(data => {
      if (data.success) {
        showAlert('success', data.message);
        setTimeout(() => {
          window.location.reload();
        },3000);
      }else{
        showAlert('error', data.message);
      }
    })
  });

  const incomeForm = document.getElementById('incomeForm');
  incomeForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('finance_action?action=create_income', {
      method : 'POST',
      body : formData
    }).then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text) });
        }
        return response.json();
    }).then(data => {
      if (data.success) {
        showAlert('success', data.message);
        setTimeout(() => {
          window.location.reload();
        },3000);
      }else{
        showAlert('error', data.message);
      }
    })
  });

  const refresForm = document.getElementById('refresForm');
  refresForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('finance_action?action=sync_finance_by_interval_date', {
      method : 'POST',
      body : formData
    }).then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text) });
        }
        return response.json();
    }).then(data => {
      if (data.success) {
        showAlert('success', data.message);
        setTimeout(() => {
          window.location.reload();
        },3000);
      }else{
        showAlert('error', data.message);
      }
    })
  });

  const updateIncomeForm = document.getElementById('updateIncomeForm');
  updateIncomeForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('finance_action?action=update_income', {
      method : 'POST',
      body : formData
    }).then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text) });
        }
        return response.json();
    }).then(data => {
      if (data.success) {
        showAlert('success', data.message);
        setTimeout(() => {
          window.location.reload();
        },3000);
      }else{
        alert('error');
      }
    })
  });

  const updateExpenditureForm = document.getElementById('updateExpenditureForm');
  updateExpenditureForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('finance_action?action=update_expenditure', {
      method : 'POST',
      body : formData
    }).then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text) });
        }
        return response.json();
    }).then(data => {
      if (data.success) {
        showAlert('success', data.message);
        setTimeout(() => {
          window.location.reload();
        },3000);
      }else{
        showAlert('error', data.message);
      }
    })
  });

  

});



</script>
<script>
  const editExpenditureModal = document.getElementById('editExpenditureModal');
  const editIncomeModal = document.getElementById('editIncomeModal');

  if (editExpenditureModal) {
    editExpenditureModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;

      const info = button.getAttribute('data-info');
      const nominal = button.getAttribute('data-nominal');
      const id = button.getAttribute('data-id');
      const type = button.getAttribute('data-type');

      editExpenditureModal.querySelector('#edit-expenditure-id').value = id;
      editExpenditureModal.querySelector('#edit-expenditure-type').value = type || 'expenditures';
      editExpenditureModal.querySelector('#edit-info').value = info;
      editExpenditureModal.querySelector('#edit-nominal').value = nominal;
    });
  }

  if (editIncomeModal) {
    editIncomeModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;

      const info = button.getAttribute('data-info');
      const nominal = button.getAttribute('data-nominal');
      const id = button.getAttribute('data-id');
      const type = button.getAttribute('data-type');

      editIncomeModal.querySelector('#edit-income-id').value = id;
      editIncomeModal.querySelector('#edit-income-type').value = type || 'income';
      editIncomeModal.querySelector('#edit-income-info').value = info;
      editIncomeModal.querySelector('#edit-income-nominal').value = nominal;
    });
  }

  const deleteModal = document.getElementById('deleteModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;

      const id = button.getAttribute('data-id');
      const type = button.getAttribute('data-type');
      
      deleteModal.querySelector('#delete-id').value = id;
      deleteModal.querySelector('#delete-type').value = type;
    });
  }

  const formDeleteFinance = document.getElementById('formDeleteFinance');
  if (formDeleteFinance) {
    formDeleteFinance.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const type = formData.get('type');
      const action = type === 'expenditures' ? 'delete_expenditure' : 'delete_income';

      fetch('finance_action?action=' + action, {
        method: 'POST',
        body: formData
      }).then(response => {
        if (!response.ok) {
          return response.text().then(text => { throw new Error(text) });
        }
        return response.json();
      }).then(data => {
        if (data.success) {
          showAlert('success', data.message);
          setTimeout(() => {
            window.location.reload();
          }, 2000);
        } else {
          showAlert('error', data.message);
          console.log('Error creating expenditure:', data.message);
        }
      }).catch(error => {
        showAlert('error', 'Error: ' + error.message);
      });
    });
  }

</script>
<script>
document.getElementById('btnExportExcel').addEventListener('click', async () => {
  const toko = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";
  const startDate = document.querySelector('input[name="start_date"]').value || "";
  const endDate = document.querySelector('input[name="end_date"]').value || "";
  const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

  const workbook = new ExcelJS.Workbook();

  function styleHeaderRow(row) {
    row.font = { bold: true };
    row.eachCell(cell => {
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCCE5FF' } };
      cell.alignment = { horizontal: 'center', vertical: 'middle' };
      cell.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
    });
  }
  function styleDataRow(row) {
    row.eachCell(cell => {
      cell.alignment = cell.alignment || { vertical: 'middle', horizontal: 'left' };
      cell.border = { top: { style: 'thin' }, bottom: { style: 'thin' }, left: { style: 'thin' }, right: { style: 'thin' } };
    });
  }

  const sheet = workbook.addWorksheet("Laporan Keuangan");

  sheet.mergeCells("A1:H1");
  sheet.getCell("A1").value = toko;
  sheet.getCell("A1").alignment = { horizontal: 'center', vertical: 'middle' };
  sheet.getCell("A1").font = { bold: true, size: 16 };

  sheet.mergeCells("A2:H2");
  sheet.getCell("A2").value = alamat;
  sheet.getCell("A2").alignment = { horizontal: 'center' };

  sheet.addRow([]);
  sheet.mergeCells("A4:H4");
  sheet.getCell("A4").value = "Laporan Keuangan";
  sheet.getCell("A4").alignment = { horizontal: 'center' };
  sheet.getCell("A4").font = { bold: true, size: 14 };

  sheet.mergeCells("A5:H5");
  sheet.getCell("A5").value = tanggal;
  sheet.getCell("A5").alignment = { horizontal: 'center' };

  sheet.addRow([]);

  // ===== DATA KEUANGAN =====
  const headerKeuangan = ['No', 'Omset Offline', 'Omset Online', 'Total Omset', 'Transfer Masuk','Cash Masuk', 'Pengeluaran', 'Saldo Kas', 'Periode'];
  styleHeaderRow(sheet.addRow(headerKeuangan));

  const rowsKeuangan = document.querySelectorAll("#tableKeuangan tbody tr");
  rowsKeuangan.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length > 7) {
      const parseRupiah = (str) => parseInt(str.replace(/Rp\s?|-|\./g, '').trim()) || 0;

      const no = tds[0].innerText.trim();
      const omsetOffline = parseRupiah(tds[1].innerText);
      const omsetOnline = parseRupiah(tds[2].innerText);
      const totalOmset = parseRupiah(tds[3].innerText);
      const transfer = parseRupiah(tds[4].innerText);
      const cash = parseRupiah(tds[5].innerText);
      const pengeluaran = parseRupiah(tds[6].innerText);
      const saldoKas = parseRupiah(tds[7].innerText);
      const periode = tds[8].innerText.trim();

      const row = sheet.addRow([no, omsetOffline, omsetOnline, totalOmset, transfer, cash, pengeluaran, saldoKas, periode]);
      [2, 3, 4, 5, 6, 7].forEach(i => {
        row.getCell(i).numFmt = '#,##0';
        row.getCell(i).alignment = { horizontal: 'right', vertical: 'middle' };
      });
      styleDataRow(row);
    }
  });

  sheet.addRow([]);
  sheet.addRow([]);

  const pengeluaranTitleRowNum = sheet.lastRow.number + 1;
  sheet.mergeCells(`A${pengeluaranTitleRowNum}:E${pengeluaranTitleRowNum}`);
  sheet.getCell(`A${pengeluaranTitleRowNum}`).value = "Data Pengeluaran Bulan Ini";
  sheet.getCell(`A${pengeluaranTitleRowNum}`).font = { bold: true, size: 14 };
  sheet.getCell(`A${pengeluaranTitleRowNum}`).alignment = { horizontal: 'center' };

  sheet.addRow([]);
  const headerPengeluaran = ['No', 'Keterangan', 'Nominal', 'Tanggal'];
  styleHeaderRow(sheet.addRow(headerPengeluaran));


  const rowsPengeluaran = document.querySelectorAll("#tablePengeluaran tbody tr");
  rowsPengeluaran.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 4) {
      const no = tds[0].innerText.trim();
      const ket = tds[1].innerText.trim();
      const nominal = parseInt(tds[2].innerText.replace(/Rp\s?|-|\./g, '').trim()) || 0;
      const tanggal = tds[3].innerText.trim();

      const row = sheet.addRow([no, ket, nominal, tanggal]);
      row.getCell(3).numFmt = '#,##0';
      styleDataRow(row);
    }
  });


  // Jeda 2 baris
  sheet.addRow([]);
  sheet.addRow([]);

  // ===== DATA PEMASUKAN =====
  const pemasukanTitleRowNum = sheet.lastRow.number + 1;
  sheet.mergeCells(`A${pemasukanTitleRowNum}:E${pemasukanTitleRowNum}`);
  sheet.getCell(`A${pemasukanTitleRowNum}`).value = "Data Pemasukan Bulan Ini";
  sheet.getCell(`A${pemasukanTitleRowNum}`).font = { bold: true, size: 14 };
  sheet.getCell(`A${pemasukanTitleRowNum}`).alignment = { horizontal: 'center' };

  sheet.addRow([]);
  const headerPemasukan = ['No', 'Keterangan', 'Nominal', 'Tanggal'];
  styleHeaderRow(sheet.addRow(headerPemasukan));

  const rowsPemasukan = document.querySelectorAll("#tablePemasukan tbody tr");
  rowsPemasukan.forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 4) {
      const no = tds[0].innerText.trim();
      const ket = tds[1].innerText.trim();
      const nominal = parseInt(tds[2].innerText.replace(/Rp\s?|-|\./g, '').trim()) || 0;
      const tanggal = tds[3].innerText.trim();

      const row = sheet.addRow([no, ket, nominal, tanggal]);
      row.getCell(3).numFmt = '#,##0';
      styleDataRow(row);
    }
  });

  // Set lebar kolom
  sheet.columns = [
    { width: 6 },  // No
    { width: 18 }, // Omset Offline / Keterangan
    { width: 18 }, // Omset Online / Nominal
    { width: 18 }, // Total Omset / Tanggal
    { width: 18 }, // Transfer Masuk / Aksi
    { width: 18 }, // Pengeluaran (kosong untuk pemasukan)
    { width: 18 }, // Saldo Kas (kosong)
    { width: 15 }  // Periode (kosong)
  ];

  // Save file
  const buffer = await workbook.xlsx.writeBuffer();
  saveAs(new Blob([buffer]), `Laporan_Keuangan_${startDate}_sd_${endDate}.xlsx`);
});

document.getElementById('btnExportWord').addEventListener('click', async function () {
  const { Document, Packer, Paragraph, Table, TableCell, TableRow, TextRun, WidthType, AlignmentType, BorderStyle } = window.docx;

  const toko = "<?= addslashes($storeName) ?>";
  const alamat = "<?= addslashes($storeAddress) ?>";

  const startDate = document.querySelector('input[name="start_date"]').value || "";
  const endDate = document.querySelector('input[name="end_date"]').value || "";
  const tanggal = (startDate && endDate) ? `Tanggal ${startDate} s.d. ${endDate}` : 'Tanggal -';

  // Fungsi helper untuk membuat header paragraph center
  function createHeader(text, size=28, bold=true, spacingAfter=200) {
    return new Paragraph({
      children: [new TextRun({ text, bold, size })],
      alignment: AlignmentType.CENTER,
      spacing: { after: spacingAfter }
    });
  }

  // Fungsi helper buat buat table cell teks
  function createCell(text, bold = false, align = AlignmentType.LEFT) {
    return new TableCell({
      children: [new Paragraph({ children: [new TextRun({ text, bold })], alignment: align })],
      margins: { top: 100, bottom: 100, left: 100, right: 100 }
    });
  }

  // Fungsi buat styling border table
  const borders = {
    top: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    bottom: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    left: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    right: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
    insideVertical: { style: BorderStyle.SINGLE, size: 1, color: "000000" },
  };

  // ======= Bikin Tabel Keuangan =======
  let keuanganRows = [];

  // Header keuangan
  keuanganRows.push(new TableRow({
    children: [
      createCell("No", true, AlignmentType.CENTER),
      createCell("Omset Offline", true, AlignmentType.CENTER),
      createCell("Omset Online", true, AlignmentType.CENTER),
      createCell("Total Omset", true, AlignmentType.CENTER),
      createCell("Transfer Masuk", true, AlignmentType.CENTER),
      createCell("Pengeluaran", true, AlignmentType.CENTER),
      createCell("Saldo Kas", true, AlignmentType.CENTER),
      createCell("Periode", true, AlignmentType.CENTER),
    ],
    tableHeader: true,
  }));

  // Ambil data dari tabel #tableKeuangan
  document.querySelectorAll("#tableKeuangan tbody tr").forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length === 8) {
      const parseRupiah = (str) => parseInt(str.replace(/Rp\s?|-|\./g, '').trim()) || 0;

      keuanganRows.push(new TableRow({
        children: [
          createCell(tds[0].innerText.trim(), false, AlignmentType.CENTER),
          createCell(tds[1].innerText.trim()),
          createCell(tds[2].innerText.trim()),
          createCell(tds[3].innerText.trim()),
          createCell(tds[4].innerText.trim()),
          createCell(tds[5].innerText.trim()),
          createCell(tds[6].innerText.trim()),
          createCell(tds[7].innerText.trim(), false, AlignmentType.CENTER),
        ],
      }));
    }
  });

  const tableKeuangan = new Table({
    rows: keuanganRows,
    width: {
      size: 100,
      type: WidthType.PERCENTAGE,
    },
    borders,
  });

  // ======= Bikin Tabel Pengeluaran =======
  let pengeluaranRows = [];

  // Header pengeluaran tanpa kolom aksi
  pengeluaranRows.push(new TableRow({
    children: [
      createCell("No", true, AlignmentType.CENTER),
      createCell("Keterangan", true, AlignmentType.CENTER),
      createCell("Nominal", true, AlignmentType.CENTER),
      createCell("Tanggal", true, AlignmentType.CENTER),
    ],
    tableHeader: true,
  }));

  document.querySelectorAll("#tablePengeluaran tbody tr").forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 4) {
      pengeluaranRows.push(new TableRow({
        children: [
          createCell(tds[0].innerText.trim(), false, AlignmentType.CENTER),
          createCell(tds[1].innerText.trim()),
          createCell(tds[2].innerText.trim()),
          createCell(tds[3].innerText.trim(), false, AlignmentType.CENTER),
        ],
      }));
    }
  });

  const tablePengeluaran = new Table({
    rows: pengeluaranRows,
    width: {
      size: 100,
      type: WidthType.PERCENTAGE,
    },
    borders,
  });

  // ======= Bikin Tabel Pemasukan =======
  let pemasukanRows = [];

  // Header pemasukan tanpa kolom aksi
  pemasukanRows.push(new TableRow({
    children: [
      createCell("No", true, AlignmentType.CENTER),
      createCell("Keterangan", true, AlignmentType.CENTER),
      createCell("Nominal", true, AlignmentType.CENTER),
      createCell("Tanggal", true, AlignmentType.CENTER),
    ],
    tableHeader: true,
  }));

  document.querySelectorAll("#tablePemasukan tbody tr").forEach(tr => {
    const tds = tr.querySelectorAll("td");
    if (tds.length >= 4) {
      pemasukanRows.push(new TableRow({
        children: [
          createCell(tds[0].innerText.trim(), false, AlignmentType.CENTER),
          createCell(tds[1].innerText.trim()),
          createCell(tds[2].innerText.trim()),
          createCell(tds[3].innerText.trim(), false, AlignmentType.CENTER),
        ],
      }));
    }
  });

  const tablePemasukan = new Table({
    rows: pemasukanRows,
    width: {
      size: 100,
      type: WidthType.PERCENTAGE,
    },
    borders,
  });

  // Buat document word
  const doc = new Document({
    sections: [{
      children: [
        createHeader(toko, 32),
        createHeader(alamat, 24),
        createHeader("Laporan Keuangan", 28),
        createHeader(tanggal, 24, false, 400),

        createHeader("Keuangan Terkini", 26, true, 200),
        tableKeuangan,

        new Paragraph({ text: "", spacing: { after: 400 }}), // spasi antar tabel

        createHeader("Data Pengeluaran Bulan Ini", 26, true, 200),
        tablePengeluaran,

        new Paragraph({ text: "", spacing: { after: 400 }}), // spasi antar tabel

        createHeader("Data Pemasukan Bulan Ini", 26, true, 200),
        tablePemasukan,
      ]
    }]
  });

  const blob = await Packer.toBlob(doc);
  saveAs(blob, `Laporan_Keuangan_${startDate}_sd_${endDate}.docx`);
});


</script>
<script>
function showImageModal(src) {
  document.getElementById('modalImage').src = src;
  const modal = new bootstrap.Modal(document.getElementById('imageModal'));
  modal.show();
}
</script>


</body>
</html>
