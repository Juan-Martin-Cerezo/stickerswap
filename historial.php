<?php
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$stmt = $conexion->prepare("
    SELECT i.*, 
           u1.nombre as user1_nombre, u1.avatar as user1_avatar,
           u2.nombre as user2_nombre, u2.avatar as user2_avatar,
           a.nombre as album_nombre, a.icono as album_icono
    FROM Intercambio i
    JOIN Usuario u1 ON i.ID_usuario_1 = u1.ID_usuario
    JOIN Usuario u2 ON i.ID_usuario_2 = u2.ID_usuario
    JOIN Album a ON i.ID_album = a.ID_album
    WHERE i.ID_usuario_1 = ? OR i.ID_usuario_2 = ?
    ORDER BY i.fecha_actualizacion DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$trades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Intercambios - Panini StickerSwap</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include("navbar.php"); ?>

    <div class="main-wrapper">
        <div style="margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <span style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: var(--panini-gold); font-weight: 700;">Registro Oficial de Transacciones</span>
                <h1 style="font-size: 28px; font-weight: 800; color: #fff; margin-top: 4px;">Historial de Intercambios</h1>
            </div>
            <a href="explorar.php" class="btn-primary">
                🔍 Buscar Nuevos Intercambios
            </a>
        </div>

        <?php if (!empty($trades)): ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($trades as $t): 
                    $is_user1 = ($t['ID_usuario_1'] == $user_id);
                    $other_name = $is_user1 ? $t['user2_nombre'] : $t['user1_nombre'];
                    $other_avatar = $is_user1 ? $t['user2_avatar'] : $t['user1_avatar'];

                    $tid = $t['ID_intercambio'];
                    $res_items = $conexion->query("
                        SELECT it.*, f.numero_figurita, f.nombre_jugador, f.Holografica 
                        FROM Intercambio_Item it 
                        JOIN Figurita f ON it.ID_figurita = f.ID_figurita 
                        WHERE it.ID_intercambio = $tid
                    ");
                    $items_given = [];
                    $items_received = [];
                    while ($it = $res_items->fetch_assoc()) {
                        if ($it['ID_usuario_dueno'] == $user_id) {
                            $items_given[] = $it;
                        } else {
                            $items_received[] = $it;
                        }
                    }
                ?>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; transition: transform 0.2s ease;">
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px; margin-bottom: 14px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="font-size: 28px;"><?php echo $other_avatar; ?></div>
                                <div>
                                    <h3 style="font-size: 17px; font-weight: 700; color: #fff;">
                                        Intercambio con <?php echo htmlspecialchars($other_name); ?>
                                    </h3>
                                    <div style="font-size: 12px; color: var(--text-secondary);">
                                        <?php echo $t['album_icono'] . ' ' . htmlspecialchars($t['album_nombre']); ?> • <?php echo date('d/m/Y H:i', strtotime($t['fecha_actualizacion'])); ?>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if ($t['estado'] === 'aceptado'): ?>
                                    <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-weight: 800; font-size: 12px; padding: 4px 12px; border-radius: var(--radius-full);">
                                        ✓ CONCRETADO
                                    </span>
                                <?php elseif ($t['estado'] === 'pendiente'): ?>
                                    <span style="background: rgba(252, 212, 0, 0.2); color: var(--panini-gold); font-weight: 800; font-size: 12px; padding: 4px 12px; border-radius: var(--radius-full);">
                                        ⏳ PENDIENTE
                                    </span>
                                <?php elseif ($t['estado'] === 'rechazado'): ?>
                                    <span style="background: rgba(239, 68, 68, 0.2); color: #f87171; font-weight: 800; font-size: 12px; padding: 4px 12px; border-radius: var(--radius-full);">
                                        ✕ RECHAZADO
                                    </span>
                                <?php else: ?>
                                    <span style="background: var(--bg-surface); color: var(--text-muted); font-weight: 800; font-size: 12px; padding: 4px 12px; border-radius: var(--radius-full);">
                                        CANCELADO
                                    </span>
                                <?php endif; ?>

                                <a href="intercambio.php?id=<?php echo $t['ID_intercambio']; ?>" class="btn-secondary" style="font-size: 13px; padding: 6px 14px;">
                                    Ver Sala y Chat
                                </a>
                            </div>
                        </div>

                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 13px;">
                            <div>
                                <span style="color: var(--panini-gold); font-weight: 700;">📤 Entregaste:</span>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                                    <?php if (!empty($items_given)): ?>
                                        <?php foreach ($items_given as $it): ?>
                                            <span style="background: var(--bg-surface); padding: 3px 8px; border-radius: 4px; color: #fff;">
                                                #<?php echo $it['numero_figurita']; ?> <?php echo htmlspecialchars($it['nombre_jugador']); ?>
                                                <?php if ($it['Holografica']) echo '✨'; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Ninguna</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <span style="color: var(--panini-emerald); font-weight: 700;">📥 Recibiste:</span>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                                    <?php if (!empty($items_received)): ?>
                                        <?php foreach ($items_received as $it): ?>
                                            <span style="background: var(--bg-surface); padding: 3px 8px; border-radius: 4px; color: #fff;">
                                                #<?php echo $it['numero_figurita']; ?> <?php echo htmlspecialchars($it['nombre_jugador']); ?>
                                                <?php if ($it['Holografica']) echo '✨'; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Ninguna</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); padding: 48px; text-align: center;">
                <div style="font-size: 44px; margin-bottom: 14px;">📜</div>
                <h3 style="color: #fff; font-size: 19px; font-weight: 700;">Aún no tenés historial de transacciones</h3>
                <p style="color: var(--text-secondary); font-size: 14px; max-width: 440px; margin: 6px auto 20px;">
                    Cuando propongas o completes intercambios con otros coleccionistas, quedará registrado todo el detalle aquí.
                </p>
                <a href="explorar.php" class="btn-primary">Iniciar un Intercambio</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
