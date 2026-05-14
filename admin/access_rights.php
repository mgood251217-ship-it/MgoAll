<?php
  if ($role === 'PRODUKSI' || $role === 'SETTING' || $role === 'ONLINE') {
    header("Location: " . BASE_URL . "/customer/customer.php");
  }
?>
