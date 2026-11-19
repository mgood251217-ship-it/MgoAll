<?php
class FailureReason {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function getAll() {
        return $this->db->query('SELECT failure_reason_id, type, name FROM failure_reasons ORDER BY type, name')->fetch_all(MYSQLI_ASSOC);
    }

    public function getByIds($ids) {
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT failure_reason_id, type, name FROM failure_reasons WHERE failure_reason_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function findOrCreate($type, $name) {
        $stmt = $this->db->prepare('INSERT INTO failure_reasons (type, name) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE failure_reason_id = LAST_INSERT_ID(failure_reason_id)');
        $stmt->bind_param('ss', $type, $name);
        if (!$stmt->execute()) throw new RuntimeException('Gagal menyimpan master alasan kegagalan.');
        $id = (int)$this->db->insert_id;
        $stmt->close();
        return $id;
    }
}
