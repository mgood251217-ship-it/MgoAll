<?php
require_once __DIR__ . '/../functions/helpers.php';

class TutorialController
{
    private $koneksi;

    public function __construct($koneksi)
    {
        $this->koneksi = $koneksi;
    }

    public function tutorials()
    {
        $data = $this->koneksi->query('SELECT * FROM tutorials ORDER BY created_at DESC, id DESC')->fetch_all(MYSQLI_ASSOC);
        $base = rtrim($_ENV['BASE_URL_UPLOAD'] ?? '', '/');
        foreach ($data as &$tutorial) {
            $tutorial['video_url'] = !empty($tutorial['video_filename'])
                ? $base . '/video/tutorials/' . rawurlencode($tutorial['video_filename']) : null;
            $tutorial['thumbnail_url'] = !empty($tutorial['thumbnail_filename'])
                ? $base . '/image/tutorials/' . rawurlencode($tutorial['thumbnail_filename']) : null;
        }
        unset($tutorial);
        send_json_response(true, 'Data tutorial berhasil diambil.', $data);
    }

}
