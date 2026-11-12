<?php
require_once __DIR__ . '/ProductHistoryController.php';

class ActivityController {
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function canView() { return (new ProductHistoryController($this->db))->canView(); }

    private function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $rows;
    }

    private function dateFilter($value) {
        if (!is_string($value)) throw new InvalidArgumentException('Tanggal tidak valid.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Tanggal tidak valid. Gunakan format YYYY-MM-DD.');
        return $date;
    }

    private function archives($store, $ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $archives = $this->query("SELECT r.*, a.name AS deleted_by_name FROM deleted_orders r
            LEFT JOIN administrator a ON a.administrator_id = r.deleted_by
            WHERE r.store_id = ? AND r.order_id IN ($placeholders)", array_merge([$store], $ids));
        $archivedItems = $this->query("SELECT i.*,
            (SELECT GROUP_CONCAT(f.name ORDER BY f.finishing_id SEPARATOR ', ') FROM finishings f
                WHERE f.store_id = i.store_id AND FIND_IN_SET(f.finishing_id, REPLACE(i.finishing, ' ', ''))) AS finishing_names
            FROM deleted_order_items i WHERE i.store_id = ? AND i.order_id IN ($placeholders)
            ORDER BY i.deleted_order_item_id", array_merge([$store], $ids));
        $items = [];
        foreach ($archivedItems as $item) $items[$item['order_id']][] = $item;
        foreach ($archives as &$archive) $archive['items'] = $items[$archive['order_id']] ?? [];
        unset($archive);
        return $archives;
    }

    public function getData() {
        if (!$this->canView()) throw new InvalidArgumentException('Halaman ini hanya untuk akun dengan akses ALL.');
        $startDate = $this->dateFilter($_GET['start_date'] ?? date('Y-m-d'));
        $endDate = $this->dateFilter($_GET['end_date'] ?? date('Y-m-d'));
        if ($startDate > $endDate) throw new InvalidArgumentException('Tanggal awal tidak boleh melewati tanggal akhir.');
        $start = $startDate->format('Y-m-d'); $end = $endDate->format('Y-m-d');
        // Batas akhir eksklusif mencakup seluruh hari, termasuk detik pecahan.
        $params = [$start . ' 00:00:00', $endDate->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];
        $stores = $this->query('SELECT store_id, name, branch FROM stores ORDER BY name, branch, store_id');
        $store = filter_var($_GET['store_id'] ?? ($stores[0]['store_id'] ?? 0), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($stores && (!$store || !in_array($store, array_map('intval', array_column($stores, 'store_id')), true))) throw new InvalidArgumentException('Toko tidak ditemukan.');
        $page = max(1, (int)($_GET['page'] ?? 1)); $limit = 50;
        $rows = []; $total = 0; $totalPages = 1; $selectedArchive = null;
        $archiveOrder = null;
        if (isset($_GET['archive_order_id'])) {
            $archiveOrder = filter_var($_GET['archive_order_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$archiveOrder) throw new InvalidArgumentException('Nomor arsip order tidak valid.');
        }
        if ($stores) {
            array_unshift($params, $store);
            $activityWhere = ' WHERE r.store_id = ? AND r.date >= ? AND r.date < ?';
            $archiveWhere = ' WHERE r.store_id = ? AND r.deleted_at >= ? AND r.deleted_at < ?';
            $total = (int)$this->query('SELECT COUNT(*) AS total FROM activity r' . $activityWhere, $params)[0]['total']
                + (int)$this->query('SELECT COUNT(*) AS total FROM deleted_orders r' . $archiveWhere, $params)[0]['total'];
            $totalPages = max(1, (int)ceil($total / $limit)); $page = min($page, $totalPages); $offset = ($page - 1) * $limit;
            $events = $this->query("SELECT 'activity' AS type, r.activity_id AS id, r.date AS event_date FROM activity r" . $activityWhere
                . " UNION ALL SELECT 'archive' AS type, r.order_id AS id, r.deleted_at AS event_date FROM deleted_orders r" . $archiveWhere
                . " ORDER BY event_date DESC, type, id DESC LIMIT $limit OFFSET $offset", array_merge($params, $params));
            $records = [];
            foreach (['activity', 'archive'] as $type) {
                $ids = array_column(array_filter($events, function ($event) use ($type) { return $event['type'] === $type; }), 'id');
                if (!$ids) continue;
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                if ($type === 'activity') {
                    $activities = $this->query("SELECT r.*, a.name AS administrator_name, COALESCE(o.date, (SELECT d.date FROM deleted_orders d WHERE d.store_id = r.store_id AND d.order_id = r.order_id ORDER BY d.deleted_at DESC LIMIT 1)) AS order_date,
                        o.order_id AS live_order_id,
                        (SELECT d.order_id FROM deleted_orders d WHERE d.store_id = r.store_id AND d.order_id = r.order_id LIMIT 1) AS archived_order_id
                        FROM activity r
                        LEFT JOIN administrator a ON a.administrator_id = r.administrator_id
                        LEFT JOIN orders o ON o.order_id = r.order_id AND o.store_id = r.store_id
                        WHERE r.store_id = ? AND r.activity_id IN ($placeholders)", array_merge([$store], $ids));
                    foreach ($activities as $activity) $records['activity:' . $activity['activity_id']] = $activity;
                    continue;
                }
                foreach ($this->archives($store, $ids) as $archive) $records['archive:' . $archive['order_id']] = $archive;
            }
            foreach ($events as $event) {
                $key = $event['type'] . ':' . $event['id'];
                if (isset($records[$key])) $rows[] = array_merge($records[$key], ['type' => $event['type'], 'event_date' => $event['event_date']]);
            }
        }
        if ($archiveOrder) {
            $selectedArchive = $stores ? ($this->archives($store, [$archiveOrder])[0] ?? null) : null;
            if (!$selectedArchive) throw new InvalidArgumentException('Arsip order tidak ditemukan pada toko ini.');
        }
        return compact('stores', 'store', 'start', 'end', 'rows', 'total', 'totalPages', 'page', 'limit', 'selectedArchive');
    }
}
