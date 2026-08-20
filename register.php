<?php
session_start();
include("config.php");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $avatar = $_POST["avatar"] ?? "⚽";

    if (!empty($nombre) && !empty($email) && !empty($password)) {
        // Verificar si el email ya existe
        $check = $conexion->prepare("SELECT ID_usuario FROM Usuario WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "El correo electrónico ya se encuentra registrado.";
        } else {
            $stmt = $conexion->prepare("INSERT INTO Usuario (nombre, email, telefono, password, avatar, es_premium) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssss", $nombre, $email, $telefono, $password, $avatar);
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $_SESSION["user_id"] = $user_id;
                $_SESSION["user_name"] = $nombre;
                $_SESSION["user_email"] = $email;
                $_SESSION["es_premium"] = 0;
                $_SESSION["avatar"] = $avatar;
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Error al crear la cuenta: " . $conexion->error;
            }
        }
    } else {
        $error = "Por favor completá los campos obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Coleccionista - Panini StickerSwap</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span style="font-size: 26px;">🎴</span> StickerSwap <span class="brand-badge">Panini Corp</span>
        </a>
        <div class="navbar-menu">
            <a href="login.php" class="btn-secondary" style="padding: 8px 16px; font-size: 14px;">Iniciar Sesión</a>
        </div>
    </nav>

    <div class="main-wrapper">
        <div class="auth-container">
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="font-size: 44px; margin-bottom: 8px;">🌟</div>
                <h1 style="font-size: 26px; font-weight: 800; color: #fff;">Crear Cuenta</h1>
                <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">Unite a la comunidad oficial de Panini StickerSwap e intercambiá en tiempo real.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center;">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="nombre">Nombre de Coleccionista *</label>
                    <input type="text" id="nombre" name="nombre" class="form-input" placeholder="Ej: Martin_Colecciones" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Correo Electrónico *</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="tu@email.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="telefono">Teléfono / WhatsApp (Opcional)</label>
                    <input type="tel" id="telefono" name="telefono" class="form-input" placeholder="+54 9 11 1234 5678" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Contraseña *</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Mínimo 4 caracteres" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Elegí tu Avatar</label>
                    <div style="display: flex; gap: 12px; margin-top: 6px;">
                        <?php 
                        $avatars = ['⚽', '🌟', '👑', '🏆', '⚡', '🎮'];
                        foreach ($avatars as $av): 
                        ?>
                            <label style="cursor: pointer; background: var(--bg-surface); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 20px; display: flex; align-items: center; gap: 4px;">
                                <input type="radio" name="avatar" value="<?php echo $av; ?>" <?php echo ($av === '⚽') ? 'checked' : ''; ?>>
                                <?php echo $av; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 16px; padding: 12px;">
                    Registrarme y Coleccionar
                </button>
            </form>

            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: center; font-size: 14px; color: var(--text-secondary);">
                ¿Ya tenés una cuenta? <a href="login.php" style="color: var(--panini-gold); font-weight: 700; text-decoration: none;">Iniciá sesión acá</a>
            </div>
        </div>
    </div>
</body>
</html>
