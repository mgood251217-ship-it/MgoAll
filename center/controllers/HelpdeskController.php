<?php
class HelpdeskController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getTickets() {
        $sql = "
            SELECT
                hc.id,
                hc.user_id,
                hc.category,
                hc.subject,
                hc.detail,
                hc.answer,
                hc.status,
                hc.datetime,
                hc.picture,
                u.name AS user_name,
                u.username,
                u.initial,
                s.name AS store_name
            FROM help_center hc
            LEFT JOIN users u ON u.user_id = hc.user_id
            LEFT JOIN stores s ON s.store_id = u.store_id
            ORDER BY hc.datetime DESC, hc.id DESC
        ";

        $result = $this->koneksi->query($sql);
        $tickets = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $tickets[] = $row;
            }
            $result->free();
        }

        return $tickets;
    }

    public function updateAnswer() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['admin_logged_in']) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
        if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['helpdesk_csrf']) || !hash_equals($_SESSION['helpdesk_csrf'], $_POST['csrf'])) return false;

        $id = (int)($_POST['id'] ?? 0);
        $answer = $_POST['answer'] ?? null;
        if ($id <= 0 || !is_string($answer)) return false;
        $answer = trim($answer);

        try {
            $stmt = $this->koneksi->prepare('SELECT id FROM help_center WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if (!$exists) return false;

            $stmt = $this->koneksi->prepare('UPDATE help_center SET answer = ? WHERE id = ?');
            $stmt->bind_param('si', $answer, $id);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        } catch (Throwable $e) {
            error_log('Helpdesk answer update failed: ' . $e->getMessage());
            return false;
        }
    }

    public function updateStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $status = isset($_POST['status']) ? strtoupper(trim($_POST['status'])) : '';
        $allowed = ['SENT', 'OPEN', 'PROCESS', 'REJECT', 'ACCEPT', 'FINISHED'];

        if ($id <= 0 || !in_array($status, $allowed, true)) {
            return false;
        }

        $stmt = $this->koneksi->prepare("UPDATE help_center SET status = ? WHERE id = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('si', $status, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}
