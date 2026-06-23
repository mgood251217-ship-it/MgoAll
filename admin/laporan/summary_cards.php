 <style>
  .summary-cards {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    overflow: hidden;
    margin-bottom: 20px;
  }

  .card-summary {
    flex: 1 1 0;
    min-width: 0;
    padding: 7px;
    border-radius: 12px;
    text-align: center;
    text-decoration: none;
    color: white;
    font-size: 0.75rem;
    transition: 0.2s;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  }

  .card-summary:hover {
    transform: scale(1.02);
    color: white;
  }

  .summary-title {
    font-weight: bold;
    font-size: 1rem;
    margin-bottom: 4px;
  }

  .summary-value {
    font-size: 0.7rem;
    line-height: 1.2;
  }

  .bg1 { background-color: #2196f3; }
  .bg2 { background-color: #ff9800; }
  .bg3 { background-color: #4caf50; }
  .bg4 { background-color: #f44336; }
  .bg5 { background-color: #673ab7; }
  .bg6 { background-color: #e91e63; }
  .bg7 { background-color: #009688; }
  .bg8 { background-color:rgb(0, 33, 180); }
  .bg9 { background-color:rgb(147, 0, 0); }
  .bg10 { background-color:rgb(248, 141, 230); }
  .bg11 { background-color:rgba(0, 74, 28, 1); }
  .summary-cards {
    width: 100%;
    box-sizing: border-box;
  }
</style>

<div class="summary-cards">
  <a href="transaksi_detil" class="card-summary bg1">
    <div class="summary-title">Transaksi Detil</div>

  </a>
  <a href="transaksi_harian" class="card-summary bg2">
    <div class="summary-title">Transaksi Harian</div>

  </a>
  <a href="transaksi_bulanan" class="card-summary bg3">
    <div class="summary-title">Transaksi Bulanan</div>

  </a>
  <a href="transaksi_item" class="card-summary bg4">
    <div class="summary-title">Transaksi per Item</div>

  </a>
  <a href="transaksi_konsumen" class="card-summary bg11">
    <div class="summary-title">Transaksi per Konsumen</div>

  </a>
  <a href="omset_item" class="card-summary bg5">
    <div class="summary-title">Omset per Item</div>

  </a>
  <a href="pemakaian_bahan" class="card-summary bg6">
    <div class="summary-title">Pemakaian Bahan</div>

  </a>
  <a href="daftar_piutang" class="card-summary bg7">
    <div class="summary-title">Daftar Piutang</div>

  </a>
  <a href="data_pelunasan" class="card-summary bg10">
    <div class="summary-title">Data Pelunasan</div>

  </a>
  <a href="keuangan" class="card-summary bg8">
    <div class="summary-title">Keuangan</div>

  </a>
  <a href="statistik_karyawan" class="card-summary bg9">
    <div class="summary-title">Statistik Karyawan</div>

  </a>
</div>
