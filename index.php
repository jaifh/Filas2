<?php
// index.php
require_once __DIR__ . '/auth.php';
requireLogin(['ADMIN','CAJA']); // debe estar logueado
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo        = getPDO();
$esAdmin    = ($_SESSION['rol'] ?? '') === 'ADMIN';
$usuario_id = (int)($_SESSION['id'] ?? 0);

// mensajes flash desde PRG
$mensaje_ok    = $_SESSION['flash_ok']    ?? '';
$mensaje_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

// 1) Selección de módulo, bloqueando si ya lo usa otro usuario
if (($_POST['accion'] ?? '') === 'set_modulo') {
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);

    if ($modulo_id > 0 && $usuario_id > 0) {
        $stmt = $pdo->prepare("
            SELECT m.usuario_en_uso, u.nombre AS nombre_usuario
            FROM modulos m
            LEFT JOIN usuarios u ON u.id = m.usuario_en_uso
            WHERE m.id = ? AND m.activo = 1
        ");
        $stmt->execute([$modulo_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $_SESSION['flash_error'] = 'Módulo no válido.';
        } else {
            $enUso = (int)($row['usuario_en_uso'] ?? 0);

            if ($enUso !== 0 && $enUso !== $usuario_id) {
                $nombreOcupando       = $row['nombre_usuario'] ?: ('ID ' . $enUso);
                $_SESSION['flash_error'] = 'Este módulo ya está siendo usado por: ' . $nombreOcupando . '.';
            } else {
                // Libera cualquier módulo que estuviera usando este usuario
                $stmt = $pdo->prepare("UPDATE modulos SET usuario_en_uso = NULL WHERE usuario_en_uso = ?");
                $stmt->execute([$usuario_id]);

                // Asigna este módulo al usuario actual
                $stmt = $pdo->prepare("UPDATE modulos SET usuario_en_uso = ? WHERE id = ?");
                $stmt->execute([$usuario_id, $modulo_id]);

                $_SESSION['modulo_id'] = $modulo_id;
                $_SESSION['flash_ok']  = 'Módulo del equipo configurado correctamente.';
            }
        }
    } else {
        $_SESSION['flash_error'] = 'Debe seleccionar un módulo válido y estar identificado.';
    }

    header('Location: index.php');
    exit;
}

// 2) Configuración de numeración (ADMIN) + respaldo de tickets
if ($esAdmin && ($_POST['accion'] ?? '') === 'config') {
    $prefijo = trim($_POST['prefijo_cola'] ?? 'P');
    $inicio  = (int)($_POST['numero_inicial'] ?? 1);
    if ($inicio < 1) {
        $inicio = 1;
    }

    try {
        $pdo->beginTransaction();

        // 1. Respaldar tickets en tickets_logs
        $sqlInsertLog = "
            INSERT INTO tickets_logs (
                ticket_id, numero, prefijo, modulo_id, estado,
                fecha, hora_creacion, hora_llamado, hora_fin
            )
            SELECT
                id, numero, prefijo, modulo_id, estado,
                fecha, hora_creacion, hora_llamado, hora_fin
            FROM tickets
        ";
        $pdo->exec($sqlInsertLog);

        // 2. Limpiar tickets
        $pdo->exec("DELETE FROM tickets");

        // 3. Actualizar configuración
        $stmt = $pdo->prepare("
            INSERT INTO configuracion (id, prefijo_cola, numero_inicial, fecha_config)
            VALUES (1, ?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE
                prefijo_cola   = VALUES(prefijo_cola),
                numero_inicial = VALUES(numero_inicial),
                fecha_config   = VALUES(fecha_config)
        ");
        $stmt->execute([$prefijo, $inicio]);

        $pdo->commit();

        $_SESSION['flash_ok'] = 'Configuración actualizada con éxito.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_error'] = 'Error al guardar: ' . $e->getMessage();
    }

    header('Location: index.php');
    exit;
}

// 3) Configuración actual
$stmt = $pdo->prepare("SELECT prefijo_cola, numero_inicial, fecha_config FROM configuracion WHERE id = 1");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);
$prefijo_actual = $config['prefijo_cola'] ?? 'P';
$inicio_actual  = (int)($config['numero_inicial'] ?? 1);

// 4) Módulos
$stmtMod = $pdo->prepare("
    SELECT m.id, m.nombre, m.codigo, m.activo, m.usuario_en_uso, u.nombre AS nombre_usuario
    FROM modulos m
    LEFT JOIN usuarios u ON u.id = m.usuario_en_uso
    ORDER BY m.id
");
$stmtMod->execute();
$modulos = $stmtMod->fetchAll(PDO::FETCH_ASSOC);

$modulo_actual_id = (int)($_SESSION['modulo_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Permisos de Circulación - Inicio</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root {
        --green-main:#059669;
        --green-dark:#047857;
        --green-soft:#d1fae5;
        --green-bg:#ecfdf5;
        --accent:#2563eb;
        --accent-dark:#1d4ed8;
        --card-bg:#ffffff;
        --text:#022c22;
        --muted:#4b5563;
        --success-bg:#d1fae5;
        --success-border:#10b981;
        --danger-bg:#fee2e2;
        --danger-border:#ef4444;
    }
    * { box-sizing:border-box; }
    body {
        margin:0;
        font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        background:var(--green-bg);
        color:var(--text);
    }
    header {
        padding:1rem 1.8rem;
        background:var(--green-main);
        color:#ecfdf5;
        display:flex;
        justify-content:space-between;
        align-items:center;
        box-shadow:0 8px 20px rgba(16,185,129,0.45);
    }
    header h1 {
        margin:0;
        font-size:1.7rem;
        display:flex;
        align-items:center;
        gap:0.4rem;
    }
    header h1 span.badge {
        font-size:0.85rem;
        padding:0.15rem 0.6rem;
        border-radius:999px;
        background:rgba(15,118,110,0.25);
        border:1px solid rgba(209,250,229,0.8);
    }
    header .sub {
        font-size:0.85rem;
        opacity:0.95;
    }
    header .links {
        display:flex;
        align-items:center;
        gap:0.4rem;
        flex-wrap:wrap;
        justify-content:flex-end;
    }
    header .links a {
        color:#ecfdf5;
        text-decoration:none;
        font-size:0.82rem;
        padding:0.25rem 0.75rem;
        border-radius:999px;
        border:1px solid rgba(209,250,229,0.8);
        background:rgba(6,95,70,0.3);
    }
    header .links a:hover {
        background:rgba(22,163,74,0.9);
    }

    main {
        max-width:1100px;
        margin:1.7rem auto 2.2rem;
        padding:0 1rem;
    }

    .mensaje-ok, .mensaje-error {
        padding:0.7rem 1rem;
        border-radius:0.7rem;
        margin-bottom:1rem;
        font-size:0.9rem;
    }
    .mensaje-ok {
        background:var(--success-bg);
        border:1px solid var(--success-border);
        color:#065f46;
    }
    .mensaje-error {
        background:var(--danger-bg);
        border:1px solid var(--danger-border);
        color:#991b1b;
    }

    .grid {
        display:flex;
        flex-wrap:wrap;
        gap:1.4rem;
        margin-top:0.4rem;
    }
    .card {
        flex:1 1 340px;
        background:var(--card-bg);
        border-radius:1rem;
        padding:1.2rem 1.4rem;
        box-shadow:0 10px 25px rgba(15,118,110,0.18);
        border:1px solid rgba(16,185,129,0.25);
    }
    .card h2 {
        margin-top:0;
        font-size:1.1rem;
        color:var(--green-dark);
        display:flex;
        align-items:center;
        gap:0.35rem;
    }
    .card h2 span.icon {
        width:1.6rem;
        height:1.6rem;
        border-radius:999px;
        background:var(--green-soft);
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:1rem;
    }
    .card p.desc {
        margin-top:0.1rem;
        font-size:0.86rem;
        color:var(--muted);
        margin-bottom:0.9rem;
    }

    label {
        display:block;
        margin-bottom:0.55rem;
        font-size:0.9rem;
    }
    label span {
        display:block;
        margin-bottom:0.15rem;
    }
    select, input[type="text"], input[type="number"] {
        width:100%;
        padding:0.45rem 0.6rem;
        border-radius:0.5rem;
        border:1px solid #d1d5db;
        font-size:0.9rem;
    }
    select:focus, input:focus {
        outline:none;
        border-color:var(--green-main);
        box-shadow:0 0 0 2px rgba(34,197,94,0.35);
    }

    .btn {
        display:inline-block;
        padding:0.55rem 1.4rem;
        border-radius:999px;
        border:none;
        font-size:0.9rem;
        font-weight:600;
        cursor:pointer;
        color:#ffffff;
        background:var(--green-main);
        box-shadow:0 6px 15px rgba(16,185,129,0.35);
    }
    .btn:hover {
        background:var(--green-dark);
    }
    .btn-accent {
        background:var(--accent);
        box-shadow:0 6px 15px rgba(37,99,235,0.3);
    }
    .btn-accent:hover {
        background:var(--accent-dark);
    }

    .nota {
        font-size:0.8rem;
        color:var(--muted);
        margin-top:0.5rem;
    }

    .badge-ocupado {
        display:inline-block;
        padding:0.1rem 0.5rem;
        border-radius:999px;
        font-size:0.7rem;
        background:rgba(248,113,113,0.16);
        color:#b91c1c;
        border:1px solid rgba(248,113,113,0.7);
        margin-left:0.35rem;
    }
    .badge-libre {
        display:inline-block;
        padding:0.1rem 0.5rem;
        border-radius:999px;
        font-size:0.7rem;
        background:rgba(187,247,208,0.45);
        color:#166534;
        border:1px solid rgba(22,163,74,0.6);
        margin-left:0.35rem;
    }

    @media (max-width:768px) {
        header {
            flex-direction:column;
            align-items:flex-start;
            gap:0.4rem;
        }
        main {
            padding:0 0.6rem;
        }
        .card {
            flex:1 1 100%;
        }
    }
</style>
</head>
<body>

<header>
    <div>
        <h1>
            Permisos de Circulación
            <span class="badge">Sistema de filas</span>
        </h1>
        <div class="sub">Módulos de atención y numeración diaria</div>
    </div>
    <div class="links">
        <a href="display.php" target="_blank">Pantalla pública</a>
        <?php if ($esAdmin): ?>
            <a href="dashboard.php" target="_blank">Dashboard</a>
            <a href="dashboard_calendario.php" target="_blank">Informe diario</a>
            <a href="admin_usuarios.php" target="_blank">Usuarios</a>
            <a href="admin_modulos.php" target="_blank">Módulos</a>
        <?php endif; ?>
        <span style="font-size:0.82rem; padding:0.2rem 0.6rem; border-radius:999px; background:rgba(5,150,105,0.25); border:1px solid rgba(209,250,229,0.9);">
            <?= htmlspecialchars($_SESSION['nombre'].' ('.($_SESSION['rol'] ?? '').')', ENT_QUOTES, 'UTF-8') ?>
        </span>
        <a href="logout.php">Cerrar sesión</a>
    </div>
</header>

<main>

<?php if ($mensaje_ok): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje_ok, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($mensaje_error): ?>
    <div class="mensaje-error"><?= htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="grid">

    <!-- Selección de módulo -->
    <div class="card">
        <h2><span class="icon">💻</span><span>Módulo de este equipo</span></h2>
        <p class="desc">
            Seleccione el módulo que trabajará en este computador. Si el módulo está siendo usado por otro usuario aparecerá bloqueado indicando quién lo usa.
        </p>

        <form method="post">
            <input type="hidden" name="accion" value="set_modulo">
            <label>
                <span>Módulo</span>
                <select name="modulo_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($modulos as $m): ?>
                        <?php
                            $enUso   = (int)($m['usuario_en_uso'] ?? 0);
                            $ocupado = $enUso !== 0 && $enUso !== $usuario_id;
                            $esMio   = $enUso !== 0 && $enUso === $usuario_id;

                            $selected = ((int)$m['id'] === $modulo_actual_id) ? 'selected' : '';
                            $disabled = $ocupado ? 'disabled' : '';

                            $etiqueta = $m['nombre'].' ('.$m['codigo'].')';
                            if ($ocupado) {
                                $etiqueta .= ' - Ocupado por '.$m['nombre_usuario'];
                            } elseif ($esMio) {
                                $etiqueta .= ' - En uso por usted';
                            }
                        ?>
                        <option value="<?= (int)$m['id'] ?>" <?= $selected ?> <?= $disabled ?>>
                            <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn">Guardar módulo</button>
        </form>

        <?php if ($modulo_actual_id): ?>
            <?php
                $mSel = null;
                foreach ($modulos as $m) {
                    if ((int)$m['id'] === $modulo_actual_id) {
                        $mSel = $m;
                        break;
                    }
                }
            ?>
            <?php if ($mSel): ?>
                <p class="nota">
                    Módulo actual del equipo:
                    <?= htmlspecialchars($mSel['nombre'].' ('.$mSel['codigo'].')', ENT_QUOTES, 'UTF-8') ?>
                    <?php if ((int)($mSel['usuario_en_uso'] ?? 0) === $usuario_id): ?>
                        <span class="badge-libre">En uso por usted</span>
                    <?php elseif (!empty($mSel['usuario_en_uso'])): ?>
                        <span class="badge-ocupado">Ocupado por <?= htmlspecialchars($mSel['nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </p>
                <p class="nota">
                    Abrir panel de caja:<br>
                    <a href="caja.php" target="_blank" class="btn btn-accent" style="margin-top:0.3rem; display:inline-block;">
                        Ir a caja de este módulo
                    </a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Configuración de numeración (solo ADMIN) -->
    <?php if ($esAdmin): ?>
    <div class="card">
        <h2><span class="icon">🔢</span><span>Numeración diaria</span></h2>
        <p class="desc">
            Defina el prefijo y número inicial del día. Al guardar, todos los tickets se respaldan en <code>tickets_logs</code> y la numeración vuelve a comenzar desde el valor indicado.
        </p>

        <form method="post"
              onsubmit="return confirm('¿Está seguro? Se respaldarán los tickets actuales en tickets_logs y la numeración volverá al número inicial indicado.');">
            <input type="hidden" name="accion" value="config">
            <label>
                <span>Prefijo (ej: P)</span>
                <input type="text" name="prefijo_cola" maxlength="5"
                       value="<?= htmlspecialchars($prefijo_actual, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                <span>Número inicial del día</span>
                <input type="number" name="numero_inicial" min="1"
                       value="<?= (int)$inicio_actual ?>">
            </label>
            <button type="submit" class="btn">Guardar configuración</button>
        </form>

        <p class="nota">
            Correlativo diario actual: comienza en
            <?= htmlspecialchars($prefijo_actual, ENT_QUOTES, 'UTF-8') ?>-
            <?= str_pad((string)$inicio_actual, 3, '0', STR_PAD_LEFT) ?>.
        </p>
    </div>
    <?php endif; ?>

</div>

</main>

</body>
</html>
