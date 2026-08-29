<?php
require_once __DIR__ . '/../functions/helpers.php';
class ProductController {
    private $koneksi;

    public function __construct($db) {
        $this->koneksi = $db;
    }

    public function getIndexData($access) {
        if ($access == 'ALL') {
            $storesResult = $this->koneksi->query("SELECT store_id, name FROM stores ORDER BY name ASC");
        } else {
            $stmt = $this->koneksi->prepare("SELECT store_id, name FROM stores WHERE administrator = ? ORDER BY name ASC");
            $stmt->bind_param("s", $access);
            $stmt->execute();
            $storesResult = $stmt->get_result();
        }

        $stores = [];
        if ($storesResult) {
            while ($row = $storesResult->fetch_assoc()) {
                $stores[] = $row;
            }
        }

        $store_id = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : null;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $search = $_GET['search'] ?? '';
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 25;

        if ($store_id === null && !empty($stores)) {
            $store_id = $stores[0]['store_id'];
        }

        $productsData = [];
        $finishingsData = [];
        $categories = [];
        $totalPages = 0;
        $total = 0;

        if ($store_id !== null) {
            $catStmt = $this->koneksi->prepare("SELECT category_id, name FROM categories WHERE store_id = ? ORDER BY name ASC");
            $catStmt->bind_param("i", $store_id);
            $catStmt->execute();
            $catResult = $catStmt->get_result();
            if ($catResult) {
                while ($c = $catResult->fetch_assoc()) {
                    $categories[] = $c;
                }
            }
            $catStmt->close();

            $search_param = "%" . $search . "%";
            $countStmt = $this->koneksi->prepare("
                SELECT COUNT(*) 
                FROM products 
                WHERE store_id = ? AND name LIKE ?
            ");
            $countStmt->bind_param("is", $store_id, $search_param);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];
            $countStmt->close();

            $totalPages = ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $stmt = $this->koneksi->prepare("
                SELECT p.*, c.name AS category 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE p.store_id = ? AND p.name LIKE ? 
                ORDER BY p.product_id DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("isii", $store_id, $search_param, $limit, $offset);
            $stmt->execute();
            
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $productsData[] = $row;
                }
            }
            $stmt->close();

            $fStmt = $this->koneksi->prepare("
                SELECT f.*, c.name AS category 
                FROM finishings f
                LEFT JOIN categories c ON f.category_id = c.category_id
                WHERE f.store_id = ? AND f.name LIKE ? 
                ORDER BY f.finishing_id DESC
            ");
            $fStmt->bind_param("is", $store_id, $search_param);
            $fStmt->execute();
            
            $fResult = $fStmt->get_result();
            if ($fResult) {
                while ($row = $fResult->fetch_assoc()) {
                    $finishingsData[] = $row;
                }
            }
            $fStmt->close();
        }

        return [
            'stores' => $stores,
            'current_store_id' => $store_id,
            'categories' => $categories,
            'products' => $productsData,
            'finishings' => $finishingsData,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'search' => $search,
            'limit' => $limit
        ];
    }

    public function addProduct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->saveData('products', 'product');
    }

    public function editProduct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->updateData('products', 'product_id', 'produk');
    }

    public function deleteProduct() {
        $this->deleteData('products', 'product_id');
    }

    public function addFinishing() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->saveData('finishings', 'finishing');
    }

    public function editFinishing() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->updateData('finishings', 'finishing_id', 'finishing');
    }

    public function deleteFinishing() {
        $this->deleteData('finishings', 'finishing_id');
    }

    private function saveData($table, $label) {
        $store_id = $_POST['store_id'] ?? null;
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $price = isset($_POST['price']) ? (int)$_POST['price'] : 0;
        $reasonable_price = isset($_POST['reasonable_price']) ? (int)$_POST['reasonable_price'] : 0;
        $failed_price = isset($_POST['failed_price']) ? (int)$_POST['failed_price'] : 0;
        $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
        $unit_type = trim($_POST['unit_type'] ?? '');

        if ($store_id && $name) {
            $stmt = $this->koneksi->prepare("INSERT INTO $table (store_id, category_id, name, price, reasonable_price, failed_price, stock, unit_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisiiiis", $store_id, $category_id, $name, $price, $reasonable_price, $failed_price, $stock, $unit_type);

            if ($stmt->execute()) {
                require_once __DIR__ . '/../functions/cacheHelper.php';
                updateStoreCache($store_id, $table);
                
                $_SESSION['swal_success'] = ucfirst($label) . " berhasil ditambahkan.";
            } else {
                $_SESSION['swal_error'] = "Gagal menyimpan data: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Toko dan nama wajib diisi.";
        }

        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? "/products?store_id=" . $store_id));
        exit;
    }

    private function updateData($table, $id_column, $label) {
        $id = $_POST[$id_column] ?? null;
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $price = isset($_POST['price']) ? (int)$_POST['price'] : 0;
        $reasonable_price = isset($_POST['reasonable_price']) ? (int)$_POST['reasonable_price'] : 0;
        $failed_price = isset($_POST['failed_price']) ? (int)$_POST['failed_price'] : 0;
        $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
        $unit_type = trim($_POST['unit_type'] ?? '');

        if ($id && $name) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM $table WHERE $id_column = ?");
            $stmtGet->bind_param("i", $id);
            $stmtGet->execute();
            $store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $stmt = $this->koneksi->prepare("UPDATE $table SET category_id=?, name=?, price=?, reasonable_price=?, failed_price=?, stock=?, unit_type=? WHERE $id_column=?");
            $stmt->bind_param("isiiiisi", $category_id, $name, $price, $reasonable_price, $failed_price, $stock, $unit_type, $id);

            if ($stmt->execute()) {
                if ($store_id) {
                    require_once __DIR__ . '/../functions/cacheHelper.php';
                    updateStoreCache($store_id, $table);
                }
                
                $_SESSION['swal_success'] = ucfirst($label) . " berhasil diperbarui.";
            } else {
                $_SESSION['swal_error'] = "Gagal memperbarui data: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = "Nama wajib diisi.";
        }

        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? "/products"));
        exit;
    }

    private function deleteData($table, $id_column) {
        $id = $_POST[$id_column] ?? null;
        if ($id) {
            $stmtGet = $this->koneksi->prepare("SELECT store_id FROM $table WHERE $id_column = ?");
            $stmtGet->bind_param("i", $id);
            $stmtGet->execute();
            $store_id = $stmtGet->get_result()->fetch_assoc()['store_id'] ?? null;
            $stmtGet->close();

            $stmt = $this->koneksi->prepare("DELETE FROM $table WHERE $id_column = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                if ($store_id) {
                    require_once __DIR__ . '/../functions/cacheHelper.php';
                    updateStoreCache($store_id, $table);
                }
                
                echo "OK";
            } else {
                echo "Error: " . $this->koneksi->error;
            }
            $stmt->close();
        } else {
            echo "ID tidak ditemukan";
        }
        exit;
    }

    public function getCategoryByStoreId($store_id){
        $stmt = $this->koneksi->prepare("SELECT * FROM categories WHERE store_id = ?");
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $caregories = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $caregories;
    }

    public function getProductByCategoryId(){
        $category_id = $_GET['category_id'] ?? '';
        $stmt = $this->koneksi->prepare("SELECT * FROM products WHERE category_id = ?");
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // return $products;
        send_json_response(true, "success", $products);
    }

    public function getFinishingByCategoryId(){
        $category_id = $_GET['category_id'] ?? '';
        $stmt = $this->koneksi->prepare("SELECT * FROM finishings WHERE category_id = ?");
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $finishings = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // return $finishings;
        send_json_response(true, "success", $finishings);
    }

    public function getProductById($id) {
        $stmt = $this->koneksi->prepare("
            SELECT
                p.*,
                c.name AS category
            FROM products p
            LEFT JOIN categories c
                ON c.category_id = p.category_id
            WHERE p.product_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data;
    }

    public function getStockByProductId($product_id) {
        $stmt = $this->koneksi->prepare("SELECT stock FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? (float)$result['stock'] : 0;
    }

    public function getFinishingStockByProductId($finishing_id) {
        $stmt = $this->koneksi->prepare("SELECT stock FROM finishings WHERE finishing_id = ? LIMIT 1");
        $stmt->bind_param("i", $finishing_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? (float)$result['stock'] : 0;
    }

    public function addStock($quantity, $product_id) {
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
        $stmt->bind_param("di", $quantity, $product_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function addFinishingStock($quantity, $finishing_id) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = stock + ? WHERE finishing_id = ?");
        $stmt->bind_param("di", $quantity, $finishing_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function getProductByNameAndStore($name, $store_id) {
        $stmt = $this->koneksi->prepare("SELECT p.*, c.name AS category FROM products p LEFT JOIN categories c ON c.category_id = p.category_id WHERE p.name = ? AND p.store_id = ? LIMIT 1");
        $stmt->bind_param("si", $name, $store_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    public function reduceStock($quantity, $product_id) {
        $stmt = $this->koneksi->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
        $stmt->bind_param("di", $quantity, $product_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function reduceFinishingStock($quantity, $finishing_id) {
        $stmt = $this->koneksi->prepare("UPDATE finishings SET stock = stock - ? WHERE finishing_id = ?");
        $stmt->bind_param("di", $quantity, $finishing_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

}