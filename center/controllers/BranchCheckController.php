<?php

class BranchCheckController
{
    private $koneksi;

    public function __construct($koneksi)
    {
        $this->koneksi = $koneksi;
    }

    public function getIndexData($access)
    {
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        $checkDate = isset($_GET['check_date']) && $_GET['check_date'] !== '' ? $_GET['check_date'] : date('Y-m-d');

        $stores = $this->getStores($access);
        if ($storeId <= 0 && !empty($stores)) {
            $storeId = (int)$stores[0]['store_id'];
        }

        $tables = $this->getChecklistTables();
        $branchCheck = $storeId > 0 ? $this->getBranchCheck($storeId, $checkDate) : null;

        $itemResults = [];
        $groupResults = [];
        $photos = [];
        if ($branchCheck) {
            [$itemResults, $groupResults] = $this->getResults($branchCheck['id']);
            $photos = $this->getPhotos($branchCheck['id']);
        }

        $tables = $this->mergeResults($tables, $itemResults, $groupResults);
        $summary = $this->calculateSummary($tables);

        $history = $storeId > 0 ? $this->getHistory($storeId) : [];
        $historyCount = $storeId > 0 ? $this->countHistory($storeId) : 0;

        return [
            'stores' => $stores,
            'current_store_id' => $storeId,
            'check_date' => $checkDate,
            'tables' => $tables,
            'branch_check' => $branchCheck,
            'summary' => $summary,
            'photos' => $photos,
            'history' => $history,
            'history_count' => $historyCount,
        ];
    }

    public function saveCheck()
    {
        header('Content-Type: application/json');
        require_once __DIR__ . '/../functions/helpers.php';

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            echo json_encode(['success' => false, 'message' => 'Payload tidak valid']);
            return;
        }

        $storeId = (int)($payload['store_id'] ?? 0);
        $checkDate = $payload['check_date'] ?? date('Y-m-d');
        $summaryNote = trim($payload['summary_note'] ?? '');
        $entries = $payload['items'] ?? [];

        $checkedBy = null;
        if (isset($_COOKIE['admin_administrator_id']) && $_COOKIE['admin_administrator_id'] !== '') {
            $checkedBy = startEnk('dek', $_COOKIE['admin_administrator_id']);
        }

        if ($storeId <= 0 || empty($entries)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $this->koneksi->begin_transaction();

        try {
            $totalItems = count($entries);
            $totalOk = 0;
            $totalNotOk = 0;
            foreach ($entries as $entry) {
                if (($entry['status'] ?? null) === 'ok') {
                    $totalOk++;
                } elseif (($entry['status'] ?? null) === 'not_ok') {
                    $totalNotOk++;
                }
            }

            $stmt = $this->koneksi->prepare(
                "INSERT INTO branch_checks (store_id, check_date, checked_by, summary, total_items, total_ok, total_not_ok)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE checked_by = VALUES(checked_by), summary = VALUES(summary),
                 total_items = VALUES(total_items), total_ok = VALUES(total_ok), total_not_ok = VALUES(total_not_ok)"
            );
            $stmt->bind_param('isssiii', $storeId, $checkDate, $checkedBy, $summaryNote, $totalItems, $totalOk, $totalNotOk);
            $stmt->execute();
            $branchCheckId = $stmt->insert_id;
            $stmt->close();

            if (!$branchCheckId) {
                $find = $this->koneksi->prepare("SELECT id FROM branch_checks WHERE store_id = ? AND check_date = ? LIMIT 1");
                $find->bind_param('is', $storeId, $checkDate);
                $find->execute();
                $row = $find->get_result()->fetch_assoc();
                $find->close();
                $branchCheckId = $row ? (int)$row['id'] : 0;
            }

            if (!$branchCheckId) {
                throw new Exception('branch check id not found');
            }

            $itemStmt = $this->koneksi->prepare(
                "INSERT INTO branch_check_results (branch_check_id, checklist_item_id, status, notes)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)"
            );
            $groupStmt = $this->koneksi->prepare(
                "INSERT INTO branch_check_results (branch_check_id, checklist_group_id, status, notes)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)"
            );

            foreach ($entries as $entry) {
                $entityId = (int)($entry['id'] ?? 0);
                if ($entityId <= 0) {
                    continue;
                }
                $status = in_array($entry['status'] ?? null, ['ok', 'not_ok'], true) ? $entry['status'] : null;
                $notes = trim($entry['notes'] ?? '');
                $kind = ($entry['kind'] ?? 'item') === 'group' ? 'group' : 'item';

                if ($kind === 'group') {
                    $groupStmt->bind_param('iiss', $branchCheckId, $entityId, $status, $notes);
                    $groupStmt->execute();
                } else {
                    $itemStmt->bind_param('iiss', $branchCheckId, $entityId, $status, $notes);
                    $itemStmt->execute();
                }
            }
            $itemStmt->close();
            $groupStmt->close();

            $this->koneksi->commit();
            echo json_encode(['success' => true, 'message' => 'Pengecekan berhasil disimpan', 'branch_check_id' => $branchCheckId]);
        } catch (Exception $e) {
            $this->koneksi->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pengecekan']);
        }
    }

    public function uploadPhoto()
    {
        header('Content-Type: application/json');

        $branchCheckId = (int)($_POST['branch_check_id'] ?? 0);
        if ($branchCheckId <= 0 || empty($_FILES['photo']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare(
            "SELECT bc.check_date, s.name AS store_name FROM branch_checks bc
             JOIN stores s ON s.store_id = bc.store_id WHERE bc.id = ?"
        );
        $stmt->bind_param('i', $branchCheckId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Data pengecekan tidak ditemukan']);
            return;
        }

        $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedExt, true)) {
            echo json_encode(['success' => false, 'message' => 'Format foto harus jpg, jpeg, png, atau webp']);
            return;
        }

        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran foto maksimal 5MB']);
            return;
        }

        $relativeDir = $this->buildPhotoDir($row['store_name'], $row['check_date']);
        $fullDir = rtrim($_ENV['BASE_PATH_UPLOAD'], '/') . '/' . $relativeDir;

        if (!is_dir($fullDir) && !mkdir($fullDir, 0755, true) && !is_dir($fullDir)) {
            echo json_encode(['success' => false, 'message' => 'Gagal membuat folder upload']);
            return;
        }

        $fileName = uniqid('check_', true) . '.' . $extension;
        $fullPath = "{$fullDir}/{$fileName}";

        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $fullPath)) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan foto']);
            return;
        }

        // Hanya nama filenya saja yang disimpan di kolom "img" (bukan path lengkap).
        // Folder tujuannya selalu dihitung ulang dari data toko + tanggal pengecekan lewat buildPhotoDir().
        $insert = $this->koneksi->prepare("INSERT INTO branch_check_photos (branch_check_id, img) VALUES (?, ?)");
        $insert->bind_param('is', $branchCheckId, $fileName);
        $insert->execute();
        $photoId = $insert->insert_id;
        $insert->close();

        $url = rtrim($_ENV['BASE_URL_UPLOAD'], '/') . '/' . $relativeDir . '/' . $fileName;

        echo json_encode(['success' => true, 'message' => 'Foto berhasil diupload', 'id' => $photoId, 'url' => $url]);
    }

    public function deletePhoto()
    {
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare(
            "SELECT p.img, bc.check_date, s.name AS store_name
             FROM branch_check_photos p
             JOIN branch_checks bc ON bc.id = p.branch_check_id
             JOIN stores s ON s.store_id = bc.store_id
             WHERE p.id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $relativeDir = $this->buildPhotoDir($row['store_name'], $row['check_date']);
            $fullPath = rtrim($_ENV['BASE_PATH_UPLOAD'], '/') . '/' . $relativeDir . '/' . $row['img'];
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }

        $del = $this->koneksi->prepare("DELETE FROM branch_check_photos WHERE id = ?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        echo json_encode(['success' => true, 'message' => 'Foto berhasil dihapus']);
    }

    private function getStores($access)
    {
        $stores = [];
        $res = $this->koneksi->query("SELECT store_id, name FROM stores ORDER BY name");
        while ($row = $res->fetch_assoc()) {
            $stores[] = $row;
        }
        return $stores;
    }

    private function getChecklistTables()
    {
        $items = [];
        $itemRes = $this->koneksi->query("SELECT id, group_id, name FROM checklist_items WHERE is_active = 1 ORDER BY sort_order, id");
        while ($row = $itemRes->fetch_assoc()) {
            $items[$row['group_id']][] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'status' => null,
                'notes' => '',
            ];
        }

        $groups = [];
        $groupRes = $this->koneksi->query("SELECT id, category_id, name FROM checklist_groups WHERE is_active = 1 ORDER BY sort_order, id");
        while ($row = $groupRes->fetch_assoc()) {
            $groups[$row['category_id']][] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'items' => $items[$row['id']] ?? [],
                'status' => null,
                'notes' => '',
            ];
        }

        $categories = [];
        $catRes = $this->koneksi->query("SELECT id, table_id, name FROM checklist_categories WHERE is_active = 1 ORDER BY sort_order, id");
        while ($row = $catRes->fetch_assoc()) {
            $categories[$row['table_id']][] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'groups' => $groups[$row['id']] ?? [],
            ];
        }

        $tables = [];
        $tableRes = $this->koneksi->query("SELECT id, name FROM checklist_tables WHERE is_active = 1 ORDER BY sort_order, id");
        while ($row = $tableRes->fetch_assoc()) {
            $tables[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'categories' => $categories[$row['id']] ?? [],
            ];
        }

        return $tables;
    }

    private function getBranchCheck($storeId, $checkDate)
    {
        $stmt = $this->koneksi->prepare(
            "SELECT bc.*, a.name AS checked_by_name
             FROM branch_checks bc
             LEFT JOIN administrator a ON a.administrator_id = bc.checked_by
             WHERE bc.store_id = ? AND bc.check_date = ? LIMIT 1"
        );
        $stmt->bind_param('is', $storeId, $checkDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function getPhotos($branchCheckId)
    {
        $photos = [];
        $baseUrl = rtrim($_ENV['BASE_URL_UPLOAD'] ?? '', '/');

        $infoStmt = $this->koneksi->prepare(
            "SELECT bc.check_date, s.name AS store_name
             FROM branch_checks bc
             JOIN stores s ON s.store_id = bc.store_id
             WHERE bc.id = ?"
        );
        $infoStmt->bind_param('i', $branchCheckId);
        $infoStmt->execute();
        $info = $infoStmt->get_result()->fetch_assoc();
        $infoStmt->close();

        if (!$info) {
            return $photos;
        }

        // "img" hanya berisi nama file; folder tujuannya dihitung ulang dari toko + tanggal pengecekan.
        $relativeDir = $this->buildPhotoDir($info['store_name'], $info['check_date']);

        $stmt = $this->koneksi->prepare("SELECT id, img FROM branch_check_photos WHERE branch_check_id = ? ORDER BY id");
        $stmt->bind_param('i', $branchCheckId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $photos[] = [
                'id' => (int)$row['id'],
                'url' => $baseUrl . '/' . $relativeDir . '/' . $row['img'],
            ];
        }
        $stmt->close();
        return $photos;
    }

    /**
     * Folder upload foto dihitung dari nama toko + tanggal pengecekan, bukan disimpan di DB.
     * Dipakai bareng oleh uploadPhoto(), getPhotos(), dan deletePhoto() supaya konsisten.
     */
    private function buildPhotoDir($storeName, $checkDate)
    {
        $storeFolder = preg_replace('/[^A-Za-z0-9_-]+/', '_', $storeName);
        $dateParts = explode('-', $checkDate);
        $year = $dateParts[0] ?? date('Y');
        $month = $dateParts[1] ?? date('m');
        $day = $dateParts[2] ?? date('d');

        return "image/branch_check/{$storeFolder}/{$year}/{$month}/{$day}";
    }

    private function getHistory($storeId, $limit = 10)
    {
        $history = [];
        $stmt = $this->koneksi->prepare(
            "SELECT bc.id, bc.check_date, bc.checked_by, a.name AS checked_by_name,
             bc.total_items, bc.total_ok, bc.total_not_ok
             FROM branch_checks bc
             LEFT JOIN administrator a ON a.administrator_id = bc.checked_by
             WHERE bc.store_id = ? ORDER BY bc.check_date DESC LIMIT ?"
        );
        $stmt->bind_param('ii', $storeId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
        return $history;
    }

    private function countHistory($storeId)
    {
        $stmt = $this->koneksi->prepare("SELECT COUNT(*) AS total FROM branch_checks WHERE store_id = ?");
        $stmt->bind_param('i', $storeId);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $count;
    }

    private function getResults($branchCheckId)
    {
        $itemResults = [];
        $groupResults = [];
        $stmt = $this->koneksi->prepare("SELECT checklist_item_id, checklist_group_id, status, notes FROM branch_check_results WHERE branch_check_id = ?");
        $stmt->bind_param('i', $branchCheckId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if ($row['checklist_item_id'] !== null) {
                $itemResults[(int)$row['checklist_item_id']] = $row;
            } elseif ($row['checklist_group_id'] !== null) {
                $groupResults[(int)$row['checklist_group_id']] = $row;
            }
        }
        $stmt->close();
        return [$itemResults, $groupResults];
    }

    private function mergeResults($tables, $itemResults, $groupResults)
    {
        foreach ($tables as &$table) {
            foreach ($table['categories'] as &$category) {
                foreach ($category['groups'] as &$group) {
                    if (isset($groupResults[$group['id']])) {
                        $group['status'] = $groupResults[$group['id']]['status'];
                        $group['notes'] = $groupResults[$group['id']]['notes'];
                    }
                    foreach ($group['items'] as &$item) {
                        if (isset($itemResults[$item['id']])) {
                            $item['status'] = $itemResults[$item['id']]['status'];
                            $item['notes'] = $itemResults[$item['id']]['notes'];
                        }
                    }
                    unset($item);
                }
                unset($group);
            }
            unset($category);
        }
        unset($table);
        return $tables;
    }

    private function calculateSummary($tables)
    {
        $total = 0;
        $ok = 0;
        $notOk = 0;

        foreach ($tables as $table) {
            foreach ($table['categories'] as $category) {
                foreach ($category['groups'] as $group) {
                    if (empty($group['items'])) {
                        $total++;
                        if ($group['status'] === 'ok') {
                            $ok++;
                        } elseif ($group['status'] === 'not_ok') {
                            $notOk++;
                        }
                        continue;
                    }
                    foreach ($group['items'] as $item) {
                        $total++;
                        if ($item['status'] === 'ok') {
                            $ok++;
                        } elseif ($item['status'] === 'not_ok') {
                            $notOk++;
                        }
                    }
                }
            }
        }

        $checked = $ok + $notOk;
        $percent = $total > 0 ? (int)round($checked / $total * 100) : 0;

        $label = 'Belum Lengkap';
        if ($notOk > 0) {
            $label = 'Perlu Perhatian';
        } elseif ($total > 0 && $checked === $total) {
            $label = 'Baik';
        }

        return [
            'total' => $total,
            'ok' => $ok,
            'not_ok' => $notOk,
            'checked' => $checked,
            'percent' => $percent,
            'label' => $label,
        ];
    }
}