<?php
require_once __DIR__ . '/../../admin/models/ProductPriceTier.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/cacheHelper.php';

class VariantController {
    private $db;
    private $variants;
    private $components;
    public function __construct($db) { $this->db = $db; $this->variants = new ProductVariant($db); $this->components = new ProductionComponent($db); }

    private function stores() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['admin_logged_in'])) throw new InvalidArgumentException('Silakan login kembali.');
        $access = startEnk('dek', $_SESSION['admin_logged_in']['access'] ?? '');
        if ($access === 'ALL') return $this->db->query('SELECT store_id, name FROM stores ORDER BY name')->fetch_all(MYSQLI_ASSOC);
        if (!$access) return [];
        $stmt = $this->db->prepare('SELECT store_id, name FROM stores WHERE administrator = ? ORDER BY name');
        $stmt->bind_param('s', $access); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
    }
    private function checkStore($store) {
        if (!$store || !in_array($store, array_column($this->stores(), 'store_id'))) throw new InvalidArgumentException('Anda tidak memiliki akses ke toko ini.');
    }
    public function getRules() {
        try {
            $store = (int)($_GET['store_id'] ?? 0); $this->checkStore($store);
            send_json_response(true, 'Varian produk', $this->variants->getByProduct($store, (int)($_GET['product_id'] ?? 0)));
        } catch (InvalidArgumentException $e) { http_response_code(403); send_json_response(false, $e->getMessage()); }
    }
    public function getData() {
        $stores = $this->stores();
        $store = (int)($_GET['store_id'] ?? ($stores[0]['store_id'] ?? 0));
        if ($store) $this->checkStore($store);
        $category = (int)($_GET['category_id'] ?? 0); $selected = (int)($_GET['product_id'] ?? 0);
        $ready = $this->variants->isAvailable() && (new ProductPriceTier($this->db))->isAvailable();
        $categories = $products = $variants = []; $product = $editing = null;
        if ($store) {
            $stmt = $this->db->prepare('SELECT category_id, name FROM categories WHERE store_id = ? ORDER BY name, category_id');
            $stmt->bind_param('i', $store); $stmt->execute(); $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $stmt = $this->db->prepare('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.store_id = ? ORDER BY p.name, p.product_id');
            $stmt->bind_param('i', $store); $stmt->execute(); $all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            foreach ($all as $p) if ((int)$p['product_id'] === $selected) $product = $p;
            if (!array_key_exists('category_id', $_GET) && $product) $category = (int)$product['category_id'];
            if (!in_array($category, array_column($categories, 'category_id'))) $category = 0;
            $products = array_values(array_filter($all, function ($p) use ($category) { return (int)$p['category_id'] === $category; }));
            if (!$product || (int)$product['category_id'] !== $category) { $selected = 0; $product = null; }
            if ($ready) {
                $variants = $this->variants->getByProduct($store, $selected);
                foreach ($variants as $v) if ((int)$v['id'] === (int)($_GET['variant_id'] ?? 0)) $editing = $v;
            }
        }
        $optionChoices = ['KUALITAS' => ['PREMIUM', 'MEDIUM'], 'PILIHAN' => ['SETELAN', 'ATASAN', 'CELANA'], 'UKURAN' => [], 'BAHAN' => [], 'WARNA' => []];
        if ($store && $ready) {
            $stmt = $this->db->prepare('SELECT v.options_json FROM product_variants v JOIN products p ON p.product_id = v.product_id AND p.store_id = v.store_id WHERE v.store_id = ? ORDER BY v.id');
            $stmt->bind_param('i', $store); $stmt->execute(); $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) foreach (json_decode($row['options_json'], true, 512, JSON_THROW_ON_ERROR) as $name => $value) {
                $name = $this->upper($name); $value = $this->upper($value);
                if (!isset($optionChoices[$name])) $optionChoices[$name] = [];
                if (!in_array($value, $optionChoices[$name], true)) $optionChoices[$name][] = $value;
            }
            $stmt->close();
        }
        return compact('stores', 'store', 'category', 'selected', 'categories', 'products', 'product', 'variants', 'editing', 'ready', 'optionChoices');
    }

    private function upper($value) { return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value); }

    private function componentAction($action) {
        $store = (int)($_POST['store_id'] ?? 0); $transaction = false; $saved = false;
        $driver = new mysqli_driver(); $report = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->checkStore($store);
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['variant_csrf']) || !is_string($_POST['csrf_token'] ?? null)
                || !hash_equals($_SESSION['variant_csrf'], $_POST['csrf_token'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            if (!$this->variants->isAvailable() || !$this->components->isAvailable()) throw new InvalidArgumentException('Jalankan SQL production_components.sql terlebih dahulu.');
            $this->db->begin_transaction(); $transaction = true;
            $stmt = $this->db->prepare('SELECT store_id FROM stores WHERE store_id = ? FOR UPDATE'); $stmt->bind_param('i', $store); $stmt->execute(); $stmt->close();
            $_SESSION['swal_success'] = $action($store);
            $this->db->commit(); $transaction = false; $saved = true;
        } catch (Throwable $e) {
            if ($transaction) $this->db->rollback();
            unset($_SESSION['swal_success']);
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : ($e instanceof mysqli_sql_exception && $e->getCode() === 1062 ? 'Nama komponen sudah dipakai di toko ini. Pilih komponen yang tersedia.' : 'Gagal menyimpan komponen produksi.');
            error_log('Production component: ' . $e->getMessage());
        } finally { mysqli_report($report); }
        if ($saved) try { updateStoreCache($store, 'production_components'); updateStoreCache($store, 'product_variants'); } catch (Throwable $e) { error_log('Component cache: ' . $e->getMessage()); }
        $filters = ['store_id' => $store, 'category_id' => (int)($_POST['category_id'] ?? 0), 'product_id' => (int)($_POST['product_id'] ?? 0)];
        if (!empty($_POST['variant_id'])) $filters['variant_id'] = (int)$_POST['variant_id'];
        if (!empty($_POST['show_legacy'])) $filters['legacy'] = 1;
        header('Location: /product-variants?' . http_build_query($filters)); exit;
    }

    public function saveComponent() {
        $this->componentAction(function ($store) {
            $name = ProductionComponent::name($_POST['name'] ?? ''); $id = $_POST['component_id'] ?? '0';
            if (!is_scalar($id) || !preg_match('/^\d+$/D', (string)$id) || (float)$id > 2147483647) throw new InvalidArgumentException('Komponen tidak valid.');
            $id = (int)$id;
            if ($id) {
                $masters = array_column($this->components->getByStore($store), 'name', 'id');
                if (!isset($masters[$id])) throw new InvalidArgumentException('Komponen tidak ditemukan pada toko ini.');
                $this->components->renameOrMerge($store, $id, $name);
            } else {
                $stmt = $this->db->prepare('INSERT INTO production_components (store_id, name) VALUES (?, ?)'); $stmt->bind_param('is', $store, $name);
                $stmt->execute(); $stmt->close();
            }
            return $id ? 'Nama komponen diperbarui untuk meteran lama dan baru. Harga dan jumlah pada nota tetap.' : 'Komponen ditambahkan. Pilih komponen ini pada varian yang menggunakannya.';
        });
    }

    public function linkComponents() {
        $this->componentAction(function ($store) {
            $keys = $_POST['legacy_key'] ?? []; $ids = $_POST['legacy_component_id'] ?? [];
            if (!is_array($keys) || !is_array($ids) || count($keys) !== count($ids) || count($keys) > 1000) throw new InvalidArgumentException('Daftar pemetaan tidak valid.');
            $groups = $this->components->legacyGroups($store, (int)($_POST['product_id'] ?? 0));
            $masters = array_column($this->components->getByStore($store), 'name', 'id'); $count = 0;
            foreach (array_values($keys) as $i => $key) {
                $id = array_values($ids)[$i];
                if (!is_string($key) || !isset($groups[$key]) || !is_scalar($id) || !preg_match('/^\d*$/D', (string)$id)) throw new InvalidArgumentException('Pemetaan sudah berubah atau tidak tersedia di toko ini. Muat ulang halaman.');
                if ($id === '' || (int)$id === 0) continue;
                $id = (int)$id;
                if (!isset($masters[$id])) throw new InvalidArgumentException('Pilih komponen dari toko yang sama.');
                $g = $groups[$key];
                $this->components->link($store, $g['product_id'], $g['snapshot'], $g['name'], $id); $count++;
            }
            if (!$count) throw new InvalidArgumentException('Pilih minimal satu komponen tujuan.');
            return "$count pemetaan disimpan. Nama di meteran mengikuti master; harga dan jumlah pada nota tidak diubah.";
        });
    }

    public function save() {
        $store = (int)($_POST['store_id'] ?? 0); $product = (int)($_POST['product_id'] ?? 0); $category = (int)($_POST['category_id'] ?? 0); $id = (int)($_POST['variant_id'] ?? 0);
        $transaction = $saved = false; $driver = new mysqli_driver(); $report = $driver->report_mode;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->checkStore($store);
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['variant_csrf']) || !is_string($_POST['csrf_token'] ?? null)
                || !hash_equals($_SESSION['variant_csrf'], $_POST['csrf_token'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
            if (!$this->variants->isAvailable() || !(new ProductPriceTier($this->db))->isAvailable()) throw new InvalidArgumentException('Jalankan SQL varian dan pastikan fitur harga bertingkat sudah terpasang.');
            $command = $_POST['command'] ?? '';
            if ($product < 1 || !in_array($command, ['save', 'delete'], true)) throw new InvalidArgumentException('Pilih produk dan aksi yang valid.');
            $this->db->begin_transaction(); $transaction = true;
            $stmt = $this->db->prepare('SELECT store_id FROM stores WHERE store_id = ? FOR UPDATE'); $stmt->bind_param('i', $store); $stmt->execute(); $stmt->close();
            $stmt = $this->db->prepare('SELECT id FROM product_variants WHERE id = ? AND store_id = ? AND product_id = ? FOR UPDATE');
            $stmt->bind_param('iii', $id, $store, $product); $stmt->execute(); $existing = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (($id || $command === 'delete') && !$existing) throw new InvalidArgumentException('Varian tidak ditemukan pada produk dan toko ini.');
            if ($command === 'delete') {
                $stmt = $this->db->prepare('DELETE FROM product_variant_components WHERE variant_id = ?'); $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
                $stmt = $this->db->prepare('DELETE FROM product_variants WHERE id = ? AND store_id = ? AND product_id = ?'); $stmt->bind_param('iii', $id, $store, $product); $stmt->execute(); $stmt->close();
            } else {
                $stmt = $this->db->prepare('SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id AND c.store_id = p.store_id WHERE p.product_id = ? AND p.store_id = ? FOR UPDATE');
                $stmt->bind_param('ii', $product, $store); $stmt->execute(); $p = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$p) throw new InvalidArgumentException('Produk tidak ditemukan pada toko ini.');
                $category = (int)$p['category_id'];
                foreach ((new ProductFinishingPrice($this->db))->getByStore($store, 0, false) as $r) if ((int)$r['product_id'] === $product) throw new InvalidArgumentException('Produk ini memakai harga paket. Kelola melalui Harga Paket.');
                foreach ((new ProductLaserConversion($this->db))->getByStore($store) as $r) if ((int)$r['product_id'] === $product) throw new InvalidArgumentException('Produk ini memakai konversi print laser. Kelola melalui Konversi Print Laser.');
                $options = ProductVariant::options($_POST['option_name'] ?? [], $_POST['option_value'] ?? []);
                $options = ProductVariant::options(array_map([$this, 'upper'], array_keys($options)), array_map([$this, 'upper'], array_values($options)));
                $current = $this->variants->getByProduct($store, $product);
                if ($current) {
                    if (array_keys($options) !== array_map([$this, 'upper'], array_keys($current[0]['options']))) throw new InvalidArgumentException('Nama dan urutan pilihan harus sama dengan varian produk yang sudah ada.');
                    // Pertahankan ejaan yang sudah dipakai agar pilihan dan pemetaan nota lama tetap cocok.
                    $canonical = [];
                    foreach (array_keys($current[0]['options']) as $name) {
                        $value = $options[$this->upper($name)];
                        foreach ($current as $v) if ($this->upper($v['options'][$name]) === $value) { $value = $v['options'][$name]; break; }
                        $canonical[$name] = $value;
                    }
                    $options = $canonical;
                }
                $price = $_POST['price'] ?? '';
                if (!is_scalar($price) || !preg_match('/^\d+(?:\.\d{1,2})?$/D', (string)$price) || (float)$price <= 0 || (float)$price > 9999999999999.99) throw new InvalidArgumentException('Harga varian harus lebih dari 0, maksimal 2 angka desimal.');
                $componentsReady = $this->components->isAvailable();
                $textNames = array_key_exists('component_name', $_POST) || !$componentsReady;
                $names = $textNames ? ($_POST['component_name'] ?? []) : ($_POST['component_id'] ?? []); $quantities = $_POST['component_quantity'] ?? [];
                if (!is_array($names) || !is_array($quantities) || count($names) !== count($quantities) || count($names) > 100) throw new InvalidArgumentException('Daftar komponen tidak valid.');
                $recipeIds = $_POST['component_row_id'] ?? array_fill(0, count($names), 0);
                if (!is_array($recipeIds) || count($recipeIds) !== count($names)) throw new InvalidArgumentException('Daftar komponen berubah. Muat ulang halaman.');
                $newNames = $_POST['component_new_name'] ?? array_fill(0, count($names), '');
                if (!is_array($newNames) || count($newNames) !== count($names)) throw new InvalidArgumentException('Daftar nama meteran tidak valid.');
                $masters = $componentsReady ? array_column($this->components->getByStore($store), 'name', 'id') : [];
                $oldVariant = null;
                foreach ($current as $v) if ((int)$v['id'] === $id) $oldVariant = $v;
                $oldRecipes = array_column($oldVariant['components'] ?? [], null, 'recipe_id');
                $components = []; $renames = [];
                foreach (array_values($names) as $i => $name) {
                    $quantity = array_values($quantities)[$i];
                    if (!is_scalar($name) || !is_scalar($quantity)) throw new InvalidArgumentException('Komponen tidak valid.');
                    $name = preg_replace('/\s+/u', ' ', trim((string)$name));
                    if ($name === '' && (string)$quantity === '') continue;
                    $componentId = null; $recipe = array_values($recipeIds)[$i];
                    if (!is_scalar($recipe) || !preg_match('/^\d+$/D', (string)$recipe) || (float)$recipe > 2147483647) throw new InvalidArgumentException('Baris komponen tidak valid. Muat ulang halaman.');
                    $recipeId = (int)$recipe;
                    if ($recipeId && !isset($oldRecipes[$recipeId])) throw new InvalidArgumentException('Komponen varian sudah berubah. Muat ulang halaman.');
                    if ($textNames) {
                        $name = ProductionComponent::name($name);
                        if ($componentsReady) {
                            $componentId = (int)($oldRecipes[$recipeId]['component_id'] ?? 0);
                            if ($componentId) {
                                if (isset($renames[$componentId]) && $renames[$componentId] !== $name) throw new InvalidArgumentException('Komponen yang sama tidak boleh memiliki dua nama berbeda.');
                                if (!isset($masters[$componentId])) throw new InvalidArgumentException('Nama meteran sudah berubah. Muat ulang halaman.');
                                $renames[$componentId] = $name;
                                $master = $this->components->renameOrMerge($store, $componentId, $name);
                                $componentId = $master['id']; $name = $master['name']; $masters[$componentId] = $name;
                            } else {
                                $master = $this->components->findOrCreate($store, $name);
                                $componentId = $master['id']; $name = $master['name']; $masters[$componentId] = $name;
                            }
                        }
                    } elseif ($componentsReady) {
                        if ($name === 'new') {
                            $master = $this->components->findOrCreate($store, array_values($newNames)[$i]);
                            $name = (string)$master['id']; $masters[$master['id']] = $master['name'];
                        }
                        if (!preg_match('/^[1-9][0-9]*$/D', $name) || (float)$name > 2147483647 || !isset($masters[(int)$name])) throw new InvalidArgumentException('Pilih komponen dari master toko ini.');
                        $componentId = (int)$name; $name = $masters[$componentId];
                    }
                    if ($componentId) {
                        if (isset($renames[$componentId]) && $renames[$componentId] !== $name) throw new InvalidArgumentException('Komponen yang sama tidak boleh memiliki dua nama berbeda.');
                        $renames[$componentId] = $name;
                    }
                    if (!preg_match('/^.{1,150}$/us', $name) || !preg_match('/^\d+(?:\.\d{1,4})?$/D', (string)$quantity) || (float)$quantity <= 0 || (float)$quantity > 1000000) throw new InvalidArgumentException('Isi nama komponen dan jumlah per satuan jual yang lebih dari 0.');
                    $components[] = ['name' => $name, 'quantity' => (float)$quantity, 'component_id' => $componentId, 'recipe_id' => $recipeId];
                }
                $json = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $key = json_encode(array_values($options), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); $price = (float)$price;
                if ($id) {
                    $stmt = $this->db->prepare('UPDATE product_variants SET options_json = ?, combination_key = SHA2(LOWER(?), 256), price = ? WHERE id = ? AND store_id = ? AND product_id = ?');
                    $stmt->bind_param('ssdiii', $json, $key, $price, $id, $store, $product);
                } else {
                    $stmt = $this->db->prepare('INSERT INTO product_variants (store_id, product_id, options_json, combination_key, price) VALUES (?, ?, ?, SHA2(LOWER(?), 256), ?)');
                    $stmt->bind_param('iissd', $store, $product, $json, $key, $price);
                }
                $stmt->execute(); if (!$id) $id = (int)$this->db->insert_id; $stmt->close();
                if ($componentsReady && $oldVariant && $oldVariant['options'] === $options) foreach ($components as $c) {
                    $old = $oldRecipes[$c['recipe_id']] ?? null;
                    if ($old && empty($old['component_id'])) $this->components->link($store, $product, $oldVariant, $old['name'], $c['component_id'], true);
                }
                $links = $componentsReady ? $this->components->links($store) : [];
                $masterNames = $componentsReady ? array_column($this->components->getByStore($store), 'name', 'id') : [];
                $combined = [];
                foreach ($components as $c) {
                    if ($componentsReady) {
                        $c['component_id'] = ProductionComponent::resolveId($c['component_id'], $links);
                        $c['name'] = $masterNames[$c['component_id']];
                    }
                    $componentKey = $componentsReady ? 'id:' . $c['component_id'] : 'name:' . $c['name'];
                    if (isset($combined[$componentKey])) $combined[$componentKey]['quantity'] = round($combined[$componentKey]['quantity'] + $c['quantity'], 4);
                    else $combined[$componentKey] = $c;
                    if ($combined[$componentKey]['quantity'] > 1000000) throw new InvalidArgumentException('Jumlah komponen setelah digabung maksimal 1.000.000.');
                }
                $components = array_values($combined);
                $stmt = $this->db->prepare('DELETE FROM product_variant_components WHERE variant_id = ?'); $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
                $insert = $componentsReady ? 'INSERT INTO product_variant_components (variant_id, name, quantity, component_id) VALUES (?, ?, ?, ?)' : 'INSERT INTO product_variant_components (variant_id, name, quantity) VALUES (?, ?, ?)';
                $stmt = $this->db->prepare($insert);
                foreach ($components as $c) {
                    if ($componentsReady) $stmt->bind_param('isdi', $id, $c['name'], $c['quantity'], $c['component_id']);
                    else $stmt->bind_param('isd', $id, $c['name'], $c['quantity']);
                    $stmt->execute();
                } $stmt->close();
            }
            $this->db->commit(); $transaction = false; $saved = true;
            $_SESSION['swal_success'] = $command === 'delete' ? 'Varian dihapus. Data varian pada nota lama tetap tersimpan.' : 'Varian tersimpan. Nama meteran yang sama digabung otomatis.';
        } catch (Throwable $e) {
            if ($transaction) $this->db->rollback();
            $_SESSION['swal_error'] = $e instanceof InvalidArgumentException ? $e->getMessage() : ($e instanceof mysqli_sql_exception && $e->getCode() === 1062 ? 'Kombinasi varian yang sama sudah ada.' : 'Gagal menyimpan varian.');
            error_log('Product variant: ' . $e->getMessage());
        } finally { mysqli_report($report); }
        if ($saved) try { updateStoreCache($store, 'product_variants'); updateStoreCache($store, 'products'); updateStoreCache($store, 'production_components'); } catch (Throwable $e) { error_log('Variant cache: ' . $e->getMessage()); }
        header('Location: /product-variants?' . http_build_query(['store_id' => $store, 'category_id' => $category, 'product_id' => $product])); exit;
    }
}
