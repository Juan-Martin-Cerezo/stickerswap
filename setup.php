<?php
include("config.php");

// Desactivar temporalmente foreign keys para recreación limpia
$conexion->query("SET FOREIGN_KEY_CHECKS = 0");
$conexion->query("DROP TABLE IF EXISTS Mensaje_Chat");
$conexion->query("DROP TABLE IF EXISTS Intercambio_Item");
$conexion->query("DROP TABLE IF EXISTS Intercambio");
$conexion->query("DROP TABLE IF EXISTS Inventario");
$conexion->query("DROP TABLE IF EXISTS Figurita");
$conexion->query("DROP TABLE IF EXISTS Album");
$conexion->query("DROP TABLE IF EXISTS Usuario");
$conexion->query("SET FOREIGN_KEY_CHECKS = 1");

// Crear tablas
$sql_tables = "
CREATE TABLE IF NOT EXISTS Usuario (
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

CREATE TABLE IF NOT EXISTS Album (
    ID_album INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    categoria VARCHAR(100) NOT NULL,
    icono VARCHAR(50) DEFAULT '🎴',
    color_tema VARCHAR(50) DEFAULT '#1e3a8a',
    total_figuritas INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Figurita (
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

CREATE TABLE IF NOT EXISTS Inventario (
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

CREATE TABLE IF NOT EXISTS Intercambio (
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

CREATE TABLE IF NOT EXISTS Intercambio_Item (
    ID_item INT AUTO_INCREMENT PRIMARY KEY,
    ID_intercambio INT NOT NULL,
    ID_usuario_dueno INT NOT NULL,
    ID_figurita INT NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'ofrecida', -- 'ofrecida', 'solicitada'
    CONSTRAINT fk_item_intercambio FOREIGN KEY (ID_intercambio) REFERENCES Intercambio(ID_intercambio) ON DELETE CASCADE,
    CONSTRAINT fk_item_usuario FOREIGN KEY (ID_usuario_dueno) REFERENCES Usuario(ID_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_item_figurita FOREIGN KEY (ID_figurita) REFERENCES Figurita(ID_figurita) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Mensaje_Chat (
    ID_mensaje_chat INT AUTO_INCREMENT PRIMARY KEY,
    ID_intercambio INT NOT NULL,
    ID_usuario INT NOT NULL,
    contenido TEXT NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'usuario', -- 'usuario', 'sistema', 'propuesta'
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensaje_intercambio FOREIGN KEY (ID_intercambio) REFERENCES Intercambio(ID_intercambio) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Ejecutar queries de tablas
$queries = explode(";", $sql_tables);
foreach ($queries as $q) {
    $q = trim($q);
    if (!empty($q)) {
        if (!$conexion->query($q)) {
            echo "Error creando tabla: " . $conexion->error . "<br>";
        }
    }
}

// 1. POBLAR ÁLBUMES
$albumes = [
    [
        'codigo' => 'mundial2026',
        'nombre' => 'Mundial FIFA 2026 - Panini Oficial',
        'descripcion' => 'Colección oficial de la Copa Mundial de la FIFA México, Estados Unidos y Canadá 2026.',
        'categoria' => 'Fútbol Internacional',
        'icono' => '⚽',
        'color_tema' => '#1e3a8a'
    ],
    [
        'codigo' => 'pokemon',
        'nombre' => 'Pokémon - Colección Kanto Clásico',
        'descripcion' => 'Colección legendaria de los 151 Pokémon originales de la región de Kanto.',
        'categoria' => 'Anime & Gaming',
        'icono' => '⚡',
        'color_tema' => '#dc2626'
    ],
    [
        'codigo' => 'panini_top_class',
        'nombre' => 'Panini Top Class 2025 - UEFA & Clubes',
        'descripcion' => 'Las máximas estrellas del fútbol europeo de clubes y selecciones mundiales.',
        'categoria' => 'Fútbol Europeo',
        'icono' => '🏆',
        'color_tema' => '#7c3aed'
    ]
];

foreach ($albumes as $alb) {
    $stmt = $conexion->prepare("INSERT INTO Album (codigo, nombre, descripcion, categoria, icono, color_tema) 
                                VALUES (?, ?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion), icono=VALUES(icono), color_tema=VALUES(color_tema)");
    $stmt->bind_param("ssssss", $alb['codigo'], $alb['nombre'], $alb['descripcion'], $alb['categoria'], $alb['icono'], $alb['color_tema']);
    $stmt->execute();
}

// Obtener IDs de álbumes
$res_alb = $conexion->query("SELECT ID_album, codigo FROM Album");
$album_ids = [];
while ($row = $res_alb->fetch_assoc()) {
    $album_ids[$row['codigo']] = $row['ID_album'];
}

// 2. POBLAR FIGURITAS
$figuritas_data = [
    // === MUNDIAL 2026 ===
    'mundial2026' => [
        // Especiales
        [1, 'FWC-00', 'Trofeo de la Copa Mundial', 'Especiales', 'Trofeo Oficial', 1, 'Legendaria Oro'],
        [2, 'FWC-01', 'Emblema Oficial 2026', 'Especiales', 'Emblema', 1, 'Holográfica'],
        [3, 'FWC-02', 'Pelota Oficial Matchball', 'Especiales', 'Accesorio', 0, 'Especial'],
        // Argentina
        [4, 'ARG-01', 'Emblema AFA - Selección Argentina', 'Argentina', 'Escudo', 1, 'Holográfica'],
        [5, 'ARG-10', 'Lionel Messi', 'Argentina', 'Delantero', 1, 'Legendaria Oro'],
        [6, 'ARG-23', 'Emiliano \"Dibu\" Martínez', 'Argentina', 'Arquero', 1, 'Holográfica'],
        [7, 'ARG-09', 'Julián Álvarez', 'Argentina', 'Delantero', 0, 'Común'],
        [8, 'ARG-22', 'Lautaro Martínez', 'Argentina', 'Delantero', 0, 'Común'],
        [9, 'ARG-07', 'Rodrigo De Paul', 'Argentina', 'Mediocampista', 0, 'Común'],
        [10, 'ARG-20', 'Alexis Mac Allister', 'Argentina', 'Mediocampista', 0, 'Común'],
        [11, 'ARG-13', 'Cristian \"Cuti\" Romero', 'Argentina', 'Defensor', 0, 'Común'],
        [12, 'ARG-24', 'Enzo Fernández', 'Argentina', 'Mediocampista', 0, 'Común'],
        // Brasil
        [13, 'BRA-01', 'Emblema CBF - Brasil', 'Brasil', 'Escudo', 1, 'Holográfica'],
        [14, 'BRA-07', 'Vinícius Júnior', 'Brasil', 'Extremo', 1, 'Holográfica'],
        [15, 'BRA-10', 'Rodrygo Goes', 'Brasil', 'Delantero', 0, 'Común'],
        [16, 'BRA-09', 'Endrick', 'Brasil', 'Delantero', 1, 'Especial Joven'],
        [17, 'BRA-01', 'Alisson Becker', 'Brasil', 'Arquero', 0, 'Común'],
        [18, 'BRA-05', 'Casemiro', 'Brasil', 'Mediocampista', 0, 'Común'],
        // Francia
        [19, 'FRA-01', 'Emblema FFF - Francia', 'Francia', 'Escudo', 1, 'Holográfica'],
        [20, 'FRA-10', 'Kylian Mbappé', 'Francia', 'Delantero', 1, 'Legendaria Oro'],
        [21, 'FRA-07', 'Antoine Griezmann', 'Francia', 'Mediapunta', 0, 'Común'],
        [22, 'FRA-08', 'Aurélien Tchouaméni', 'Francia', 'Mediocampista', 0, 'Común'],
        [23, 'FRA-06', 'Eduardo Camavinga', 'Francia', 'Mediocampista', 0, 'Común'],
        // España
        [24, 'ESP-01', 'Emblema RFEF - España', 'España', 'Escudo', 1, 'Holográfica'],
        [25, 'ESP-19', 'Lamine Yamal', 'España', 'Extremo', 1, 'Legendaria Oro'],
        [26, 'ESP-08', 'Pedri González', 'España', 'Mediocampista', 1, 'Holográfica'],
        [27, 'ESP-16', 'Rodri Hernández', 'España', 'Mediocampista', 1, 'Holográfica'],
        [28, 'ESP-17', 'Nico Williams', 'España', 'Extremo', 0, 'Común'],
        // Inglaterra & Alemania
        [29, 'ENG-10', 'Jude Bellingham', 'Inglaterra', 'Mediocampista', 1, 'Legendaria Oro'],
        [30, 'ENG-09', 'Harry Kane', 'Inglaterra', 'Delantero', 0, 'Común'],
        [31, 'ENG-07', 'Bukayo Saka', 'Inglaterra', 'Extremo', 0, 'Común'],
        [32, 'GER-10', 'Jamal Musiala', 'Alemania', 'Mediapunta', 1, 'Holográfica'],
        [33, 'GER-07', 'Florian Wirtz', 'Alemania', 'Mediapunta', 1, 'Holográfica']
    ],
    // === POKÉMON KANTO ===
    'pokemon' => [
        [1, 'PKM-001', 'Bulbasaur', 'Planta / Veneno', 'Inicial', 0, 'Común'],
        [2, 'PKM-002', 'Ivysaur', 'Planta / Veneno', 'Evolución 1', 0, 'Común'],
        [3, 'PKM-003', 'Venusaur', 'Planta / Veneno', 'Evolución Final', 1, 'Holográfica'],
        [4, 'PKM-004', 'Charmander', 'Fuego', 'Inicial', 0, 'Común'],
        [5, 'PKM-005', 'Charmeleon', 'Fuego', 'Evolución 1', 0, 'Común'],
        [6, 'PKM-006', 'Charizard', 'Fuego / Volador', 'Evolución Final', 1, 'Legendaria Oro'],
        [7, 'PKM-007', 'Squirtle', 'Agua', 'Inicial', 0, 'Común'],
        [8, 'PKM-008', 'Wartortle', 'Agua', 'Evolución 1', 0, 'Común'],
        [9, 'PKM-009', 'Blastoise', 'Agua', 'Evolución Final', 1, 'Holográfica'],
        [10, 'PKM-025', 'Pikachu', 'Eléctrico', 'Ícono Mundial', 1, 'Legendaria Oro'],
        [11, 'PKM-026', 'Raichu', 'Eléctrico', 'Evolución', 0, 'Común'],
        [12, 'PKM-094', 'Gengar', 'Fantasma / Veneno', 'Evolución Final', 1, 'Holográfica'],
        [13, 'PKM-130', 'Gyarados', 'Agua / Volador', 'Dragón Marino', 1, 'Holográfica'],
        [14, 'PKM-133', 'Eevee', 'Normal', 'Evolutivo', 0, 'Común'],
        [15, 'PKM-143', 'Snorlax', 'Normal', 'Gigante', 0, 'Común'],
        [16, 'PKM-144', 'Articuno', 'Hielo / Volador', 'Ave Legendaria', 1, 'Legendaria'],
        [17, 'PKM-145', 'Zapdos', 'Eléctrico / Volador', 'Ave Legendaria', 1, 'Legendaria'],
        [18, 'PKM-146', 'Moltres', 'Fuego / Volador', 'Ave Legendaria', 1, 'Legendaria'],
        [19, 'PKM-149', 'Dragonite', 'Dragón / Volador', 'Pseudo-Legendario', 1, 'Holográfica'],
        [20, 'PKM-150', 'Mewtwo', 'Psíquico', 'Legendario Máximo', 1, 'Legendaria Oro'],
        [21, 'PKM-151', 'Mew', 'Psíquico', 'Mítico', 1, 'Legendaria Oro']
    ],
    // === PANINI TOP CLASS 2025 ===
    'panini_top_class' => [
        [1, 'TOP-01', 'Trofeo UEFA Champions League', 'Especiales', 'Trofeo', 1, 'Legendaria Oro'],
        [2, 'TOP-02', 'Erling Haaland (Manchester City)', 'Goleadores Top', 'Delantero', 1, 'Legendaria Oro'],
        [3, 'TOP-03', 'Kevin De Bruyne (Manchester City)', 'Magos del Balón', 'Mediocampista', 1, 'Holográfica'],
        [4, 'TOP-04', 'Kylian Mbappé (Real Madrid)', 'Galácticos', 'Delantero', 1, 'Legendaria Oro'],
        [5, 'TOP-05', 'Jude Bellingham (Real Madrid)', 'Galácticos', 'Mediocampista', 1, 'Legendaria Oro'],
        [6, 'TOP-06', 'Vinícius Júnior (Real Madrid)', 'Galácticos', 'Delantero', 1, 'Holográfica'],
        [7, 'TOP-07', 'Lamine Yamal (FC Barcelona)', 'Jóvenes Talentos', 'Extremo', 1, 'Legendaria Oro'],
        [8, 'TOP-08', 'Robert Lewandowski (FC Barcelona)', 'Goleadores Top', 'Delantero', 0, 'Común'],
        [9, 'TOP-09', 'Harry Kane (Bayern Múnich)', 'Goleadores Top', 'Delantero', 0, 'Común'],
        [10, 'TOP-10', 'Cole Palmer (Chelsea FC)', 'Jóvenes Talentos', 'Mediapunta', 1, 'Holográfica'],
        [11, 'TOP-11', 'Mohamed Salah (Liverpool FC)', 'Íconos', 'Extremo', 1, 'Holográfica'],
        [12, 'TOP-12', 'Luka Modrić (Real Madrid)', 'Leyendas', 'Mediocampista', 1, 'Legendaria']
    ]
];

$stmt_fig = $conexion->prepare("INSERT INTO Figurita (ID_album, numero_figurita, codigo_figurita, nombre_jugador, Seleccion, posicion_rol, Holografica, rareza)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE codigo_figurita=VALUES(codigo_figurita), nombre_jugador=VALUES(nombre_jugador), Seleccion=VALUES(Seleccion), posicion_rol=VALUES(posicion_rol), Holografica=VALUES(Holografica), rareza=VALUES(rareza)");

foreach ($figuritas_data as $alb_code => $figs) {
    $alb_id = $album_ids[$alb_code];
    foreach ($figs as $f) {
        $stmt_fig->bind_param("iissssis", $alb_id, $f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $f[6]);
        $stmt_fig->execute();
    }
    // Actualizar conteo total
    $conexion->query("UPDATE Album SET total_figuritas = (SELECT COUNT(*) FROM Figurita WHERE ID_album = $alb_id) WHERE ID_album = $alb_id");
}

// 3. CREAR USUARIOS DEMO
$usuarios_demo = [
    ['nombre' => 'Juan', 'email' => 'juan@stickerswap.com', 'telefono' => '1122334455', 'password' => '1234', 'es_premium' => 1, 'avatar' => '👑'],
    ['nombre' => 'Carlos_Coleccionista', 'email' => 'carlos@stickerswap.com', 'telefono' => '1199887766', 'password' => '1234', 'es_premium' => 0, 'avatar' => '⚽'],
    ['nombre' => 'Sofia_Panini', 'email' => 'sofia@stickerswap.com', 'telefono' => '1144556677', 'password' => '1234', 'es_premium' => 1, 'avatar' => '🌟'],
    ['nombre' => 'Mateo_Stickers', 'email' => 'mateo@stickerswap.com', 'telefono' => '1155667788', 'password' => '1234', 'es_premium' => 0, 'avatar' => '🎮']
];

$stmt_user = $conexion->prepare("INSERT INTO Usuario (nombre, email, telefono, password, es_premium, avatar) 
                                 VALUES (?, ?, ?, ?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), telefono=VALUES(telefono), password=VALUES(password), es_premium=VALUES(es_premium), avatar=VALUES(avatar)");

foreach ($usuarios_demo as $u) {
    $stmt_user->bind_param("ssssis", $u['nombre'], $u['email'], $u['telefono'], $u['password'], $u['es_premium'], $u['avatar']);
    $stmt_user->execute();
}

// Obtener IDs de usuarios
$res_usr = $conexion->query("SELECT ID_usuario, email FROM Usuario");
$user_ids = [];
while ($row = $res_usr->fetch_assoc()) {
    $user_ids[$row['email']] = $row['ID_usuario'];
}

// 4. CARGAR INVENTARIOS DE DEMO (Para que haya repetidas y faltantes reales para cambiar)
$u_juan = $user_ids['juan@stickerswap.com'] ?? 1;
$u_carlos = $user_ids['carlos@stickerswap.com'] ?? 2;
$u_sofia = $user_ids['sofia@stickerswap.com'] ?? 3;
$u_mateo = $user_ids['mateo@stickerswap.com'] ?? 4;

$m26_id = $album_ids['mundial2026'];
$pkm_id = $album_ids['pokemon'];

// Figuritas de Mundial 2026
$res_m26 = $conexion->query("SELECT ID_figurita, numero_figurita FROM Figurita WHERE ID_album = $m26_id ORDER BY numero_figurita ASC");
$figs_m26 = [];
while ($row = $res_m26->fetch_assoc()) {
    $figs_m26[$row['numero_figurita']] = $row['ID_figurita'];
}

// Cargar para Juan: Tiene casi todo, repetidas de Messi (5), Dibu (6), Yamal (25)
$stmt_inv = $conexion->prepare("INSERT INTO Inventario (ID_usuario, ID_figurita, estado, cantidad_repetidas, pegada_en_album) 
                                VALUES (?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE estado=VALUES(estado), cantidad_repetidas=VALUES(cantidad_repetidas), pegada_en_album=VALUES(pegada_en_album)");

// Juan inventario
$juan_figs = [1, 2, 4, 5, 6, 7, 8, 9, 13, 14, 19, 20, 24, 25, 26, 29, 32];
foreach ($juan_figs as $num) {
    if (isset($figs_m26[$num])) {
        $rep = in_array($num, [5, 6, 25, 8, 14]) ? 2 : 0; // Messi, Dibu, Yamal, Lautaro, Vinicius repetidas
        $estado = $rep > 0 ? 'repetida' : 'tengo';
        $pegada = 1;
        $fig_id = $figs_m26[$num];
        $stmt_inv->bind_param("iisii", $u_juan, $fig_id, $estado, $rep, $pegada);
        $stmt_inv->execute();
    }
}

// Carlos inventario: Le falta Messi y Yamal, pero tiene repetidas de Mbappé (20), Bellingham (29), Musiala (32), Endrick (16)
$carlos_figs = [1, 3, 7, 10, 11, 15, 16, 17, 18, 20, 21, 22, 27, 28, 29, 30, 31, 32, 33];
foreach ($carlos_figs as $num) {
    if (isset($figs_m26[$num])) {
        $rep = in_array($num, [20, 29, 32, 16, 11]) ? 1 : 0;
        $estado = $rep > 0 ? 'repetida' : 'tengo';
        $pegada = 1;
        $fig_id = $figs_m26[$num];
        $stmt_inv->bind_param("iisii", $u_carlos, $fig_id, $estado, $rep, $pegada);
        $stmt_inv->execute();
    }
}

// Sofia inventario: Repetidas de Trofeo (1), Mascota (2), Rodri (27), Camavinga (23)
$sofia_figs = [1, 2, 5, 9, 12, 14, 23, 27, 30];
foreach ($sofia_figs as $num) {
    if (isset($figs_m26[$num])) {
        $rep = in_array($num, [1, 2, 27, 23]) ? 3 : 0;
        $estado = $rep > 0 ? 'repetida' : 'tengo';
        $pegada = 1;
        $fig_id = $figs_m26[$num];
        $stmt_inv->bind_param("iisii", $u_sofia, $fig_id, $estado, $rep, $pegada);
        $stmt_inv->execute();
    }
}

// 5. CREAR UN INTERCAMBIO DE EJEMPLO ACTIVO CON CHAT
$check_trade = $conexion->query("SELECT ID_intercambio FROM Intercambio WHERE ID_usuario_1 = $u_carlos AND ID_usuario_2 = $u_juan AND estado = 'pendiente'");
if ($check_trade->num_rows == 0 && isset($figs_m26[20], $figs_m26[5])) {
    $conexion->query("INSERT INTO Intercambio (ID_album, ID_usuario_1, ID_usuario_2, ultimo_proponente_id, estado) 
                      VALUES ($m26_id, $u_carlos, $u_juan, $u_carlos, 'pendiente')");
    $trade_id = $conexion->insert_id;

    // Carlos ofrece Mbappé (20), solicita Messi (5)
    $f_mbappe = $figs_m26[20];
    $f_messi = $figs_m26[5];
    $conexion->query("INSERT INTO Intercambio_Item (ID_intercambio, ID_usuario_dueno, ID_figurita, tipo) VALUES ($trade_id, $u_carlos, $f_mbappe, 'ofrecida')");
    $conexion->query("INSERT INTO Intercambio_Item (ID_intercambio, ID_usuario_dueno, ID_figurita, tipo) VALUES ($trade_id, $u_juan, $f_messi, 'solicitada')");

    // Mensajes de chat
    $conexion->query("INSERT INTO Mensaje_Chat (ID_intercambio, ID_usuario, contenido, tipo) 
                      VALUES ($trade_id, 0, 'Propuesta de intercambio iniciada por Carlos_Coleccionista.', 'sistema')");
    $conexion->query("INSERT INTO Mensaje_Chat (ID_intercambio, ID_usuario, contenido, tipo) 
                      VALUES ($trade_id, $u_carlos, '¡Hola Juan! Vi que tenés a Messi repetido. Te ofrezco a Mbappé holográfica dorada mano a mano.', 'usuario')");
}

echo "<div style='font-family: sans-serif; padding: 30px; background: #0f172a; color: #f8fafc; border-radius: 12px; max-width: 600px; margin: 40px auto;'>
    <h2 style='color: #fcd400;'>🎉 ¡Base de Datos Configurada con Éxito!</h2>
    <p>Se crearon las tablas relacionales y se precargaron los álbumes:</p>
    <ul>
        <li>⚽ <strong>Mundial FIFA 2026 - Panini Oficial</strong> (Selecciones, Messi, Yamal, Mbappé, Holográficas)</li>
        <li>⚡ <strong>Pokémon Kanto Clásico</strong> (Pikachu, Charizard, Mewtwo, Legendarios)</li>
        <li>🏆 <strong>Panini Top Class 2025</strong> (Haaland, Bellingham, Vinicius Jr, Champions League)</li>
    </ul>
    <p>Se crearon 4 usuarios demo con inventarios e intercambios de prueba listos:</p>
    <ul>
        <li><strong>Juan</strong> (juan@stickerswap.com / 1234) - Administrador / Premium</li>
        <li><strong>Carlos_Coleccionista</strong> (carlos@stickerswap.com / 1234)</li>
        <li><strong>Sofia_Panini</strong> (sofia@stickerswap.com / 1234)</li>
        <li><strong>Mateo_Stickers</strong> (mateo@stickerswap.com / 1234)</li>
    </ul>
    <div style='margin-top: 25px;'>
        <a href='dashboard.php' style='background: #fcd400; color: #000; padding: 12px 24px; text-decoration: none; font-weight: bold; border-radius: 8px; display: inline-block;'>Ir al Dashboard</a>
        <a href='login.php' style='color: #94a3b8; margin-left: 20px;'>Ir al Login</a>
    </div>
</div>";

$conexion->close();
?>