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
                $_SESSION['flash_ok']  = 'Módulo configurado correctamente. Ya puede comenzar a atender.';
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
        $_SESSION['flash_ok'] = 'Correlativos reiniciados con éxito para la atención de hoy.';
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

    // ========================================================================
    // AUTO-SELECCIÓN MÁGICA: Si la sesión no tiene módulo, pero la Base de Datos 
    // dice que este usuario ya tiene una caja asignada, la recuperamos.
    // ========================================================================
    if ($modulo_actual_id === 0) {
        foreach ($modulos as $m) {
            if ((int)$m['usuario_en_uso'] === $usuario_id) {
                $modulo_actual_id = (int)$m['id'];
                $_SESSION['modulo_id'] = $modulo_actual_id;
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Sistema de Filas - Menú Principal</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root { 
        --primary: #16a34a;       /* Verde base */
        --primary-light: #dcfce7; /* Verde clarito (fondos) */
        --primary-dark: #15803d;  /* Verde oscuro (hover botones) */
        
        --success: #10b981; 
        --warning: #f59e0b; 
        --danger: #ef4444; 
        --bg: #f8fafc; 
        --card-bg: #ffffff; 
        --text: #0f172a; 
        --text-muted: #64748b; 
        --border: #e2e8f0;
    }
    
    * { box-sizing: border-box; } 
    body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
    
    header { background: linear-gradient(135deg, #064e3b 0%, #15803d 100%); color: white; padding: 1.2rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.15); flex-wrap: wrap; gap: 1rem; }
    .header-info h1 { margin: 0; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px; }
    .header-info .subtitle { font-size: 0.95rem; color: #a7f3d0; margin-top: 2px; }
    
    .nav-links { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
    .nav-links a { color: white; text-decoration: none; font-size: 0.9rem; font-weight: 600; padding: 0.6rem 1.2rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.15); transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
    .nav-links a:hover { background: rgba(255,255,255,0.25); transform: translateY(-2px); border-color: rgba(255,255,255,0.5); }
    .nav-links .logout { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.5); }
    .nav-links .logout:hover { background: rgba(239, 68, 68, 0.6); }

    main { max-width: 1200px; margin: 2.5rem auto; padding: 0 1.5rem; }
    
    .alert { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 600; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .alert-success { background: #dcfce7; color: #166534; border-left: 5px solid #22c55e; }
    .alert-error { background: #fee2e2; color: #991b1b; border-left: 5px solid #ef4444; }

    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; }
    
    .card { background: var(--card-bg); border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid var(--border); transition: transform 0.2s; }
    .card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    
    .card h2 { margin: 0 0 1rem 0; font-size: 1.3rem; color: var(--primary-dark); display: flex; align-items: center; gap: 8px; border-bottom: 2px solid var(--primary-light); padding-bottom: 10px; }
    .card p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; }
    
    label { display: block; font-weight: 600; font-size: 0.9rem; color: #334155; margin-bottom: 5px; }
    .input-group { margin-bottom: 1.2rem; }
    input[type="text"], input[type="number"], select { width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 1rem; color: #1e293b; background: #f8fafc; transition: border-color 0.2s; }
    input:focus, select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); background: white; }
    select:disabled { opacity: 0.6; cursor: not-allowed; }

    .btn { display: inline-block; width: 100%; padding: 0.8rem 1.5rem; border-radius: 8px; border: none; font-size: 1rem; font-weight: 700; cursor: pointer; color: white; background: var(--primary); text-align: center; text-decoration: none; transition: background 0.2s; }
    .btn:hover { background: var(--primary-dark); }
    .btn-action { background: #ea580c; margin-top: 10px; box-shadow: 0 4px 6px rgba(234, 88, 12, 0.3); }
    .btn-action:hover { background: #c2410c; }
    .btn-super { background: #059669; } 
    .btn-super:hover { background: #047857; }

    .user-badge { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.15); padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; border: 1px solid rgba(255,255,255,0.3); }

    @media (max-width: 768px) {
        header { flex-direction: column; align-items: flex-start; }
        .nav-links { width: 100%; }
        .nav-links a { flex: 1; justify-content: center; }
        .user-badge { width: 100%; justify-content: center; margin-bottom: 10px; }
    }
</style>
</head>
<body>

<header>
    <div class="header-info">
        <h1>Sistema Central de Filas</h1>
        <div class="subtitle">
            <?= $esSuperAdmin ? '🛡️ Panel de Control Maestro' : '🏢 ' . htmlspecialchars($config_dept['nombre'] ?? 'Sin departamento') ?>
        </div>
    </div>
    
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 10px;">
        <div class="user-badge">
            👤 <?= htmlspecialchars($_SESSION['nombre']) ?> (<?= $rol ?>)
        </div>
        <div class="nav-links">
            <?php if ($esSuperAdmin): ?>
                <a href="admin_departamentos.php">🏢 Oficinas / Pantallas</a>
                <a href="admin_usuarios.php">👥 Usuarios</a>
                <a href="admin_modulos.php">💻 Módulos</a>
                <a href="dashboard.php">📊 Estadísticas</a>
            <?php else: ?>
                <a href="display.php?dept=<?= $dept_id ?>" target="_blank">📺 Ver Pantalla</a>
                <?php if ($esAdmin): ?>
                    <a href="admin_usuarios.php">👥 Usuarios</a>
                    <a href="admin_modulos.php">💻 Módulos</a>
                    <a href="dashboard.php">📊 Estadísticas</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="logout.php" class="logout">🚪 Salir</a>
        </div>
    </div>
</header>

<main>
    <?php if ($mensaje_ok): ?><div class="alert alert-success">✅ <?= htmlspecialchars($mensaje_ok, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($mensaje_error): ?><div class="alert alert-error">❌ <?= htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="grid">
        <?php if ($esSuperAdmin): ?>
            <div class="card" style="grid-column: 1 / -1; background: #f0fdf4; border-color: #bbf7d0;">
                <h2 style="color: #166534; border-bottom-color: #dcfce7;">👑 Bienvenido, Super-Administrador</h2>
                <p style="font-size: 1.1rem; color: #14532d;">Desde aquí usted tiene control total sobre la infraestructura del sistema.</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 2rem;">
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                        <h3 style="margin-top:0; color:#15803d;">🏢 1. Oficinas y Pantallas</h3>
                        <p style="font-size:0.9rem;">Cree departamentos (ej. Permisos, RSH), asigne prefijos y suba las fotos para los monitores públicos.</p>
                        <a href="admin_departamentos.php" class="btn btn-super">Ir a Departamentos</a>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                        <h3 style="margin-top:0; color:#15803d;">👥 2. Usuarios del Sistema</h3>
                        <p style="font-size:0.9rem;">Cree a los Administradores de cada oficina. Ellos se encargarán de crear a sus propios cajeros.</p>
                        <a href="admin_usuarios.php" class="btn btn-super">Ir a Usuarios</a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            
            <div class="card">
                <h2>💻 Módulo de Atención</h2>
                <p>Seleccione el módulo o caja en la que va a trabajar. Esto le permitirá llamar a las personas a su puesto.</p>

                <form method="post">
                    <input type="hidden" name="accion" value="set_modulo">
                    <div class="input-group">
                        <label>Seleccione su caja:</label>
                        <select name="modulo_id" required>
                            <option value="">-- Click para seleccionar --</option>
                            <?php foreach ($modulos as $m): 
                                $enUso   = (int)($m['usuario_en_uso'] ?? 0);
                                $ocupado = $enUso !== 0 && $enUso !== $usuario_id;
                                $texto = $m['nombre'] . ' (' . $m['codigo'] . ')';
                                if ($ocupado) $texto .= ' - ⚠️ Ocupado por ' . $m['nombre_usuario'];
                                elseif ($enUso === $usuario_id) $texto .= ' - ✅ Su caja actual';
                            ?>
                                <option value="<?= $m['id'] ?>" <?= $m['id'] == $modulo_actual_id ? 'selected' : '' ?> <?= $ocupado ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($texto) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">💾 Guardar Selección</button>
                </form>

                <?php if ($modulo_actual_id): ?>
                    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px dashed var(--border); text-align: center;">
                        <p style="margin-bottom: 10px; color: #166534; font-weight: bold;">Módulo configurado con éxito.</p>
                        <a href="caja.php" class="btn btn-action">📢 IR AL PANEL DE LLAMADOS</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($esAdmin): ?>
            <div class="card">
                <h2>🔢 Reinicio Diario de Fila</h2>
                <p>Al guardar, <strong>se archivarán los tickets de ayer</strong> y la numeración de las pantallas volverá a cero.</p>
                
                <form method="post" onsubmit="return confirm('ATENCIÓN: ¿Está seguro de reiniciar los correlativos? Hacer esto en medio del día borrará la fila actual en pantalla.');">
                    <input type="hidden" name="accion" value="config">
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="input-group" style="flex: 1;">
                            <label>Prefijo General:</label>
                            <input type="text" name="prefijo_cola" value="<?= htmlspecialchars($config_dept['prefijo_cola']) ?>" required>
                        </div>
                        <div class="input-group" style="flex: 1;">
                            <label>Inicio General:</label>
                            <input type="number" name="numero_inicial" value="<?= htmlspecialchars($config_dept['numero_inicial']) ?>" min="1" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <div class="input-group" style="flex: 1;">
                            <label>Prefijo Preferencial:</label>
                            <input type="text" name="prefijo_preferencial" value="<?= htmlspecialchars($config_dept['prefijo_preferencial']) ?>" required>
                        </div>
                        <div class="input-group" style="flex: 1;">
                            <label>Inicio Preferencial:</label>
                            <input type="number" name="numero_inicial_preferencial" value="<?= htmlspecialchars($config_dept['numero_inicial_preferencial']) ?>" min="1" required>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="background: var(--warning); color: #78350f;">⚠️ Reiniciar Numeración Hoy</button>
                </form>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

</body>
</html>