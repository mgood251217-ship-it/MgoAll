<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../connect.php';
require_once BASE_PATH . '/session.php';

function normalizeNumber($value) {
    $value = trim($value);
    if ($value === '' || $value === null) {
        return null;
    }

    // Hapus simbol mata uang dan spasi
    $value = preg_replace('/[^0-9,\.\-]/u', '', $value);

    // Angka 1.234.567,89 atau 1,234,567.89
    if (substr_count($value, ',') > 0 && substr_count($value, '.') > 0) {
        if (strrpos($value, ',') > strrpos($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
    } elseif (substr_count($value, ',') > 0) {
        $value = str_replace(',', '.', $value);
    }

    return is_numeric($value) ? floatval($value) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        if (($handle = fopen($file, "r")) !== false) {
            $row_num = 0;
            $imported = 0;
            $errors = [];

            while (($data = fgetcsv($handle)) !== false) {
                $row_num++;
                
                // Skip header row (baris pertama)
                if ($row_num === 1) {
                    continue;
                }

                // Validasi minimal 4 kolom
                if (count($data) < 4) {
                    $errors[] = "Baris $row_num: Terlalu sedikit kolom";
                    continue;
                }

                // Urutan kolom: TYPE, NAME, PRICE, REASONABLE_PRICE, FAILED_PRICE, UNIT_TYPE
                $type = trim($data[0]);
                $name = trim($data[1]);
                $price = normalizeNumber($data[2]);
                $reasonable_price = isset($data[3]) ? normalizeNumber($data[3]) : null;
                $failed_price = isset($data[4]) ? normalizeNumber($data[4]) : null;
                $unit_type = trim(isset($data[5]) ? $data[5] : '');

                // Jika price kosong atau string kosong, set 0
                if ($price === null) {
                    $price = 0;
                }

                if ($reasonable_price === null) {
                    $reasonable_price = 0;
                }

                if ($failed_price === null) {
                    $failed_price = 0;
                }

                // Validasi data penting
                if (empty($type) || empty($name) || $price < 0 || empty($unit_type)) {
                    $errors[] = "Baris $row_num: Data tidak lengkap (Jenis, Nama, Harga, Satuan harus diisi)";
                    continue;
                }

                $stmt = $koneksi->prepare("INSERT INTO products (store_id, type, name, price, reasonable_price, failed_price, unit_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issddss", $store_id, $type, $name, $price, $reasonable_price, $failed_price, $unit_type);
                
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $errors[] = "Baris $row_num: Gagal insert ke database";
                }
                $stmt->close();
            }
            fclose($handle);
            
            $message = "Import selesai. $imported produk berhasil ditambahkan.";
            if (!empty($errors)) {
                $message .= "\n\nWarning:\n" . implode("\n", array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= "\n... dan " . (count($errors) - 5) . " error lainnya";
                }
            }
            echo "<script>alert(" . json_encode($message) . ");window.location='barang.php';</script>";
        } else {
            echo "<script>alert('Gagal membuka file CSV.');window.location='barang.php';</script>";
        }
    } else {
        echo "<script>alert('Format file tidak didukung. Hanya CSV yang diterima.');window.location='barang.php';</script>";
    }
    exit;
}