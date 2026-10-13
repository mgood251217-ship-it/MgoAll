<?php
function tutorialJsonResponse($success, $message = '', $status = 200)
{
    // Buang output dari include/warning agar respons hanya berisi JSON.
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$base = $base === '.' ? '' : $base;
$tutorialUrl = $base . '/index.php?url=tutorials';
$isUploadRequest = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
if (!isset($_SESSION['admin_logged_in'])) {
    if ($isUploadRequest) {
        tutorialJsonResponse(false, 'Sesi login berakhir. Silakan login kembali.', 401);
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
            $controller->update($id, $values, $_FILES['video'] ?? null, $_FILES['thumbnail'] ?? null);
            $message = 'Tutorial berhasil diperbarui.';
        } elseif ($action === 'create') {
            $controller->create($values, $_FILES['video'] ?? null, $_FILES['thumbnail'] ?? null);
            $message = 'Tutorial berhasil ditambahkan.';
        } else {
            throw new InvalidArgumentException('Aksi tutorial tidak valid.');
        }
        if ($isUploadRequest) {
            $_SESSION['tutorial_success'] = $message;
            tutorialJsonResponse(true, $message);
        }
        $_SESSION['tutorial_success'] = $message;
        header('Location: ' . $tutorialUrl);
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
    if ($e instanceof mysqli_sql_exception && strpos($e->getMessage(), 'thumbnail_filename') !== false) {
        $error = 'Kolom thumbnail_filename belum tersedia. Jalankan migrasi tutorials_thumbnail_migration.sql pada database yang digunakan server.';
    } else {
        $error = 'Tutorial tidak dapat dimuat atau disimpan. Pastikan tabel tutorials sudah tersedia dan folder upload dapat ditulis.';
    }
}
if ($isUploadRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    tutorialJsonResponse(false, $error ?: 'Tutorial gagal disimpan.', 422);
}
?>
<div class="page-header"><h2>Tutorial</h2></div>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= $escape($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<div class="filter-card">
    <h4><?= $editing ? 'Edit Tutorial' : 'Tambah Tutorial' ?></h4>
    <form id="tutorial-form" method="post" action="<?= $escape($tutorialUrl) ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= $escape($_SESSION['tutorial_csrf']) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= (int)$editId ?>">
        <?php if ($editing && $editing['video_filename']): ?><p class="form-text">Video saat ini: <?= $escape($editing['video_filename']) ?>. Kosongkan upload untuk mempertahankan video, atau pilih video baru untuk menggantinya.</p><?php endif; ?>
        <div class="mb-3"><label for="tutorial-title" class="form-label">Judul</label><input class="form-control" id="tutorial-title" name="title" maxlength="200" required value="<?= $escape($values['title']) ?>"></div>
        <div class="mb-3"><label for="tutorial-category" class="form-label">Kategori (opsional)</label><input class="form-control" id="tutorial-category" name="category" maxlength="100" value="<?= $escape($values['category']) ?>"></div>
        <div class="mb-3"><label for="tutorial-content" class="form-label">Isi tutorial</label><textarea class="form-control" id="tutorial-content" name="content" rows="6" required><?= $escape($values['content']) ?></textarea></div>
        <div class="mb-3"><label for="tutorial-video" class="form-label">Video (opsional)</label><input class="form-control" type="file" id="tutorial-video" name="video" accept=".mp4,.webm,.ogv"><div class="form-text">MP4, WebM, OGV. Maksimal 100 MB; batas server: upload <?= $escape(ini_get('upload_max_filesize')) ?>, total request <?= $escape(ini_get('post_max_size')) ?>. Jika penyimpanan gagal, pilih ulang video.</div></div>
        <div class="mb-3"><label for="tutorial-thumbnail" class="form-label">Thumbnail (opsional)</label><input type="file" class="form-control" id="tutorial-thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG, WebP, maksimal 5 MB. Jika dikosongkan saat upload video baru, thumbnail diambil otomatis dari video. Saat edit tanpa video baru, thumbnail lama dipertahankan.</div></div>
        <div id="tutorial-upload-progress" class="mb-3" hidden>
            <div class="progress"><div id="tutorial-upload-bar" class="progress-bar" role="progressbar" aria-label="Upload tutorial" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%">0%</div></div>
            <p id="tutorial-upload-status" class="form-text" role="status" aria-live="polite"></p>
        </div>
        <div id="tutorial-upload-error" class="alert alert-danger" role="alert" hidden></div>
        <button id="tutorial-submit" type="submit" class="btn-primary-custom"><i class="fas fa-plus"></i> Simpan Tutorial</button>
        <?php if ($editing): ?><a class="btn btn-secondary" href="<?= $escape($tutorialUrl) ?>">Batal Edit</a><?php endif; ?>
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
    function prepareThumbnail(file) {
        return new Promise(function (resolve, reject) {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5242880) {
                reject(new Error('Thumbnail harus JPG, PNG, atau WebP, maksimal 5 MB.'));
                return;
            }
            const image = new Image();
            const url = URL.createObjectURL(file);
            image.onload = function () {
                try {
                    const scale = Math.min(1, 1280 / image.naturalWidth, 1280 / image.naturalHeight);
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
                    canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
                    const context = canvas.getContext('2d');
                    context.fillStyle = '#ffffff';
                    context.fillRect(0, 0, canvas.width, canvas.height);
                    context.drawImage(image, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob(function (blob) {
                        URL.revokeObjectURL(url);
                        if (blob) resolve(blob);
                        else reject(new Error('Thumbnail gagal diproses. Pilih gambar lain.'));
                    }, 'image/jpeg', .85);
                } catch (e) {
                    URL.revokeObjectURL(url);
                    reject(new Error('Thumbnail gagal diproses. Pilih gambar lain.'));
                }
            };
            image.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('File thumbnail tidak dapat dibaca sebagai gambar.'));
            };
            image.src = url;
        });
    }
    function screenshot(file) {
        return new Promise(function (resolve, reject) {
            const video = document.createElement('video');
            const url = URL.createObjectURL(file);
            video.muted = true;
            video.preload = 'auto';
            const timer = setTimeout(function () { finish(null); }, 20000);
            function finish(blob) {
                clearTimeout(timer);
                video.onloadeddata = video.onseeked = video.onerror = null;
                video.removeAttribute('src');
                video.load();
                URL.revokeObjectURL(url);
                if (blob) resolve(blob);
                else reject(new Error('Tidak dapat mengambil screenshot video. Silakan upload thumbnail secara manual.'));
            }
            function capture() {
                const canvas = document.createElement('canvas');
                canvas.width = Math.min(1280, video.videoWidth);
                canvas.height = Math.round(video.videoHeight * canvas.width / video.videoWidth);
                if (!canvas.width || !canvas.height) { finish(null); return; }
                try {
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob(finish, 'image/jpeg', .85);
                } catch (e) { finish(null); }
            }
            video.onloadeddata = function () {
                if (Number.isFinite(video.duration) && video.duration > .1) {
                    video.onseeked = capture;
                    video.currentTime = Math.min(1, video.duration / 2);
                } else capture();
            };
            video.onerror = function () { finish(null); };
            video.src = url;
        });
    }
    form.addEventListener('submit', async function (event) {
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
        const selectedThumbnail = document.getElementById('tutorial-thumbnail').files[0];
        if (selectedThumbnail) {
            status.textContent = 'Menyiapkan thumbnail...';
            try {
                const blob = await prepareThumbnail(selectedThumbnail);
                data.set('thumbnail', blob, 'thumbnail.jpg');
            } catch (e) { fail(e.message); return; }
        } else if (hasVideo) {
            status.textContent = 'Mengambil screenshot video untuk thumbnail...';
            try {
                const blob = await screenshot(document.getElementById('tutorial-video').files[0]);
                data.set('thumbnail', blob, 'thumbnail.jpg');
            } catch (e) { fail(e.message); return; }
        }
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
                console.error('Respons upload tutorial tidak valid:', xhr.status, xhr.responseText);
                fail(xhr.status === 413 ? 'Ukuran upload melebihi batas server. Pilih file yang lebih kecil.' : 'Respons server tidak valid (HTTP ' + xhr.status + '). Periksa daftar tutorial sebelum mencoba kembali.');
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
        // Gunakan URL halaman yang sudah berhasil dibuka, termasuk route/query-nya.
        const uploadUrl = new URL(window.location.href);
        uploadUrl.searchParams.delete('edit');
        uploadUrl.hash = '';
        xhr.open('POST', uploadUrl.href);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(data);
    });
})();
</script>
<section class="tutorial-library" aria-labelledby="tutorial-library-title">
    <div class="tutorial-library-header">
        <div><h4 id="tutorial-library-title">Daftar Tutorial <span class="tutorial-count"><?= count($tutorials) ?></span></h4><p>Panduan dan video tutorial yang telah ditambahkan.</p></div>
    </div>
    <?php if (!$tutorials): ?>
        <div class="tutorial-empty"><i class="fas fa-book-open" aria-hidden="true"></i><h5>Belum ada tutorial</h5><p>Tambahkan panduan pertama melalui form di atas.</p><a class="btn btn-outline-primary" href="#tutorial-title">Tambah Tutorial</a></div>
    <?php endif; ?>
    <div class="tutorial-grid">
    <?php foreach ($tutorials as $tutorial): ?>
        <article class="tutorial-card">
            <?php if ($tutorial['video_filename']): ?>
                <div class="tutorial-media"><video controls preload="none" poster="<?= !empty($tutorial['thumbnail_filename']) ? $escape(rtrim($_ENV['BASE_URL_UPLOAD'] ?? '', '/') . '/image/tutorials/' . rawurlencode($tutorial['thumbnail_filename'])) : '' ?>" aria-label="<?= $escape('Video ' . $tutorial['title']) ?>" src="<?= $escape(rtrim($_ENV['BASE_URL_UPLOAD'] ?? '', '/') . '/video/tutorials/' . rawurlencode($tutorial['video_filename'])) ?>">Browser Anda tidak mendukung pemutar video.</video></div>
            <?php elseif (!empty($tutorial['thumbnail_filename'])): ?>
                <div class="tutorial-media"><img style="width:100%;height:100%;object-fit:cover" loading="lazy" alt="<?= $escape($tutorial['title']) ?>" src="<?= $escape(rtrim($_ENV['BASE_URL_UPLOAD'] ?? '', '/') . '/image/tutorials/' . rawurlencode($tutorial['thumbnail_filename'])) ?>"></div>
            <?php else: ?>
                <div class="tutorial-media tutorial-text-cover"><i class="fas fa-book-open" aria-hidden="true"></i><span>Panduan tertulis</span></div>
            <?php endif; ?>
            <div class="tutorial-card-body">
                <div class="tutorial-meta"><span class="tutorial-category"><?= $escape($tutorial['category'] ?: 'Tanpa kategori') ?></span><span><i class="fas <?= $tutorial['video_filename'] ? 'fa-video' : 'fa-file-alt' ?>" aria-hidden="true"></i> <?= $tutorial['video_filename'] ? 'Video' : 'Artikel' ?></span></div>
                <h5><?= $escape($tutorial['title']) ?></h5>
                <p class="tutorial-excerpt"><?= $escape(mb_strimwidth(preg_replace('/\s+/u', ' ', $tutorial['content']), 0, 160, '...')) ?></p>
                <details class="tutorial-details"><summary>Baca selengkapnya</summary><div><?= $escape($tutorial['content']) ?></div></details>
            </div>
            <div class="tutorial-card-footer">
                <time datetime="<?= $escape(str_replace(' ', 'T', $tutorial['created_at'])) ?>"><i class="far fa-calendar" aria-hidden="true"></i> <?= $escape(date('d/m/Y H:i', strtotime($tutorial['created_at']))) ?></time>
                <div class="tutorial-actions">
                <a class="btn btn-sm btn-outline-primary" aria-label="<?= $escape('Edit ' . $tutorial['title']) ?>" href="<?= $escape($tutorialUrl . '&edit=' . (int)$tutorial['id']) ?>"><i class="fas fa-pen" aria-hidden="true"></i> Edit</a>
                <form method="post" action="<?= $escape($tutorialUrl) ?>" onsubmit="if (!confirm('Hapus tutorial beserta videonya?')) return false; this.querySelector('button').disabled = true;">
                    <input type="hidden" name="csrf" value="<?= $escape($_SESSION['tutorial_csrf']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$tutorial['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" aria-label="<?= $escape('Hapus ' . $tutorial['title']) ?>" type="submit"><i class="fas fa-trash-alt" aria-hidden="true"></i> Hapus</button>
                </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</section>
