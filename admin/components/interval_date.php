<?php 
$showSearch = $showSearch ?? false;
$showExport = $showExport ?? false; 
?>

<form method="get" id="filterForm"></form>

<div class="row g-2 align-items-end justify-content-end flex-nowrap" style="margin-bottom:0;">
  
  <div class="col-auto">
    <label for="start_date" class="form-label">Dari</label>
    <input
      type="date"
      name="start_date"
      id="start_date"
      class="form-control"
      form="filterForm"
      value="<?= htmlspecialchars($_GET['start_date'] ?? date('Y-m-d')) ?>"
    >
  </div>
  
  <div class="col-auto">
    <label for="end_date" class="form-label">Sampai</label>
    <input
      type="date"
      name="end_date"
      id="end_date"
      class="form-control"
      form="filterForm"
      value="<?= htmlspecialchars($_GET['end_date'] ?? date('Y-m-d')) ?>"
    >
  </div>

  <?php if ($showSearch): ?>
  <div class="col-auto">
    <label for="search" class="form-label">Cari</label>
    <input
      type="text"
      name="search"
      id="search"
      value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
      class="form-control"
      placeholder="Nama / Nomorator"
      form="filterForm"
    >
  </div>
  <?php endif; ?>
  
  <div class="col-auto">
    <input type="submit" value="Filter" class="btn btn-primary" form="filterForm">
  </div>
    <?php if ($showExport): ?>
    <div class="col-auto">
        <button id="btnExportExcel" class="btn btn-success">Export Excel</button>
        <button id="btnExportWord" class="btn btn-primary">Export Word</button>
    </div>
    <?php endif; ?>
  
</div>