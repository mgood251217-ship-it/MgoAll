<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
        }
        .main-wrapper {
            margin-left: 70px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .content {
            padding: 24px;
            flex: 1;
        }
    </style>
</head>
<body>

    <?php require 'sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php require 'navbar.php'; ?>
        
        <div class="content">
            <?php echo $content; ?>
        </div>
        
        <?php require 'footer.php'; ?>
    </div>

</body>
</html>