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
$trade_id = intval($_POST['trade_id'] ?? 0);

if ($action === 'create_trade') {
    $con_usuario_id = intval($_POST['con_usuario'] ?? 0);
    $album_id = intval($_POST['album_id'] ?? 1);
    $ofrecidas = isset($_POST['ofrecidas']) ? (is_array($_POST['ofrecidas']) ? $_POST['ofrecidas'] : explode(',', $_POST['ofrecidas'])) : [];
    $solicitadas = isset($_POST['solicitadas']) ? (is_array($_POST['solicitadas']) ? $_POST['solicitadas'] : explode(',', $_POST['solicitadas'])) : [];

    if ($con_usuario_id <= 0 || $con_usuario_id === $user_id) {
        echo json_encode(['success' => false, 'error' => 'Usuario destinatario inválido']);
        exit;
    }

    if (empty($ofrecidas) && empty($solicitadas)) {
        echo json_encode(['success' => false, 'error' => 'Debés seleccionar al menos una figurita para intercambiar']);
        exit;
    }

    $stmt = $conexion->prepare("INSERT INTO Intercambio (ID_album, ID_usuario_1, ID_usuario_2, ultimo_proponente_id, estado) VALUES (?, ?, ?, ?, 'pendiente')");
    $stmt->bind_param("iiii", $album_id, $user_id, $con_usuario_id, $user_id);
    $stmt->execute();
    $new_trade_id = $stmt->insert_id;

    $stmt_item = $conexion->prepare("INSERT INTO Intercambio_Item (ID_intercambio, ID_usuario_dueno, ID_figurita, tipo) VALUES (?, ?, ?, ?)");
    foreach ($ofrecidas as $fid) {
        $fid = intval($fid);
        if ($fid > 0) {
            $tipo = 'ofrecida';
            $stmt_item->bind_param("iiis", $new_trade_id, $user_id, $fid, $tipo);
            $stmt_item->execute();
        }
    }

    foreach ($solicitadas as $fid) {
        $fid = intval($fid);
        if ($fid > 0) {
            $tipo = 'solicitada';
            $stmt_item->bind_param("iiis", $new_trade_id, $con_usuario_id, $fid, $tipo);
            $stmt_item->execute();
        }
    }

    $u_name = $_SESSION['user_name'];
    $sys_msg = "🤝 $u_name creó una propuesta de intercambio.";
    $stmt_chat = $conexion->prepare("INSERT INTO Mensaje_Chat (ID_intercambio, ID_usuario, contenido, tipo) VALUES (?, 0, ?, 'sistema')");
    $stmt_chat->bind_param("is", $new_trade_id, $sys_msg);
    $stmt_chat->execute();

    echo json_encode(['success' => true, 'trade_id' => $new_trade_id]);
    exit;
}

if ($trade_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de intercambio inválido']);
    exit;
}

$stmt_t = $conexion->prepare("SELECT * FROM Intercambio WHERE ID_intercambio = ?");
$stmt_t->bind_param("i", $trade_id);
$stmt_t->execute();
$trade = $stmt_t->get_result()->fetch_assoc();

if (!$trade || ($trade['ID_usuario_1'] != $user_id && $trade['ID_usuario_2'] != $user_id)) {
    echo json_encode(['success' => false, 'error' => 'Intercambio no encontrado o sin permisos']);
    exit;
}

if ($action === 'accept_trade') {
    if ($trade['estado'] !== 'pendiente') {
        echo json_encode(['success' => false, 'error' => 'El intercambio ya no está pendiente']);
        exit;
    }

    if ($trade['ultimo_proponente_id'] == $user_id) {
        echo json_encode(['success' => false, 'error' => 'No podés aceptar tu propia propuesta, debés esperar la respuesta del otro coleccionista']);
        exit;
    }

    $u1 = $trade['ID_usuario_1'];
    $u2 = $trade['ID_usuario_2'];

    $conexion->begin_transaction();

    try {
        $items_res = $conexion->query("SELECT * FROM Intercambio_Item WHERE ID_intercambio = $trade_id");
        while ($item = $items_res->fetch_assoc()) {
            $giver_id = $item['ID_usuario_dueno'];
            $receiver_id = ($giver_id == $u1) ? $u2 : $u1;
            $fig_id = $item['ID_figurita'];

            $stmt_giver = $conexion->prepare("SELECT cantidad_repetidas FROM Inventario WHERE ID_usuario = ? AND ID_figurita = ?");
            $stmt_giver->bind_param("ii", $giver_id, $fig_id);
            $stmt_giver->execute();
            $g_res = $stmt_giver->get_result()->fetch_assoc();
            $curr_rep = $g_res ? intval($g_res['cantidad_repetidas']) : 0;

            if ($curr_rep > 1) {
                $conexion->query("UPDATE Inventario SET cantidad_repetidas = cantidad_repetidas - 1 WHERE ID_usuario = $giver_id AND ID_figurita = $fig_id");
            } elseif ($curr_rep === 1) {
                $conexion->query("UPDATE Inventario SET estado = 'tengo', cantidad_repetidas = 0 WHERE ID_usuario = $giver_id AND ID_figurita = $fig_id");
            }

            $stmt_rec = $conexion->prepare("SELECT ID_inventario, estado, cantidad_repetidas FROM Inventario WHERE ID_usuario = ? AND ID_figurita = ?");
            $stmt_rec->bind_param("ii", $receiver_id, $fig_id);
            $stmt_rec->execute();
            $r_res = $stmt_rec->get_result()->fetch_assoc();

            if ($r_res) {
                $conexion->query("UPDATE Inventario SET estado = 'repetida', cantidad_repetidas = cantidad_repetidas + 1 WHERE ID_usuario = $receiver_id AND ID_figurita = $fig_id");
            } else {
                $conexion->query("INSERT INTO Inventario (ID_usuario, ID_figurita, estado, cantidad_repetidas, pegada_en_album) VALUES ($receiver_id, $fig_id, 'tengo', 0, 1)");
            }
        }

        $conexion->query("UPDATE Intercambio SET estado = 'aceptado', fecha_actualizacion = NOW() WHERE ID_intercambio = $trade_id");

        $accept_name = $_SESSION['user_name'];
        $sys_msg = "🎉 ¡Intercambio aceptado con éxito por $accept_name! Las figuritas han sido transferidas a sus respectivos inventarios.";
        $stmt_chat = $conexion->prepare("INSERT INTO Mensaje_Chat (ID_intercambio, ID_usuario, contenido, tipo) VALUES (?, 0, ?, 'sistema')");
        $stmt_chat->bind_param("is", $trade_id, $sys_msg);
        $stmt_chat->execute();

        $conexion->commit();
        echo json_encode(['success' => true, 'message' => '¡Intercambio completado exitosamente!']);
    } catch (Exception $e) {
        $conexion->rollback();
        echo json_encode(['success' => false, 'error' => 'Error al procesar el intercambio: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'counter_offer') {
    $ofrecidas = isset($_POST['ofrecidas']) ? (is_array($_POST['ofrecidas']) ? $_POST['ofrecidas'] : explode(',', $_POST['ofrecidas'])) : [];
    $solicitadas = isset($_POST['solicitadas']) ? (is_array($_POST['solicitadas']) ? $_POST['solicitadas'] : explode(',', $_POST['solicitadas'])) : [];
    $other_user_id = ($trade['ID_usuario_1'] == $user_id) ? $trade['ID_usuario_2'] : $trade['ID_usuario_1'];

    $conexion->begin_transaction();
    try {
        $conexion->query("DELETE FROM Intercambio_Item WHERE ID_intercambio = $trade_id");

        $stmt_item = $conexion->prepare("INSERT INTO Intercambio_Item (ID_intercambio, ID_usuario_dueno, ID_figurita, tipo) VALUES (?, ?, ?, ?)");
        foreach ($ofrecidas as $fid) {
            $fid = intval($fid);
            if ($fid > 0) {
                $tipo = 'ofrecida';
                $stmt_item->bind_param("iiis", $trade_id, $user_id, $fid, $tipo);
                $stmt_item->execute();
            }
        }

        foreach ($solicitadas as $fid) {
            $fid = intval($fid);
            if ($fid > 0) {
                $tipo = 'solicitada';
                $stmt_item->bind_param("iiis", $trade_id, $other_user_id, $fid, $tipo);
                $stmt_item->execute();
            }
        }

        $conexion->query("UPDATE Intercambio SET ultimo_proponente_id = $user_id, estado = 'pendiente', fecha_actualizacion = NOW() WHERE ID_intercambio = $trade_id");

        $u_name = $_SESSION['user_name'];
        $sys_msg = "🔄 $u_name ha enviado una CONTRAOFERTA modificando las figuritas en negociación.";
        $stmt_chat = $conexion->prepare("INSERT INTO Mensaje_Chat (ID_intercambio, ID_usuario, contenido, tipo) VALUES (?, 0, ?, 'sistema')");
        $stmt_chat->bind_param("is", $trade_id, $sys_msg);
        $stmt_chat->execute();

        $conexion->commit();
        echo json_encode(['success' => true, 'message' => 'Contraoferta enviada']);
    } catch (Exception $e) {
        $conexion->rollback();
        echo json_encode(['success' => false, 'error' => 'Error al enviar contraoferta: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'reject_trade' || $action === 'cancel_trade') {
    $nuevo_estado = ($action === 'reject_trade') ? 'rechazado' : 'cancelado';
    $conexion->query("UPDATE Intercambio SET estado = '$nuevo_estado', fecha_actualizacion = NOW() WHERE ID_intercambio = $trade_id");

    $u_name = $_SESSION['user_name'];
    $sys_msg = ($action === 'reject_trade') ? "❌ $u_name ha rechazado la propuesta de intercambio." : "⚠️ $u_name ha cancelado el intercambio.";
    $stmt_chat = $conexion->prepare("INSERT INTO Mensaje_Chat (ID_intercambio, ID_usuario, contenido, tipo) VALUES (?, 0, ?, 'sistema')");
    $stmt_chat->bind_param("is", $trade_id, $sys_msg);
    $stmt_chat->execute();

    echo json_encode(['success' => true, 'message' => "Intercambio $nuevo_estado"]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
