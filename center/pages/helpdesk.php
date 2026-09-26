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

<table class="table-modern">
    <thead>
        <tr>
            <th>ID</th>
            <th>Pengguna</th>
            <th>Kategori</th>
            <th>Subjek</th>
            <th>Detail</th>
            <th>Lampiran</th>
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
                $picture = $ticket['picture'] ?? '';
                $pictureUrl = $picture ? rtrim($_ENV['BASE_URL_UPLOAD'], '/') . '/image/help_center/' . $picture : '';
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
                    <div class="migrated-style-25">
                        <span class="status-badge status-<?= strtolower(htmlspecialchars($status)) ?>">
                            <?= htmlspecialchars(strtoupper($ticket['category'] ?? '-')) ?>
                        </span>
                    </div>
                </td>
                <td>
                    <div class="ticket-subject"><?= htmlspecialchars($ticket['subject'] ?? '-') ?></div>
                </td>
                <td>
                    <div class="ticket-detail"><?= htmlspecialchars($ticket['detail'] ?? '-') ?></div>
                </td>
                <td>
                    <?php if ($pictureUrl): ?>
                        <a href="<?= htmlspecialchars($pictureUrl) ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?= htmlspecialchars($pictureUrl) ?>" alt="Lampiran" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
                        </a>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form class="migrated-style-24" method="POST" action="/action?action=update_help_status">
                        <input type="hidden" name="id" value="<?= (int) ($ticket['id'] ?? 0) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle migrated-style-100" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ubah status tiket">
                                <?= htmlspecialchars($statusName) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 migrated-style-20">
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <li>
                                    <button type="button" class="dropdown-item <?= $status === $value ? 'active' : '' ?>" data-status="<?= htmlspecialchars($value) ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        </div>
                    </form>
                    <div class="migrated-style-25">
                        <span class="status-badge status-<?= strtolower(htmlspecialchars($status)) ?>">
                            <?= htmlspecialchars($statusName) ?>
                        </span>
                    </div>
                </td>
                <td><?= htmlspecialchars($ticket['datetime'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
document.querySelectorAll('[data-status]').forEach(function(button) {
    button.addEventListener('click', function() {
        const form = button.closest('form');
        form.querySelector('input[name="status"]').value = button.dataset.status;
        form.submit();
    });
});
</script>