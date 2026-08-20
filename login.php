<?php
session_start();
if (isset($_POST["email"])) {
    include("config.php");
    $email = $_POST["email"];
    $password = $_POST["password"];
    $sql = "SELECT * FROM Usuario WHERE email = '$email' AND password = '$password'";
    $resultado = $conexion->query($sql);
    
    if ($resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();
        $_SESSION["user_id"] = $usuario["ID_usuario"];
        $_SESSION["user_name"] = $usuario["nombre"];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Iniciar Sesión - StickerSwap</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
    <div class="contenedor">
        <h1>StickerSwap</h1>

        <?php if (isset($error)): ?>
            <div style="color: red; text-align: center; margin-bottom: 15px; font-size: 14px;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="campo">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            <input type="submit" class="boton" value="Ingresar">
        </form>
        
        <div class="enlace">
            <a href="register.php">Regístrate</a>
        </div>
    </div>
</body>
</html>
