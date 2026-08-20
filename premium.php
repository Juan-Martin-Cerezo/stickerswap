<?php
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_premium"])) {
    $current_premium = !empty($_SESSION["es_premium"]) ? 0 : 1;
    $conexion->query("UPDATE Usuario SET es_premium = $current_premium WHERE ID_usuario = $user_id");
    $_SESSION["es_premium"] = $current_premium;
    $msg = ($current_premium == 1) ? "🎉 ¡Felicidades! Activaste tu membresía Panini Gold Pass." : "Membresía desactivada.";
}

$stmt = $conexion->prepare("SELECT es_premium, nombre, avatar FROM Usuario WHERE ID_usuario = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$usr = $stmt->get_result()->fetch_assoc();
$is_premium = ($usr['es_premium'] == 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panini Gold Pass - StickerSwap Oficial</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include("navbar.php"); ?>

    <div class="main-wrapper">
        <div style="text-align: center; max-width: 680px; margin: 0 auto 36px;">
            <span class="brand-badge" style="font-size: 13px; padding: 4px 12px;">Membresía Exclusiva Panini</span>
            <h1 style="font-size: 36px; font-weight: 800; color: #fff; margin-top: 10px;">
                👑 Panini Gold Pass
            </h1>
            <p style="color: var(--text-secondary); font-size: 16px; margin-top: 8px;">
                Elevá tu experiencia de coleccionismo al máximo nivel con beneficios exclusivos y prioridad en el mercado de cambios.
            </p>
        </div>

        <?php if (!empty($msg)): ?>
            <div style="max-width: 680px; margin: 0 auto 24px; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 14px; border-radius: 8px; text-align: center; font-weight: 700;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; max-width: 960px; margin: 0 auto 36px;">
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px;">
                <div style="font-size: 36px; margin-bottom: 12px;">🚫</div>
                <h3 style="color: #fff; font-size: 18px; font-weight: 700; margin-bottom: 6px;">Experiencia Sin Publicidad</h3>
                <p style="color: var(--text-secondary); font-size: 14px;">Eliminá todos los banners de patrocinadores y disfrutá de una navegación limpia y rápida.</p>
            </div>

            <div style="background: var(--bg-card); border: 1px solid var(--border-gold); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-gold);">
                <div style="font-size: 36px; margin-bottom: 12px;">⚡</div>
                <h3 style="color: var(--panini-gold); font-size: 18px; font-weight: 700; margin-bottom: 6px;">Prioridad en el Matchmaker</h3>
                <p style="color: var(--text-secondary); font-size: 14px;">Tus figuritas repetidas aparecerán en la cima de búsquedas de otros coleccionistas para cerrar intercambios más rápido.</p>
            </div>

            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px;">
                <div style="font-size: 36px; margin-bottom: 12px;">✨</div>
                <h3 style="color: #fff; font-size: 18px; font-weight: 700; margin-bottom: 6px;">Insignia VIP & Resaltado Holográfico</h3>
                <p style="color: var(--text-secondary); font-size: 14px;">Insignia dorada en tu perfil y efectos brillantes personalizados en todas tus cartas del álbum.</p>
            </div>
        </div>

        
        <div style="max-width: 520px; margin: 0 auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 28px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 12px;">
                <?php echo $is_premium ? '🌟' : '🛡️'; ?>
            </div>
            <h3 style="color: #fff; font-size: 20px; font-weight: 800;">
                Estado Actual: <?php echo $is_premium ? '<span style="color: var(--panini-gold);">ACTIVO (GOLD VIP)</span>' : '<span style="color: var(--text-muted);">Estándar Gratuito</span>'; ?>
            </h3>
            <p style="color: var(--text-secondary); font-size: 14px; margin: 8px 0 20px;">
                <?php echo $is_premium ? 'Tenés todos los beneficios y sin anuncios activados.' : 'Podés activar la suscripción de prueba para habilitar los beneficios al instante.'; ?>
            </p>

            <form method="POST">
                <input type="hidden" name="toggle_premium" value="1">
                <button type="submit" class="<?php echo $is_premium ? 'btn-secondary' : 'btn-primary'; ?>" style="width: 100%; justify-content: center; padding: 12px; font-size: 16px;">
                    <?php echo $is_premium ? 'Desactivar Membresía Gold' : '👑 Activar Panini Gold Pass'; ?>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
