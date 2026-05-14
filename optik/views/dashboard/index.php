<?php
global $pdo;
$store_id = $_SESSION['store_id'] ?? 0;
$today = date('Y-m-d');

require_once 'models/Finance.php';
$financeModel = new Finance($pdo);
$financeModel->refreshDailyFinance($store_id, $today);

$stmt = $pdo->prepare("
    SELECT 
        SUM(cash_revenue + transfer_revenue + cash_income + transfer_income) - 
        SUM(cash_expenditure + transfer_expenditure) as saldo_akhir
    FROM finance WHERE store_id = ? AND date <= ?
");
$stmt->execute([$store_id, $today]);
$saldo_akhir = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
    SELECT 
        SUM(cash_revenue + transfer_revenue) as omzet_hari_ini,
        SUM(cash_expenditure + transfer_expenditure) as keluar_hari_ini
    FROM finance WHERE store_id = ? AND date = ?
");
$stmt->execute([$store_id, $today]);
$fin_today = $stmt->fetch(PDO::FETCH_ASSOC);

$omzet_hari_ini = $fin_today['omzet_hari_ini'] ?? 0;
$keluar_hari_ini = $fin_today['keluar_hari_ini'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE store_id = ? AND DATE(create_at) = ?");
$stmt->execute([$store_id, $today]);
$order_hari_ini = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders o
    LEFT JOIN (SELECT order_id, SUM(nominal) as paid FROM payments GROUP BY order_id) p ON o.id = p.order_id
    WHERE o.store_id = ? AND (p.paid IS NULL OR p.paid < o.total)
");
$stmt->execute([$store_id]);
$order_belum_lunas = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(stock) as total_stok, COUNT(*) as total_produk FROM products WHERE store_id = ?");
$stmt->execute([$store_id]);
$stock_data = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT name, stock FROM products WHERE store_id = ? AND stock < 5 ORDER BY stock ASC LIMIT 6");
$stmt->execute([$store_id]);
$low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);


$chart_dates = [];
$chart_omzet = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_dates[] = date('d M', strtotime($d));
    
    $stmt = $pdo->prepare("SELECT SUM(cash_revenue + transfer_revenue) FROM finance WHERE store_id = ? AND date = ?");
    $stmt->execute([$store_id, $d]);
    $chart_omzet[] = (float)($stmt->fetchColumn() ?: 0);
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .dashboard-container { padding: 30px; width: 100%; overflow-y: auto; }
    .header-title { font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 25px; }

    .stats-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
        gap: 20px; 
        margin-bottom: 30px; 
    }
    .stat-card { 
        background: #fff; 
        padding: 20px; 
        border-radius: 16px; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        display: flex;
        align-items: center;
        border-left: 5px solid;
    }
    .stat-icon {
        width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px; color: white;
    }
    .stat-icon svg { width: 26px; height: 26px; }
    .stat-info h4 { margin: 0; font-size: 13px; color: #64748b; text-transform: uppercase; font-weight: 700; }
    .stat-info h2 { margin: 5px 0 0 0; font-size: 22px; color: #1e293b; font-weight: 800; }
    .stat-info p { margin: 5px 0 0 0; font-size: 12px; font-weight: 600; }

    .card-saldo { border-color: #8b5cf6; } .card-saldo .stat-icon { background: #8b5cf6; }
    .card-omzet { border-color: #10b981; } .card-omzet .stat-icon { background: #10b981; }
    .card-order { border-color: #f59e0b; } .card-order .stat-icon { background: #f59e0b; }
    .card-stok { border-color: #ef4444; } .card-stok .stat-icon { background: #ef4444; }

    .chart-grid { 
        display: grid; 
        grid-template-columns: 2fr 1fr; 
        gap: 20px; 
    }
    .chart-container, .side-panel {
        background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }
    .panel-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .panel-title svg { width: 18px; height: 18px; color: #64748b; }

    .list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #e2e8f0; }
    .list-item:last-child { border-bottom: none; }
    .list-title { font-size: 13px; font-weight: 600; color: #475569; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; padding-right: 10px;}
    .list-val { font-size: 13px; font-weight: 700; background: #fee2e2; color: #dc2626; padding: 4px 10px; border-radius: 12px;}
    .list-val.safe { background: #dcfce7; color: #166534; }

    .btn-action { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-bottom: 10px; font-size: 14px; }
    .btn-primary { background: #2b6cb0; color: white; border: none; }
    .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    @media (max-width: 992px) {
        .chart-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="dashboard-container">
    <div class="header-title">Dashboard</div>

    <div class="stats-grid">
        <div class="stat-card card-saldo">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M21 4H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H3V6h18v12zm-2-7a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
            </div>
            <div class="stat-info">
                <h4>Total Saldo</h4>
                <h2>Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></h2>
                <p style="color:#8b5cf6;">Terkumpul Saat Ini</p>
            </div>
        </div>

        <div class="stat-card card-omzet">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
            </div>
            <div class="stat-info">
                <h4>Omzet Hari Ini</h4>
                <h2>Rp <?= number_format($omzet_hari_ini, 0, ',', '.') ?></h2>
                <p style="color:#ef4444;">Pengeluaran: Rp <?= number_format($keluar_hari_ini, 0, ',', '.') ?></p>
            </div>
        </div>

        <div class="stat-card card-order">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
            </div>
            <div class="stat-info">
                <h4>Pesanan Hari Ini</h4>
                <h2><?= $order_hari_ini ?> Transaksi</h2>
                <p style="color:#f59e0b;">Belum Lunas: <?= $order_belum_lunas ?> Nota</p>
            </div>
        </div>

        <div class="stat-card card-stok">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/><path d="M7 12h10v2H7z"/></svg>
            </div>
            <div class="stat-info">
                <h4>Data Barang</h4>
                <h2><?= $stock_data['total_produk'] ?? 0 ?> Produk</h2>
                <p style="color:#64748b;">Kapasitas: <?= $stock_data['total_stok'] ?? 0 ?> Pcs</p>
            </div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-container">
            <div class="panel-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83M22 12A10 10 0 0 0 12 2v10z"/></svg>
                Grafik Penjualan 7 Hari Terakhir
            </div>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <div class="side-panel">
            <div class="panel-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg>
                Peringatan Stok Menipis
            </div>
            
            <?php if (!empty($low_stock_items)): ?>
                <?php foreach ($low_stock_items as $item): ?>
                <div class="list-item">
                    <div class="list-title" title="<?= htmlspecialchars($item['name']) ?>">
                        <?= htmlspecialchars($item['name']) ?>
                    </div>
                    <div class="list-val">Sisa <?= $item['stock'] ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="list-item">
                    <div class="list-title">Seluruh stok barang aman.</div>
                    <div class="list-val safe">Aman</div>
                </div>
            <?php endif; ?>
            
            <div class="panel-title" style="margin-top: 30px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM13 2v7h7"/></svg>
                Pintasan
            </div>
            <a href="/order/create" class="btn-action btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Buat Pesanan Baru
            </a>
            <a href="/product" class="btn-action btn-secondary">
                Kelola Inventori Barang
            </a>
        </div>
    </div>
</div>

<script>
    const chartLabels = <?= json_encode($chart_dates) ?>;
    const chartData = <?= json_encode($chart_omzet) ?>;

    const ctx = document.getElementById('salesChart').getContext('2d');
    
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.5)'); 
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)'); 

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Omzet',
                data: chartData,
                borderColor: '#10b981',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#10b981',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { borderDash: [5, 5], color: '#f1f5f9' },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000) + ' Jt';
                            if (value >= 1000) return (value / 1000) + ' Rb';
                            return value;
                        }
                    }
                },
                x: { 
                    grid: { display: false }
                }
            }
        }
    });
</script>