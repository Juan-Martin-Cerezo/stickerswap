<?php
header('Content-Type: application/json');
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION["user_id"];
$action = $_POST['action'] ?? $_GET['action'] ?? 'get_messages';
$last_id = intval($_POST['last_id'] ?? $_GET['last_id'] ?? 0);

// 1. PUBLICAR MENSAJE EN LA COMUNIDAD
if ($action === 'send_message') {
    $mensaje = trim($_POST['mensaje'] ?? '');
    $album_id = intval($_POST['album_id'] ?? 0);

    if (!empty($mensaje)) {
        $stmt = $conexion->prepare("INSERT INTO Mensaje_Comunidad (ID_usuario, ID_album, contenido) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $album_id, $mensaje);
        $stmt->execute();
    }
}

// 2. OBTENER MENSAJES (Soporta delta a través de last_id)
$stmt_msgs = $conexion->prepare("
    SELECT m.*, u.nombre as autor_nombre, u.avatar as autor_avatar, u.es_premium as autor_premium, a.nombre as album_nombre, a.icono as album_icono
    FROM Mensaje_Comunidad m
    JOIN Usuario u ON m.ID_usuario = u.ID_usuario
    LEFT JOIN Album a ON m.ID_album = a.ID_album
    WHERE m.ID_mensaje > ?
    ORDER BY m.ID_mensaje ASC
    LIMIT 100
");
$stmt_msgs->bind_param("i", $last_id);
$stmt_msgs->execute();
$res = $stmt_msgs->get_result();

$messages = [];
$max_id = $last_id;

while ($row = $res->fetch_assoc()) {
    $is_mine = ($row['ID_usuario'] == $user_id);
    $messages[] = [
        'id' => $row['ID_mensaje'],
        'user_id' => $row['ID_usuario'],
        'user_name' => htmlspecialchars($row['autor_nombre']),
        'user_avatar' => $row['autor_avatar'] ?? '👤',
        'is_premium' => ($row['autor_premium'] == 1),
        'album_tag' => !empty($row['album_nombre']) ? ($row['album_icono'] . ' ' . htmlspecialchars($row['album_nombre'])) : null,
        'content' => htmlspecialchars($row['contenido']),
        'is_mine' => $is_mine,
        'time' => date('H:i', strtotime($row['fecha_hora']))
    ];
    if ($row['ID_mensaje'] > $max_id) {
        $max_id = $row['ID_mensaje'];
    }
}

// Obtener usuarios conectados / registrados activos
$res_online = $conexion->query("SELECT ID_usuario, nombre, avatar, es_premium FROM Usuario ORDER BY ID_usuario DESC LIMIT 20");
$online_users = [];
while ($u = $res_online->fetch_assoc()) {
    $online_users[] = [
        'id' => $u['ID_usuario'],
        'name' => htmlspecialchars($u['nombre']),
        'avatar' => $u['avatar'],
        'is_premium' => ($u['es_premium'] == 1),
        'is_me' => ($u['ID_usuario'] == $user_id)
    ];
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'last_id' => $max_id,
    'active_users' => $online_users
]);
