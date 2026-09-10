<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/HelpdeskController.php';

$helpdesk = new HelpdeskController($koneksi);
$tickets = $helpdesk->getTickets();

$badgeColors = [
    'SENT' => '#0ea5e9',
    'OPEN' => '#3b82f6',
    'PROCESS' => '#8b5cf6',
    'REJECT' => '#ef4444',
    'ACCEPT' => '#10b981',
    'FINISHED' => '#64748b',
];

$statusLabels = [
    'SENT' => 'Sent',
    'OPEN' => 'Open',
    'PROCESS' => 'Process',
    'REJECT' => 'Reject',
    'ACCEPT' => 'Accept',
    'FINISHED' => 'Finished',
];
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .summary-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    .summary-card .label {
        display: block;
        margin-bottom: 8px;
        color: #64748b;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .summary-card .value {
        color: #0f172a;
        font-size: 1.6rem;
        font-weight: 700;
    }

    .table-container {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .table-modern {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .table-modern th,
    .table-modern td {
        padding: 16px 18px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }

    .table-modern th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .table-modern tbody tr:hover {
        background-color: #f8fafc;
    }

    .ticket-subject {
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .ticket-detail {
        font-size: 0.82rem;
        color: #475569;
        line-height: 1.5;
        max-width: 360px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 12px;
        border-radius: 999px;
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .user-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .user-name {
        font-weight: 600;
        color: #0f172a;
    }

    .user-store {
        color: #64748b;
        font-size: 0.78rem;
    }

    .select-status {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 8px 10px;
        background: #fff;
        color: #0f172a;
        font-size: 0.82rem;
        min-width: 120px;
    }

    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: #64748b;
        font-size: 0.95rem;
    }
</style>

<div class="page-header">
    <h2>Help Desk</h2>
</div>

<div class="summary-grid">
    <div class="summary-card">
        <span class="label">Total Ticket</span>
        <span class="value"><?= count($tickets) ?></span>
    </div>
    <div class="summary-card">
        <span class="label">Sent</span>
        <span class="value"><?= count(array_filter($tickets, fn($ticket) => strtoupper((string) ($ticket['status'] ?? '')) === 'SENT')) ?></span>
    </div>
    <div class="summary-card">
        <span class="label">Open</span>
        <span class="value"><?= count(array_filter($tickets, fn($ticket) => strtoupper((string) ($ticket['status'] ?? '')) === 'OPEN')) ?></span>
    </div>
    <div class="summary-card">
        <span class="label">Process</span>
        <span class="value"><?= count(array_filter($tickets, fn($ticket) => strtoupper((string) ($ticket['status'] ?? '')) === 'PROCESS')) ?></span>
    </div>
    <div class="summary-card">
        <span class="label">Finished</span>
        <span class="value"><?= count(array_filter($tickets, fn($ticket) => strtoupper((string) ($ticket['status'] ?? '')) === 'FINISHED')) ?></span>
    </div>
</div>

<div class="table-container">
    <?php if (empty($tickets)): ?>
        <div class="empty-state">Belum ada tiket help desk yang masuk.</div>
    <?php else: ?>
        <table class="table-modern">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pengguna</th>
                    <th>Kategori</th>
                    <th>Subjek</th>
                    <th>Detail</th>
                    <th>Status</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <?php
                        $status = strtoupper((string) ($ticket['status'] ?? 'SENT'));
                        $statusName = $statusLabels[$status] ?? ucfirst(strtolower($status));
                        $statusColor = $badgeColors[$status] ?? '#64748b';
                    ?>
                    <tr>
                        <td>#<?= (int) ($ticket['id'] ?? 0) ?></td>
                        <td>
                            <div class="user-meta">
                                <span class="user-name"><?= htmlspecialchars($ticket['user_name'] ?? 'Unknown') ?></span>
                                <span class="user-store"><?= htmlspecialchars($ticket['store_name'] ?? 'Tanpa Toko') ?></span>
                                <span class="user-store"><?= htmlspecialchars($ticket['username'] ?? '-') ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge" style="background: #e2e8f0; color:#0f172a;">
                                <?= htmlspecialchars(strtoupper($ticket['category'] ?? '-')) ?>
                            </span>
                        </td>
                        <td>
                            <div class="ticket-subject"><?= htmlspecialchars($ticket['subject'] ?? '-') ?></div>
                        </td>
                        <td>
                            <div class="ticket-detail"><?= htmlspecialchars($ticket['detail'] ?? '-') ?></div>
                        </td>
                        <td>
                            <form method="POST" action="/action?action=update_help_status" style="display:flex; align-items:center; gap:8px;">
                                <input type="hidden" name="id" value="<?= (int) ($ticket['id'] ?? 0) ?>">
                                <select name="status" class="select-status" aria-label="Ubah status tiket" onchange="this.form.submit()">
                                    <?php foreach ($statusLabels as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <div style="margin-top:8px;">
                                <span class="status-badge" style="background: <?= htmlspecialchars($statusColor) ?>;">
                                    <?= htmlspecialchars($statusName) ?>
                                </span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($ticket['datetime'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
