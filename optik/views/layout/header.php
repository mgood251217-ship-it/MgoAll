<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganesha Optik</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="/assets/web/OPTIK.ico" type="image/x-icon">
    <style>
        :root {
            --primary: #2b6cb0;     
            --primary-hover: #1e4e8c;
            --bg-color: #f4f7fe;    
            --card-bg: #ffffff;     
            --text-dark: #1e293b;
            --border-radius: 20px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body {
            background-color: var(--bg-color);
            min-height: 100dvh;
            min-height: 100vh;
            display: flex;
            overflow: auto;
            color: var(--text-dark);
        }

        .main-layout {
            margin-left: 70px;
            flex: 1;
            display: flex;
            flex-direction: column;
            width: calc(100% - 70px);
            min-height: 100dvh;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            display: block;
            overflow-y: auto;
            overflow-x: hidden;
        }

        @media screen and (max-width: 768px) {
            .main-layout {
                margin-left: 0 !important;
                width: 100% !important;
                min-height: 100dvh !important;
                min-height: 100vh !important;
                height: auto !important;
            }
            .content-area {
                display: block !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                margin-bottom: 80px !important;
                min-height: 0 !important;
                height: auto !important;
            }
        }
    </style>
</head>
<body>