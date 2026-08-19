<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Center</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-wrapper {
            display: flex;
            flex: 1;
        }
        .content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .content {
            padding: 20px;
            flex: 1;
        }
        .navbar {
            background-color: #333;
            color: white;
            padding: 15px 20px;
        }
        .sidebar {
            width: 250px;
            background-color: #f4f4f4;
            padding: 20px;
            border-right: 1px solid #ddd;
        }
        .footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 10px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            margin-bottom: 10px;
        }
        .sidebar ul li a {
            text-decoration: none;
            color: #333;
        }
    </style>
</head>
<body>

    <?php require 'navbar.php'; ?>
    
    <div class="main-wrapper">
        <?php require 'sidebar.php'; ?>
        
        <div class="content-wrapper">
            <div class="content">
                <?php echo $content; ?>
            </div>
            
            <?php require 'footer.php'; ?>
        </div>
    </div>

</body>
</html>