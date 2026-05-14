<?php
session_start();
require_once "../connect.php"; // koneksi ke database

$pesan_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input_password = $_POST['password'] ?? '';

  // Ambil hash password dari administrator
  $query = $koneksi->query("SELECT password FROM administrator LIMIT 1");

  if ($query && $row = $query->fetch_assoc()) {
    $hashed_password = $row['password'];

    if (password_verify($input_password, $hashed_password)) {
      // Simpan status login ke session
      $_SESSION['admin_logged_in'] = true;
      header("Location: dashboard/dashboard.php");
      exit;
    } else {
      $pesan_error = 'Password salah.';
    }
  } else {
    $pesan_error = 'Gagal mengambil data administrator.';
  }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
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
            <h4>Login Admin</h4>
          </div>
          <div class="card-body">
            <form method="POST">
              <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
              </div>
              <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php if ($pesan_error): ?>
<script>
  Swal.fire({
    icon: 'error',
    title: 'Login Gagal',
    text: <?= json_encode($pesan_error) ?>,
    confirmButtonColor: '#d33'
  });
</script>
<?php endif; ?>
</body>
</html>
