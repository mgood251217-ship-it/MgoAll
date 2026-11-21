<?php

final class DesktopLoginTicket {
    private $db;
    private $path;

    public function __construct($db, $path = null) {
        $this->db = $db;
        $this->path = $path ?? sys_get_temp_dir() . '/mgo-desktop-login-' . hash('sha256', dirname(__DIR__, 2)) . '.json';
    }

    // Periksa ulang izin dan manager toko saat tiket dibuat maupun digunakan.
    public function owner($administratorId, $storeId) {
        $stmt = $this->db->prepare('SELECT a.access, s.administrator, s.owner_id
            FROM administrator a JOIN stores s ON s.store_id = ? WHERE a.administrator_id = ?');
        $stmt->bind_param('ii', $storeId, $administratorId);
        $stmt->execute(); $store = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$store || ($store['access'] !== 'ALL' && ($store['access'] === '' || (string)$store['access'] !== (string)$store['administrator']))) {
            throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
        }
        if ($store['access'] !== 'ALL') {
            $ready = $this->db->query("SELECT EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'administrator_permissions') AS ready")->fetch_assoc()['ready'];
            if ($ready) {
                $stmt = $this->db->prepare("SELECT allowed FROM administrator_permissions WHERE administrator_id = ? AND permission = 'set_session'");
                $stmt->bind_param('i', $administratorId); $stmt->execute();
                $permission = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if ($permission && !(int)$permission['allowed']) throw new InvalidArgumentException('Anda tidak memiliki izin kelola toko.');
            }
        }
        $stmt = $this->db->prepare('SELECT user_id, username, name, store_id, initial, role, picture FROM users WHERE user_id = ? AND store_id = ? AND is_deleted = 0');
        $stmt->bind_param('ii', $store['owner_id'], $storeId); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$user) throw new InvalidArgumentException('Manager toko belum tersedia atau sudah pindah toko. Periksa pengaturan toko.');
        return $user;
    }

    public function issue($administratorId, $storeId) {
        $user = $this->owner($administratorId, $storeId);
        $ticket = bin2hex(random_bytes(32));
        $this->update(function (&$entries) use ($ticket, $administratorId, $storeId, $user) {
            if (count($entries) >= 2000) throw new RuntimeException('Penyimpanan tiket penuh.');
            $entries[hash('sha256', $ticket)] = [
                'administrator_id' => $administratorId, 'store_id' => $storeId,
                'user_id' => (int)$user['user_id'], 'expires' => time() + 120,
            ];
        });
        return $ticket;
    }

    public function consume($ticket) {
        if (!is_string($ticket) || !preg_match('/^[a-f0-9]{64}$/D', $ticket)) {
            throw new InvalidArgumentException('Tautan MGO tidak valid. Klik Kelola kembali dari Center.');
        }
        $entry = $this->update(function (&$entries) use ($ticket) {
            $key = hash('sha256', $ticket);
            $entry = $entries[$key] ?? null;
            unset($entries[$key]);
            return $entry;
        });
        if (!$entry) throw new InvalidArgumentException('Tautan sudah digunakan, kedaluwarsa, atau berasal dari server berbeda. Klik Kelola kembali dari Center.');
        $user = $this->owner($entry['administrator_id'], $entry['store_id']);
        if ((int)$user['user_id'] !== $entry['user_id']) throw new InvalidArgumentException('Manager toko telah berubah. Klik Kelola kembali dari Center.');
        return $user;
    }

    private function update($callback) {
        $handle = @fopen($this->path, 'c+');
        if (!$handle) throw new RuntimeException('Penyimpanan tiket tidak tersedia.');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('Gagal mengunci tiket.');
            @chmod($this->path, 0600);
            $raw = stream_get_contents($handle);
            $entries = $raw === '' ? [] : json_decode($raw, true);
            if (!is_array($entries)) throw new RuntimeException('Penyimpanan tiket tidak valid.');
            foreach ($entries as $key => $entry) if (($entry['expires'] ?? 0) <= time()) unset($entries[$key]);
            $result = $callback($entries);
            $json = json_encode($entries, JSON_THROW_ON_ERROR);
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('Gagal menyimpan tiket.');
            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
