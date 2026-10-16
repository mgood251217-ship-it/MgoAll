<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-container">
    <div class="header-title">Dashboard</div>

    <div class="stats-grid">
        <div class="stat-card border-accent">
            <div class="stat-icon bg-accent">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M21 4H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H3V6h18v12zm-2-7a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
            </div>
            <div class="stat-info">
                <h4>Total Saldo</h4>
                <h2>Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></h2>
                <p class="text-accent">Terkumpul Saat Ini</p>
            </div>
        </div>

        <div class="stat-card border-success">
            <div class="stat-icon bg-success">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
            </div>
            <div class="stat-info">
                <h4>Omzet Hari Ini</h4>
                <h2>Rp <?= number_format($omzet_hari_ini, 0, ',', '.') ?></h2>
                <p class="text-danger">Pengeluaran: Rp <?= number_format($keluar_hari_ini, 0, ',', '.') ?></p>
            </div>
        </div>

        <div class="stat-card border-warning">
            <div class="stat-icon bg-warning">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
            </div>
            <div class="stat-info">
                <h4>Pesanan Hari Ini</h4>
                <h2><?= $order_hari_ini ?> Transaksi</h2>
                <p class="text-warning">Belum Lunas: <?= $order_belum_lunas ?> Nota</p>
            </div>
        </div>

        <div class="stat-card border-danger">
            <div class="stat-icon bg-danger">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/><path d="M7 12h10v2H7z"/></svg>
            </div>
            <div class="stat-info">
                <h4>Data Barang</h4>
                <h2><?= $stock_data['total_produk'] ?? 0 ?> Produk</h2>
                <p class="text-muted">Kapasitas: <?= $stock_data['total_stok'] ?? 0 ?> Pcs</p>
            </div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-container">
            <div class="panel-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83M22 12A10 10 0 0 0 12 2v10z"/></svg>
                Grafik Penjualan 7 Hari Terakhir
            </div>
            <div class="dashboard-chart">
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
                    <div class="list-val bg-danger">Sisa <?= $item['stock'] ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="list-item">
                    <div class="list-title">Seluruh stok barang aman.</div>
                    <div class="list-val bg-success">Aman</div>
                </div>
            <?php endif; ?>

            <div class="panel-title mt-30">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM13 2v7h7"/></svg>
                Pintasan
            </div>
            <a href="/order/create" class="btn-action btn bg-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Buat Pesanan Baru
            </a>
            <a href="/product" class="btn-action btn bg-secondary text-muted">
                Kelola Inventori Barang
            </a>
        </div>
    </div>
</div>

<script>
    const chartLabels = <?= json_encode($chart_dates) ?>;
    const chartData = <?= json_encode($chart_omzet) ?>;

    const ctx = document.getElementById('salesChart').getContext('2d');
    const chartTheme = getComputedStyle(document.documentElement);
    const chartColor = name => chartTheme.getPropertyValue(name).trim();

    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, `color-mix(in srgb, ${chartColor('--success')} 50%, transparent)`);
    gradient.addColorStop(1, 'transparent');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Omzet',
                data: chartData,
                borderColor: chartColor('--success'),
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: chartColor('--surface'),
                pointBorderColor: chartColor('--success'),
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
                    grid: { borderDash: [5, 5], color: chartColor('--border') },
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
