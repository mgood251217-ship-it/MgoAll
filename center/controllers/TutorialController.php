<?php

class TutorialController
{
    private $db;
    const MAX_VIDEO_SIZE = 104857600;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function all()
    {
        return $this->db->query('SELECT * FROM tutorials ORDER BY created_at DESC, id DESC')->fetch_all(MYSQLI_ASSOC);
    }

    public function create($input, $file, $thumbnail = null)
    {
        $this->save($input, $file, null, $thumbnail);
    }

    public function find($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM tutorials WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new InvalidArgumentException('Tutorial tidak ditemukan.');
        }
        return $row;
    }

    public function update($id, $input, $file, $thumbnail = null)
    {
        $this->save($input, $file, $id, $thumbnail);
    }

    private function removeVideo($filename, $folder = 'video/tutorials')
    {
        if (!$filename) return;
        $base = rtrim($_ENV['BASE_PATH_UPLOAD'] ?? '', '/\\');
        if ($base === '' || basename($filename) !== $filename || strpos($filename, '\\') !== false) {
            error_log('Tutorial: lokasi video tidak valid untuk penghapusan.');
            return;
        }
        $path = $base . '/' . $folder . '/' . $filename;
        if (is_file($path) && !unlink($path)) {
            error_log('Tutorial: gagal menghapus video ' . $filename);
        }
    }

    public function delete($id)
    {
        $old = $this->find($id);
        $stmt = $this->db->prepare('DELETE FROM tutorials WHERE id = ?');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) throw new RuntimeException('Gagal menghapus tutorial.');
        $stmt->close();
        $this->removeVideo($old['video_filename']);
        $this->removeVideo($old['thumbnail_filename'] ?? null, 'image/tutorials');
    }

    private function save($input, $file, $id = null, $thumbnail = null)
    {
        $old = $id !== null ? $this->find($id) : null;
        $title = trim((string)($input['title'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        if ($title === '' || $content === '') {
            throw new InvalidArgumentException('Judul dan isi tutorial wajib diisi.');
        }
        if (mb_strlen($title) > 200 || mb_strlen($category) > 100 || strlen($content) > 1000000) {
            throw new InvalidArgumentException('Judul maksimal 200 karakter, kategori 100 karakter, dan isi 1 MB.');
        }

        $filename = $old['video_filename'] ?? null;
        $destination = null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? 'Video melebihi batas upload server. Pilih video yang lebih kecil.'
                    : 'Upload video gagal. Silakan pilih ulang file dan coba lagi.');
            }
            if (!is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) <= 0 || filesize($file['tmp_name']) > self::MAX_VIDEO_SIZE) {
                throw new InvalidArgumentException('Ukuran video harus antara 1 byte dan 100 MB.');
            }
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $types = ['mp4' => ['video/mp4', 'application/mp4'], 'webm' => ['video/webm'], 'ogv' => ['video/ogg', 'application/ogg']];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset($types[$extension]) || !in_array($mime, $types[$extension], true)) {
                throw new InvalidArgumentException('File harus berupa video MP4, WebM, atau OGV yang valid.');
            }
            $uploadBase = rtrim($_ENV['BASE_PATH_UPLOAD'] ?? '', '/\\');
            if ($uploadBase === '' || empty($_ENV['BASE_URL_UPLOAD'])) {
                throw new RuntimeException('Konfigurasi lokasi atau URL upload belum tersedia.');
            }
            $directory = $uploadBase . '/video/tutorials';
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('Tidak dapat membuat folder video.');
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = $directory . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('Tidak dapat menyimpan video.');
            }
        }
        $thumbnailName = $old['thumbnail_filename'] ?? null;
        $thumbnailDestination = null;
        try {
            if ($thumbnail && ($thumbnail['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($thumbnail['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($thumbnail['tmp_name']) || filesize($thumbnail['tmp_name']) > 5242880) {
                    throw new InvalidArgumentException('Upload thumbnail gagal. Maksimal 5 MB.');
                }
                $image = getimagesize($thumbnail['tmp_name']);
                $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!$image || !isset($types[$image['mime']]) || $image[0] > 10000 || $image[1] > 10000) {
                    throw new InvalidArgumentException('Thumbnail harus berupa JPG, PNG, atau WebP yang valid (maksimal 10000 piksel per sisi).');
                }
                $uploadBase = rtrim($_ENV['BASE_PATH_UPLOAD'] ?? '', '/\\');
                if ($uploadBase === '' || empty($_ENV['BASE_URL_UPLOAD'])) throw new RuntimeException('Konfigurasi upload belum tersedia.');
                $directory = $uploadBase . '/image/tutorials';
                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Folder thumbnail tidak dapat dibuat.');
                $thumbnailName = bin2hex(random_bytes(16)) . '.' . $types[$image['mime']];
                $thumbnailDestination = $directory . '/' . $thumbnailName;
                if (!move_uploaded_file($thumbnail['tmp_name'], $thumbnailDestination)) throw new RuntimeException('Thumbnail gagal disimpan.');
            } elseif ($destination) {
                throw new InvalidArgumentException('Thumbnail video belum tersedia. Pilih gambar thumbnail atau gunakan browser yang dapat membaca video tersebut.');
            }
            if ($id !== null) {
                $stmt = $this->db->prepare('UPDATE tutorials SET title = ?, category = ?, content = ?, video_filename = ?, thumbnail_filename = ? WHERE id = ?');
                $stmt->bind_param('sssssi', $title, $category, $content, $filename, $thumbnailName, $id);
            } else {
                $stmt = $this->db->prepare('INSERT INTO tutorials (title, category, content, video_filename, thumbnail_filename) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('sssss', $title, $category, $content, $filename, $thumbnailName);
            }
            if (!$stmt->execute()) {
                throw new RuntimeException('Gagal menyimpan tutorial.');
            }
            $stmt->close();
        } catch (Throwable $e) {
            if ($thumbnailDestination && is_file($thumbnailDestination)) unlink($thumbnailDestination);
            if ($destination && is_file($destination)) {
                unlink($destination);
            }
            throw $e;
        }
        if ($old && $destination && $old['video_filename'] !== $filename) {
            $this->removeVideo($old['video_filename']);
        }
        if ($old && $thumbnailDestination && ($old['thumbnail_filename'] ?? null) !== $thumbnailName) {
            $this->removeVideo($old['thumbnail_filename'] ?? null, 'image/tutorials');
        }
    }
}
