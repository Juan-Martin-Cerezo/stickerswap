-- ==========================================================
-- PROYECTO: Panini StickerSwap - Base de Datos Relacional
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS Mensaje_Chat;
DROP TABLE IF EXISTS Intercambio_Item;
DROP TABLE IF EXISTS Intercambio;
DROP TABLE IF EXISTS Inventario;
DROP TABLE IF EXISTS Figurita;
DROP TABLE IF EXISTS Album;
DROP TABLE IF EXISTS Usuario;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Tabla de Usuarios
CREATE TABLE Usuario (
    ID_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    es_premium BOOLEAN DEFAULT 0,
    avatar VARCHAR(50) DEFAULT '👤',
    reputacion INT DEFAULT 100,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabla de Álbumes
CREATE TABLE Album (
    ID_album INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    categoria VARCHAR(100) NOT NULL,
    icono VARCHAR(50) DEFAULT '🎴',
    color_tema VARCHAR(50) DEFAULT '#1e3a8a',
    total_figuritas INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla de Figuritas
CREATE TABLE Figurita (
    ID_figurita INT AUTO_INCREMENT PRIMARY KEY,
    ID_album INT NOT NULL,
    numero_figurita INT NOT NULL,
    codigo_figurita VARCHAR(50) NOT NULL,
    nombre_jugador VARCHAR(150) NOT NULL,
    Seleccion VARCHAR(100) NOT NULL,
    posicion_rol VARCHAR(50) DEFAULT 'Jugador',
    Holografica BOOLEAN NOT NULL DEFAULT 0,
    rareza VARCHAR(50) DEFAULT 'Común',
    imagen_url VARCHAR(255) DEFAULT '',
    CONSTRAINT fk_figurita_album FOREIGN KEY (ID_album) REFERENCES Album(ID_album) ON DELETE CASCADE,
    UNIQUE KEY uk_album_numero (ID_album, numero_figurita)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabla de Inventario de Coleccionistas
CREATE TABLE Inventario (
    ID_inventario INT AUTO_INCREMENT PRIMARY KEY,
    ID_usuario INT NOT NULL,
    ID_figurita INT NOT NULL,
    estado VARCHAR(50) NOT NULL DEFAULT 'tengo', -- 'tengo', 'falta', 'repetida'
    cantidad_repetidas INT NOT NULL DEFAULT 0,
    pegada_en_album BOOLEAN NOT NULL DEFAULT 1,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventario_usuario FOREIGN KEY (ID_usuario) REFERENCES Usuario(ID_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_inventario_figurita FOREIGN KEY (ID_figurita) REFERENCES Figurita(ID_figurita) ON DELETE CASCADE,
    UNIQUE KEY uk_usuario_figurita (ID_usuario, ID_figurita)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabla de Intercambios
CREATE TABLE Intercambio (
    ID_intercambio INT AUTO_INCREMENT PRIMARY KEY,
    ID_album INT NOT NULL,
    ID_usuario_1 INT NOT NULL,
    ID_usuario_2 INT NOT NULL,
    ultimo_proponente_id INT NOT NULL,
    estado VARCHAR(50) NOT NULL DEFAULT 'pendiente', -- 'pendiente', 'aceptado', 'rechazado', 'cancelado'
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_intercambio_album FOREIGN KEY (ID_album) REFERENCES Album(ID_album) ON DELETE CASCADE,
    CONSTRAINT fk_intercambio_usuario1 FOREIGN KEY (ID_usuario_1) REFERENCES Usuario(ID_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_intercambio_usuario2 FOREIGN KEY (ID_usuario_2) REFERENCES Usuario(ID_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabla de Ítems del Intercambio (Figuritas en la mesa de negociación)
CREATE TABLE Intercambio_Item (
    ID_item INT AUTO_INCREMENT PRIMARY KEY,
    ID_intercambio INT NOT NULL,
    ID_usuario_dueno INT NOT NULL,
    ID_figurita INT NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'ofrecida', -- 'ofrecida', 'solicitada'
    CONSTRAINT fk_item_intercambio FOREIGN KEY (ID_intercambio) REFERENCES Intercambio(ID_intercambio) ON DELETE CASCADE,
    CONSTRAINT fk_item_usuario FOREIGN KEY (ID_usuario_dueno) REFERENCES Usuario(ID_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_item_figurita FOREIGN KEY (ID_figurita) REFERENCES Figurita(ID_figurita) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabla de Mensajes y Negociación en Tiempo Real
CREATE TABLE Mensaje_Chat (
    ID_mensaje_chat INT AUTO_INCREMENT PRIMARY KEY,
    ID_intercambio INT NOT NULL,
    ID_usuario INT NOT NULL,
    contenido TEXT NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'usuario', -- 'usuario', 'sistema', 'propuesta'
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensaje_intercambio FOREIGN KEY (ID_intercambio) REFERENCES Intercambio(ID_intercambio) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;