<?php

class ChecklistMasterController
{
    private $koneksi;

    public function __construct($koneksi)
    {
        $this->koneksi = $koneksi;
    }

    public function getIndexData()
    {
        $items = [];
        $itemRes = $this->koneksi->query("SELECT id, group_id, name FROM checklist_items WHERE is_active = 1 ORDER BY sort_order, id");
        while ($row = $itemRes->fetch_assoc()) {
            $items[$row['group_id']][] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
            ];
        }

        $groups = [];
        $groupRes = $this->koneksi->query("SELECT id, category_id, name FROM checklist_groups WHERE is_active = 1 ORDER BY sort_order, id");
        while ($row = $groupRes->fetch_assoc()) {
            $groups[$row['category_id']][] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'items' => $items[$row['id']] ?? [],
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

        return ['tables' => $tables];
    }

    public function addTable()
    {
        header('Content-Type: application/json');
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Nama tabel wajib diisi']);
            return;
        }

        $order = $this->nextSortOrder('checklist_tables');
        $stmt = $this->koneksi->prepare("INSERT INTO checklist_tables (name, sort_order) VALUES (?, ?)");
        $stmt->bind_param('si', $name, $order);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Tabel berhasil ditambahkan', 'id' => $id, 'name' => $name]);
    }

    public function editTable()
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare("UPDATE checklist_tables SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $name, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Tabel berhasil diperbarui']);
    }

    public function deleteTable()
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare("UPDATE checklist_tables SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Tabel berhasil dihapus']);
    }

    public function addCategory()
    {
        header('Content-Type: application/json');
        $tableId = (int)($_POST['table_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($tableId <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $order = $this->nextSortOrder('checklist_categories', 'table_id', $tableId);
        $stmt = $this->koneksi->prepare("INSERT INTO checklist_categories (table_id, name, sort_order) VALUES (?, ?, ?)");
        $stmt->bind_param('isi', $tableId, $name, $order);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Kategori berhasil ditambahkan', 'id' => $id, 'name' => $name, 'table_id' => $tableId]);
    }

    public function editCategory()
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare("UPDATE checklist_categories SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $name, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Kategori berhasil diperbarui']);
    }

    public function deleteCategory()
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare("UPDATE checklist_categories SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Kategori berhasil dihapus']);
    }

    public function addGroup()
    {
        header('Content-Type: application/json');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($categoryId <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $order = $this->nextSortOrder('checklist_groups', 'category_id', $categoryId);
        $stmt = $this->koneksi->prepare("INSERT INTO checklist_groups (category_id, name, sort_order) VALUES (?, ?, ?)");
        $stmt->bind_param('isi', $categoryId, $name, $order);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Grup berhasil ditambahkan', 'id' => $id, 'name' => $name, 'category_id' => $categoryId]);
    }

    public function editGroup()
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare("UPDATE checklist_groups SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $name, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Grup berhasil diperbarui']);
    }

    public function deleteGroup()
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare("UPDATE checklist_groups SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Grup berhasil dihapus']);
    }

    public function addItem()
    {
        header('Content-Type: application/json');
        $groupId = (int)($_POST['group_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($groupId <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $order = $this->nextSortOrder('checklist_items', 'group_id', $groupId);
        $stmt = $this->koneksi->prepare("INSERT INTO checklist_items (group_id, name, sort_order) VALUES (?, ?, ?)");
        $stmt->bind_param('isi', $groupId, $name, $order);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Item berhasil ditambahkan', 'id' => $id, 'name' => $name, 'group_id' => $groupId]);
    }

    public function editItem()
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare("UPDATE checklist_items SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $name, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Item berhasil diperbarui']);
    }

    public function deleteItem()
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            return;
        }

        $stmt = $this->koneksi->prepare("UPDATE checklist_items SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Item berhasil dihapus']);
    }

    private function nextSortOrder($table, $scopeColumn = null, $scopeValue = null)
    {
        if ($scopeColumn === null) {
            $res = $this->koneksi->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM {$table}");
            return (int)$res->fetch_assoc()['next_order'];
        }

        $stmt = $this->koneksi->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM {$table} WHERE {$scopeColumn} = ?");
        $stmt->bind_param('i', $scopeValue);
        $stmt->execute();
        $order = (int)$stmt->get_result()->fetch_assoc()['next_order'];
        $stmt->close();
        return $order;
    }
}
