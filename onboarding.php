<?php
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$step = intval($_GET['step'] ?? 1);

$res_albums = $conexion->query("SELECT * FROM Album ORDER BY ID_album ASC");
$all_albums = $res_albums->fetch_all(MYSQLI_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_albums"])) {
    $selected_albums = $_POST['albums'] ?? [];

    $conexion->query("DELETE FROM Usuario_Album WHERE ID_usuario = $user_id");

    if (!empty($selected_albums)) {
        $stmt_ua = $conexion->prepare("INSERT INTO Usuario_Album (ID_usuario, ID_album) VALUES (?, ?)");
        foreach ($selected_albums as $alb_id) {
            $alb_id = intval($alb_id);
            $stmt_ua->bind_param("ii", $user_id, $alb_id);
            $stmt_ua->execute();
        }
    } else {
        $first_id = $all_albums[0]['ID_album'];
        $conexion->query("INSERT INTO Usuario_Album (ID_usuario, ID_album) VALUES ($user_id, $first_id)");
    }

    header("Location: onboarding.php?step=2");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_stickers"])) {
    $album_id = intval($_POST['target_album_id'] ?? $all_albums[0]['ID_album']);
    $tengo_nums = trim($_POST['tengo_numeros'] ?? '');
    $rep_nums = trim($_POST['rep_numeros'] ?? '');

    function parseNumbers($str) {
        $nums = [];
        $parts = explode(',', $str);
        foreach ($parts as $p) {
            $p = trim($p);
            if (strpos($p, '-') !== false) {
                $range = explode('-', $p);
                if (count($range) === 2) {
                    $start = intval($range[0]);
                    $end = intval($range[1]);
                    if ($start > 0 && $end >= $start) {
                        for ($i = $start; $i <= $end; $i++) {
                            $nums[] = $i;
                        }
                    }
                }
            } else {
                $n = intval($p);
                if ($n > 0) $nums[] = $n;
            }
        }
        return array_unique($nums);
    }

    $tengo_arr = parseNumbers($tengo_nums);
    $rep_arr = parseNumbers($rep_nums);

    $stmt_m = $conexion->prepare("SELECT ID_figurita, numero_figurita FROM Figurita WHERE ID_album = ?");
    $stmt_m->bind_param("i", $album_id);
    $stmt_m->execute();
    $figs_map = [];
    $res_m = $stmt_m->get_result();
    while ($r = $res_m->fetch_assoc()) {
        $figs_map[$r['numero_figurita']] = $r['ID_figurita'];
    }

    $stmt_inv = $conexion->prepare("INSERT INTO Inventario (ID_usuario, ID_figurita, estado, cantidad_repetidas, pegada_en_album) 
                                    VALUES (?, ?, ?, ?, 1) 
                                    ON DUPLICATE KEY UPDATE estado=VALUES(estado), cantidad_repetidas=VALUES(cantidad_repetidas)");

    foreach ($tengo_arr as $num) {
        if (isset($figs_map[$num])) {
            $fid = $figs_map[$num];
            $is_rep = in_array($num, $rep_arr);
            $cant_rep = $is_rep ? 1 : 0;
            $estado = $is_rep ? 'repetida' : 'tengo';
            $stmt_inv->bind_param("iisi", $user_id, $fid, $estado, $cant_rep);
            $stmt_inv->execute();
        }
    }

    foreach ($rep_arr as $num) {
        if (isset($figs_map[$num]) && !in_array($num, $tengo_arr)) {
            $fid = $figs_map[$num];
            $estado = 'repetida';
            $cant_rep = 1;
            $stmt_inv->bind_param("iisi", $user_id, $fid, $estado, $cant_rep);
            $stmt_inv->execute();
        }
    }

    $conexion->query("UPDATE Usuario SET onboarding_completado = 1 WHERE ID_usuario = $user_id");
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_custom_album"])) {
    $nombre_alb = trim($_POST['custom_album_nombre'] ?? '');
    $cat_alb = trim($_POST['custom_album_categoria'] ?? 'Coleccionables');
    $icono_alb = trim($_POST['custom_album_icono'] ?? '🎴');
    $desc_alb = trim($_POST['custom_album_desc'] ?? '');
    $cant_total = intval($_POST['custom_album_total'] ?? 20);

    if (!empty($nombre_alb)) {
        $cod = 'custom_' . time() . '_' . rand(100, 999);
        $stmt_c = $conexion->prepare("INSERT INTO Album (codigo, nombre, descripcion, categoria, icono, total_figuritas, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_c->bind_param("sssssii", $cod, $nombre_alb, $desc_alb, $cat_alb, $icono_alb, $cant_total, $user_id);
        $stmt_c->execute();
        $new_alb_id = $stmt_c->insert_id;

        $stmt_cf = $conexion->prepare("INSERT INTO Figurita (ID_album, numero_figurita, codigo_figurita, nombre_jugador, Seleccion, posicion_rol, Holografica, rareza) VALUES (?, ?, ?, ?, ?, 'Cromo', 0, 'Común')");
        for ($i = 1; $i <= $cant_total; $i++) {
            $cod_f = "FIG-$i";
            $nom_f = "Figurita #$i";
            $sec_f = "Colección General";
            $stmt_cf->bind_param("iisss", $new_alb_id, $i, $cod_f, $nom_f, $sec_f);
            $stmt_cf->execute();
        }

        $conexion->query("INSERT INTO Usuario_Album (ID_usuario, ID_album) VALUES ($user_id, $new_alb_id)");
        header("Location: onboarding.php?step=2");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenida & Onboarding - StickerSwap</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .step-progress-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 36px;
        }
        .step-node {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(
        }
        .step-node.active {
            color: var(
        }
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(
            border: 2px solid var(
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }
        .step-node.active .step-circle {
            border-color: var(
            background: rgba(252, 212, 0, 0.2);
            color: var(
        }
        .album-selection-card {
            background: var(
            border: 2px solid var(
            border-radius: var(
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .album-selection-card:hover {
            border-color: var(
            transform: translateY(-3px);
        }
        .album-selection-card input[type="checkbox"] {
            position: absolute;
            top: 18px;
            right: 18px;
            width: 22px;
            height: 22px;
            accent-color: var(
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span style="font-size: 26px;">🎴</span> StickerSwap <span class="brand-badge">Panini Corp</span>
        </a>
        <div class="navbar-menu">
            <span style="font-size: 14px; color: var(--text-secondary);">Hola, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
            <a href="logout.php" class="btn-secondary" style="padding: 6px 12px; font-size: 13px;">Salir</a>
        </div>
    </nav>

    <div class="main-wrapper" style="max-width: 900px;">
        
        <div class="step-progress-bar">
            <div class="step-node <?php echo ($step === 1) ? 'active' : ''; ?>">
                <div class="step-circle">1</div>
                <span>Elegí tus Álbumes</span>
            </div>
            <div style="width: 40px; height: 2px; background: var(--border-color);"></div>
            <div class="step-node <?php echo ($step === 2) ? 'active' : ''; ?>">
                <div class="step-circle">2</div>
                <span>Carga Rápida de Figuritas</span>
            </div>
        </div>

        <?php if ($step === 1): ?>
            
            <div style="text-align: center; margin-bottom: 32px;">
                <h1 style="font-size: 32px; font-weight: 800; color: #fff;">¿Qué álbumes estás juntando?</h1>
                <p style="color: var(--text-secondary); font-size: 16px; margin-top: 6px;">
                    Seleccioná las colecciones oficiales que querés tener activas en tu perfil o creá un álbum propio.
                </p>
            </div>

            <form method="POST">
                <input type="hidden" name="save_albums" value="1">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 32px;">
                    <?php foreach ($all_albums as $alb): ?>
                        <label class="album-selection-card">
                            <input type="checkbox" name="albums[]" value="<?php echo $alb['ID_album']; ?>" checked>
                            <div style="font-size: 40px; margin-bottom: 12px;"><?php echo $alb['icono']; ?></div>
                            <span class="brand-badge" style="font-size: 10px;"><?php echo htmlspecialchars($alb['categoria']); ?></span>
                            <h3 style="font-size: 18px; font-weight: 800; color: #fff; margin: 8px 0 4px;"><?php echo htmlspecialchars($alb['nombre']); ?></h3>
                            <p style="color: var(--text-secondary); font-size: 13px;"><?php echo htmlspecialchars($alb['descripcion']); ?></p>
                            <div style="margin-top: 14px; font-size: 12px; color: var(--panini-gold); font-weight: 700;">
                                <?php echo $alb['total_figuritas']; ?> figuritas en total
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="btn-secondary" onclick="toggleCustomModal()">
                        ➕ Crear Álbum Personalizado
                    </button>
                    <button type="submit" class="btn-primary" style="padding: 12px 32px; font-size: 16px;">
                        Continuar al Paso 2 →
                    </button>
                </div>
            </form>

            
            <div id="customModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 200; align-items: center; justify-content: center; padding: 20px;">
                <div style="background: var(--bg-card); border: 1px solid var(--border-gold); border-radius: var(--radius-lg); max-width: 520px; width: 100%; padding: 28px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                        <h3 style="font-size: 20px; font-weight: 800; color: #fff;">Crear Álbum Personalizado</h3>
                        <button type="button" onclick="toggleCustomModal()" style="background: none; border: none; color: #fff; font-size: 22px; cursor: pointer;">✕</button>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="create_custom_album" value="1">
                        <div class="form-group">
                            <label class="form-label">Nombre del Álbum</label>
                            <input type="text" name="custom_album_nombre" class="form-input" placeholder="Ej: Dragon Ball Z / Copa Libertadores" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Categoría</label>
                            <input type="text" name="custom_album_categoria" class="form-input" placeholder="Ej: Anime / Fútbol / Series" value="Coleccionables">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ícono / Emoji</label>
                            <input type="text" name="custom_album_icono" class="form-input" placeholder="🐉" value="🎴">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cantidad Total de Figuritas</label>
                            <input type="number" name="custom_album_total" class="form-input" value="30" min="5" max="200">
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                            <button type="button" class="btn-secondary" onclick="toggleCustomModal()">Cancelar</button>
                            <button type="submit" class="btn-primary">Guardar y Agregar Álbum</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            
            <div style="text-align: center; margin-bottom: 32px;">
                <h1 style="font-size: 32px; font-weight: 800; color: #fff;">Cargá tus primeras figuritas</h1>
                <p style="color: var(--text-secondary); font-size: 16px; margin-top: 6px;">
                    Podés ingresar los números de las figuritas que ya tenés pegadas y las repetidas que querés ofrecer.
                </p>
            </div>

            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: var(--shadow-md);">
                <form method="POST">
                    <input type="hidden" name="save_stickers" value="1">

                    <div class="form-group">
                        <label class="form-label">Elegí para qué álbum querés cargar figuritas ahora:</label>
                        <select name="target_album_id" class="form-input" style="font-weight: 700;">
                            <?php foreach ($all_albums as $alb): ?>
                                <option value="<?php echo $alb['ID_album']; ?>">
                                    <?php echo $alb['icono'] . ' ' . htmlspecialchars($alb['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label class="form-label" style="color: var(--panini-emerald);">
                            ✓ Números de figuritas que TENÉS PEGADAS en tu álbum:
                        </label>
                        <textarea name="tengo_numeros" class="form-input" rows="3" placeholder="Ingresá los números separados por coma o rangos (Ej: 1, 2, 4, 5, 7-12, 19, 25)"></textarea>
                        <small style="color: var(--text-muted); margin-top: 4px;">Ejemplo: <code>1, 4, 5, 7-12, 20</code></small>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label class="form-label" style="color: var(--panini-gold);">
                            ⭐ Números de figuritas que tenés REPETIDAS para cambiar:
                        </label>
                        <textarea name="rep_numeros" class="form-input" rows="3" placeholder="Ingresá los números de tus repetidas (Ej: 5, 14, 25)"></textarea>
                        <small style="color: var(--text-muted); margin-top: 4px;">Estas figuritas aparecerán en el Matchmaker para que otros coleccionistas te propongan intercambios.</small>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 32px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                        <a href="onboarding.php?step=1" class="btn-secondary">← Volver al Paso 1</a>
                        <div>
                            <button type="submit" name="skip" class="btn-secondary" style="margin-right: 12px;" onclick="document.querySelector('[name=tengo_numeros]').value=''; document.querySelector('[name=rep_numeros]').value='';">
                                Omitir y cargar luego
                            </button>
                            <button type="submit" class="btn-primary" style="padding: 12px 32px; font-size: 16px;">
                                ¡Finalizar y Entrar al Dashboard! 🎉
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleCustomModal() {
            const m = document.getElementById('customModal');
            if (m) m.style.display = (m.style.display === 'flex') ? 'none' : 'flex';
        }
    </script>
</body>
</html>
