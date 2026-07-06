<?php
require_once("config.php");

$sql = "CREATE DATABASE IF NOT EXISTS stickerswap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
if ($conexion->query($sql) === TRUE) {
    echo "Base de datos creada o existente.<br>";
} else {
    die("Error al crear base de datos: " . $conexion->error);
}

$conexion->select_db("stickerswap");