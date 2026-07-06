<?php
if (isset($_POST["email"])) {
    include("config.php");
    $email = $_POST["email"];
    $password = $_POST["password"];
    
    $sql = "SELECT * FROM Usuario WHERE email = '$email' AND password = '$password'";
    $resultado = $conexion->query($sql);
    
    if ($resultado->num_rows > 0) {
        echo "Sesión iniciada correctamente. ¡Bienvenido!";
    } else {
        echo "Usuario o contraseña incorrectos. <a href='login.php'>Volver a intentar</a>";
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
