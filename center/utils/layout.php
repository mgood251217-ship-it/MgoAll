<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <?php
    $centerScriptDirectory = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $centerBaseUrl = $centerScriptDirectory !== '' ? '/' . $centerScriptDirectory : '';
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($centerBaseUrl . '/assets/css/pages.css?v=1.0', ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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