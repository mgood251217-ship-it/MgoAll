<?php
require_once '../connect.php';
require_once BASE_PATH . '/session.php';
require_once BASE_PATH . '/components/Table.php';
require_once BASE_PATH . '/models/Activity.php';

$activityModel = new Activity($koneksi);
$activity = $activityModel->getActivitiesByStoreId($store_id);


$htmlTableAktivitas = renderTable([
    'data'        => $activity,
    'table_class' => 'table table-bordered table-striped',
    'thead_class' => 'table-primary',
    'tbody_tr_class' => 'activity-row',
    'columns'     => [
        [
            'header' => 'No',
            'type'   => 'number'
        ],
        [
            'header' => 'Judul',
            'field'  => 'title'
        ],
        [
            'header' => 'Pesan',
            'field'  => 'message'
        ],
        [
            'header' => 'Keterangan',
            'field'  => 'information'
        ],
        [
            'header' => 'Order ID',
            'field'  => 'order_id'
        ],
        [
            'header' => 'Tanggal',
            'field'  => 'date'
        ],
        [
            'header' => 'Aksi',
            'render' => function($row) {
                $isChecked = ($row['done'] == "1") ? 'checked' : '';
                return '<input type="checkbox" 
                            class="styled-checkbox activity-check" 
                            data-id="' . htmlspecialchars($row['activity_id']) . '" 
                            ' . $isChecked . '>';
            }
        ]
    ]
]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Aktivitas</title>
  <?php include BASE_PATH . '/header.php'; ?>
  <style>
    .checkbox-cell {
      text-align: center;
      vertical-align: middle;
      padding: 0;
    }

    .styled-checkbox {
      width: 20px;
      height: 20px;
      cursor: pointer;
      accent-color: #dc3545;
      border-radius: 6px;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      display: inline-block;
    }

    .styled-checkbox:hover {
      transform: scale(1.15);
      box-shadow: 0 0 6px rgba(220, 53, 69, 0.5);
    }

    .styled-checkbox:checked {
      transform: scale(1.2);
      box-shadow: 0 0 6px rgba(220, 53, 69, 0.8);
    }
    #toggleAll {
      min-width: 120px;
      transition: 0.2s ease;
    }
    #toggleAll:hover {
      transform: scale(1.05);
    }

  </style>
</head>

<body>
<div id="main-wrapper" >
  <?php include BASE_PATH . '/navbar.php'; ?>

  <div id="main-content" <?= ($mode ?? 0) === 1 ? 'class="dark-mode"' : '' ?>>
    <?php include BASE_PATH . '/sidebar.php'; ?>

    <div id="page-content-wrapper">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Log Aktivitas</h1>
        <input type="text" id="searchInput" class="form-control" placeholder="Cari Nama Barang..." style="max-width: 250px;">
      </div>
      <?php if (empty($activity)): ?>
        <div class="alert alert-warning">Belum ada aktivitas</div>
      <?php else: ?>
        <div class="table-responsive">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5>Daftar Aktivitas</h5>
          <button id="toggleAll" class="btn btn-sm btn-success">✅ Cek Semua</button>
        </div>
              <?= $htmlTableAktivitas ?>
          </div>

      <?php endif; ?>
    </div>
  </div>

  <?php include BASE_PATH . '/footer.php'; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggleAllBtn = document.getElementById('toggleAll');
  const rows = document.querySelectorAll('tr.activity-row');

  function updateActivityStatus(activityId, doneValue, checkbox) {
    fetch('finance_action.php?action=update_activity', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `activity_id=${activityId}&done=${doneValue}`
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        checkbox.checked = !checkbox.checked;
      }
    })
    .catch(err => {
      console.error(err);
      checkbox.checked = !checkbox.checked;
    });
  }

  rows.forEach(row => {
    const checkbox = row.querySelector('.activity-check');
    const activityId = checkbox.dataset.id;

    row.addEventListener('click', e => {
      if (e.target.classList.contains('activity-check')) return;
      checkbox.checked = !checkbox.checked;
      updateActivityStatus(activityId, checkbox.checked ? 1 : 0, checkbox);
    });

    checkbox.addEventListener('change', () => {
      updateActivityStatus(activityId, checkbox.checked ? 1 : 0, checkbox);
    });
  });

  toggleAllBtn.addEventListener('click', () => {
    const allChecked = [...document.querySelectorAll('.activity-check')].every(cb => cb.checked);
    const newStatus = allChecked ? 0 : 1;

    document.querySelectorAll('.activity-check').forEach(cb => {
      cb.checked = newStatus === 1;
      const activityId = cb.dataset.id;
      updateActivityStatus(activityId, newStatus, cb);
    });

    toggleAllBtn.textContent = newStatus === 1 ? '❌ Uncek Semua' : '✅ Cek Semua';
    toggleAllBtn.classList.toggle('btn-danger', newStatus === 1);
    toggleAllBtn.classList.toggle('btn-success', newStatus === 0);
  });
});
</script>

</body>
</html>
