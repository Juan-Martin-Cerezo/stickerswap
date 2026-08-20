<?php
$servidor = "localhost";
$usuario = "juan";
$password = "";
$basedatos = "stickerswap";
$puerto = 3306;
$socket = "/home/juan/Escritorio/Colegio/Bases de Datos/StickerSwap/db/mysql.sock";

$conexion = new mysqli($servidor, $usuario, $password, $basedatos, $puerto, $socket);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
