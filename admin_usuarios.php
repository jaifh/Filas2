<?php
// admin_usuarios.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$accion = $_POST['accion'] ?? '';
$mensaje_ok = '';
$mensaje_error = '';
$usuario_editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($accion === 'crear') {
            $username = trim($_POST['username'] ?? '');
            $nombre   = trim($_POST['nombre'] ?? '');
            $rol      = $_POST['rol'] ?? 'CAJA';
            $activo   = isset($_POST['activo']) ? 1 : 0;
            $pass     = $_POST['password'] ?? '';

            if ($username === '' || $nombre === '' || $pass === '') {
                throw new RuntimeException('Usuario, nombre y contraseña son obligatorios.');
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (username, password_hash, nombre, rol, activo)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hash, $nombre, $rol, $activo]);
            $mensaje_ok = 'Usuario creado correctamente.';

        } elseif ($accion === 'guardar_edicion') {
            $id       = (int)($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $nombre   = trim($_POST['nombre'] ?? '');
            $rol      = $_POST['rol'] ?? 'CAJA';
            $activo   = isset($_POST['activo']) ? 1 : 0;

            if ($id <= 0) throw new RuntimeException('ID inválido.');
            if ($username === '' || $nombre === '') {
                throw new RuntimeException('Usuario y nombre son obligatorios.');
            }

            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET username = ?, nombre = ?, rol = ?, activo = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $nombre, $rol, $activo, $id]);
            $mensaje_ok = 'Usuario actualizado correctamente.';

        } elseif ($accion === 'reset_pass') {
            $id   = (int)($_POST['id'] ?? 0);
            $pass = $_POST['password'] ?? '';
            if ($id <= 0)  throw new RuntimeException('ID inválido.');
            if ($pass === '') throw new RuntimeException('Debe indicar nueva contraseña.');

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $id]);
            $mensaje_ok = 'Contraseña actualizada.';

        } elseif ($accion === 'cargar_edicion') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $usuario_editar = $stmt->fetch();
                if (!$usuario_editar) {
                    throw new RuntimeException('Usuario no encontrado.');
                }
            }
        }
    } catch (Throwable $e) {
        $mensaje_error = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT id, username, nombre, rol, activo, creado_en FROM usuarios ORDER BY id ASC");
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Administrar usuarios</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root {
        --primary:#0055aa;
        --primary-dark:#003f80;
        --bg:#f3f4f6;
        --card-bg:#ffffff;
        --border:#d1d5db;
        --text:#111827;
        --muted:#6b7280;
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
        background:#003366;
        color:#fff;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }
    header h1 { margin:0; font-size:1.5rem; }
    header .sub { font-size:0.9rem; opacity:0.9; }
    header .actions a {
        color:#fff;
        text-decoration:none;
        font-size:0.85rem;
        padding:0.35rem 0.8rem;
        border-radius:999px;
        border:1px solid rgba(255,255,255,0.4);
    }
    header .actions a:hover {
        background:rgba(255,255,255,0.15);
    }

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
        box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }
    th,td {
        padding:0.6rem 0.75rem;
        font-size:0.9rem;
        border-bottom:1px solid #e5e7eb;
    }
    th {
        background:#f9fafb;
        text-align:left;
        font-weight:600;
    }
    tr:last-child td { border-bottom:none; }
    .badge {
        display:inline-block;
        padding:0.1rem 0.45rem;
        border-radius:999px;
        font-size:0.75rem;
        font-weight:600;
    }
    .badge-admin { background:#dbeafe; color:#1d4ed8; }
    .badge-caja  { background:#dcfce7; color:#15803d; }
    .badge-visor { background:#fef9c3; color:#92400e; }
    .badge-activo { background:#bbf7d0; color:#166534; }
    .badge-inactivo { background:#fee2e2; color:#b91c1c; }

    .btn {
        padding:0.35rem 0.7rem;
        border-radius:999px;
        border:none;
        font-size:0.8rem;
        cursor:pointer;
        background:var(--primary);
        color:#fff;
    }
    .btn:hover { background:var(--primary-dark); }
    .btn-secondary {
        background:#e5e7eb;
        color:#111827;
    }
    .btn-secondary:hover {
        background:#d1d5db;
    }

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
    input[type="password"],
    select {
        width:100%;
        padding:0.4rem 0.55rem;
        border-radius:0.4rem;
        border:1px solid var(--border);
        font-size:0.9rem;
    }
    input:focus, select:focus {
        outline:none;
        border-color:var(--primary);
        box-shadow:0 0 0 2px rgba(37,99,235,0.15);
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
        <h1>Administración de usuarios</h1>
        <div class="sub">Perfiles de acceso al sistema de filas</div>
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

<h2 style="font-size:1rem; margin-bottom:0.4rem;">Usuarios registrados</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Nombre</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Creado</th>
            <th style="width:260px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <?php
                $rol = $u['rol'];
                $badgeClass = $rol === 'ADMIN' ? 'badge-admin' : ($rol === 'CAJA' ? 'badge-caja' : 'badge-visor');
                ?>
                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td>
                <?php if ($u['activo']): ?>
                    <span class="badge badge-activo">Activo</span>
                <?php else: ?>
                    <span class="badge badge-inactivo">Inactivo</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($u['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="accion" value="cargar_edicion">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn-secondary">Editar</button>
                </form>
                <form method="post" class="inline">
                    <input type="hidden" name="accion" value="reset_pass">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input type="password" name="password" placeholder="Nueva clave" style="width:110px;">
                    <button type="submit" class="btn">Cambiar clave</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="panel">
    <div class="card">
        <h3>Crear usuario</h3>
        <p class="desc">Agregar nuevos usuarios (cajas, visores o administradores) al sistema.</p>
        <form method="post">
            <input type="hidden" name="accion" value="crear">
            <label>
                <span>Usuario</span>
                <input type="text" name="username" required>
            </label>
            <label>
                <span>Nombre</span>
                <input type="text" name="nombre" required>
            </label>
            <label>
                <span>Rol</span>
                <select name="rol">
                    <option value="ADMIN">ADMIN</option>
                    <option value="CAJA" selected>CAJA</option>
                    <option value="VISOR">VISOR</option>
                </select>
            </label>
            <label>
                <span>Contraseña inicial</span>
                <input type="password" name="password" required>
            </label>
            <div class="chk-inline">
                <input type="checkbox" name="activo" checked id="crear_activo">
                <label for="crear_activo">Usuario activo</label>
            </div>
            <div style="margin-top:0.8rem;">
                <button type="submit" class="btn">Crear usuario</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Editar usuario</h3>
        <p class="desc">Seleccione “Editar” en la tabla superior para cargar aquí los datos del usuario.</p>
        <?php if ($usuario_editar): ?>
            <form method="post">
                <input type="hidden" name="accion" value="guardar_edicion">
                <input type="hidden" name="id" value="<?= (int)$usuario_editar['id'] ?>">

                <label>
                    <span>Usuario</span>
                    <input type="text" name="username"
                           value="<?= htmlspecialchars($usuario_editar['username'], ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>
                    <span>Nombre</span>
                    <input type="text" name="nombre"
                           value="<?= htmlspecialchars($usuario_editar['nombre'], ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label>
                    <span>Rol</span>
                    <select name="rol">
                        <option value="ADMIN" <?= $usuario_editar['rol']==='ADMIN'?'selected':'' ?>>ADMIN</option>
                        <option value="CAJA"  <?= $usuario_editar['rol']==='CAJA'?'selected':'' ?>>CAJA</option>
                        <option value="VISOR" <?= $usuario_editar['rol']==='VISOR'?'selected':'' ?>>VISOR</option>
                    </select>
                </label>
                <div class="chk-inline">
                    <input type="checkbox" name="activo" id="editar_activo"
                           <?= $usuario_editar['activo'] ? 'checked' : '' ?>>
                    <label for="editar_activo">Usuario activo</label>
                </div>
                <div style="margin-top:0.8rem;">
                    <button type="submit" class="btn">Guardar cambios</button>
                </div>
            </form>
        <?php else: ?>
            <p style="font-size:0.9rem; color:var(--muted);">Aún no hay usuario cargado.</p>
        <?php endif; ?>
    </div>
</div>

</main>
</body>
</html>
