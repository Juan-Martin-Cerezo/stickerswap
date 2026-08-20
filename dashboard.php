<?php
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$stmt_u = $conexion->prepare("SELECT onboarding_completado FROM Usuario WHERE ID_usuario = ?");
$stmt_u->bind_param("i", $user_id);
$stmt_u->execute();
$u_data = $stmt_u->get_result()->fetch_assoc();
if ($u_data && $u_data['onboarding_completado'] == 0) {
    header("Location: onboarding.php?step=1");
    exit;
}

$res_albums = $conexion->query("SELECT * FROM Album ORDER BY ID_album ASC");
$albums = [];
while ($row = $res_albums->fetch_assoc()) {
    $albums[] = $row;
}

$active_album_id = intval($_GET['album_id'] ?? $_SESSION['active_album_id'] ?? ($albums[0]['ID_album'] ?? 1));
$_SESSION['active_album_id'] = $active_album_id;

$stmt_cur_alb = $conexion->prepare("SELECT * FROM Album WHERE ID_album = ?");
$stmt_cur_alb->bind_param("i", $active_album_id);
$stmt_cur_alb->execute();
$current_album = $stmt_cur_alb->get_result()->fetch_assoc();

if (!$current_album && !empty($albums)) {
    $current_album = $albums[0];
    $active_album_id = $current_album['ID_album'];
}

$stmt_total = $conexion->prepare("SELECT COUNT(*) as total FROM Figurita WHERE ID_album = ?");
$stmt_total->bind_param("i", $active_album_id);
$stmt_total->execute();
$total_figs = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;

$stmt_owned = $conexion->prepare("
    SELECT COUNT(DISTINCT i.ID_figurita) as owned,
           SUM(CASE WHEN i.cantidad_repetidas > 0 THEN i.cantidad_repetidas ELSE 0 END) as repetidas_totales,
           COUNT(CASE WHEN i.cantidad_repetidas > 0 THEN 1 END) as distintas_repetidas
    FROM Inventario i
    JOIN Figurita f ON i.ID_figurita = f.ID_figurita
    WHERE i.ID_usuario = ? AND f.ID_album = ? AND (i.estado = 'tengo' OR i.estado = 'repetida')
");
$stmt_owned->bind_param("ii", $user_id, $active_album_id);
$stmt_owned->execute();
$stats_row = $stmt_owned->get_result()->fetch_assoc();

$owned_figs = $stats_row['owned'] ?? 0;
$repetidas_totales = $stats_row['repetidas_totales'] ?? 0;
$distintas_repetidas = $stats_row['distintas_repetidas'] ?? 0;
$missing_figs = max(0, $total_figs - $owned_figs);
$progress_pct = ($total_figs > 0) ? round(($owned_figs / $total_figs) * 100) : 0;

$stmt_trades = $conexion->prepare("
    SELECT i.*, 
           u1.nombre as proponente_nombre, u1.avatar as proponente_avatar,
           u2.nombre as receptor_nombre, u2.avatar as receptor_avatar,
           a.nombre as album_nombre, a.icono as album_icono
    FROM Intercambio i
    JOIN Usuario u1 ON i.ID_usuario_1 = u1.ID_usuario
    JOIN Usuario u2 ON i.ID_usuario_2 = u2.ID_usuario
    JOIN Album a ON i.ID_album = a.ID_album
    WHERE (i.ID_usuario_1 = ? OR i.ID_usuario_2 = ?) AND i.estado = 'pendiente'
    ORDER BY i.fecha_actualizacion DESC
");
$stmt_trades->bind_param("ii", $user_id, $user_id);
$stmt_trades->execute();
$active_trades = $stmt_trades->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StickerSwap Panini Oficial</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include("navbar.php"); ?>

    <div class="main-wrapper">
        
        <div style="margin-bottom: 8px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <span style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: var(--panini-gold); font-weight: 700;">Colecciones Panini Disponibles</span>
                <h2 style="font-size: 24px; font-weight: 800; color: #fff;">Seleccioná tu Álbum Activo</h2>
            </div>
            <a href="album.php?album_id=<?php echo $active_album_id; ?>" class="btn-primary">
                📖 Abrir <?php echo htmlspecialchars($current_album['nombre'] ?? ''); ?>
            </a>
        </div>

        <div class="album-selector-bar">
            <?php foreach ($albums as $alb): ?>
                <a href="dashboard.php?album_id=<?php echo $alb['ID_album']; ?>" 
                   class="album-tab <?php echo ($alb['ID_album'] == $active_album_id) ? 'active' : ''; ?>">
                    <span class="album-tab-icon"><?php echo $alb['icono']; ?></span>
                    <div>
                        <div style="font-size: 14px; font-weight: 700;"><?php echo htmlspecialchars($alb['nombre']); ?></div>
                        <div style="font-size: 12px; opacity: 0.7;"><?php echo htmlspecialchars($alb['categoria']); ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        

        
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-title">Progreso del Álbum</span>
                <div class="stat-value" style="color: var(--panini-gold);"><?php echo $progress_pct; ?>%</div>
                <div class="stat-subtitle"><?php echo "$owned_figs de $total_figs figuritas pegadas"; ?></div>
                <div class="progress-container">
                    <div class="progress-fill" style="width: <?php echo $progress_pct; ?>%;"></div>
                </div>
            </div>

            <div class="stat-card">
                <span class="stat-title">Figuritas en Álbum</span>
                <div class="stat-value" style="color: var(--panini-emerald);"><?php echo $owned_figs; ?></div>
            </div>

            <div class="stat-card">
                <span class="stat-title">Te Faltan</span>
                <div class="stat-value" style="color: var(--panini-red);"><?php echo $missing_figs; ?></div>
            </div>

            <div class="stat-card">
                <span class="stat-title">Repetidas para Cambio</span>
                <div class="stat-value" style="color: #38bdf8;"><?php echo $repetidas_totales; ?></div>
            </div>
        </div>

        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 36px;">
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="font-size: 32px; margin-bottom: 12px;">🎴</div>
                    <h3 style="font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 8px;">Gestionar Mi Álbum</h3>
                </div>
                <div style="margin-top: 20px;">
                    <a href="album.php?album_id=<?php echo $active_album_id; ?>" class="btn-primary" style="width: 100%; justify-content: center;">
                        Ver Mi Colección
                    </a>
                </div>
            </div>

            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="font-size: 32px; margin-bottom: 12px;">💬</div>
                    <h3 style="font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 8px;">Chat de la Comunidad en Vivo</h3>
                </div>
                <div style="margin-top: 20px;">
                    <a href="chat_comunidad.php" class="btn-success" style="width: 100%; justify-content: center;">
                        Abrir Chat Global
                    </a>
                </div>
            </div>
        </div>

        <div class="section-title-wrapper">
            <h2 class="section-title">
                <span>💬</span> Mis Negociaciones e Intercambios Activos
            </h2>
            <a href="historial.php" class="btn-secondary" style="font-size: 13px;">Ver Historial Completo</a>
        </div>

        <?php if (!empty($active_trades)): ?>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($active_trades as $tr): 
                    $other_is_user1 = ($tr['ID_usuario_1'] != $user_id);
                    $other_name = $other_is_user1 ? $tr['proponente_nombre'] : $tr['receptor_nombre'];
                    $other_avatar = $other_is_user1 ? $tr['proponente_avatar'] : $tr['receptor_avatar'];
                    $is_my_turn = ($tr['ultimo_proponente_id'] != $user_id);
                ?>
                    <div class="match-card">
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <div style="font-size: 36px; background: var(--bg-surface); width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--border-color);">
                                <?php echo $other_avatar; ?>
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <h4 style="font-size: 17px; font-weight: 700; color: #fff;"><?php echo htmlspecialchars($other_name); ?></h4>
                                    <span style="font-size: 12px; background: var(--bg-surface); padding: 2px 8px; border-radius: var(--radius-full); color: var(--text-secondary);">
                                        <?php echo $tr['album_icono'] . ' ' . htmlspecialchars($tr['album_nombre']); ?>
                                    </span>
                                </div>
                                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                                    <?php if ($is_my_turn): ?>
                                        <span style="color: var(--panini-gold); font-weight: 700;">🔔 ¡Es tu turno de responder la propuesta!</span>
                                    <?php else: ?>
                                        <span style="color: #38bdf8;">⏳ Esperando respuesta del coleccionista...</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <a href="intercambio.php?id=<?php echo $tr['ID_intercambio']; ?>" class="btn-primary" style="font-size: 14px;">
                                Entrar a la Negociación y Chat →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 36px; text-align: center;">
                <div style="font-size: 40px; margin-bottom: 12px;">🤝</div>
                <h4 style="color: #fff; font-size: 17px; font-weight: 700;">No tenés negociaciones abiertas en este momento</h4>
                <p style="color: var(--text-secondary); font-size: 14px; max-width: 480px; margin: 6px auto 18px;">
                    Podés entrar al chat de la comunidad para encontrar otros coleccionistas y proponerles un intercambio en tiempo real.
                </p>
                <a href="chat_comunidad.php" class="btn-primary">
                    Ir al Chat de la Comunidad
                </a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
