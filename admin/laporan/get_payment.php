<?php
require_once '../connect.php'; 
require_once BASE_PATH . '/session.php';

if (!isset($_GET['order_id'])) {
    http_response_code(400);
    exit('Missing order_id');
}
    $stmt = $koneksi->prepare("SELECT mode FROM user_setting WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $mode = (int)$row['mode'];
    }
$order_id = (int)$_GET['order_id'];

$paymentQuery = $koneksi->prepare("
    SELECT payment_id, date, nominal, payment_method, status 
    FROM payment 
    WHERE order_id = ? 
    ORDER BY date DESC
");
$paymentQuery->bind_param("i", $order_id);
$paymentQuery->execute();
$result = $paymentQuery->get_result();

if ($result->num_rows > 0) {
    while ($payment = $result->fetch_assoc()) {
        $payment_id = (int)$payment['payment_id'];
        $tanggal = htmlspecialchars($payment['date']);
        $nominal = number_format((int)$payment['nominal'], 0, ',', '.');
        $method = htmlspecialchars($payment['payment_method']);
        $status = htmlspecialchars($payment['status']);
        ?>
        <div class="editable-payment border p-2 rounded bg-light" <?= ($mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>
            data-payment-id="<?= $payment_id ?>"
            data-order="<?= $order_id ?>"
            data-nominal="<?= $payment['nominal'] ?>"
            data-metode="<?= $method ?>"
            data-tanggal="<?= $tanggal ?>">
          <div><strong>Tanggal:</strong> <?= $tanggal ?></div>
          <div><strong>Nominal:</strong> Rp<?= $nominal ?></div>
          <div><strong>Metode Pembayaran:</strong> <?= $method ?></div>
          <div><strong>Status:</strong> <?= $status ?></div>
        </div>

        <?php
    }

                
    $storeNameUpload = preg_replace('/[^a-zA-Z0-9_-]/', '_', $storeName);
    $query = "SELECT transfer_id, img FROM transfers WHERE order_id = ?";
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $fotos = [];
    while ($row = $result->fetch_assoc()) {
        $fotos[] = $row;
    }
    $stmt->close();
    if (!empty($fotos)) {
    foreach ($fotos as $f) {
        $imgUrl = BASE_URL . '/assets/img/buktitf/'. $storeNameUpload. "/" . $f['img'];
?>
                  <div class="conimg position-relative d-inline-block me-2 mb-2" id="img-<?= $f['transfer_id'] ?>">
                    <img 
                      src="<?= $imgUrl ?>" 
                      onclick="showImageModal('<?= $imgUrl ?>')" 
                      alt="Bukti Transfer" 
                      class="payimg rounded img-fluid shadow-sm border"
                      style="object-fit: cover; max-height: 120px;"
                    >

                    <button 
                      type="button" 
                      class="btn btn-sm btn-light rounded-circle shadow-sm btn-delete-img position-absolute top-0 end-0 m-1"
                      data-transfer-id="<?= $f['transfer_id'] ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                          <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                        </svg>
                    </button>
                  </div>  
    <?php

    }
    }

    
} else { ?>
     <div <?= ($mode === 1) ? 'style="background-color: #333 !important; color: #e0e0e0 !important;"' : '' ?>>Belum ada pembayaran.</div>
<?php
} ?>
