<?php
// admin_modulos.php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getPDO();

$accion         = $_POST['accion'] ?? '';
$mensaje_ok     = '';
$mensaje_error  = '';
$modulo_editar  = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '' || $codigo === '') {
                throw new RuntimeException('Nombre y código son obligatorios.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO modulos (nombre, codigo, activo)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$nombre, $codigo, $activo]);
            $mensaje_ok = 'Módulo creado correctamente.';

        } elseif ($accion === 'guardar_edicion') {
            $id     = (int)($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($id <= 0) {
                throw new RuntimeException('ID inválido.');
            }
            if ($nombre === '' || $codigo === '') {
                throw new RuntimeException('Nombre y código son obligatorios.');
            }

            $stmt = $pdo->prepare("
                UPDATE modulos
                SET nombre = ?, codigo = ?, activo = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $codigo, $activo, $id]);
            $mensaje_ok = 'Módulo actualizado correctamente.';

        } elseif ($accion === 'cargar_edicion') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM modulos WHERE id = ?");
                $stmt->execute([$id]);
                $modulo_editar = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$modulo_editar) {
                    throw new RuntimeException('Módulo no encontrado.');
                }
            }
        } elseif ($accion === 'liberar_modulo') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('ID inválido.');
            }
            $stmt = $pdo->prepare("UPDATE modulos SET usuario_en_uso = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $mensaje_ok = 'Módulo liberado (sin usuario en uso).';
        }
    }
} catch (Throwable $e) {
    $mensaje_error = $e->getMessage();
}

$stmt = $pdo->query("
    SELECT m.id, m.nombre, m.codigo, m.activo, m.usuario_en_uso, u.nombre AS nombre_usuario
    FROM modulos m
    LEFT JOIN usuarios u ON u.id = m.usuario_en_uso
    ORDER BY m.id ASC
");
$modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Administrar módulos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root {
        --green-main:#059669;
        --green-dark:#047857;
        --green-soft:#d1fae5;
        --bg:#ecfdf5;
        --card-bg:#ffffff;
        --border:#d1d5db;
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
        background:var(--bg);
        color:var(--text);
    }
    header {
        padding:1rem 1.8rem;
        background:var(--green-main);
        color:#ecfdf5;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }
    header h1 { margin:0; font-size:1.6rem; }
    header .sub { font-size:0.9rem; opacity:0.9; }
    header .actions a {
        color:#ecfdf5;
        text-decoration:none;
        font-size:0.85rem;
        padding:0.3rem 0.8rem;
        border-radius:999px;
        border:1px solid rgba(209,250,229,0.9);
    }
    header .actions a:hover { background:rgba(22,163,74,0.9); }

    main { max-width:1200px; margin:1.5rem auto 2rem; padding:0 1rem; }

    .mensaje-ok, .mensaje-error {
        padding:0.7rem 1rem;
        border-radius:0.5rem;
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

    table {
        width:100%;
        border-collapse:collapse;
        background:var(--card-bg);
        border-radius:0.75rem;
        overflow:hidden;
        box-shadow:0 4px 12px rgba(0,0,0,0.06);
    }
    th,td {
        padding:0.65rem 0.75rem;
        font-size:0.9rem;
        border-bottom:1px solid #e5e7eb;
        text-align:left;
    }
    th {
        background:#dcfce7;
        font-weight:600;
    }
    tr:last-child td { border-bottom:none; }

    .badge {
        display:inline-block;
        padding:0.1rem 0.5rem;
        border-radius:999px;
        font-size:0.75rem;
        font-weight:600;
    }
    .badge-activo { background:#bbf7d0; color:#166534; }
    .badge-inactivo { background:#fee2e2; color:#b91c1c; }
    .badge-uso { background:#fef3c7; color:#92400e; }

    .btn {
        padding:0.35rem 0.7rem;
        border-radius:999px;
        border:none;
        font-size:0.8rem;
        cursor:pointer;
        background:var(--green-main);
        color:#fff;
    }
    .btn:hover { background:var(--green-dark); }
    .btn-secondary {
        background:#e5e7eb;
        color:#111827;
    }
    .btn-secondary:hover { background:#d1d5db; }

    form.inline { display:inline-flex; align-items:center; gap:0.25rem; }

    .panel {
        display:flex;
        flex-wrap:wrap;
        gap:1.5rem;
        margin-top:1.5rem;
    }
    .card {
        flex:1 1 340px;
        background:var(--card-bg);
        padding:1.2rem 1.4rem;
        border-radius:0.75rem;
        box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }
    .card h3 { margin-top:0; margin-bottom:0.6rem; font-size:1.1rem; }
    .card p.desc { margin-top:0; font-size:0.85rem; color:var(--muted); margin-bottom:0.8rem; }

    label { display:block; margin-bottom:0.55rem; font-size:0.85rem; }
    label span { display:block; margin-bottom:0.15rem; }
    input[type="text"],
    select {
        width:100%;
        padding:0.4rem 0.55rem;
        border-radius:0.4rem;
        border:1px solid var(--border);
        font-size:0.9rem;
    }
    input:focus, select:focus {
        outline:none;
        border-color:var(--green-main);
        box-shadow:0 0 0 2px rgba(34,197,94,0.35);
    }
    .chk-inline {
        display:flex;
        align-items:center;
        gap:0.4rem;
        font-size:0.85rem;
        margin-top:0.2rem;
    }

    @media (max-width:768px) {
        header { flex-direction:column; align-items:flex-start; gap:0.3rem; }
        main { padding:0 0.5rem; }
    }
</style>
</head>
<body>

<header>
    <div>
        <h1>Administración de módulos</h1>
        <div class="sub">Cajas de atención del sistema de filas</div>
    </div>
    <div class="actions">
        <a href="index.php">Volver al inicio</a>
    </div>
</header>

<main>

<?php if ($mensaje_ok): ?>
    <div class="mensaje-ok"><?= htmlspecialchars($mensaje_ok, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($mensaje_error): ?>
    <div class="mensaje-error"><?= htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<h2 style="font-size:1rem; margin-bottom:0.4rem;">Módulos configurados</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Código</th>
            <th>Estado</th>
            <th>En uso por</th>
            <th style="width:260px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($modulos as $m): ?>
        <tr>
            <td><?= (int)$m['id'] ?></td>
            <td><?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($m['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <?php if ($m['activo']): ?>
                    <span class="badge badge-activo">Activo</span>
                <?php else: ?>
                    <span class="badge badge-inactivo">Inactivo</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($m['usuario_en_uso']): ?>
                    <span class="badge badge-uso">
                        <?= htmlspecialchars($m['nombre_usuario'] ?: ('ID '.$m['usuario_en_uso']), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php else: ?>
                    <span style="font-size:0.8rem; color:var(--muted);">Libre</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="accion" value="cargar_edicion">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="btn-secondary">Editar</button>
                </form>
                <?php if ($m['usuario_en_uso']): ?>
                    <form method="post" class="inline"
                          onsubmit="return confirm('¿Liberar este módulo? El usuario dejará de aparecer como en uso.');">
                        <input type="hidden" name="accion" value="liberar_modulo">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="btn">Liberar</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="panel">

    <div class="card">
        <h3>Crear módulo</h3>
        <p class="desc">Agregue una nueva caja/módulo de atención al sistema.</p>
        <form method="post">
            <input type="hidden" name="accion" value="crear">
            <label>
                <span>Nombre</span>
                <input type="text" name="nombre" required>
            </label>
            <label>
                <span>Código (ej: C1, C2)</span>
                <input type="text" name="codigo" maxlength="10" required>
            </label>
            <div class="chk-inline">
                <input type="checkbox" name="activo" id="crear_activo" checked>
                <label for="crear_activo">Módulo activo</label>
            </div>
            <div style="margin-top:0.8rem;">
                <button type="submit" class="btn">Crear módulo</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Editar módulo</h3>
        <p class="desc">Use el botón “Editar” en la tabla para cargar aquí un módulo.</p>
        <?php if ($modulo_editar): ?>
            <form method="post">
                <input type="hidden" name="accion" value="guardar_edicion">
                <input type="hidden" name="id" value="<?= (int)$modulo_editar['id'] ?>">

                <label>
                    <span>Nombre</span>
                    <input type="text" name="nombre"
                           value="<?= htmlspecialchars($modulo_editar['nombre'], ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>
                    <span>Código</span>
                    <input type="text" name="codigo" maxlength="10"
                           value="<?= htmlspecialchars($modulo_editar['codigo'], ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <div class="chk-inline">
                    <input type="checkbox" name="activo" id="editar_activo"
                           <?= $modulo_editar['activo'] ? 'checked' : '' ?>>
                    <label for="editar_activo">Módulo activo</label>
                </div>
                <div style="margin-top:0.8rem;">
                    <button type="submit" class="btn">Guardar cambios</button>
                </div>
            </form>
        <?php else: ?>
            <p style="font-size:0.9rem; color:var(--muted);">Aún no hay módulo cargado.</p>
        <?php endif; ?>
    </div>

</div>

</main>
</body>
</html>
