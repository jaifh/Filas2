<?php
// caja.php
require_once __DIR__ . '/auth.php';
requireLogin(['ADMIN','CAJA']);
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getPDO();

$modulo_id  = (int)($_SESSION['modulo_id'] ?? 0);
$usuario_id = (int)($_SESSION['id'] ?? 0);

if ($modulo_id <= 0 || $usuario_id <= 0) {
    http_response_code(400);
    echo 'Este equipo no tiene módulo asignado. Vuelva a index.php y seleccione un módulo.';
    exit;
}

// Marcar módulo en uso por este usuario (solo este módulo)
$stmt = $pdo->prepare("UPDATE modulos SET usuario_en_uso = ? WHERE id = ?");
$stmt->execute([$usuario_id, $modulo_id]);

$stmt = $pdo->prepare('SELECT id, nombre, codigo, activo FROM modulos WHERE id = ?');
$stmt->execute([$modulo_id]);
$modulo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$modulo || (int)$modulo['activo'] !== 1) {
    http_response_code(404);
    echo 'Módulo no válido o inactivo.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Caja - <?= htmlspecialchars($modulo['nombre'], ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="assets/js/app.js" defer></script>
<style>
    :root {
        --green-main:#059669;
        --green-dark:#047857;
        --green-soft:#d1fae5;
        --bg:#ecfdf5;
        --card-bg:#ffffff;
        --text:#022c22;
        --muted:#4b5563;
        --danger:#dc2626;
        --danger-dark:#b91c1c;
        --accent:#2563eb;
    }
    * { box-sizing:border-box; }
    body {
        margin:0;
        font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        background:var(--bg);
        color:var(--text);
    }
    header {
        padding:1rem 1.5rem;
        background:var(--green-main);
        color:#ecfdf5;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }
    header h1 {
        margin:0;
        font-size:1.6rem;
        display:flex;
        align-items:center;
        gap:0.4rem;
    }
    header h1 span.badge {
        font-size:0.9rem;
        padding:0.1rem 0.5rem;
        border-radius:999px;
        background:rgba(6,182,212,0.18);
        border:1px solid rgba(255,255,255,0.5);
    }
    header .user {
        font-size:0.9rem;
        opacity:0.95;
    }

    main {
        max-width:900px;
        margin:1.5rem auto 2rem;
        padding:0 1rem;
    }
    .card {
        background:var(--card-bg);
        border-radius:1rem;
        padding:1.5rem 1.7rem;
        box-shadow:0 10px 25px rgba(15,118,110,0.18);
        border:1px solid rgba(16,185,129,0.25);
    }
    .card-title {
        font-size:1.05rem;
        font-weight:600;
        margin:0 0 0.5rem;
        color:var(--green-dark);
        display:flex;
        align-items:center;
        gap:0.4rem;
    }
    .card-title span.icon {
        width:1.7rem;
        height:1.7rem;
        border-radius:999px;
        background:var(--green-soft);
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:1.1rem;
    }
    .card-desc {
        margin:0;
        font-size:0.9rem;
        color:var(--muted);
        margin-bottom:1.2rem;
    }

    .numero-label {
        font-size:0.95rem;
        color:var(--muted);
        margin-bottom:0.3rem;
    }
    .numero-grande {
        font-size:4.2rem;
        font-weight:800;
        text-align:center;
        padding:0.9rem 0;
        margin-bottom:1.3rem;
        border-radius:0.9rem;
        background:var(--green-soft);
        color:var(--green-dark);
        letter-spacing:0.08em;
        border:2px solid rgba(16,185,129,0.5);
    }

    .btn-group {
        display:flex;
        flex-wrap:wrap;
        gap:0.8rem;
        justify-content:center;
        margin-bottom:0.6rem;
    }
    .btn {
        min-width:180px;
        padding:0.9rem 1rem;
        border-radius:999px;
        border:none;
        font-size:1rem;
        font-weight:700;
        cursor:pointer;
        color:#ffffff;
        display:flex;
        align-items:center;
        justify-content:center;
        gap:0.4rem;
        transition:transform 0.05s ease-out, box-shadow 0.05s ease-out, background 0.1s;
        box-shadow:0 6px 15px rgba(0,0,0,0.15);
    }
    .btn:active {
        transform:translateY(1px) scale(0.99);
        box-shadow:0 3px 8px rgba(0,0,0,0.18);
    }
    .btn-primary {
        background:var(--green-main);
    }
    .btn-primary:hover {
        background:var(--green-dark);
    }
    .btn-secondary {
        background:var(--accent);
    }
    .btn-secondary:hover {
        background:#1d4ed8;
    }
    .btn-danger {
        background:var(--danger);
    }
    .btn-danger:hover {
        background:var(--danger-dark);
    }

    .hint {
        text-align:center;
        font-size:0.85rem;
        color:var(--muted);
        margin-top:0.4rem;
    }

    @media (max-width:640px) {
        header {
            flex-direction:column;
            align-items:flex-start;
            gap:0.3rem;
        }
        header h1 { font-size:1.3rem; }
        main { padding:0 0.6rem; }
        .numero-grande { font-size:3rem; }
        .btn {
            min-width:100%;
            font-size:0.95rem;
        }
    }
</style>
</head>
<body>

<header>
    <div>
        <h1>
            Caja <?= htmlspecialchars($modulo['codigo'], ENT_QUOTES, 'UTF-8') ?>
            <span class="badge"><?= htmlspecialchars($modulo['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
        </h1>
    </div>
    <div class="user">
        Usuario: <?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        <form method="post" action="liberar_caja.php" style="display:inline-block; margin-left:0.5rem;">
            <button type="submit"
                    style="border:none; border-radius:999px; padding:0.25rem 0.7rem; font-size:0.8rem;
                           background:#ef4444; color:#fff; cursor:pointer;">
                Liberar caja
            </button>
        </form>
    </div>
</header>


<main>
    <div class="card" id="panel-caja" data-modulo-id="<?= (int)$modulo['id'] ?>">
        <div class="card-title">
            <span class="icon">🔔</span>
            <span>Panel de llamado</span>
        </div>
        <p class="card-desc">
            Use los botones para llamar el siguiente número, volver a llamar al actual
            o finalizar la atención. El número se mostrará en verde y se enviará a la pantalla pública.
        </p>

        <div class="numero-label">Número actual</div>
        <div id="numero-actual" class="numero-grande">--</div>

        <div class="btn-group">
            <button type="button" class="btn btn-primary" onclick="llamarSiguiente()">
                ▶ Llamar siguiente
            </button>
            <button type="button" class="btn btn-secondary" onclick="rellamar()">
                🔁 Re-llamar
            </button>
            <button type="button" class="btn btn-danger" onclick="finalizar()">
                ✔ Finalizar
            </button>
        </div>

        <div class="hint">
            Sugerencia: mantenga esta ventana siempre visible para operar la caja con comodidad.
        </div>
    </div>
</main>

</body>
</html>
