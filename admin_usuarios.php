<?php
// admin_usuarios.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN', 'ADMIN']);
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$rol_actual = $_SESSION['rol'];
$esSuperAdmin = ($rol_actual === 'SUPER-ADMIN');
$mi_dept_id = $_SESSION['departamento_id'] ?? null;

$mensaje_ok = ''; 
$mensaje_error = '';
$usuario_editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        // 1. CREAR USUARIO
        if ($accion === 'crear') {
            $username = trim($_POST['username']);
            $nombre   = trim($_POST['nombre']);
            $pass     = $_POST['password'];
            $rol      = $_POST['rol'];
            $activo   = 1;
            
            $dept_asignar = $esSuperAdmin ? ($_POST['departamento_id'] ?: null) : $mi_dept_id;

            if (!$esSuperAdmin && $rol === 'SUPER-ADMIN') throw new Exception("No tiene permisos para crear Super-Admins.");

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, nombre, rol, departamento_id, activo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $nombre, $rol, $dept_asignar, $activo]);
            $mensaje_ok = 'Usuario creado exitosamente.';
        }
        
        // 2. CARGAR USUARIO PARA EDICIÓN
        elseif ($accion === 'cargar_edicion') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $usuario_editar = $stmt->fetch();
            
            if (!$usuario_editar) throw new Exception("Usuario no encontrado.");
            if (!$esSuperAdmin && $usuario_editar['departamento_id'] != $mi_dept_id) throw new Exception("No puede editar usuarios de otros departamentos.");
            if (!$esSuperAdmin && $usuario_editar['rol'] === 'SUPER-ADMIN') throw new Exception("No puede editar a un Super-Admin.");
        }
        
        // 3. GUARDAR EDICIÓN
        elseif ($accion === 'editar') {
            $id_editar = (int)$_POST['id'];
            $username  = trim($_POST['username']);
            $nombre    = trim($_POST['nombre']);
            $rol       = $_POST['rol'];
            $activo    = isset($_POST['activo']) ? 1 : 0;
            
            $dept_asignar = $esSuperAdmin ? ($_POST['departamento_id'] ?: null) : $mi_dept_id;

            // Validaciones de seguridad
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id_editar]);
            $target = $stmt->fetch();
            
            if (!$target) throw new Exception("Usuario no encontrado.");
            if (!$esSuperAdmin && $target['departamento_id'] != $mi_dept_id) throw new Exception("No puede modificar usuarios de otros departamentos.");
            if (!$esSuperAdmin && $target['rol'] === 'SUPER-ADMIN') throw new Exception("No puede modificar a un Super-Admin.");
            if (!$esSuperAdmin && $rol === 'SUPER-ADMIN') throw new Exception("No puede asignar el rol de Super-Admin.");

            $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, nombre = ?, rol = ?, departamento_id = ?, activo = ? WHERE id = ?");
            $stmt->execute([$username, $nombre, $rol, $dept_asignar, $activo, $id_editar]);
            $mensaje_ok = 'Usuario actualizado correctamente.';
        }
        
        // 4. CAMBIAR CONTRASEÑA
        elseif ($accion === 'reset_pass') {
            $id_pass = (int)$_POST['id'];
            $nueva_pass = $_POST['password'];
            
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id_pass]);
            $target = $stmt->fetch();
            
            if (!$target) throw new Exception("Usuario no encontrado.");
            if (!$esSuperAdmin && $target['departamento_id'] != $mi_dept_id) throw new Exception("No autorizado.");
            if (!$esSuperAdmin && $target['rol'] === 'SUPER-ADMIN') throw new Exception("No autorizado.");
            if (strlen($nueva_pass) < 4) throw new Exception("La contraseña es muy corta.");

            $hash = password_hash($nueva_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $id_pass]);
            $mensaje_ok = 'Contraseña actualizada.';
        }
        
        // 5. DAR DE BAJA / ACTIVAR (Toggle Rápido)
        elseif ($accion === 'toggle_estado') {
            $id_toggle = (int)$_POST['id'];
            
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id_toggle]);
            $target = $stmt->fetch();
            
            if (!$target) throw new Exception("Usuario no encontrado.");
            if (!$esSuperAdmin && $target['departamento_id'] != $mi_dept_id) throw new Exception("No autorizado.");
            if (!$esSuperAdmin && $target['rol'] === 'SUPER-ADMIN') throw new Exception("No autorizado.");
            
            // Si tú mismo te intentas desactivar, lo bloqueamos por seguridad
            if ($target['id'] == $_SESSION['id']) throw new Exception("No puedes darte de baja a ti mismo.");

            $nuevo_estado = $target['activo'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $id_toggle]);
            $mensaje_ok = $nuevo_estado ? 'Usuario activado nuevamente.' : 'Usuario dado de baja exitosamente.';
        }

    } catch (Throwable $e) {
        $mensaje_error = $e->getMessage();
    }
}

// OBTENER LISTA DE USUARIOS Y DEPARTAMENTOS
if ($esSuperAdmin) {
    $usuarios = $pdo->query("SELECT u.*, d.nombre as depto FROM usuarios u LEFT JOIN departamentos d ON u.departamento_id = d.id ORDER BY u.id DESC")->fetchAll();
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT u.*, d.nombre as depto FROM usuarios u LEFT JOIN departamentos d ON u.departamento_id = d.id WHERE u.departamento_id = ? ORDER BY u.id DESC");
    $stmt->execute([$mi_dept_id]);
    $usuarios = $stmt->fetchAll();
    // Un Admin normal no necesita la lista de todos los departamentos, solo el suyo.
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Administrar Usuarios</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; padding: 20px; margin: 0; }
        .header-nav { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-nav a { text-decoration: none; color: #4f46e5; font-weight: bold; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .grid-forms { display: flex; gap: 20px; flex-wrap: wrap; }
        .grid-forms .card { flex: 1; min-width: 300px; }
        input[type="text"], input[type="password"], select { padding: 8px; margin: 5px 0 15px 0; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        label { font-weight: bold; font-size: 0.9rem; color: #374151; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; } 
        th, td { border-bottom: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f9fafb; }
        .btn { background: #059669; color: white; border: none; font-weight: bold; cursor: pointer; padding: 10px 15px; border-radius: 5px; }
        .btn-edit { background: #3b82f6; padding: 6px 12px; }
        .btn-baja { background: #ef4444; padding: 6px 12px; }
        .btn-alta { background: #10b981; padding: 6px 12px; }
        .badge { padding: 4px 8px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge-activo { background: #d1fae5; color: #065f46; }
        .badge-inactivo { background: #fee2e2; color: #991b1b; }
        .inline-form { display: inline-block; margin: 0; }
    </style>
</head>
<body>
    
    <div class="header-nav">
        <h2 style="margin: 0; color: #111827;">Gestión de Usuarios</h2>
        <a href="index.php">← Volver al Menú Principal</a>
    </div>
    
    <?php if ($mensaje_ok): ?><div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold;"><?= $mensaje_ok ?></div><?php endif; ?>
    <?php if ($mensaje_error): ?><div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold;"><?= $mensaje_error ?></div><?php endif; ?>

    <div class="grid-forms">
        <div class="card">
            <h3 style="margin-top:0; color:#059669;">Crear Nuevo Usuario</h3>
            <form method="post">
                <input type="hidden" name="accion" value="crear">
                <label>Usuario (Para iniciar sesión):</label> <input type="text" name="username" required>
                <label>Nombre Completo:</label> <input type="text" name="nombre" required>
                <label>Contraseña:</label> <input type="password" name="password" required>
                
                <label>Rol en el sistema:</label>
                <select name="rol">
                    <?php if ($esSuperAdmin): ?><option value="SUPER-ADMIN">SUPER-ADMIN (Acceso Total a todo)</option><?php endif; ?>
                    <option value="ADMIN">ADMINISTRADOR (Encargado de la oficina)</option>
                    <option value="CAJA" selected>CAJERO (Llama números)</option>
                </select>

                <?php if ($esSuperAdmin): ?>
                <label>Asignar a Departamento:</label>
                <select name="departamento_id">
                    <option value="">-- Ninguno (Para los Super-Admins) --</option>
                    <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <button type="submit" class="btn" style="width: 100%;">Registrar Usuario</button>
            </form>
        </div>

        <div class="card" style="<?= !$usuario_editar ? 'opacity: 0.5; pointer-events: none;' : 'border: 2px solid #3b82f6;' ?>">
            <h3 style="margin-top:0; color:#3b82f6;">Editar Usuario</h3>
            <?php if (!$usuario_editar): ?>
                <p style="color: #6b7280; font-style: italic;">Seleccione "Editar" en la tabla para cargar un usuario aquí.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="<?= $usuario_editar['id'] ?>">
                    
                    <label>Usuario (Login):</label> <input type="text" name="username" value="<?= htmlspecialchars($usuario_editar['username']) ?>" required>
                    <label>Nombre Completo:</label> <input type="text" name="nombre" value="<?= htmlspecialchars($usuario_editar['nombre']) ?>" required>
                    
                    <label>Rol:</label>
                    <select name="rol">
                        <?php if ($esSuperAdmin && $usuario_editar['rol'] === 'SUPER-ADMIN'): ?>
                            <option value="SUPER-ADMIN" selected>SUPER-ADMIN</option>
                        <?php endif; ?>
                        <option value="ADMIN" <?= $usuario_editar['rol'] === 'ADMIN' ? 'selected' : '' ?>>ADMINISTRADOR</option>
                        <option value="CAJA" <?= $usuario_editar['rol'] === 'CAJA' ? 'selected' : '' ?>>CAJERO</option>
                    </select>

                    <?php if ($esSuperAdmin): ?>
                    <label>Departamento:</label>
                    <select name="departamento_id">
                        <option value="">-- Ninguno --</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $usuario_editar['departamento_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>

                    <div style="margin-bottom: 15px;">
                        <input type="checkbox" name="activo" id="chk_activo" <?= $usuario_editar['activo'] ? 'checked' : '' ?> style="width: auto;">
                        <label for="chk_activo">El usuario está Activo (Puede iniciar sesión)</label>
                    </div>

                    <button type="submit" class="btn btn-edit" style="width: 100%;">Guardar Cambios</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0;">Directorio de Usuarios</h3>
        <div style="overflow-x: auto;">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Departamento</th>
                    <th>Acciones Rápidas</th>
                </tr>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td>
                        <span class="badge <?= $u['activo'] ? 'badge-activo' : 'badge-inactivo' ?>">
                            <?= $u['activo'] ? 'Activo' : 'De Baja' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><strong><?= $u['rol'] ?></strong></td>
                    <td><?= htmlspecialchars($u['depto'] ?? 'Global') ?></td>
                    <td>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="accion" value="cargar_edicion">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-edit">✏️ Editar</button>
                        </form>

                        <form method="post" class="inline-form" onsubmit="return confirm('¿Seguro que desea cambiar el estado de este usuario?');">
                            <input type="hidden" name="accion" value="toggle_estado">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <?php if ($u['activo']): ?>
                                <button type="submit" class="btn btn-baja" <?= ($u['id'] == $_SESSION['id']) ? 'disabled title="No puedes darte de baja a ti mismo"' : '' ?>>⬇️ Dar de Baja</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-alta">⬆️ Activar</button>
                            <?php endif; ?>
                        </form>
                        
                        <form method="post" class="inline-form" onsubmit="return confirm('¿Restablecer la contraseña de este usuario?');" style="margin-left: 10px;">
                            <input type="hidden" name="accion" value="reset_pass">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <input type="password" name="password" placeholder="Nueva clave" required style="width: 100px; padding: 5px; margin: 0;">
                            <button type="submit" class="btn" style="padding: 5px 10px; font-size: 0.8rem;">Cambiar Clave</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>