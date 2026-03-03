<?php
// admin_modulos.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN', 'ADMIN']);
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$rol_actual = $_SESSION['rol'];
$esSuperAdmin = ($rol_actual === 'SUPER-ADMIN');
$mi_dept_id = $_SESSION['departamento_id'] ?? null;

$mensaje_ok = ''; 
$mensaje_error = '';
$modulo_editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        // 1. CREAR MÓDULO
        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre']);
            $codigo = trim($_POST['codigo']);
            $activo = 1;
            
            $dept_asignar = $esSuperAdmin ? ($_POST['departamento_id'] ?: null) : $mi_dept_id;
            
            if (!$dept_asignar) throw new Exception("Debe asignar el módulo a un departamento.");

            $stmt = $pdo->prepare("SELECT id FROM modulos WHERE codigo = ?");
            $stmt->execute([$codigo]);
            if ($stmt->fetch()) throw new Exception("El código '$codigo' ya está en uso por otro módulo.");

            $stmt = $pdo->prepare("INSERT INTO modulos (nombre, codigo, activo, departamento_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $codigo, $activo, $dept_asignar]);
            $mensaje_ok = 'Módulo creado exitosamente.';
        }
        
        // 2. CARGAR PARA EDICIÓN
        elseif ($accion === 'cargar_edicion') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM modulos WHERE id = ?");
            $stmt->execute([$id]);
            $modulo_editar = $stmt->fetch();
            
            if (!$modulo_editar) throw new Exception("Módulo no encontrado.");
            if (!$esSuperAdmin && $modulo_editar['departamento_id'] != $mi_dept_id) throw new Exception("No autorizado.");
        }
        
        // 3. GUARDAR EDICIÓN
        elseif ($accion === 'editar') {
            $id_editar = (int)$_POST['id'];
            $nombre    = trim($_POST['nombre']);
            $codigo    = trim($_POST['codigo']);
            $activo    = isset($_POST['activo']) ? 1 : 0;
            
            $dept_asignar = $esSuperAdmin ? ($_POST['departamento_id'] ?: null) : $mi_dept_id;

            $stmt = $pdo->prepare("SELECT * FROM modulos WHERE id = ?");
            $stmt->execute([$id_editar]);
            $target = $stmt->fetch();
            
            if (!$target) throw new Exception("Módulo no encontrado.");
            if (!$esSuperAdmin && $target['departamento_id'] != $mi_dept_id) throw new Exception("No puede modificar módulos ajenos.");

            $stmt = $pdo->prepare("SELECT id FROM modulos WHERE codigo = ? AND id != ?");
            $stmt->execute([$codigo, $id_editar]);
            if ($stmt->fetch()) throw new Exception("El código '$codigo' ya está siendo usado.");

            $stmt = $pdo->prepare("UPDATE modulos SET nombre = ?, codigo = ?, departamento_id = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $codigo, $dept_asignar, $activo, $id_editar]);
            $mensaje_ok = 'Módulo actualizado correctamente.';
        }
        
        // 4. DAR DE BAJA / ACTIVAR
        elseif ($accion === 'toggle_estado') {
            $id_toggle = (int)$_POST['id'];
            
            $stmt = $pdo->prepare("SELECT * FROM modulos WHERE id = ?");
            $stmt->execute([$id_toggle]);
            $target = $stmt->fetch();
            
            if (!$target) throw new Exception("Módulo no encontrado.");
            if (!$esSuperAdmin && $target['departamento_id'] != $mi_dept_id) throw new Exception("No autorizado.");

            $nuevo_estado = $target['activo'] ? 0 : 1;
            if ($nuevo_estado === 0) {
                $stmt = $pdo->prepare("UPDATE modulos SET activo = 0, usuario_en_uso = NULL WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE modulos SET activo = 1 WHERE id = ?");
            }
            $stmt->execute([$id_toggle]);
            $mensaje_ok = $nuevo_estado ? 'Módulo activado.' : 'Módulo dado de baja.';
        }

        // 5. LIBERAR MÓDULO
        elseif ($accion === 'liberar') {
            $id_liberar = (int)$_POST['id'];
            
            $stmt = $pdo->prepare("SELECT * FROM modulos WHERE id = ?");
            $stmt->execute([$id_liberar]);
            $target = $stmt->fetch();
            
            if (!$target) throw new Exception("Módulo no encontrado.");
            if (!$esSuperAdmin && $target['departamento_id'] != $mi_dept_id) throw new Exception("No autorizado.");

            $stmt = $pdo->prepare("UPDATE modulos SET usuario_en_uso = NULL WHERE id = ?");
            $stmt->execute([$id_liberar]);
            $mensaje_ok = 'El módulo ha sido liberado forzosamente y ya puede ser usado por otro cajero.';
        }

    } catch (Throwable $e) {
        $mensaje_error = $e->getMessage();
    }
}

// OBTENER LISTA DE MÓDULOS Y DEPARTAMENTOS
if ($esSuperAdmin) {
    $stmt = $pdo->query("SELECT m.*, d.nombre as depto, u.nombre as usuario_ocupando FROM modulos m LEFT JOIN departamentos d ON m.departamento_id = d.id LEFT JOIN usuarios u ON m.usuario_en_uso = u.id ORDER BY m.departamento_id, m.codigo ASC");
    $modulos = $stmt->fetchAll();
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT m.*, d.nombre as depto, u.nombre as usuario_ocupando FROM modulos m LEFT JOIN departamentos d ON m.departamento_id = d.id LEFT JOIN usuarios u ON m.usuario_en_uso = u.id WHERE m.departamento_id = ? ORDER BY m.codigo ASC");
    $stmt->execute([$mi_dept_id]);
    $modulos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Administrar Módulos</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0fdf4; padding: 20px; margin: 0; color: #1e293b; }
        .header-nav { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header-nav h2 { margin: 0; color: #065f46; font-size: 1.5rem; }
        .header-nav a { text-decoration: none; color: #2563eb; font-weight: 600; padding: 8px 15px; border-radius: 6px; background: #eff6ff; transition: 0.2s; }
        .header-nav a:hover { background: #dbeafe; }
        
        .card { background: white; padding: 25px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; }
        .grid-forms { display: flex; gap: 20px; flex-wrap: wrap; }
        .grid-forms .card { flex: 1; min-width: 320px; }
        
        /* CORRECCIÓN DE FORMULARIOS */
        label { display: block; font-weight: 700; font-size: 0.95rem; color: #334155; margin-top: 15px; margin-bottom: 5px; }
        .nota { display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 8px; font-weight: 400; }
        input[type="text"], select { width: 100%; padding: 10px 12px; margin-bottom: 5px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.2s; }
        input[type="text"]:focus, select:focus { outline: none; border-color: #059669; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1); }
        
        /* TABLA */
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.95rem; margin-top: 15px; } 
        th, td { border-bottom: 1px solid #e2e8f0; padding: 12px 15px; text-align: left; }
        th { background: #f8fafc; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 0.85rem; border-top: 1px solid #e2e8f0; }
        
        /* BOTONES */
        .btn { background: #059669; color: white; border: none; font-weight: bold; cursor: pointer; padding: 12px 20px; border-radius: 6px; font-size: 1rem; transition: background 0.2s; margin-top: 15px; }
        .btn:hover { background: #047857; }
        .btn-edit { background: #3b82f6; padding: 6px 12px; margin-top: 0; font-size: 0.85rem; }
        .btn-edit:hover { background: #2563eb; }
        .btn-baja { background: #ef4444; padding: 6px 12px; margin-top: 0; font-size: 0.85rem; }
        .btn-baja:hover { background: #dc2626; }
        .btn-alta { background: #10b981; padding: 6px 12px; margin-top: 0; font-size: 0.85rem; }
        .btn-warning { background: #f59e0b; padding: 6px 12px; margin-top: 0; color: #fff; font-size: 0.85rem; }
        .btn-warning:hover { background: #d97706; }
        
        /* BADGES */
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; display: inline-block; }
        .badge-activo { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-inactivo { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-ocupado { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
        
        .inline-form { display: inline-block; margin: 0; }
        
        /* ALERTAS */
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    
    <div class="header-nav">
        <h2>Gestión de Módulos (Cajas)</h2>
        <a href="index.php">← Volver al Menú Principal</a>
    </div>
    
    <?php if ($mensaje_ok): ?><div class="alert alert-success">✅ <?= $mensaje_ok ?></div><?php endif; ?>
    <?php if ($mensaje_error): ?><div class="alert alert-error">❌ <?= $mensaje_error ?></div><?php endif; ?>

    <div class="grid-forms">
        <div class="card">
            <h3 style="margin-top:0; color:#059669; border-bottom: 2px solid #ecfdf5; padding-bottom: 10px;">Crear Nuevo Módulo</h3>
            <form method="post">
                <input type="hidden" name="accion" value="crear">
                
                <label>Nombre Público:</label> 
                <span class="nota">Ej: Módulo 1, Caja Preferencial, Ventanilla A</span>
                <input type="text" name="nombre" required>
                
                <label>Código Interno:</label> 
                <span class="nota">Abreviatura única corta. Ej: C1, M2</span>
                <input type="text" name="codigo" maxlength="10" required>
                
                <?php if ($esSuperAdmin): ?>
                <label>Asignar a Departamento:</label>
                <select name="departamento_id" required>
                    <option value="">-- Seleccione Departamento --</option>
                    <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <button type="submit" class="btn" style="width: 100%;">Registrar Módulo</button>
            </form>
        </div>

        <div class="card" style="<?= !$modulo_editar ? 'opacity: 0.6; pointer-events: none; background: #f8fafc;' : 'border: 2px solid #3b82f6;' ?>">
            <h3 style="margin-top:0; color:#3b82f6; border-bottom: 2px solid #eff6ff; padding-bottom: 10px;">Editar Módulo</h3>
            <?php if (!$modulo_editar): ?>
                <p style="color: #64748b; font-style: italic; text-align: center; padding: 20px 0;">Seleccione "Editar" en la tabla para cargar un módulo aquí.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="<?= $modulo_editar['id'] ?>">
                    
                    <label>Nombre Público:</label> 
                    <input type="text" name="nombre" value="<?= htmlspecialchars($modulo_editar['nombre']) ?>" required>
                    
                    <label>Código Interno:</label> 
                    <input type="text" name="codigo" value="<?= htmlspecialchars($modulo_editar['codigo']) ?>" maxlength="10" required>
                    
                    <?php if ($esSuperAdmin): ?>
                    <label>Departamento:</label>
                    <select name="departamento_id" required>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $modulo_editar['departamento_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>

                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="activo" <?= $modulo_editar['activo'] ? 'checked' : '' ?> style="width: auto; margin: 0; transform: scale(1.2);">
                        Módulo Habilitado (Visible para operar)
                    </label>

                    <button type="submit" class="btn" style="background: #3b82f6; width: 100%;">Guardar Cambios</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">Directorio de Módulos</h3>
        <div style="overflow-x: auto;">
            <table>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th>Uso Actual</th>
                    <?php if ($esSuperAdmin) echo "<th>Departamento</th>"; ?>
                    <th>Acciones</th>
                </tr>
                <?php foreach ($modulos as $m): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['codigo']) ?></strong></td>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <td>
                        <span class="badge <?= $m['activo'] ? 'badge-activo' : 'badge-inactivo' ?>">
                            <?= $m['activo'] ? 'Habilitado' : 'De Baja' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($m['usuario_ocupando']): ?>
                            <span class="badge badge-ocupado">Ocupado por: <?= htmlspecialchars($m['usuario_ocupando']) ?></span>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-style: italic;">Libre</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($esSuperAdmin) echo "<td>" . htmlspecialchars($m['depto'] ?? 'Ninguno') . "</td>"; ?>
                    <td>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="accion" value="cargar_edicion">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-edit">✏️ Editar</button>
                        </form>

                        <form method="post" class="inline-form" onsubmit="return confirm('¿Cambiar el estado operativo de este módulo?');">
                            <input type="hidden" name="accion" value="toggle_estado">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <?php if ($m['activo']): ?>
                                <button type="submit" class="btn btn-baja">⬇️ Bajar</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-alta">⬆️ Activar</button>
                            <?php endif; ?>
                        </form>

                        <?php if ($m['usuario_ocupando']): ?>
                        <form method="post" class="inline-form" onsubmit="return confirm('¿Está seguro de forzar la liberación? Si el cajero actual está en medio de una atención, se le cortará el acceso a la caja.');" style="margin-left: 5px;">
                            <input type="hidden" name="accion" value="liberar">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-warning">🔓 Liberar Forzosamente</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>