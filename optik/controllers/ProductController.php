<?php

require_once __DIR__ . '/../models/Product.php';

class ProductController {
    private $product;
    private $asset_path;

    public function __construct(Product $product, $asset_path = null) {
        $this->product = $product;
        $this->asset_path = $asset_path ?? __DIR__ . '/../assets';
    }

    public function index($store_id, array $query) {
        $limit = 14;
        $hal = max(1, (int) ($query['hal'] ?? 1));
        $brand_id = (string) ($query['brand'] ?? '');
        $search = (string) ($query['search'] ?? '');
        $products = $this->product->getProductsByStorePaginated($store_id, $brand_id, $search, $limit, ($hal - 1) * $limit);
        $total_products = $this->product->countProductsByStore($store_id, $brand_id, $search);

        return [
            'products' => $products,
            'brands' => $this->product->getBrandsByStore($store_id),
            'cats' => $this->product->getCategoriesGlobal(),
            'brand_id' => $brand_id,
            'search' => $search,
            'hal' => $hal,
            'total_products' => $total_products,
            'total_pages' => (int) ceil($total_products / $limit)
        ];
    }

    public function catalog(array $data) {
        return [
            'products' => array_map(function($product) {
                return [
                    'id' => $product['id'],
                    'code' => $product['product_code'] ?? '',
                    'name' => $product['name'] ?? '',
                    'price' => $product['price'] ?? 0,
                    'maxStock' => $product['stock'] ?? 0,
                    'img' => $product['img'] ?? '',
                    'info' => $product['info'] ?? ''
                ];
            }, $data['products']),
            'total_pages' => $data['total_pages'],
            'current_page' => $data['hal']
        ];
    }

    public function getDashboardData($store_id) {
        return [
            'stock_data' => $this->product->getStockSummary($store_id),
            'low_stock_items' => $this->product->getLowStockProducts($store_id)
        ];
    }

    public function dispatch($action, $store_id, array $query, array $post, array $files, $request_method = 'GET') {
        $read_actions = ['api_detail', 'print_label', 'delete_product', 'delete_brand'];
        $write_actions = ['store_cat', 'store_brand', 'store_product', 'edit_stock', 'update_product', 'update_brand'];

        if (!in_array($action, array_merge($read_actions, $write_actions), true)) {
            throw new InvalidArgumentException('Aksi produk tidak ditemukan.');
        }

        $expected_method = in_array($action, $read_actions, true) ? 'GET' : 'POST';
        if ($request_method !== $expected_method) {
            throw new InvalidArgumentException('Metode request tidak sesuai.');
        }

        if ($action === 'api_detail') {
            return ['json' => $this->product->getDetailByStore((int) ($query['id'] ?? 0), $store_id)];
        }

        if ($action === 'print_label') {
            $this->requireProduct((int) ($query['id'] ?? 0), $store_id);
            return ['body' => ''];
        }

        if ($action === 'delete_product') {
            $id = (int) ($query['id'] ?? 0);
            $product = $this->product->getDetailByStore($id, $store_id);
            if ($product) {
                $this->ensureSaved($this->product->deleteProduct($id, $store_id));
                $this->removeImage($product['img'] ?? '', 'products');
            }
        } elseif ($action === 'delete_brand') {
            $id = (int) ($query['id'] ?? 0);
            $this->product->transaction(function() use ($id, $store_id) {
                $this->ensureSaved($this->product->detachBrand($id, $store_id));
                $this->ensureSaved($this->product->deleteBrand($id, $store_id));
            });
        } elseif ($action === 'store_cat') {
            $this->ensureSaved($this->product->createCategory($post['name'] ?? ''));
        } elseif ($action === 'edit_stock') {
            $this->ensureSaved($this->product->updateStock((int) ($post['product_id'] ?? 0), $store_id, (int) ($post['new_stock'] ?? 0)));
        } elseif ($action === 'store_product' || $action === 'update_product') {
            $this->saveProduct($store_id, $post, $files, $action === 'update_product');
        } else {
            $this->saveBrand($store_id, $post, $files, $action === 'update_brand');
        }

        return ['redirect' => '/product'];
    }

    private function requireProduct($id, $store_id) {
        $product = $this->product->getDetailByStore($id, $store_id);
        if (!$product) {
            throw new InvalidArgumentException('Data produk tidak ditemukan.');
        }
        return $product;
    }

    private function saveProduct($store_id, array $post, array $files, $editing) {
        $id = (int) ($post['product_id'] ?? 0);
        $existing = $editing ? $this->requireProduct($id, $store_id) : [];
        $code = $post['product_code'] ?? '';
        if (!$editing) {
            do {
                $code = (string) random_int(100000000, 999999999);
            } while ($this->product->checkProductCodeExists($code, $store_id));
        }

        $image = $this->uploadImage($files['img'] ?? [], 'products', 800);
        $data = [
            'product_code' => $code,
            'name' => $post['name'] ?? '',
            'info' => $post['info'] ?? '',
            'year' => $post['year'] ?? '',
            'brand_id' => $post['brand_id'] ?? 0,
            'category_id' => $post['category_id'] ?? 0,
            'color' => $post['color'] ?? '',
            'price' => $post['price'] ?? 0,
            'img' => $image ?? ($existing['img'] ?? 'default.png')
        ];

        try {
            if ($editing) {
                $this->ensureSaved($this->product->updateProduct($id, $store_id, $data));
            } else {
                $data['stock'] = (int) ($post['stock'] ?? 0);
                $this->ensureSaved($this->product->createProduct($store_id, $data));
            }
        } catch (Throwable $e) {
            $this->removeImage($image, 'products');
            throw $e;
        }
    }

    private function saveBrand($store_id, array $post, array $files, $editing) {
        $id = (int) ($post['brand_id'] ?? 0);
        $existing = $editing ? $this->product->getBrandByStore($id, $store_id) : [];
        if ($editing && !$existing) {
            throw new InvalidArgumentException('Data brand tidak ditemukan.');
        }

        $image = $this->uploadImage($files['img'] ?? [], 'brands', 400);
        $data = [
            'brand_code' => $post['brand_code'] ?? '',
            'name' => $post['name'] ?? '',
            'img' => $image ?? ($existing['img'] ?? 'default.png')
        ];

        try {
            $this->ensureSaved($editing
                ? $this->product->updateBrand($id, $store_id, $data)
                : $this->product->createBrand($store_id, $data));
        } catch (Throwable $e) {
            $this->removeImage($image, 'brands');
            throw $e;
        }
    }

    private function ensureSaved($saved) {
        if (!$saved) {
            throw new RuntimeException('Perubahan produk tidak dapat disimpan.');
        }
    }

    private function uploadImage(array $file, $folder, $max_size) {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new InvalidArgumentException('Gambar gagal diunggah.');
        }

        $info = @getimagesize($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp'];
        if (!$info || !isset($extensions[$info['mime']])) {
            throw new InvalidArgumentException('Format gambar tidak didukung.');
        }

        $directory = $this->asset_path . '/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder gambar tidak dapat dibuat.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$info['mime']];
        $target = $directory . '/' . $filename;

        try {
            if ($info['mime'] === 'image/jpeg' || $info['mime'] === 'image/png') {
                $this->resizeImage($file['tmp_name'], $target, $info, $max_size);
            } elseif (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new RuntimeException('Gambar tidak dapat disimpan.');
            }
        } catch (Throwable $e) {
            $this->removeImage($filename, $folder);
            throw $e;
        }

        return $filename;
    }

    private function resizeImage($source, $target, array $info, $max_size) {
        $scale = min(1, $max_size / max($info[0], $info[1]));
        $width = max(1, (int) floor($info[0] * $scale));
        $height = max(1, (int) floor($info[1] * $scale));
        $image = $info['mime'] === 'image/png' ? imagecreatefrompng($source) : imagecreatefromjpeg($source);
        $resized = imagecreatetruecolor($width, $height);

        try {
            if ($info['mime'] === 'image/png') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
            $saved = $info['mime'] === 'image/png' ? imagepng($resized, $target, 2) : imagejpeg($resized, $target, 80);
            if (!$saved) {
                throw new RuntimeException('Gambar tidak dapat disimpan.');
            }
        } finally {
            imagedestroy($image);
            imagedestroy($resized);
        }
    }

    private function removeImage($filename, $folder) {
        if (!$filename || $filename === 'default.png' || basename($filename) !== $filename) {
            return;
        }
        $path = $this->asset_path . '/' . $folder . '/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
