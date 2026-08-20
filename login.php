<?php
session_start();
include("config.php");

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (!empty($email) && !empty($password)) {
        $stmt = $conexion->prepare("SELECT ID_usuario, nombre, email, es_premium, avatar, onboarding_completado FROM Usuario WHERE email = ? AND password = ?");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $usuario = $resultado->fetch_assoc();
            $_SESSION["user_id"] = $usuario["ID_usuario"];
            $_SESSION["user_name"] = $usuario["nombre"];
            $_SESSION["user_email"] = $usuario["email"];
            $_SESSION["es_premium"] = $usuario["es_premium"];
            $_SESSION["avatar"] = $usuario["avatar"];

            if ($usuario["onboarding_completado"] == 0) {
                header("Location: onboarding.php?step=1");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error = "Correo electrónico o contraseña incorrectos.";
        }
    } else {
        $error = "Por favor completá todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Panini StickerSwap</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span style="font-size: 26px;">🎴</span> StickerSwap <span class="brand-badge">Panini Corp</span>
        </a>
        <div class="navbar-menu">
            <a href="register.php" class="btn-primary" style="padding: 8px 16px; font-size: 14px;">Registrarse</a>
        </div>
    </nav>

    <div class="main-wrapper">
        <div class="auth-container">
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="font-size: 44px; margin-bottom: 8px;">⚽</div>
                <h1 style="font-size: 26px; font-weight: 800; color: #fff;">Iniciar Sesión</h1>
                <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">Ingresá a tu cuenta para gestionar tus álbumes e intercambiar figuritas.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center;">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="ejemplo@stickerswap.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 12px; padding: 12px;">
                    Entrar al Sistema
                </button>
            </form>

            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: center; font-size: 14px; color: var(--text-secondary);">
                ¿No tenés una cuenta? <a href="register.php" style="color: var(--panini-gold); font-weight: 700; text-decoration: none;">Registrate acá</a>
            </div>
        </div>
    </div>
</body>
</html>
