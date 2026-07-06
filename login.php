<?php
    include("config.php");
    $email = $_POST["email"];
    $password = $_POST["password"];
    
    $sql = "SELECT * FROM Usuario WHERE email = '$email' AND password = '$password'";
    $resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Iniciar Sesión</title>
</head>
<body>
    <form action="login.php" method="POST">
        Email <input type="email" name="email"><br>
        Contraseña <input type="password" name="password"><br>
        <input type="submit" value="Ingresar">
    </form>
</body>
</html>
