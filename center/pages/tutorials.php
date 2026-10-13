<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$base = $base === '.' ? '' : $base;
$isUploadRequest = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
if (!isset($_SESSION['admin_logged_in'])) {
    if ($isUploadRequest) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Sesi login berakhir. Silakan login kembali.']);
        exit;
    }
    header('Location: ' . $base . '/login');
    exit;
}
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../controllers/TutorialController.php';
$controller = new TutorialController($koneksi);
$_SESSION['tutorial_csrf'] = $_SESSION['tutorial_csrf'] ?? bin2hex(random_bytes(32));
$escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$error = '';
$success = $_SESSION['tutorial_success'] ?? '';
unset($_SESSION['tutorial_success']);
$values = ['title' => '', 'category' => '', 'content' => ''];
$tutorials = [];
$editId = 0;
$editing = null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($values as $key => $value) {
            $values[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
        }
        if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
            throw new InvalidArgumentException('Ukuran upload melebihi batas server (post_max_size: ' . ini_get('post_max_size') . '). Pilih video yang lebih kecil.');
        }
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['tutorial_csrf'], $_POST['csrf'])) {
            throw new InvalidArgumentException('Sesi form tidak valid. Muat ulang halaman dan coba lagi.');
        }
        $action = $_POST['action'] ?? 'create';
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($action === 'update' || $action === 'delete') {
            if (!$id || $id < 1) throw new InvalidArgumentException('ID tutorial tidak valid.');
        }
        if ($action === 'delete') {
            $controller->delete($id);
            $message = 'Tutorial berhasil dihapus.';
        } elseif ($action === 'update') {
            $editId = $id;
            $editing = $controller->find($id);
            $controller->update($id, $values, $_FILES['video'] ?? null);
            $message = 'Tutorial berhasil diperbarui.';
        } elseif ($action === 'create') {
            $controller->create($values, $_FILES['video'] ?? null);
            $message = 'Tutorial berhasil ditambahkan.';
        } else {
            throw new InvalidArgumentException('Aksi tutorial tidak valid.');
        }
        if ($isUploadRequest) {
            $_SESSION['tutorial_success'] = $message;
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true]);
            exit;
        }
        $_SESSION['tutorial_success'] = $message;
        header('Location: ' . $base . '/tutorials');
        exit;
    }
    if (isset($_GET['edit'])) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
        if (!$editId || $editId < 1) throw new InvalidArgumentException('ID tutorial tidak valid.');
        $editing = $controller->find($editId);
        foreach ($values as $key => $value) $values[$key] = $editing[$key];
    }
    $tutorials = $controller->all();
} catch (InvalidArgumentException $e) {
    $error = $e->getMessage();
    try {
        $tutorials = $controller->all();
    } catch (Throwable $loadError) {
        error_log('Tutorial list: ' . $loadError->getMessage());
    }
} catch (Throwable $e) {
    error_log('Tutorial: ' . $e->getMessage());
    $error = 'Tutorial tidak dapat dimuat atau disimpan. Pastikan tabel tutorials sudah tersedia dan folder upload dapat ditulis.';
}
if ($isUploadRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $error ?: 'Tutorial gagal disimpan.']);
    exit;
}
?>
<div class="page-header"><h2>Tutorial</h2></div>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= $escape($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<div class="filter-card">
    <h4><?= $editing ? 'Edit Tutorial' : 'Tambah Tutorial' ?></h4>
    <form id="tutorial-form" method="post" action="<?= $escape($base . '/tutorials') ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= $escape($_SESSION['tutorial_csrf']) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= (int)$editId ?>">
        <?php if ($editing && $editing['video_filename']): ?><p class="form-text">Video saat ini: <?= $escape($editing['video_filename']) ?>. Kosongkan upload untuk mempertahankan video, atau pilih video baru untuk menggantinya.</p><?php endif; ?>
        <div class="mb-3"><label for="tutorial-title" class="form-label">Judul</label><input class="form-control" id="tutorial-title" name="title" maxlength="200" required value="<?= $escape($values['title']) ?>"></div>
        <div class="mb-3"><label for="tutorial-category" class="form-label">Kategori (opsional)</label><input class="form-control" id="tutorial-category" name="category" maxlength="100" value="<?= $escape($values['category']) ?>"></div>
        <div class="mb-3"><label for="tutorial-content" class="form-label">Isi tutorial</label><textarea class="form-control" id="tutorial-content" name="content" rows="6" required><?= $escape($values['content']) ?></textarea></div>
        <div class="mb-3"><label for="tutorial-video" class="form-label">Video (opsional)</label><input class="form-control" type="file" id="tutorial-video" name="video" accept=".mp4,.webm,.ogv"><div class="form-text">MP4, WebM, OGV. Maksimal 100 MB; batas server: upload <?= $escape(ini_get('upload_max_filesize')) ?>, total request <?= $escape(ini_get('post_max_size')) ?>. Jika penyimpanan gagal, pilih ulang video.</div></div>
        <div id="tutorial-upload-progress" class="mb-3" hidden>
            <div class="progress"><div id="tutorial-upload-bar" class="progress-bar" role="progressbar" aria-label="Upload tutorial" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%">0%</div></div>
            <p id="tutorial-upload-status" class="form-text" role="status" aria-live="polite"></p>
        </div>
        <div id="tutorial-upload-error" class="alert alert-danger" role="alert" hidden></div>
        <button id="tutorial-submit" type="submit" class="btn-primary-custom"><i class="fas fa-plus"></i> Simpan Tutorial</button>
        <?php if ($editing): ?><a class="btn btn-secondary" href="<?= $escape($base . '/tutorials') ?>">Batal Edit</a><?php endif; ?>
    </form>
</div>
<script>
(function () {
    const form = document.getElementById('tutorial-form');
    const button = document.getElementById('tutorial-submit');
    const progress = document.getElementById('tutorial-upload-progress');
    const bar = document.getElementById('tutorial-upload-bar');
    const status = document.getElementById('tutorial-upload-status');
    const error = document.getElementById('tutorial-upload-error');
    let submitting = false;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (submitting) return;
        const data = new FormData(form);
        const hasVideo = document.getElementById('tutorial-video').files.length > 0;
        const xhr = new XMLHttpRequest();
        submitting = true;
        button.disabled = true;
        error.hidden = true;
        progress.hidden = false;
        bar.style.width = '0%';
        bar.textContent = '0%';
        bar.setAttribute('aria-valuenow', '0');
        status.textContent = hasVideo ? 'Mengupload video: 0%' : 'Mengirim tutorial…';
        function fail(message) {
            submitting = false;
            button.disabled = false;
            status.textContent = 'Penyimpanan belum berhasil.';
            error.textContent = message;
            error.hidden = false;
        }
        xhr.upload.addEventListener('progress', function (event) {
            if (!event.lengthComputable) {
                status.textContent = 'Mengupload… ukuran total belum tersedia.';
                return;
            }
            const percent = Math.min(100, Math.floor(event.loaded / event.total * 100));
            bar.style.width = percent + '%';
            bar.textContent = percent + '%';
            bar.setAttribute('aria-valuenow', String(percent));
            status.textContent = percent === 100 ? 'Upload 100%. Menyimpan tutorial…' : 'Mengupload: ' + percent + '%';
        });
        xhr.upload.addEventListener('load', function () {
            bar.style.width = '100%';
            bar.textContent = '100%';
            bar.setAttribute('aria-valuenow', '100');
            status.textContent = 'Upload 100%. Menyimpan tutorial…';
        });
        xhr.addEventListener('load', function () {
            let result;
            try { result = JSON.parse(xhr.responseText); } catch (e) {
                fail('Respons server tidak dapat dibaca. Periksa daftar tutorial sebelum mencoba kembali.');
                return;
            }
            if (xhr.status >= 200 && xhr.status < 300 && result.success) {
                status.textContent = 'Tutorial berhasil disimpan.';
                window.location.assign(form.action);
                return;
            }
            fail(result.message || 'Tutorial gagal disimpan. Silakan coba kembali.');
        });
        xhr.addEventListener('error', function () {
            fail('Koneksi terputus. Periksa daftar tutorial sebelum mencoba kembali.');
        });
        xhr.addEventListener('abort', function () { fail('Upload dibatalkan.'); });
        xhr.open('POST', form.action);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(data);
    });
})();
</script>
<div class="table-container">
    <h4 class="table-title">Daftar Tutorial (<?= count($tutorials) ?>)</h4>
    <?php if (!$tutorials): ?><p class="p-3">Belum ada tutorial.</p><?php endif; ?>
    <?php foreach ($tutorials as $tutorial): ?>
        <article class="p-3 border-bottom">
            <h5><?= $escape($tutorial['title']) ?></h5>
            <div class="d-flex gap-2 mb-2">
                <a class="btn btn-sm btn-outline-primary" href="<?= $escape($base . '/tutorials?edit=' . (int)$tutorial['id']) ?>">Edit</a>
                <form method="post" action="<?= $escape($base . '/tutorials') ?>" onsubmit="if (!confirm('Hapus tutorial beserta videonya?')) return false; this.querySelector('button').disabled = true;">
                    <input type="hidden" name="csrf" value="<?= $escape($_SESSION['tutorial_csrf']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$tutorial['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                </form>
            </div>
            <p class="text-muted"><?= $escape($tutorial['category'] ?: 'Tanpa kategori') ?> &middot; <?= $escape($tutorial['created_at']) ?></p>
            <details><summary>Baca tutorial</summary><div class="mt-2" style="white-space:pre-wrap;overflow-wrap:anywhere"><?= $escape($tutorial['content']) ?></div></details>
            <?php if ($tutorial['video_filename']): ?>
                <video class="mt-3" controls preload="none" style="width:100%;max-width:640px" src="<?= $escape(rtrim($_ENV['BASE_URL_UPLOAD'] ?? '', '/') . '/video/tutorials/' . rawurlencode($tutorial['video_filename'])) ?>">Browser Anda tidak mendukung pemutar video.</video>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
