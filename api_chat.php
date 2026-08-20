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
$trade_id = intval($_POST['trade_id'] ?? $_GET['trade_id'] ?? 0);
$last_id = intval($_POST['last_id'] ?? $_GET['last_id'] ?? 0);

if ($trade_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Intercambio inválido']);
    exit;
}

// Validar que el usuario sea parte del intercambio
$stmt_perm = $conexion->prepare("SELECT ID_intercambio, estado FROM Intercambio WHERE ID_intercambio = ? AND (ID_usuario_1 = ? OR ID_usuario_2 = ?)");
$stmt_perm->bind_param("iii", $trade_id, $user_id, $user_id);
$stmt_perm->execute();
$trade_perm = $stmt_perm->get_result()->fetch_assoc();

if (!$trade_perm) {
    echo json_encode(['success' => false, 'error' => 'Sin permisos para acceder a esta sala']);
    exit;
}

// 1. ENVIAR MENSAJE
if ($action === 'send_message') {
    $mensaje = trim($_POST['mensaje'] ?? '');
    if (!empty($mensaje)) {
        $stmt_ins = $conexion->prepare("INSERT INTO Mensaje_Chat (ID_intercambio, ID_usuario, contenido, tipo) VALUES (?, ?, ?, 'usuario')");
        $stmt_ins->bind_param("iis", $trade_id, $user_id, $mensaje);
        $stmt_ins->execute();
    }
}

// 2. OBTENER MENSAJES (Soporta delta a través de last_id)
$stmt_msgs = $conexion->prepare("
    SELECT m.*, u.nombre as autor_nombre, u.avatar as autor_avatar
    FROM Mensaje_Chat m
    LEFT JOIN Usuario u ON m.ID_usuario = u.ID_usuario
    WHERE m.ID_intercambio = ? AND m.ID_mensaje_chat > ?
    ORDER BY m.ID_mensaje_chat ASC
");
$stmt_msgs->bind_param("ii", $trade_id, $last_id);
$stmt_msgs->execute();
$res = $stmt_msgs->get_result();

$messages = [];
$max_id = $last_id;

while ($row = $res->fetch_assoc()) {
    $is_mine = ($row['ID_usuario'] == $user_id);
    $messages[] = [
        'id' => $row['ID_mensaje_chat'],
        'sender_id' => $row['ID_usuario'],
        'sender_name' => $row['autor_nombre'] ?? 'Sistema Panini',
        'sender_avatar' => $row['autor_avatar'] ?? '🤖',
        'content' => htmlspecialchars($row['contenido']),
        'tipo' => $row['tipo'],
        'is_mine' => $is_mine,
        'time' => date('H:i', strtotime($row['fecha_hora']))
    ];
    if ($row['ID_mensaje_chat'] > $max_id) {
        $max_id = $row['ID_mensaje_chat'];
    }
}

echo json_encode([
    'success' => true,
    'trade_status' => $trade_perm['estado'],
    'messages' => $messages,
    'last_id' => $max_id
]);
