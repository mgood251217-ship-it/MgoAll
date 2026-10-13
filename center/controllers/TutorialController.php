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

    public function create($input, $file)
    {
        $title = trim((string)($input['title'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        if ($title === '' || $content === '') {
            throw new InvalidArgumentException('Judul dan isi tutorial wajib diisi.');
        }
        if (mb_strlen($title) > 200 || mb_strlen($category) > 100 || strlen($content) > 1000000) {
            throw new InvalidArgumentException('Judul maksimal 200 karakter, kategori 100 karakter, dan isi 1 MB.');
        }

        $filename = null;
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
        try {
            $stmt = $this->db->prepare('INSERT INTO tutorials (title, category, content, video_filename) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $title, $category, $content, $filename);
            if (!$stmt->execute()) {
                throw new RuntimeException('Gagal menyimpan tutorial.');
            }
            $stmt->close();
        } catch (Throwable $e) {
            if ($destination && is_file($destination)) {
                unlink($destination);
            }
            throw $e;
        }
    }
}
