<?php
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$trade_id = intval($_GET['id'] ?? 0);
$is_new = isset($_GET['nuevo']);

if ($is_new) {
    $other_user_id = intval($_GET['con_usuario'] ?? 0);
    $album_id = intval($_GET['album_id'] ?? 1);

    if ($other_user_id <= 0 || $other_user_id === $user_id) {
        header("Location: explorar.php?album_id=$album_id");
        exit;
    }

    $stmt_u = $conexion->prepare("SELECT * FROM Usuario WHERE ID_usuario = ?");
    $stmt_u->bind_param("i", $other_user_id);
    $stmt_u->execute();
    $other_user = $stmt_u->get_result()->fetch_assoc();

    $stmt_a = $conexion->prepare("SELECT * FROM Album WHERE ID_album = ?");
    $stmt_a->bind_param("i", $album_id);
    $stmt_a->execute();
    $current_album = $stmt_a->get_result()->fetch_assoc();

    $stmt_my_rep = $conexion->prepare("
        SELECT f.*, i.cantidad_repetidas 
        FROM Inventario i
        JOIN Figurita f ON i.ID_figurita = f.ID_figurita
        WHERE i.ID_usuario = ? AND f.ID_album = ? AND i.cantidad_repetidas > 0
    ");
    $stmt_my_rep->bind_param("ii", $user_id, $album_id);
    $stmt_my_rep->execute();
    $my_repeated = $stmt_my_rep->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt_their_rep = $conexion->prepare("
        SELECT f.*, i.cantidad_repetidas 
        FROM Inventario i
        JOIN Figurita f ON i.ID_figurita = f.ID_figurita
        WHERE i.ID_usuario = ? AND f.ID_album = ? AND i.cantidad_repetidas > 0
    ");
    $stmt_their_rep->bind_param("ii", $other_user_id, $album_id);
    $stmt_their_rep->execute();
    $their_repeated = $stmt_their_rep->get_result()->fetch_all(MYSQLI_ASSOC);

    $page_mode = 'create';
} else {
    $stmt_t = $conexion->prepare("
        SELECT i.*, 
               u1.nombre as user1_nombre, u1.avatar as user1_avatar, u1.es_premium as user1_premium,
               u2.nombre as user2_nombre, u2.avatar as user2_avatar, u2.es_premium as user2_premium,
               a.nombre as album_nombre, a.icono as album_icono
        FROM Intercambio i
        JOIN Usuario u1 ON i.ID_usuario_1 = u1.ID_usuario
        JOIN Usuario u2 ON i.ID_usuario_2 = u2.ID_usuario
        JOIN Album a ON i.ID_album = a.ID_album
        WHERE i.ID_intercambio = ? AND (i.ID_usuario_1 = ? OR i.ID_usuario_2 = ?)
    ");
    $stmt_t->bind_param("iii", $trade_id, $user_id, $user_id);
    $stmt_t->execute();
    $trade = $stmt_t->get_result()->fetch_assoc();

    if (!$trade) {
        header("Location: dashboard.php");
        exit;
    }

    $album_id = $trade['ID_album'];
    $is_user1 = ($trade['ID_usuario_1'] == $user_id);
    $other_user_id = $is_user1 ? $trade['ID_usuario_2'] : $trade['ID_usuario_1'];
    $other_user_name = $is_user1 ? $trade['user2_nombre'] : $trade['user1_nombre'];
    $other_user_avatar = $is_user1 ? $trade['user2_avatar'] : $trade['user1_avatar'];
    $other_user_premium = $is_user1 ? $trade['user2_premium'] : $trade['user1_premium'];

    $stmt_items = $conexion->prepare("
        SELECT it.*, f.numero_figurita, f.codigo_figurita, f.nombre_jugador, f.Seleccion, f.Holografica, f.rareza
        FROM Intercambio_Item it
        JOIN Figurita f ON it.ID_figurita = f.ID_figurita
        WHERE it.ID_intercambio = ?
    ");
    $stmt_items->bind_param("i", $trade_id);
    $stmt_items->execute();
    $all_items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    $items_i_give = [];
    $items_i_receive = [];

    foreach ($all_items as $item) {
        if ($item['ID_usuario_dueno'] == $user_id) {
            $items_i_give[] = $item;
        } else {
            $items_i_receive[] = $item;
        }
    }

    $stmt_my_rep = $conexion->prepare("
        SELECT f.*, i.cantidad_repetidas 
        FROM Inventario i
        JOIN Figurita f ON i.ID_figurita = f.ID_figurita
        WHERE i.ID_usuario = ? AND f.ID_album = ? AND i.cantidad_repetidas > 0
    ");
    $stmt_my_rep->bind_param("ii", $user_id, $album_id);
    $stmt_my_rep->execute();
    $my_repeated = $stmt_my_rep->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt_their_rep = $conexion->prepare("
        SELECT f.*, i.cantidad_repetidas 
        FROM Inventario i
        JOIN Figurita f ON i.ID_figurita = f.ID_figurita
        WHERE i.ID_usuario = ? AND f.ID_album = ? AND i.cantidad_repetidas > 0
    ");
    $stmt_their_rep->bind_param("ii", $other_user_id, $album_id);
    $stmt_their_rep->execute();
    $their_repeated = $stmt_their_rep->get_result()->fetch_all(MYSQLI_ASSOC);

    $page_mode = 'room';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sala de Negociación - StickerSwap Panini</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .selectable-sticker {
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: var(
            padding: 8px;
            background: var(
            transition: all 0.2s ease;
        }
        .selectable-sticker:hover {
            border-color: var(
            transform: scale(1.02);
        }
        .selectable-sticker.selected {
            border-color: var(
            background: rgba(252, 212, 0, 0.15);
            box-shadow: 0 0 10px rgba(252, 212, 0, 0.3);
        }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>

    <div class="main-wrapper">
        <?php if ($page_mode === 'create'): ?>
            
            <div style="margin-bottom: 24px;">
                <span class="brand-badge"><?php echo htmlspecialchars($current_album['nombre']); ?></span>
                <h1 style="font-size: 26px; font-weight: 800; color: #fff; margin-top: 6px;">Proponer Intercambio con <?php echo htmlspecialchars($other_user['nombre']); ?></h1>
                <p style="color: var(--text-secondary); font-size: 14px;">Seleccioná las figuritas que ofrecés y las que solicitás recibir a cambio.</p>
            </div>

            <div class="trade-board">
                <div class="trade-exchange-grid">
                    
                    <div class="trade-column give">
                        <div class="trade-col-header">
                            <span style="color: var(--panini-gold);">📤 Vos Entregás (Das)</span>
                            <span style="font-size: 12px; color: var(--text-secondary);">Tus Repetidas</span>
                        </div>

                        <?php if (!empty($my_repeated)): ?>
                            <div style="display: flex; flex-direction: column; gap: 8px; max-height: 380px; overflow-y: auto;">
                                <?php foreach ($my_repeated as $f): ?>
                                    <div class="selectable-sticker" onclick="toggleSelect(this, 'give', <?php echo $f['ID_figurita']; ?>)">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong style="color: #fff;">#<?php echo $f['numero_figurita']; ?> <?php echo htmlspecialchars($f['nombre_jugador']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($f['Seleccion']); ?> • Repetida x<?php echo $f['cantidad_repetidas']; ?></div>
                                            </div>
                                            <?php if ($f['Holografica']): ?>
                                                <span style="font-size: 10px; background: var(--panini-gold); color: #000; font-weight: 800; padding: 2px 6px; border-radius: 4px;">HOLO</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 13px;">
                                No tenés figuritas repetidas marcadas en este álbum para ofrecer.
                            </div>
                        <?php endif; ?>
                    </div>

                    
                    <div class="trade-versus-badge">⇄</div>

                    
                    <div class="trade-column receive">
                        <div class="trade-col-header">
                            <span style="color: var(--panini-emerald);">📥 Vos Recibes</span>
                            <span style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($other_user['nombre']); ?></span>
                        </div>

                        <?php if (!empty($their_repeated)): ?>
                            <div style="display: flex; flex-direction: column; gap: 8px; max-height: 380px; overflow-y: auto;">
                                <?php foreach ($their_repeated as $f): ?>
                                    <div class="selectable-sticker" onclick="toggleSelect(this, 'receive', <?php echo $f['ID_figurita']; ?>)">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong style="color: #fff;">#<?php echo $f['numero_figurita']; ?> <?php echo htmlspecialchars($f['nombre_jugador']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($f['Seleccion']); ?> • Disponible x<?php echo $f['cantidad_repetidas']; ?></div>
                                            </div>
                                            <?php if ($f['Holografica']): ?>
                                                <span style="font-size: 10px; background: var(--panini-emerald); color: #fff; font-weight: 800; padding: 2px 6px; border-radius: 4px;">HOLO</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 13px;">
                                Este usuario no tiene figuritas repetidas registradas actualmente.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 14px; margin-top: 14px; border-top: 1px solid var(--border-color); padding-top: 18px;">
                    <a href="explorar.php?album_id=<?php echo $album_id; ?>" class="btn-secondary">Cancelar</a>
                    <button type="button" class="btn-primary" onclick="submitNewTrade(<?php echo $other_user_id; ?>, <?php echo $album_id; ?>)">
                        🚀 Enviar Propuesta e Iniciar Negociación
                    </button>
                </div>
            </div>

        <?php else: ?>
            
            <div style="margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="brand-badge"><?php echo $trade['album_icono'] . ' ' . htmlspecialchars($trade['album_nombre']); ?></span>
                        <span style="font-size: 13px; color: var(--text-secondary);">Intercambio #<?php echo $trade_id; ?></span>
                    </div>
                    <h1 style="font-size: 24px; font-weight: 800; color: #fff; margin-top: 4px;">
                        Negociando con <?php echo htmlspecialchars($other_user_name); ?> <?php echo $other_user_avatar; ?>
                    </h1>
                </div>

                
                <div>
                    <?php if ($trade['estado'] === 'pendiente'): ?>
                        <span style="background: rgba(252, 212, 0, 0.2); color: var(--panini-gold); border: 1px solid rgba(252, 212, 0, 0.4); padding: 6px 14px; border-radius: var(--radius-full); font-weight: 700; font-size: 13px;">
                            ⏳ Propuesta Pendiente
                        </span>
                    <?php elseif ($trade['estado'] === 'aceptado'): ?>
                        <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); padding: 6px 14px; border-radius: var(--radius-full); font-weight: 700; font-size: 13px;">
                            🎉 ¡Intercambio Concretado!
                        </span>
                    <?php elseif ($trade['estado'] === 'rechazado'): ?>
                        <span style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); padding: 6px 14px; border-radius: var(--radius-full); font-weight: 700; font-size: 13px;">
                            ❌ Rechazado
                        </span>
                    <?php else: ?>
                        <span style="background: var(--bg-surface); color: var(--text-muted); padding: 6px 14px; border-radius: var(--radius-full); font-weight: 700; font-size: 13px;">
                            ⚠️ Cancelado
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="trade-container">
                
                <div class="trade-board">
                    <div class="trade-exchange-grid">
                        
                        <div class="trade-column give">
                            <div class="trade-col-header">
                                <span style="color: var(--panini-gold);">📤 Lo que Vos Entregás (Das)</span>
                                <span style="font-size: 12px; color: var(--text-secondary);"><?php echo count($items_i_give); ?> figuritas</span>
                            </div>

                            <?php if (!empty($items_i_give)): ?>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <?php foreach ($items_i_give as $item): ?>
                                        <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong style="color: #fff; font-size: 14px;">#<?php echo $item['numero_figurita']; ?> <?php echo htmlspecialchars($item['nombre_jugador']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($item['Seleccion']); ?> • <?php echo htmlspecialchars($item['posicion_rol'] ?? 'Jugador'); ?></div>
                                            </div>
                                            <?php if ($item['Holografica']): ?>
                                                <span style="font-size: 10px; background: var(--panini-gold); color: #000; font-weight: 800; padding: 2px 6px; border-radius: 4px;">✨ HOLO</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 13px;">Ninguna figurita ofrecida.</div>
                            <?php endif; ?>
                        </div>

                        <div class="trade-versus-badge">⇄</div>

                        
                        <div class="trade-column receive">
                            <div class="trade-col-header">
                                <span style="color: var(--panini-emerald);">📥 Lo que Recibes</span>
                                <span style="font-size: 12px; color: var(--text-secondary);"><?php echo count($items_i_receive); ?> figuritas</span>
                            </div>

                            <?php if (!empty($items_i_receive)): ?>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <?php foreach ($items_i_receive as $item): ?>
                                        <div style="background: var(--bg-card); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong style="color: #fff; font-size: 14px;">#<?php echo $item['numero_figurita']; ?> <?php echo htmlspecialchars($item['nombre_jugador']); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($item['Seleccion']); ?> • <?php echo htmlspecialchars($item['posicion_rol'] ?? 'Jugador'); ?></div>
                                            </div>
                                            <?php if ($item['Holografica']): ?>
                                                <span style="font-size: 10px; background: var(--panini-emerald); color: #fff; font-weight: 800; padding: 2px 6px; border-radius: 4px;">✨ HOLO</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 13px;">Ninguna figurita solicitada.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    
                    <?php if ($trade['estado'] === 'pendiente'): 
                        $is_my_turn = ($trade['ultimo_proponente_id'] != $user_id);
                    ?>
                        <div style="border-top: 1px solid var(--border-color); padding-top: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
                            <?php if ($is_my_turn): ?>
                                <div>
                                    <button type="button" class="btn-success" onclick="tradeAction('accept_trade')">
                                        ✓ Aceptar Intercambio
                                    </button>
                                    <button type="button" class="btn-secondary" style="color: var(--panini-gold); margin-left: 8px;" onclick="toggleCounterOfferModal()">
                                        🔄 Contraoferta
                                    </button>
                                </div>
                                <button type="button" class="btn-danger" onclick="tradeAction('reject_trade')">
                                    ✕ Rechazar
                                </button>
                            <?php else: ?>
                                <div style="font-size: 14px; color: #38bdf8; display: flex; align-items: center; gap: 8px;">
                                    <span>⏳</span> Esperando respuesta de <?php echo htmlspecialchars($other_user_name); ?>...
                                </div>
                                <div>
                                    <button type="button" class="btn-secondary" style="color: var(--panini-gold);" onclick="toggleCounterOfferModal()">
                                        🔄 Modificar Oferta
                                    </button>
                                    <button type="button" class="btn-danger" style="margin-left: 8px;" onclick="tradeAction('cancel_trade')">
                                        Cancelar
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                
                <div class="chat-panel">
                    <div class="chat-header">
                        <div style="font-size: 24px;"><?php echo $other_user_avatar; ?></div>
                        <div>
                            <div style="font-weight: 700; font-size: 15px; color: #fff;"><?php echo htmlspecialchars($other_user_name); ?></div>
                            <div style="font-size: 11px; color: #34d399; display: flex; align-items: center; gap: 4px;">
                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #34d399;"></span> Chat en Vivo
                            </div>
                        </div>
                    </div>

                    
                    <div class="chat-messages" id="chatMessages">
                        
                    </div>

                    
                    <form class="chat-input-form" onsubmit="sendChatMessage(event)">
                        <input type="text" id="chatInput" class="chat-input" placeholder="Escribí un mensaje..." autocomplete="off">
                        <button type="submit" class="btn-primary" style="padding: 10px 16px;">
                            Enviar
                        </button>
                    </form>
                </div>
            </div>

            
            <div id="counterModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.8); z-index: 200; align-items: center; justify-content: center; padding: 20px;">
                <div style="background: var(--bg-card); border: 1px solid var(--border-gold); border-radius: var(--radius-lg); max-width: 720px; width: 100%; padding: 24px; max-height: 90vh; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 20px; font-weight: 800; color: #fff;">🔄 Modificar / Enviar Contraoferta</h3>
                        <button type="button" onclick="toggleCounterOfferModal()" style="background: none; border: none; color: #fff; font-size: 22px; cursor: pointer;">✕</button>
                    </div>

                    <div class="trade-exchange-grid" style="margin-bottom: 20px;">
                        
                        <div class="trade-column give">
                            <div class="trade-col-header">
                                <span style="color: var(--panini-gold);">📤 Vos Ofreces</span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px; max-height: 240px; overflow-y: auto;">
                                <?php foreach ($my_repeated as $f): 
                                    $is_sel = in_array($f['ID_figurita'], array_column($items_i_give, 'ID_figurita'));
                                ?>
                                    <div class="selectable-sticker <?php echo $is_sel ? 'selected' : ''; ?>" onclick="toggleSelect(this, 'give', <?php echo $f['ID_figurita']; ?>)">
                                        <div style="font-size: 13px; font-weight: 700; color: #fff;">#<?php echo $f['numero_figurita']; ?> <?php echo htmlspecialchars($f['nombre_jugador']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="trade-versus-badge">⇄</div>

                        
                        <div class="trade-column receive">
                            <div class="trade-col-header">
                                <span style="color: var(--panini-emerald);">📥 Vos Solicitás</span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px; max-height: 240px; overflow-y: auto;">
                                <?php foreach ($their_repeated as $f): 
                                    $is_sel = in_array($f['ID_figurita'], array_column($items_i_receive, 'ID_figurita'));
                                ?>
                                    <div class="selectable-sticker <?php echo $is_sel ? 'selected' : ''; ?>" onclick="toggleSelect(this, 'receive', <?php echo $f['ID_figurita']; ?>)">
                                        <div style="font-size: 13px; font-weight: 700; color: #fff;">#<?php echo $f['numero_figurita']; ?> <?php echo htmlspecialchars($f['nombre_jugador']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 12px;">
                        <button type="button" class="btn-secondary" onclick="toggleCounterOfferModal()">Cancelar</button>
                        <button type="button" class="btn-primary" onclick="submitCounterOffer()">Enviar Contraoferta</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    
    <script>
        const selectedGive = new Set(<?php 
            if (!empty($items_i_give)) {
                echo json_encode(array_map('intval', array_column($items_i_give, 'ID_figurita')));
            } else {
                echo '[]';
            }
        ?>);
        const selectedReceive = new Set(<?php 
            if (!empty($items_i_receive)) {
                echo json_encode(array_map('intval', array_column($items_i_receive, 'ID_figurita')));
            } else {
                echo '[]';
            }
        ?>);

        function toggleSelect(element, type, id) {
            const set = (type === 'give') ? selectedGive : selectedReceive;
            if (set.has(id)) {
                set.delete(id);
                element.classList.remove('selected');
            } else {
                set.add(id);
                element.classList.add('selected');
            }
        }

        async function submitNewTrade(otherUserId, albumId) {
            if (selectedGive.size === 0 && selectedReceive.size === 0) {
                alert('Debés seleccionar al menos una figurita para intercambiar.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'create_trade');
            formData.append('con_usuario', otherUserId);
            formData.append('album_id', albumId);
            formData.append('ofrecidas', Array.from(selectedGive).join(','));
            formData.append('solicitadas', Array.from(selectedReceive).join(','));

            try {
                const res = await fetch('api_trade.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'intercambio.php?id=' + data.trade_id;
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function tradeAction(action) {
            const tradeId = <?php echo $trade_id; ?>;
            const formData = new FormData();
            formData.append('action', action);
            formData.append('trade_id', tradeId);

            try {
                const res = await fetch('api_trade.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error);
                }
            } catch (e) {
                console.error(e);
            }
        }

        function toggleCounterOfferModal() {
            const m = document.getElementById('counterModal');
            if (m) {
                m.style.display = (m.style.display === 'flex') ? 'none' : 'flex';
            }
        }

        async function submitCounterOffer() {
            if (selectedGive.size === 0 && selectedReceive.size === 0) {
                alert('Debés seleccionar al menos una figurita para la contraoferta.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'counter_offer');
            formData.append('trade_id', <?php echo $trade_id; ?>);
            formData.append('ofrecidas', Array.from(selectedGive).join(','));
            formData.append('solicitadas', Array.from(selectedReceive).join(','));

            try {
                const res = await fetch('api_trade.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error);
                }
            } catch (e) {
                console.error(e);
            }
        }

        <?php if ($page_mode === 'room'): ?>
            let lastMessageId = 0;
            const tradeId = <?php echo $trade_id; ?>;

            async function pollChat() {
                try {
                    const res = await fetch(`api_chat.php?trade_id=${tradeId}&last_id=${lastMessageId}`);
                    const data = await res.json();

                    if (data.success && data.messages.length > 0) {
                        const container = document.getElementById('chatMessages');
                        data.messages.forEach(msg => {
                            const bubble = document.createElement('div');
                            if (msg.tipo === 'sistema') {
                                bubble.className = 'message-bubble system';
                                bubble.innerHTML = msg.content;
                            } else {
                                bubble.className = 'message-bubble ' + (msg.is_mine ? 'mine' : 'theirs');
                                bubble.innerHTML = `
                                    <div style="font-weight: 700; font-size: 11px; margin-bottom: 2px; opacity: 0.8;">${msg.sender_avatar} ${msg.sender_name}</div>
                                    <div>${msg.content}</div>
                                    <div class="message-time">${msg.time}</div>
                                `;
                            }
                            container.appendChild(bubble);
                        });

                        lastMessageId = data.last_id;
                        container.scrollTop = container.scrollHeight;
                    }
                } catch (e) {
                    console.error('Error polling chat:', e);
                }
            }

            async function sendChatMessage(e) {
                e.preventDefault();
                const input = document.getElementById('chatInput');
                const text = input.value.trim();
                if (!text) return;

                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('trade_id', tradeId);
                formData.append('mensaje', text);
                input.value = '';

                try {
                    await fetch('api_chat.php', { method: 'POST', body: formData });
                    pollChat();
                } catch (err) {
                    console.error(err);
                }
            }

            pollChat();
            setInterval(pollChat, 1500);
        <?php endif; ?>
    </script>
</body>
</html>
