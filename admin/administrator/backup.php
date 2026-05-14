<?php
/**
 * FULL BACKUP aman (Database + Folder Admin)
 * Tanpa shell_exec(), hanya ZipArchive
 * Author: Viki Edition (2025)
 */

require_once "../connect.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

// === Konfigurasi dasar ===
if (!defined('BASE_PATH'))  define('BASE_PATH', __DIR__);
$backupDir = BASE_PATH . "/backups";
$adminDir  = BASE_PATH; // folder admin itu sendiri

// Pastikan folder backups ada
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0777, true)) {
        die("❌ Gagal membuat folder backup di: $backupDir");
    }
}

// === Nama file ===
$dbNameRes = mysqli_query($koneksi, "SELECT DATABASE()");
$dbNameRow = mysqli_fetch_row($dbNameRes);
$databaseName = $dbNameRow[0] ?? 'database';
$timestamp = date("Ymd_His");
$sqlFile = "$backupDir/backup_{$databaseName}_{$timestamp}.sql";
$zipFile = "$backupDir/full_backup_{$databaseName}_{$timestamp}.zip";

// === STEP 1: Backup Database ===
$tables = [];
$result = mysqli_query($koneksi, "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    $tables[] = $row[0];
}

$sql = "-- Backup Database: $databaseName\n";
$sql .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $res = mysqli_query($koneksi, "SHOW CREATE TABLE `$table`");
    $row = mysqli_fetch_assoc($res);
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql .= $row['Create Table'] . ";\n\n";

    $resData = mysqli_query($koneksi, "SELECT * FROM `$table`");
    $colCount = mysqli_num_fields($resData);

    while ($data = mysqli_fetch_row($resData)) {
        $sql .= "INSERT INTO `$table` VALUES(";
        for ($i = 0; $i < $colCount; $i++) {
            $val = isset($data[$i]) ? addslashes($data[$i]) : '';
            $val = str_replace("\n", "\\n", $val);
            $sql .= '"' . $val . '"';
            if ($i < $colCount - 1) $sql .= ",";
        }
        $sql .= ");\n";
    }
    $sql .= "\n";
}
$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
file_put_contents($sqlFile, $sql);

// === STEP 2: Buat ZIP ===
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("❌ Tidak bisa membuat file zip: $zipFile");
}

// Tambahkan folder admin
$rootPath = realpath($adminDir);
if (!$rootPath) {
    die("❌ Folder admin tidak ditemukan: $adminDir");
}
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($files as $file) {
    $filePath = $file->getRealPath();
    $localPath = substr($filePath, strlen($rootPath) + 1);
    if ($file->isDir()) {
        $zip->addEmptyDir("admin/$localPath");
    } else {
        $zip->addFile($filePath, "admin/$localPath");
    }
}

// Tambahkan file SQL backup
$zip->addFile($sqlFile, basename($sqlFile));
$zip->close();

// === STEP 3: Download ===
if (!file_exists($zipFile)) {
    die("❌ File ZIP tidak ditemukan setelah dibuat!");
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);

// Hapus sementara
@unlink($sqlFile);
@unlink($zipFile);
exit;
?>
