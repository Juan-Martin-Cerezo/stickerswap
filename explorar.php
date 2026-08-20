<?php
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Obtener álbumes
$res_albums = $conexion->query("SELECT * FROM Album ORDER BY ID_album ASC");
$albums = $res_albums->fetch_all(MYSQLI_ASSOC);

$active_album_id = intval($_GET['album_id'] ?? $_SESSION['active_album_id'] ?? ($albums[0]['ID_album'] ?? 1));
$_SESSION['active_album_id'] = $active_album_id;

// Álbum actual
$stmt_alb = $conexion->prepare("SELECT * FROM Album WHERE ID_album = ?");
$stmt_alb->bind_param("i", $active_album_id);
$stmt_alb->execute();
$current_album = $stmt_alb->get_result()->fetch_assoc();

// 1. Obtener todas las figuritas del álbum
$res_all_figs = $conexion->query("SELECT ID_figurita, numero_figurita, codigo_figurita, nombre_jugador, Seleccion, Holografica FROM Figurita WHERE ID_album = $active_album_id");
$album_figuritas = [];
while ($row = $res_all_figs->fetch_assoc()) {
    $album_figuritas[$row['ID_figurita']] = $row;
}

// 2. Inventario de mi usuario
$my_inventory_res = $conexion->query("
    SELECT f.ID_figurita, i.estado, i.cantidad_repetidas 
    FROM Figurita f 
    LEFT JOIN Inventario i ON f.ID_figurita = i.ID_figurita AND i.ID_usuario = $user_id
    WHERE f.ID_album = $active_album_id
");
$my_missing_ids = [];
$my_repeated_ids = [];

while ($row = $my_inventory_res->fetch_assoc()) {
    $fid = $row['ID_figurita'];
    if (empty($row['estado']) || $row['estado'] === 'falta') {
        $my_missing_ids[$fid] = true;
    }
    if ($row['cantidad_repetidas'] > 0) {
        $my_repeated_ids[$fid] = $row['cantidad_repetidas'];
    }
}

// 3. Buscar otros usuarios y calcular coincidencias
$other_users_res = $conexion->query("SELECT ID_usuario, nombre, email, avatar, reputacion, es_premium FROM Usuario WHERE ID_usuario != $user_id");
$matches = [];

while ($other = $other_users_res->fetch_assoc()) {
    $other_id = $other['ID_usuario'];

    // Inventario del otro usuario
    $other_inv_res = $conexion->query("
        SELECT f.ID_figurita, i.estado, i.cantidad_repetidas 
        FROM Figurita f 
        LEFT JOIN Inventario i ON f.ID_figurita = i.ID_figurita AND i.ID_usuario = $other_id
        WHERE f.ID_album = $active_album_id
    ");

    $they_can_give_me = [];
    $i_can_give_them = [];

    while ($row = $other_inv_res->fetch_assoc()) {
        $fid = $row['ID_figurita'];
        $fig_data = $album_figuritas[$fid] ?? null;
        if (!$fig_data) continue;

        // Si ellos tienen repetida y a mí me falta
        if ($row['cantidad_repetidas'] > 0 && isset($my_missing_ids[$fid])) {
            $they_can_give_me[] = $fig_data;
        }

        // Si yo tengo repetida y a ellos les falta
        if (isset($my_repeated_ids[$fid]) && (empty($row['estado']) || $row['estado'] === 'falta')) {
            $i_can_give_them[] = $fig_data;
        }
    }

    $is_perfect_match = (!empty($they_can_give_me) && !empty($i_can_give_them));

    if (!empty($they_can_give_me) || !empty($i_can_give_them)) {
        $matches[] = [
            'user' => $other,
            'is_perfect' => $is_perfect_match,
            'they_give' => $they_can_give_me,
            'i_give' => $i_can_give_them,
            'score' => count($they_can_give_me) + count($i_can_give_them) + ($is_perfect_match ? 100 : 0)
        ];
    }
}

// Ordenar: primero los perfect matches y con mayor cantidad de coincidencias
usort($matches, function($a, $b) {
    return $b['score'] <=> $a['score'];
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matchmaker - Intercambios Panini</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include("navbar.php"); ?>

    <div class="main-wrapper">
        <!-- Selector de Álbum -->
        <div class="album-selector-bar">
            <?php foreach ($albums as $alb): ?>
                <a href="explorar.php?album_id=<?php echo $alb['ID_album']; ?>" 
                   class="album-tab <?php echo ($alb['ID_album'] == $active_album_id) ? 'active' : ''; ?>">
                    <span class="album-tab-icon"><?php echo $alb['icono']; ?></span>
                    <span style="font-size: 14px; font-weight: 700;"><?php echo htmlspecialchars($alb['nombre']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="margin-bottom: 24px;">
            <span style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: var(--panini-gold); font-weight: 700;">Algoritmo de Emparejamiento Panini</span>
            <h1 style="font-size: 28px; font-weight: 800; color: #fff; margin-top: 4px;">Coleccionistas Compatibles</h1>
            <p style="color: var(--text-secondary); font-size: 15px;">
                Detectamos automáticamente a los coleccionistas que tienen las figuritas que te faltan y necesitan las que vos tenés repetidas.
            </p>
        </div>

        <?php if (!empty($matches)): ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($matches as $m): 
                    $u = $m['user'];
                ?>
                    <div style="background: var(--bg-card); border: 1px solid <?php echo $m['is_perfect'] ? 'rgba(252, 212, 0, 0.5)' : 'var(--border-color)'; ?>; border-radius: var(--radius-lg); padding: 24px; box-shadow: <?php echo $m['is_perfect'] ? 'var(--shadow-gold)' : 'var(--shadow-sm)'; ?>;">
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <div style="font-size: 38px; background: var(--bg-surface); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--border-color);">
                                    <?php echo $u['avatar']; ?>
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <h3 style="font-size: 19px; font-weight: 800; color: #fff;"><?php echo htmlspecialchars($u['nombre']); ?></h3>
                                        <?php if ($u['es_premium']): ?>
                                            <span style="background: var(--panini-gold); color: #000; font-size: 11px; font-weight: 800; padding: 2px 6px; border-radius: 4px;">GOLD VIP</span>
                                        <?php endif; ?>
                                        <?php if ($m['is_perfect']): ?>
                                            <span style="background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-size: 12px; font-weight: 800; padding: 3px 10px; border-radius: var(--radius-full);">🔥 MATCH PERFECTO</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-secondary); margin-top: 2px;">
                                        Reputación: <strong style="color: var(--panini-gold);"><?php echo $u['reputacion']; ?> ⭐</strong>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <a href="intercambio.php?nuevo=1&con_usuario=<?php echo $u['ID_usuario']; ?>&album_id=<?php echo $active_album_id; ?>" 
                                   class="btn-primary" style="font-size: 15px; padding: 12px 24px;">
                                    🤝 Proponer Intercambio
                                </a>
                            </div>
                        </div>

                        <!-- Detalle de Cromos Cruzados -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                            <!-- Lo que te puede dar -->
                            <div style="background: var(--bg-surface); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid var(--panini-emerald);">
                                <div style="font-size: 14px; font-weight: 700; color: #34d399; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                                    <span>📥</span> Tiene repetidas que a vos TE FALTAN (<?php echo count($m['they_give']); ?>)
                                </div>
                                <?php if (!empty($m['they_give'])): ?>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php foreach ($m['they_give'] as $fig): ?>
                                            <span style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #fff; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px;">
                                                #<?php echo $fig['numero_figurita']; ?> <?php echo htmlspecialchars($fig['nombre_jugador']); ?>
                                                <?php if ($fig['Holografica']) echo '✨'; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 13px; color: var(--text-muted);">No tiene repetidas que te falten actualmente.</div>
                                <?php endif; ?>
                            </div>

                            <!-- Lo que vos le podés dar -->
                            <div style="background: var(--bg-surface); padding: 16px; border-radius: var(--radius-md); border-left: 4px solid var(--panini-gold);">
                                <div style="font-size: 14px; font-weight: 700; color: var(--panini-gold); margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                                    <span>📤</span> Tenés repetidas que a él LE FALTAN (<?php echo count($m['i_give']); ?>)
                                </div>
                                <?php if (!empty($m['i_give'])): ?>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php foreach ($m['i_give'] as $fig): ?>
                                            <span style="background: rgba(252, 212, 0, 0.15); border: 1px solid rgba(252, 212, 0, 0.3); color: #fff; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px;">
                                                #<?php echo $fig['numero_figurita']; ?> <?php echo htmlspecialchars($fig['nombre_jugador']); ?>
                                                <?php if ($fig['Holografica']) echo '✨'; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 13px; color: var(--text-muted);">No tenés repetidas que le falten a este usuario.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); padding: 48px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                <h3 style="color: #fff; font-size: 20px; font-weight: 800;">No encontramos coincidencias automáticas en este momento</h3>
                <p style="color: var(--text-secondary); font-size: 15px; max-width: 520px; margin: 8px auto 24px;">
                    Asegurate de haber marcado tus figuritas repetidas y pegadas en la sección <strong>Mi Álbum</strong> para que el motor de emparejamiento pueda cruzar datos con otros coleccionistas.
                </p>
                <a href="album.php?album_id=<?php echo $active_album_id; ?>" class="btn-primary">
                    Ir a Cargar Figuritas en Mi Álbum
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
