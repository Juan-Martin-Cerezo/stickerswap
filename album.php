<?php
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$res_albums = $conexion->query("SELECT * FROM Album ORDER BY ID_album ASC");
$albums = $res_albums->fetch_all(MYSQLI_ASSOC);

$active_album_id = intval($_GET['album_id'] ?? $_SESSION['active_album_id'] ?? ($albums[0]['ID_album'] ?? 1));
$_SESSION['active_album_id'] = $active_album_id;

$stmt_alb = $conexion->prepare("SELECT * FROM Album WHERE ID_album = ?");
$stmt_alb->bind_param("i", $active_album_id);
$stmt_alb->execute();
$current_album = $stmt_alb->get_result()->fetch_assoc();

if (!$current_album && !empty($albums)) {
    $current_album = $albums[0];
    $active_album_id = $current_album['ID_album'];
}

$stmt_figs = $conexion->prepare("
    SELECT f.*, 
           IFNULL(i.estado, 'falta') as user_status, 
           IFNULL(i.cantidad_repetidas, 0) as repetidas,
           IFNULL(i.pegada_en_album, 0) as pegada
    FROM Figurita f
    LEFT JOIN Inventario i ON f.ID_figurita = i.ID_figurita AND i.ID_usuario = ?
    WHERE f.ID_album = ?
    ORDER BY f.numero_figurita ASC
");
$stmt_figs->bind_param("ii", $user_id, $active_album_id);
$stmt_figs->execute();
$figuritas = $stmt_figs->get_result()->fetch_all(MYSQLI_ASSOC);

$grouped_figs = [];
$total_figs = count($figuritas);
$owned_count = 0;
$repeated_total_count = 0;

foreach ($figuritas as $fig) {
    $grouped_figs[$fig['Seleccion']][] = $fig;
    if ($fig['user_status'] === 'tengo' || $fig['user_status'] === 'repetida') {
        $owned_count++;
    }
    if ($fig['repetidas'] > 0) {
        $repeated_total_count += $fig['repetidas'];
    }
}

$missing_count = max(0, $total_figs - $owned_count);
$progress_pct = ($total_figs > 0) ? round(($owned_count / $total_figs) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Álbum - <?php echo htmlspecialchars($current_album['nombre']); ?></title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include("navbar.php"); ?>

    <div class="main-wrapper">
        
        <div class="album-selector-bar">
            <?php foreach ($albums as $alb): ?>
                <a href="album.php?album_id=<?php echo $alb['ID_album']; ?>" 
                   class="album-tab <?php echo ($alb['ID_album'] == $active_album_id) ? 'active' : ''; ?>">
                    <span class="album-tab-icon"><?php echo $alb['icono']; ?></span>
                    <span style="font-size: 14px; font-weight: 700;"><?php echo htmlspecialchars($alb['nombre']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="font-size: 48px; background: var(--bg-surface); width: 80px; height: 80px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
                    <?php echo $current_album['icono']; ?>
                </div>
                <div>
                    <span class="brand-badge"><?php echo htmlspecialchars($current_album['categoria']); ?></span>
                    <h1 style="font-size: 26px; font-weight: 800; color: #fff; margin-top: 4px;"><?php echo htmlspecialchars($current_album['nombre']); ?></h1>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-top: 2px;"><?php echo htmlspecialchars($current_album['descripcion']); ?></p>
                </div>
            </div>

            
            <div style="min-width: 240px; background: var(--bg-surface); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 700; margin-bottom: 6px;">
                    <span style="color: var(--text-secondary);">Progreso de Colección</span>
                    <span id="stat-progress-text" style="color: var(--panini-gold);"><?php echo $progress_pct; ?>%</span>
                </div>
                <div class="progress-container" style="margin: 0 0 10px 0;">
                    <div id="stat-progress-fill" class="progress-fill" style="width: <?php echo $progress_pct; ?>%;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted);">
                    <span>Pegadas: <strong id="stat-owned-count" style="color: var(--panini-emerald);"><?php echo $owned_count; ?></strong>/<?php echo $total_figs; ?></span>
                    <span>Repetidas: <strong id="stat-rep-count" style="color: #38bdf8;"><?php echo $repeated_total_count; ?></strong></span>
                </div>
            </div>
        </div>

        
        <div style="background: var(--bg-surface); padding: 14px 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 28px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px;">
            <div class="filters-bar" style="margin-bottom: 0;">
                <button class="filter-btn active" onclick="filterStickers('all', this)">Todas (<?php echo $total_figs; ?>)</button>
                <button class="filter-btn" onclick="filterStickers('missing', this)">Faltantes (<?php echo $missing_count; ?>)</button>
                <button class="filter-btn" onclick="filterStickers('owned', this)">Pegadas / Tengo (<?php echo $owned_count; ?>)</button>
                <button class="filter-btn" onclick="filterStickers('repeated', this)">Repetidas (<?php echo $repeated_total_count; ?>)</button>
                <button class="filter-btn" onclick="filterStickers('holo', this)">✨ Holográficas</button>
            </div>

            <div style="position: relative; min-width: 240px;">
                <input type="text" id="searchInput" placeholder="Buscar jugador, número o equipo..." 
                       class="form-input" style="padding: 8px 14px; font-size: 13px; width: 100%; border-radius: var(--radius-full);"
                       onkeyup="searchStickers(this.value)">
            </div>
        </div>

        
        <?php foreach ($grouped_figs as $seccion_nombre => $figs_list): ?>
            <div class="category-section" data-category="<?php echo htmlspecialchars($seccion_nombre); ?>">
                <div class="section-title-wrapper">
                    <h2 class="section-title">
                        <span>🛡️</span> <?php echo htmlspecialchars($seccion_nombre); ?>
                        <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">(<?php echo count($figs_list); ?> figuritas)</span>
                    </h2>
                </div>

                <div class="stickers-grid">
                    <?php foreach ($figs_list as $f): 
                        $card_class = '';
                        if ($f['user_status'] === 'falta') {
                            $card_class = 'missing';
                        } elseif ($f['user_status'] === 'repetida' && $f['repetidas'] > 0) {
                            $card_class = 'repeated';
                        } else {
                            $card_class = 'owned';
                        }

                        $is_holo = ($f['Holografica'] == 1);
                        if ($is_holo) {
                            $card_class .= ' holo';
                        }
                    ?>
                        <div class="sticker-card <?php echo $card_class; ?>" 
                             id="card-<?php echo $f['ID_figurita']; ?>"
                             data-id="<?php echo $f['ID_figurita']; ?>"
                             data-status="<?php echo $f['user_status']; ?>"
                             data-rep="<?php echo $f['repetidas']; ?>"
                             data-holo="<?php echo $is_holo ? '1' : '0'; ?>"
                             data-name="<?php echo strtolower(htmlspecialchars($f['nombre_jugador'])); ?>"
                             data-code="<?php echo strtolower(htmlspecialchars($f['codigo_figurita'])); ?>"
                             data-num="<?php echo $f['numero_figurita']; ?>">

                            <?php if ($is_holo): ?>
                                <span class="sticker-badge-holo">✨ HOLO</span>
                            <?php endif; ?>

                            <span class="sticker-number-badge">#<?php echo $f['numero_figurita']; ?></span>

                            
                            <div class="sticker-avatar">
                                <?php 
                                if (strpos($seccion_nombre, 'Planta') !== false) echo '🍃';
                                elseif (strpos($seccion_nombre, 'Fuego') !== false) echo '🔥';
                                elseif (strpos($seccion_nombre, 'Agua') !== false) echo '💧';
                                elseif (strpos($seccion_nombre, 'Eléctrico') !== false) echo '⚡';
                                elseif (strpos($seccion_nombre, 'Psíquico') !== false) echo '🔮';
                                elseif ($is_holo) echo '🌟';
                                else echo '⚽';
                                ?>
                            </div>

                            <div class="sticker-name"><?php echo htmlspecialchars($f['nombre_jugador']); ?></div>
                            <div class="sticker-team"><?php echo htmlspecialchars($f['codigo_figurita']) . ' • ' . htmlspecialchars($f['posicion_rol']); ?></div>

                            
                            <div id="tag-<?php echo $f['ID_figurita']; ?>" class="sticker-status-tag tag-<?php echo $f['user_status']; ?>">
                                <?php 
                                if ($f['user_status'] === 'repetida' && $f['repetidas'] > 0) {
                                    echo "Repetida (x" . $f['repetidas'] . ")";
                                } elseif ($f['user_status'] === 'tengo') {
                                    echo "✓ Pegada";
                                } else {
                                    echo "Falta";
                                }
                                ?>
                            </div>

                            
                            <div class="sticker-actions">
                                <button type="button" class="btn-sticker-action" title="Alternar Tengo/Falta" onclick="updateInventory(<?php echo $f['ID_figurita']; ?>, 'toggle_owned')">
                                    ✓ Tengo
                                </button>
                                <button type="button" class="btn-sticker-action" style="background: rgba(252, 212, 0, 0.15); color: var(--panini-gold);" title="Agregar Repetida" onclick="updateInventory(<?php echo $f['ID_figurita']; ?>, 'add_repeated')">
                                    + Rep
                                </button>
                                <?php if ($f['repetidas'] > 0): ?>
                                    <button type="button" class="btn-sticker-action btn-minus-rep" style="background: rgba(239, 68, 68, 0.15); color: #f87171; max-width: 32px;" title="Restar Repetida" onclick="updateInventory(<?php echo $f['ID_figurita']; ?>, 'remove_repeated')">
                                        -
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    
    <script>
        const ALBUM_ID = <?php echo $active_album_id; ?>;

        async function updateInventory(figId, action) {
            const formData = new FormData();
            formData.append('fig_id', figId);
            formData.append('action', action);
            formData.append('album_id', ALBUM_ID);

            try {
                const response = await fetch('api_inventory.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    const card = document.getElementById('card-' + figId);
                    const tag = document.getElementById('tag-' + figId);
                    const isHolo = card.getAttribute('data-holo') === '1';

                    card.setAttribute('data-status', data.status);
                    card.setAttribute('data-rep', data.repetidas);

                    card.className = 'sticker-card';
                    if (isHolo) card.classList.add('holo');

                    if (data.status === 'falta') {
                        card.classList.add('missing');
                        tag.className = 'sticker-status-tag tag-falta';
                        tag.innerText = 'Falta';
                    } else if (data.status === 'repetida' && data.repetidas > 0) {
                        card.classList.add('repeated');
                        tag.className = 'sticker-status-tag tag-repetida';
                        tag.innerText = 'Repetida (x' + data.repetidas + ')';
                    } else {
                        card.classList.add('owned');
                        tag.className = 'sticker-status-tag tag-tengo';
                        tag.innerText = '✓ Pegada';
                    }

                    document.getElementById('stat-progress-text').innerText = data.progress_pct + '%';
                    document.getElementById('stat-progress-fill').style.width = data.progress_pct + '%';
                    document.getElementById('stat-owned-count').innerText = data.owned_figs;
                    document.getElementById('stat-rep-count').innerText = data.repetidas_totales;
                }
            } catch (err) {
                console.error('Error actualizando inventario:', err);
            }
        }

        function filterStickers(filterType, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            document.querySelectorAll('.sticker-card').forEach(card => {
                const status = card.getAttribute('data-status');
                const rep = parseInt(card.getAttribute('data-rep'));
                const isHolo = card.getAttribute('data-holo') === '1';

                let show = true;
                if (filterType === 'missing') {
                    show = (status === 'falta');
                } else if (filterType === 'owned') {
                    show = (status === 'tengo' || (status === 'repetida' && rep > 0));
                } else if (filterType === 'repeated') {
                    show = (status === 'repetida' && rep > 0);
                } else if (filterType === 'holo') {
                    show = isHolo;
                }

                card.style.display = show ? 'flex' : 'none';
            });
        }

        function searchStickers(query) {
            const q = query.toLowerCase().trim();
            document.querySelectorAll('.sticker-card').forEach(card => {
                const name = card.getAttribute('data-name');
                const code = card.getAttribute('data-code');
                const num = card.getAttribute('data-num');

                if (name.includes(q) || code.includes(q) || num.includes(q)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
