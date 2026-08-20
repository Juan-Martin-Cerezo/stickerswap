<?php
header('Content-Type: application/json');
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION["user_id"];
$action = $_POST['action'] ?? '';
$fig_id = intval($_POST['fig_id'] ?? 0);
$album_id = intval($_POST['album_id'] ?? 1);

if ($fig_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Figurita inválida']);
    exit;
}

// Obtener estado actual del inventario
$stmt = $conexion->prepare("SELECT * FROM Inventario WHERE ID_usuario = ? AND ID_figurita = ?");
$stmt->bind_param("ii", $user_id, $fig_id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

$new_status = 'falta';
$new_rep = 0;

if ($action === 'toggle_owned') {
    if (!$current || $current['estado'] === 'falta') {
        $new_status = 'tengo';
        $new_rep = 0;
    } else {
        $new_status = 'falta';
        $new_rep = 0;
    }
} elseif ($action === 'add_repeated') {
    $current_rep = $current ? intval($current['cantidad_repetidas']) : 0;
    $new_rep = $current_rep + 1;
    $new_status = 'repetida';
} elseif ($action === 'remove_repeated') {
    $current_rep = $current ? intval($current['cantidad_repetidas']) : 0;
    if ($current_rep > 1) {
        $new_rep = $current_rep - 1;
        $new_status = 'repetida';
    } elseif ($current_rep === 1) {
        $new_rep = 0;
        $new_status = 'tengo';
    } else {
        $new_rep = 0;
        $new_status = 'falta';
    }
} elseif ($action === 'set_missing') {
    $new_status = 'falta';
    $new_rep = 0;
}

// Guardar en la base de datos
if ($new_status === 'falta') {
    $del_stmt = $conexion->prepare("DELETE FROM Inventario WHERE ID_usuario = ? AND ID_figurita = ?");
    $del_stmt->bind_param("ii", $user_id, $fig_id);
    $del_stmt->execute();
} else {
    $save_stmt = $conexion->prepare("
        INSERT INTO Inventario (ID_usuario, ID_figurita, estado, cantidad_repetidas, pegada_en_album)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE estado = VALUES(estado), cantidad_repetidas = VALUES(cantidad_repetidas), pegada_en_album = 1
    ");
    $save_stmt->bind_param("iisi", $user_id, $fig_id, $new_status, $new_rep);
    $save_stmt->execute();
}

// Recalcular estadísticas del álbum
$stmt_total = $conexion->prepare("SELECT COUNT(*) as total FROM Figurita WHERE ID_album = ?");
$stmt_total->bind_param("i", $album_id);
$stmt_total->execute();
$total_figs = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;

$stmt_owned = $conexion->prepare("
    SELECT COUNT(DISTINCT i.ID_figurita) as owned,
           SUM(CASE WHEN i.cantidad_repetidas > 0 THEN i.cantidad_repetidas ELSE 0 END) as repetidas_totales
    FROM Inventario i
    JOIN Figurita f ON i.ID_figurita = f.ID_figurita
    WHERE i.ID_usuario = ? AND f.ID_album = ? AND (i.estado = 'tengo' OR i.estado = 'repetida')
");
$stmt_owned->bind_param("ii", $user_id, $album_id);
$stmt_owned->execute();
$stats = $stmt_owned->get_result()->fetch_assoc();

$owned_figs = $stats['owned'] ?? 0;
$repetidas_totales = $stats['repetidas_totales'] ?? 0;
$missing_figs = max(0, $total_figs - $owned_figs);
$progress_pct = ($total_figs > 0) ? round(($owned_figs / $total_figs) * 100) : 0;

echo json_encode([
    'success' => true,
    'fig_id' => $fig_id,
    'status' => $new_status,
    'repetidas' => $new_rep,
    'owned_figs' => $owned_figs,
    'missing_figs' => $missing_figs,
    'repetidas_totales' => $repetidas_totales,
    'progress_pct' => $progress_pct
]);
