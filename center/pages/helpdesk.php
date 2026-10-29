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
$_SESSION['helpdesk_csrf'] = $_SESSION['helpdesk_csrf'] ?? bin2hex(random_bytes(32));

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

<?php foreach (['swal_success' => 'success', 'swal_error' => 'danger'] as $key => $type): ?>
    <?php if (isset($_SESSION[$key])): ?>
        <div class="alert alert-<?= $type ?>" role="alert"><?= htmlspecialchars($_SESSION[$key], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION[$key]); ?>
    <?php endif; ?>
<?php endforeach; ?>

<div class="table-container"><div class="table-scroll">
<table class="table-modern">
    <thead>
        <tr>
            <th>ID</th>
            <th>Pengguna</th>
            <th>Kategori</th>
            <th>Subjek</th>
            <th>Detail</th>
            <th>Jawaban</th>
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
                    <div class="ticket-detail mb-2"><?= trim($ticket['answer'] ?? '') === '' ? '<span class="text-muted">Belum dijawab</span>' : nl2br(htmlspecialchars($ticket['answer'], ENT_QUOTES, 'UTF-8')) ?></div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#answerModal"
                        data-id="<?= (int)$ticket['id'] ?>" data-answer="<?= htmlspecialchars($ticket['answer'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= trim($ticket['answer'] ?? '') === '' ? 'Tambah Jawaban' : 'Edit Jawaban' ?>
                    </button>
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
</div></div>

<div class="modal fade" id="answerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content modal-content-custom" method="post" action="/action?action=update_help_answer" id="answerForm">
            <input type="hidden" name="id" id="answer_ticket_id">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['helpdesk_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title-custom" id="answerTitle">Jawaban Tiket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-custom">
                <label for="ticket_answer" class="form-label-custom">Jawaban</label>
                <textarea name="answer" id="ticket_answer" class="form-control-custom" rows="6" placeholder="Tulis jawaban untuk tiket ini..."></textarea>
            </div>
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-primary-custom">Simpan Jawaban</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('answerModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    if (!button) return;
    document.getElementById('answer_ticket_id').value = button.dataset.id;
    document.getElementById('ticket_answer').value = button.dataset.answer;
    document.getElementById('answerTitle').textContent = `Jawaban Tiket #${button.dataset.id}`;
    const submit = document.querySelector('#answerForm button[type="submit"]');
    submit.disabled = false;
    submit.textContent = 'Simpan Jawaban';
});
document.getElementById('answerForm').addEventListener('submit', function() {
    const submit = this.querySelector('button[type="submit"]');
    submit.disabled = true;
    submit.textContent = 'Menyimpan...';
});
document.querySelectorAll('[data-status]').forEach(function(button) {
    button.addEventListener('click', function() {
        const form = button.closest('form');
        form.querySelector('input[name="status"]').value = button.dataset.status;
        form.submit();
    });
});
</script>
