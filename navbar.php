<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged = isset($_SESSION["user_id"]);
$current_page = basename($_SERVER['PHP_SELF']);
$active_album_id = $_GET['album_id'] ?? $_SESSION['active_album_id'] ?? 1;

$pending_trades_count = 0;
if ($is_logged && isset($conexion)) {
    $uid = $_SESSION["user_id"];
    $res_count = $conexion->query("SELECT COUNT(*) as total FROM Intercambio WHERE (ID_usuario_1 = $uid OR ID_usuario_2 = $uid) AND estado = 'pendiente'");
    if ($res_count) {
        $row_c = $res_count->fetch_assoc();
        $pending_trades_count = $row_c['total'] ?? 0;
    }
}
?>
<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <span style="font-size: 26px;">🎴</span> StickerSwap <span class="brand-badge">Panini Corp</span>
    </a>
    
    <div class="navbar-menu">
        <?php if ($is_logged): ?>
            <a href="dashboard.php" class="navbar-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                📊 Dashboard
            </a>
            <a href="album.php?album_id=<?php echo $active_album_id; ?>" class="navbar-link <?php echo ($current_page == 'album.php') ? 'active' : ''; ?>">
                📖 Mi Álbum
            </a>
            <a href="explorar.php?album_id=<?php echo $active_album_id; ?>" class="navbar-link <?php echo ($current_page == 'explorar.php') ? 'active' : ''; ?>">
                🔍 Matchmaker
            </a>
            <a href="chat_comunidad.php" class="navbar-link <?php echo ($current_page == 'chat_comunidad.php') ? 'active' : ''; ?>">
                💬 Chat Comunidad
            </a>
            <a href="historial.php" class="navbar-link <?php echo ($current_page == 'historial.php' || $current_page == 'intercambio.php') ? 'active' : ''; ?>">
                🤝 Intercambios 
                <?php if ($pending_trades_count > 0): ?>
                    <span class="badge-counter"><?php echo $pending_trades_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="premium.php" class="navbar-link <?php echo ($current_page == 'premium.php') ? 'active' : ''; ?>" style="color: var(--panini-gold);">
                👑 Premium
            </a>

            <div class="user-pill <?php echo (!empty($_SESSION['es_premium'])) ? 'is-premium' : ''; ?>">
                <span><?php echo $_SESSION['avatar'] ?? '👤'; ?></span>
                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <?php if (!empty($_SESSION['es_premium'])): ?>
                    <span style="font-size: 11px; background: var(--panini-gold); color: #000; padding: 1px 5px; border-radius: 4px; font-weight: 800;">GOLD</span>
                <?php endif; ?>
            </div>
            <a href="logout.php" class="btn-secondary" style="padding: 6px 12px; font-size: 13px;">Salir</a>
        <?php else: ?>
            <a href="login.php" class="navbar-link">Iniciar Sesión</a>
            <a href="register.php" class="btn-primary" style="padding: 8px 16px; font-size: 14px;">Registrarse</a>
        <?php endif; ?>
    </div>
</nav>
