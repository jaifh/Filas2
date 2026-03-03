<?php
// index.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN', 'ADMIN', 'CAJA']);
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$rol = $_SESSION['rol'] ?? '';
$esSuperAdmin = ($rol === 'SUPER-ADMIN');
$esAdmin      = ($rol === 'ADMIN');
$usuario_id   = (int)($_SESSION['id'] ?? 0);
$dept_id      = $_SESSION['departamento_id'] ?? null;

// Mensajes flash
$mensaje_ok    = $_SESSION['flash_ok']    ?? '';
$mensaje_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

// 1) LÓGICA DE MÓDULOS (Solo para ADMIN y CAJA)
if (!$esSuperAdmin && ($_POST['accion'] ?? '') === 'set_modulo') {
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    if ($modulo_id > 0 && $usuario_id > 0) {
        $stmt = $pdo->prepare("SELECT usuario_en_uso, nombre FROM modulos WHERE id = ? AND departamento_id = ? AND activo = 1");
        $stmt->execute([$modulo_id, $dept_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $_SESSION['flash_error'] = 'Módulo no válido o no pertenece a su departamento.';
        } else {
            $enUso = (int)($row['usuario_en_uso'] ?? 0);
            if ($enUso !== 0 && $enUso !== $usuario_id) {
                $_SESSION['flash_error'] = 'Este módulo ya está ocupado.';
            } else {
                $pdo->prepare("UPDATE modulos SET usuario_en_uso = NULL WHERE usuario_en_uso = ?")->execute([$usuario_id]);
                $pdo->prepare("UPDATE modulos SET usuario_en_uso = ? WHERE id = ?")->execute([$usuario_id, $modulo_id]);
                $_SESSION['modulo_id'] = $modulo_id;
                $_SESSION['flash_ok']  = 'Módulo configurado correctamente.';
            }
        }
    }
    header('Location: index.php'); exit;
}

// 2) CONFIGURACIÓN DE NUMERACIÓN (Solo para ADMIN de ese departamento)
if ($esAdmin && ($_POST['accion'] ?? '') === 'config' && $dept_id) {
    $prefijo      = trim($_POST['prefijo_cola'] ?? 'N');
    $inicio       = max(1, (int)($_POST['numero_inicial'] ?? 1));
    $prefijo_pref = trim($_POST['prefijo_preferencial'] ?? 'P');
    $inicio_pref  = max(1, (int)($_POST['numero_inicial_preferencial'] ?? 1));

    try {
        $pdo->beginTransaction();
        // Respaldar solo los tickets de este departamento
        $pdo->exec("INSERT INTO tickets_logs (ticket_id, numero, prefijo, tipo, modulo_id, departamento_id, estado, fecha, hora_creacion, hora_llamado, hora_fin) SELECT id, numero, prefijo, tipo, modulo_id, departamento_id, estado, fecha, hora_creacion, hora_llamado, hora_fin FROM tickets WHERE departamento_id = " . (int)$dept_id);
        
        // Limpiar tickets actuales de este departamento
        $pdo->exec("DELETE FROM tickets WHERE departamento_id = " . (int)$dept_id);

        // Actualizar el departamento
        $stmt = $pdo->prepare("UPDATE departamentos SET prefijo_cola = ?, numero_inicial = ?, prefijo_preferencial = ?, numero_inicial_preferencial = ? WHERE id = ?");
        $stmt->execute([$prefijo, $inicio, $prefijo_pref, $inicio_pref, $dept_id]);
        $pdo->commit();
        $_SESSION['flash_ok'] = 'Configuración reiniciada con éxito para este departamento.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = 'Error al guardar: ' . $e->getMessage();
    }
    header('Location: index.php'); exit;
}

// Cargar datos según perfil
if (!$esSuperAdmin) {
    // Info del departamento
    $stmt = $pdo->prepare("SELECT * FROM departamentos WHERE id = ?");
    $stmt->execute([$dept_id]);
    $config_dept = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Módulos del departamento
    $stmtMod = $pdo->prepare("SELECT m.id, m.nombre, m.codigo, m.activo, m.usuario_en_uso, u.nombre AS nombre_usuario FROM modulos m LEFT JOIN usuarios u ON u.id = m.usuario_en_uso WHERE m.departamento_id = ? ORDER BY m.id");
    $stmtMod->execute([$dept_id]);
    $modulos = $stmtMod->fetchAll(PDO::FETCH_ASSOC);
    $modulo_actual_id = (int)($_SESSION['modulo_id'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Sistema de Filas - Inicio</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    /* Usa exactamente tus estilos CSS originales aquí */
    :root { --green-main:#059669; --green-dark:#047857; --green-soft:#d1fae5; --green-bg:#ecfdf5; --accent:#2563eb; --accent-dark:#1d4ed8; --card-bg:#ffffff; --text:#022c22; --muted:#4b5563; --success-bg:#d1fae5; --success-border:#10b981; --danger-bg:#fee2e2; --danger-border:#ef4444; }
    * { box-sizing:border-box; } body { margin:0; font-family:system-ui,sans-serif; background:var(--green-bg); color:var(--text); }
    header { padding:1rem 1.8rem; background:var(--green-main); color:#ecfdf5; display:flex; justify-content:space-between; align-items:center; }
    header h1 { margin:0; font-size:1.7rem; } header .links { display:flex; gap:0.4rem; flex-wrap:wrap; }
    header .links a { color:#ecfdf5; text-decoration:none; font-size:0.85rem; padding:0.3rem 0.8rem; border-radius:999px; background:rgba(6,95,70,0.3); }
    main { max-width:1100px; margin:2rem auto; padding:0 1rem; }
    .grid { display:flex; flex-wrap:wrap; gap:1.4rem; }
    .card { flex:1 1 340px; background:var(--card-bg); border-radius:1rem; padding:1.5rem; box-shadow:0 10px 25px rgba(0,0,0,0.1); border:1px solid rgba(16,185,129,0.25); }
    .btn { padding:0.6rem 1.4rem; border-radius:999px; border:none; color:white; background:var(--green-main); cursor:pointer; font-weight:bold; }
    label { display:block; margin-bottom:0.5rem; font-size:0.9rem; } input, select { width:100%; padding:0.5rem; margin-bottom:1rem; border-radius:5px; border:1px solid #ccc; }
    .mensaje-ok { padding:1rem; background:var(--success-bg); color:#065f46; border-radius:5px; margin-bottom:1rem; }
</style>
</head>
<body>

<header>
    <div>
        <h1>Sistema Central de Filas</h1>
        <div style="font-size: 0.9rem;">
            <?= $esSuperAdmin ? 'Panel de Control Maestro' : htmlspecialchars($config_dept['nombre'] ?? 'Sin departamento') ?>
        </div>
    </div>
    <div class="links">
        <?php if ($esSuperAdmin): ?>
            <a href="admin_departamentos.php">🏢 Oficinas / Pantallas</a>
            <a href="admin_usuarios.php">👥 Usuarios</a>
        <?php else: ?>
            <a href="display.php?dept=<?= $dept_id ?>" target="_blank">📺 Ver Pantalla</a>
            <?php if ($esAdmin): ?>
                <a href="admin_usuarios.php">Usuarios</a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="logout.php">Cerrar sesión (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
    </div>
</header>

<main>
    <?php if ($mensaje_ok): ?><div class="mensaje-ok"><?= $mensaje_ok ?></div><?php endif; ?>
    <?php if ($mensaje_error): ?><div class="mensaje-error" style="background:#fee2e2; color:red; padding:1rem;"><?= $mensaje_error ?></div><?php endif; ?>

    <div class="grid">
        <?php if ($esSuperAdmin): ?>
            <div class="card" style="background: #eff6ff; border-color: #3b82f6;">
                <h2 style="color: #1d4ed8;">Bienvenido, Super-Administrador</h2>
                <p>Desde aquí usted controla toda la estructura del sistema. Usted no atiende cajas, usted administra a los administradores.</p>
                <ul>
                    <li><strong>Oficinas / Pantallas:</strong> Cree los departamentos (ej. Permisos, Registro Social), asigne sus prefijos y suba las fotos para cada pantalla.</li>
                    <li><strong>Usuarios:</strong> Cree a los Administradores de cada departamento. Ellos luego crearán a sus propios cajeros.</li>
                </ul>
                <br>
                <a href="admin_departamentos.php" class="btn" style="background: #2563eb; text-decoration: none;">Gestionar Departamentos</a>
            </div>

        <?php else: ?>
            <div class="card">
                <h2>Módulo de este equipo</h2>
                <form method="post">
                    <input type="hidden" name="accion" value="set_modulo">
                    <label>Seleccione su módulo:
                        <select name="modulo_id" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($modulos as $m): 
                                $ocupado = !empty($m['usuario_en_uso']) && $m['usuario_en_uso'] != $usuario_id;
                                $texto = $m['nombre'] . ($ocupado ? " (Ocupado por {$m['nombre_usuario']})" : "");
                            ?>
                                <option value="<?= $m['id'] ?>" <?= $m['id'] == $modulo_actual_id ? 'selected' : '' ?> <?= $ocupado ? 'disabled' : '' ?>><?= $texto ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn">Guardar módulo</button>
                </form>
                <?php if ($modulo_actual_id): ?>
                    <br><a href="caja.php" class="btn" style="background: #ea580c; text-decoration:none;">Ir a Operar Caja</a>
                <?php endif; ?>
            </div>

            <?php if ($esAdmin): ?>
            <div class="card">
                <h2>Numeración Diaria (<?= htmlspecialchars($config_dept['nombre']) ?>)</h2>
                <form method="post" onsubmit="return confirm('¿Reiniciar correlativos a cero para hoy?');">
                    <input type="hidden" name="accion" value="config">
                    <label>Prefijo Normal <input type="text" name="prefijo_cola" value="<?= htmlspecialchars($config_dept['prefijo_cola']) ?>"></label>
                    <label>Inicio Normal <input type="number" name="numero_inicial" value="<?= htmlspecialchars($config_dept['numero_inicial']) ?>"></label>
                    <label>Prefijo Preferencial <input type="text" name="prefijo_preferencial" value="<?= htmlspecialchars($config_dept['prefijo_preferencial']) ?>"></label>
                    <label>Inicio Preferencial <input type="number" name="numero_inicial_preferencial" value="<?= htmlspecialchars($config_dept['numero_inicial_preferencial']) ?>"></label>
                    <button type="submit" class="btn">Guardar y Reiniciar Fila</button>
                </form>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>
</body>
</html>