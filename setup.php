<?php
$conexion = new mysqli("localhost", "juan", "", "", 3306, "/home/juan/Escritorio/Colegio/Bases de Datos/StickerSwap/db/mysql.sock");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->query("CREATE DATABASE IF NOT EXISTS stickerswap");

$conexion->select_db("stickerswap");

$sql1 = "CREATE TABLE IF NOT EXISTS Usuario (
    ID_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    password VARCHAR(255) NOT NULL
);";
if (!$conexion->query($sql1)) {
    echo "Error al crear tabla Usuario: " . $conexion->error . "<br>";
}

$sql2 = "CREATE TABLE IF NOT EXISTS Figurita (
    ID_figurita INT AUTO_INCREMENT PRIMARY KEY,
    numero_figurita INT NOT NULL,
    Seleccion VARCHAR(100) NOT NULL,
    Holografica BOOLEAN NOT NULL DEFAULT 0
);";
if (!$conexion->query($sql2)) {
    echo "Error al crear tabla Figurita: " . $conexion->error . "<br>";
}

$sql3 = "CREATE TABLE IF NOT EXISTS Inventario (
    ID_inventario INT AUTO_INCREMENT PRIMARY KEY,
    ID_usuario INT NOT NULL,
    ID_figurita INT NOT NULL,
    estado VARCHAR(50),
    repetida BOOLEAN NOT NULL DEFAULT 0,
    CONSTRAINT fk_inventario_usuario FOREIGN KEY (ID_usuario) REFERENCES Usuario(ID_usuario),
    CONSTRAINT fk_inventario_figurita FOREIGN KEY (ID_figurita) REFERENCES Figurita(ID_figurita)
);";
if (!$conexion->query($sql3)) {
    echo "Error al crear tabla Inventario: " . $conexion->error . "<br>";
}

$sql4 = "CREATE TABLE IF NOT EXISTS Intercambio (
    ID_intercambio INT AUTO_INCREMENT PRIMARY KEY,
    ID_usuario_1 INT NOT NULL,
    ID_usuario_2 INT NOT NULL,
    fecha_hora DATETIME,
    CONSTRAINT fk_intercambio_usuario1 FOREIGN KEY (ID_usuario_1) REFERENCES Usuario(ID_usuario),
    CONSTRAINT fk_intercambio_usuario2 FOREIGN KEY (ID_usuario_2) REFERENCES Usuario(ID_usuario)
);";
if (!$conexion->query($sql4)) {
    echo "Error al crear tabla Intercambio: " . $conexion->error . "<br>";
}

$sql5 = "CREATE TABLE IF NOT EXISTS Mensaje_Chat (
    ID_mensaje_chat INT AUTO_INCREMENT PRIMARY KEY,
    ID_intercambio INT NOT NULL,
    ID_usuario INT NOT NULL,
    contenido TEXT NOT NULL,
    fecha_hora DATETIME,
    CONSTRAINT fk_mensaje_intercambio FOREIGN KEY (ID_intercambio) REFERENCES Intercambio(ID_intercambio),
    CONSTRAINT fk_mensaje_usuario FOREIGN KEY (ID_usuario) REFERENCES Usuario(ID_usuario)
);";
if (!$conexion->query($sql5)) {
    echo "Error al crear tabla Mensaje_Chat: " . $conexion->error . "<br>";
} else {
    echo "Base de datos y tablas creadas exitosamente.";
}

$conexion->close();
?>