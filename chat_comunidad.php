<?php
session_start();
include("config.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$res_albums = $conexion->query("SELECT * FROM Album ORDER BY ID_album ASC");
$albums = $res_albums->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunidad & Chat en Vivo - StickerSwap</title>
    <link rel="stylesheet" href="estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .community-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 24px;
            height: calc(100vh - 160px);
        }
        @media (max-width: 850px) {
            .community-layout {
                grid-template-columns: 1fr;
                height: auto;
            }
        }
        .user-item-lobby {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(
            padding: 10px 14px;
            border-radius: var(
            border: 1px solid var(
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }
        .user-item-lobby:hover {
            border-color: var(
            transform: translateX(4px);
        }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>

    <div class="main-wrapper" style="padding-bottom: 20px;">
        <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--panini-gold); font-weight: 700;">Sala Oficial de la Red</span>
                <h1 style="font-size: 24px; font-weight: 800; color: #fff;">💬 Chat de la Comunidad en Vivo</h1>
            </div>
            <span style="font-size: 13px; color: #34d399; display: flex; align-items: center; gap: 6px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #34d399;"></span> Conexión en Tiempo Real
            </span>
        </div>

        <div class="community-layout">
            
            <div class="chat-panel" style="height: 100%;">
                <div class="chat-messages" id="globalChatMessages" style="padding: 20px;">
                    
                </div>

                <form class="chat-input-form" onsubmit="sendCommunityMessage(event)" style="padding: 16px; background: var(--bg-surface); display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <select id="msgAlbumSelect" class="form-input" style="padding: 6px 12px; font-size: 12px; max-width: 220px;">
                            <option value="0">💬 Tema General</option>
                            <?php foreach ($albums as $alb): ?>
                                <option value="<?php echo $alb['ID_album']; ?>">
                                    <?php echo $alb['icono'] . ' ' . htmlspecialchars($alb['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span style="font-size: 12px; color: var(--text-muted);">Elegí sobre qué álbum hablás</span>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="communityInput" class="chat-input" placeholder="Escribí un mensaje para todos los coleccionistas en la red... (Ej: Busco a Messi #5, tengo a Mbappé repetido!)" autocomplete="off" required>
                        <button type="submit" class="btn-primary" style="padding: 10px 24px;">
                            Publicar
                        </button>
                    </div>
                </form>
            </div>

            
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; display: flex; flex-direction: column;">
                <h3 style="font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                    <span>👥</span> Coleccionistas en la Red
                </h3>

                <div id="usersLobbyList" style="flex: 1; overflow-y: auto;">
                    
                </div>
            </div>
        </div>
    </div>

    <script>
        let lastId = 0;

        async function pollCommunity() {
            try {
                const res = await fetch(`api_comunidad.php?last_id=${lastId}`);
                const data = await res.json();

                if (data.success) {
                    if (data.messages.length > 0) {
                        const container = document.getElementById('globalChatMessages');
                        data.messages.forEach(msg => {
                            const div = document.createElement('div');
                            div.className = 'message-bubble ' + (msg.is_mine ? 'mine' : 'theirs');
                            div.style.maxWidth = '85%';

                            let tagHtml = msg.album_tag ? `<span style="font-size: 10px; background: rgba(255,255,255,0.15); padding: 2px 6px; border-radius: 4px; margin-left: 6px;">${msg.album_tag}</span>` : '';
                            let vipHtml = msg.is_premium ? `<span style="font-size: 9px; background: var(--panini-gold); color: #000; font-weight: 800; padding: 1px 4px; border-radius: 3px; margin-left: 4px;">GOLD</span>` : '';
                            let tradeBtn = (!msg.is_mine) ? `<a href="intercambio.php?nuevo=1&con_usuario=${msg.user_id}" style="color: var(--panini-gold); font-size: 11px; margin-left: 8px; font-weight: 700; text-decoration: none;">[🤝 Proponer Cambio]</a>` : '';

                            div.innerHTML = `
                                <div style="display: flex; align-items: center; margin-bottom: 4px; font-size: 12px; font-weight: 700; opacity: 0.9;">
                                    <span>${msg.user_avatar} ${msg.user_name}</span>
                                    ${vipHtml}
                                    ${tagHtml}
                                    ${tradeBtn}
                                </div>
                                <div style="font-size: 14px; line-height: 1.4;">${msg.content}</div>
                                <div class="message-time">${msg.time}</div>
                            `;
                            container.appendChild(div);
                        });

                        lastId = data.last_id;
                        container.scrollTop = container.scrollHeight;
                    }

                    if (data.active_users) {
                        const usersContainer = document.getElementById('usersLobbyList');
                        usersContainer.innerHTML = '';
                        data.active_users.forEach(u => {
                            const uDiv = document.createElement('div');
                            uDiv.className = 'user-item-lobby';
                            uDiv.innerHTML = `
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 20px;">${u.avatar}</span>
                                    <div>
                                        <div style="font-weight: 700; font-size: 13px; color: #fff;">
                                            ${u.name} ${u.is_me ? '<span style="color: #38bdf8; font-size: 11px;">(Vos)</span>' : ''}
                                        </div>
                                    </div>
                                </div>
                                ${!u.is_me ? `<a href="intercambio.php?nuevo=1&con_usuario=${u.id}" class="btn-primary" style="padding: 4px 10px; font-size: 12px;">Cambiar</a>` : ''}
                            `;
                            usersContainer.appendChild(uDiv);
                        });
                    }
                }
            } catch (err) {
                console.error('Error polling community chat:', err);
            }
        }

        async function sendCommunityMessage(e) {
            e.preventDefault();
            const input = document.getElementById('communityInput');
            const select = document.getElementById('msgAlbumSelect');
            const text = input.value.trim();
            if (!text) return;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('mensaje', text);
            formData.append('album_id', select.value);
            input.value = '';

            try {
                await fetch('api_comunidad.php', { method: 'POST', body: formData });
                pollCommunity();
            } catch (err) {
                console.error(err);
            }
        }

        pollCommunity();
        setInterval(pollCommunity, 1500);
    </script>
</body>
</html>
