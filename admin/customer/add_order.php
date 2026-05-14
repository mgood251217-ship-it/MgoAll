<?php
require_once '../connect.php';
require_once 'functions.php';
require_once BASE_PATH . '/session.php';

$customer_name = $_POST['customer_name'] ?? '';
$nomor = $_POST['nomor'] ?? ''; // Tetap string
$total = 0; // Default awal

// Konversi deadline ke format SQL
$deadline_input = $_POST['deadline'] ?? '';
$deadline = date('Y-m-d H:i:s', strtotime($deadline_input));

$user_id = (int)$_POST['user_id'] ?? 0;

// Set timezone & waktu sekarang

$tanggalSekarang = date('Y-m-d H:i:s');

if ($customer_name == '' || $user_id == 0 || $deadline_input == '') {
    header("Location: customer.php");
    exit;
}

$tglSekarang = date('Y-m-d', strtotime($tanggalSekarang));
$tglDeadline = date('Y-m-d', strtotime($deadline));

if ($tglDeadline < $tglSekarang) {
    header("Location: customer.php");
    exit;
}



// Ambil role dari user_id untuk menentukan sistem
$stmtRole = $koneksi->prepare("SELECT role FROM users WHERE user_id = ?");
$stmtRole->bind_param("i", $user_id);
$stmtRole->execute();
$stmtRole->bind_result($roleUser);
$stmtRole->fetch();
$stmtRole->close();

$sis = ($roleUser === 'ONLINE') ? 'ONLINE' : 'OFFLINE';

// Generate nomorator otomatis
$nomorator = generateNomorator($koneksi, $store_id, $sis);

// Simpan ke tabel orders
$stmt = $koneksi->prepare("
    INSERT INTO orders 
    (store_id, nomorator, customer_name, nomor, total, deadline, user_id, system, date) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isssdssss", $store_id, $nomorator, $customer_name, $nomor, $total, $deadline, $user_id, $sis, $tanggalSekarang);

if ($stmt->execute()) {
    $order_id = $stmt->insert_id;

    // Insert ke tabel projects
    $stmtProduk = $koneksi->prepare("
        INSERT INTO projects (order_id, status, process, user_id, date) 
        VALUES (?, 'PEMBAYARAN', 'PEMBAYARAN', 0, ?)
    ");
    $stmtProduk->bind_param("is", $order_id, $tanggalSekarang);
    $stmtProduk->execute();

    ?>
    <form id="redirectForm" action="nota.php" method="post">
      <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
    </form>
    <script>document.getElementById('redirectForm').submit();</script>
    <?php
    exit;
} else {
    echo "Gagal menambahkan order: " . $stmt->error;
}
?>
