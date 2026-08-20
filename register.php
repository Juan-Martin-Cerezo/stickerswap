<?php
session_start();
if (isset($_POST["nombre"])) {
    include("config.php");
    $nombre = $_POST["nombre"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    
    $sql = "INSERT INTO Usuario (nombre, telefono, email, password) VALUES ('$nombre', '$telefono', '$email', '$password')";
    if ($conexion->query($sql)) {
        $_SESSION["user_id"] = $conexion->insert_id;
        $_SESSION["user_name"] = $nombre;
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registro - StickerSwap</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
    <div class="contenedor">
        <h1>StickerSwap</h1>

        <form action="register.php" method="POST">
            <div class="campo">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>
            
            <div class="campo">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono">
            </div>

            <div class="campo">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <input type="submit" class="boton" value="Registrar">
        </form>
        
        <div class="enlace">
            <a href="login.php">Inicia Sesión</a>
        </div>
    </div>
</body>
</html>
