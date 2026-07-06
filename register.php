<?php
    include("config.php");
    $nombre = $_POST["nombre"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    
    $sql = "INSERT INTO Usuario (nombre, telefono, email, password) VALUES ('$nombre', '$telefono', '$email', '$password')";
    $conexion->query($sql);
    echo "Usuario registrado correctamente <a href='login.php'>Iniciar sesión</a>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registro</title>
</head>
<body>
    <form action="register.php" method="POST">
        Nombre <input type="text" name="nombre"><br>
        Telefono <input type="text" name="telefono"><br>
        Email <input type="email" name="email"><br>
        Contraseña <input type="password" name="password"><br>
        <input type="submit" value="Registrar">
    </form>
</body>
</html>
