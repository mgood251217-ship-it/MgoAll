<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user_logged_in'])) {
    header("Location: /order");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config/database.php';
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $sql = "SELECT u.*, s.name as store_name, s.img as store_img, s.address as store_address
                FROM users u 
                LEFT JOIN stores s ON u.store_id = s.id
                WHERE u.username = :username LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id']        = $user['id'];
            $_SESSION['nama_user']      = $user['name'];
            $_SESSION['user_img']       = $user['img'];

            $_SESSION['store_id']       = $user['store_id'];
            $_SESSION['store_name']     = $user['store_name'];
            $_SESSION['store_img']      = $user['store_img'];
            $_SESSION['store_address']  = $user['store_address'];
            
            header("Location: /order");
            exit();
        } else {
            $error = "Username atau password salah!";
        }
    } catch (PDOException $e) {
        $error = "Error Database: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kasir Optik</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; height: 100vh; display: flex; justify-content: center; align-items: center; }
        .login-container { background: white; padding: 50px 40px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); width: 100%; max-width: 420px; text-align: center; }
        .login-logo { width: 60px; height: 60px; background: #2b6cb0; color: white; border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 24px; font-weight: bold; margin: 0 auto 20px auto; }
        h2 { color: #1e293b; margin-bottom: 10px; font-size: 24px; }
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #475569; }
        .input-group input { width: 100%; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; outline: none; transition: all 0.2s; background: #f8fafc; }
        .input-group input:focus { border-color: #2b6cb0; background: white; box-shadow: 0 0 0 3px rgba(43,108,176,0.1); }
        .btn-login { width: 100%; padding: 15px; background: #2b6cb0; color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn-login:hover { background: #1e4e8c; transform: translateY(-2px); }
        .alert-error { background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-logo">OP</div>
        <h2>Selamat Datang</h2>

        <?php if($error != ""): ?>
            <div class="alert-error"><?= $error; ?></div>
        <?php endif; ?>

        <form action="/login" method="POST">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn-login">Masuk</button>
        </form>
    </div>

</body>
</html>