<?php
session_start();
include("config.php");

$is_logged_in = isset($_SESSION["user_id"]);
$user_name = "";

if ($is_logged_in) {
    $user_name = $_SESSION["user_name"];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard principal de StickerSwap - Gestiona tu álbum de figuritas.">
    <title>Dashboard - StickerSwap</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <nav class="navbar" id="main-navbar">
            <a href="dashboard.php" class="navbar-brand">
                <span style="font-size: 28px;">🎴</span> StickerSwap
            </a>
            <div class="navbar-menu">
                <a href="dashboard.php" class="navbar-link">Inicio</a>
                <?php if ($is_logged_in): ?>
                    <span style="color: #fcd400; font-weight: bold; margin-left: 10px;"><?php echo htmlspecialchars($user_name); ?></span>
                    <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="login.php" class="btn-login">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </nav>

        <header class="welcome-section">
            <?php if ($is_logged_in): ?>
                <h1 class="welcome-title">Bienvenido a StickerSwap <?php echo htmlspecialchars($user_name);?></h1>                
            <?php else: ?>
                <h1 class="welcome-title">Intercambio de Figuritas</h1>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="register.php" class="btn-login" style="padding: 12px 24px; font-size: 16px; display: inline-block;">Registrarme Ahora</a>
                    <a href="login.php" style="color: #ffffff; margin-left: 20px; font-weight: bold; text-decoration: none; border-bottom: 2px solid #ffffff;">Iniciar sesión</a>
                </div>
            <?php endif; ?>
        </header>
    </div>
</body>
</html>
