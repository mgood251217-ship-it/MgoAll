<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../connect.php';

$errors = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = strtoupper(trim($_POST['name'] ?? ''));
  $username = strtolower(trim($_POST['usernames'] ?? ''));
  $password = $_POST['password'] ?? '';

  $stmt = $koneksi->prepare("SELECT COUNT(*) FROM administrator WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->bind_result($userCount);
  $stmt->fetch();
  $stmt->close();

  if ($userCount > 0) {
      $errors = "Admin sudah digunakan.";
  }
  if (!$errors) {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $koneksi->prepare("INSERT INTO administrator (name, username, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $username, $passwordHash);
    if ($stmt->execute()) {
        $success = "Admin berhasil ditambahkan.";
    } else {
        $errors = "Gagal menambahkan admin.";
    }
    $stmt->close();
  }

}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Tambah Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      min-height: 100vh;
      background: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80') no-repeat center center fixed;
      background-size: cover;
      position: relative;
    }
    body::before {
      content: "";
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: inherit;
      filter: blur(8px) brightness(0.7);
      z-index: 0;
    }
    .container, .card {
      position: relative;
      z-index: 1;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container">
    <div class="row justify-content-center align-items-center vh-100">
      <div class="col-md-4">
        <div class="card shadow">
          <div class="card-header text-center">
            <h4>Tambah Administrator</h4>
          </div>
          <div class="card-body">
            <form method="POST" autocomplete="off" novalidate>
              <div class="mb-3">
                <label class="form-label">Nama</label>
                <input type="text" name="name" class="form-control" required>
                <label class="form-label">Username</label>
                <input  type="text" name="usernames" class="form-control" required>
                <label class="form-label">Password</label>
                <input type="text" name="password" class="form-control" required style="-webkit-text-security: disc;">
              </div>
              <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php if ($errors): ?>
<script>
  Swal.fire({
    icon: 'error',
    title: 'Administrator',
    text: <?= json_encode($errors) ?>,
    confirmButtonColor: '#d33'
  });
</script>
<?php endif; ?>
<?php if ($success): ?>
<script>
  Swal.fire({
    icon: 'success',
    title: 'Administrator',
    text: <?= json_encode($success) ?>,
    confirmButtonColor: '#3085d6',
    confirmButtonText: 'OK'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "login.php";
    }
  });
</script>
<?php endif; ?>
</body>
</html>
