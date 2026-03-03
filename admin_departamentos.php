<?php
// admin_departamentos.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN']);
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$mensaje_ok = ''; 
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        
        // Subida de imágenes
        $rutas_imagenes = [];
        $directorio = __DIR__ . '/assets/img/';
        
        // Intentar crear la carpeta si no existe
        if (!is_dir($directorio)) {
            if (!@mkdir($directorio, 0775, true)) {
                $mensaje_error .= "No se pudo crear la carpeta assets/img/. Permisos denegados.<br>";
            }
        }

        // Procesar las 2 imágenes
        for ($i = 1; $i <= 2; $i++) {
            $input_name = 'banner_' . $i;
            
            // Si el usuario envió un archivo
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] !== UPLOAD_ERR_NO_FILE) {
                
                // Verificar si hubo errores en la subida (ej. peso excedido)
                if ($_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) {
                    $mensaje_error .= "Error al subir Banner $i (Código: " . $_FILES[$input_name]['error'] . "). El archivo podría ser muy pesado.<br>";
                    continue; // Saltar a la siguiente foto
                }

                $ext = strtolower(pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION));
                $nombre_archivo = "dept_banner{$i}_" . time() . "_" . rand(100, 999) . ".$ext";
                
                // Intentar mover el archivo temporal a la carpeta final
                if (@move_uploaded_file($_FILES[$input_name]['tmp_name'], $directorio . $nombre_archivo)) {
                    $rutas_imagenes[$input_name] = 'assets/img/' . $nombre_archivo;
                } else {
                    $mensaje_error .= "Fallo al guardar el Banner $i en el servidor. La carpeta 'assets/img/' no tiene permisos de escritura.<br>";
                }
            }
        }

        // Si no hubo errores fatales, guardamos en la BD
        if (empty($mensaje_error)) {
            try {
                if ($accion === 'crear') {
                    $b1 = $rutas_imagenes['banner_1'] ?? 'assets/img/banner_central.jpg';
                    $b2 = $rutas_imagenes['banner_2'] ?? 'assets/img/qr_pago.png';
                    $stmt = $pdo->prepare("INSERT INTO departamentos (nombre, banner_1, banner_2) VALUES (?, ?, ?)");
                    $stmt->execute([$nombre, $b1, $b2]);
                    $mensaje_ok = "Departamento creado exitosamente.";
                } else {
                    $sql = "UPDATE departamentos SET nombre = ?";
                    $params = [$nombre];
                    if (isset($rutas_imagenes['banner_1'])) { $sql .= ", banner_1 = ?"; $params[] = $rutas_imagenes['banner_1']; }
                    if (isset($rutas_imagenes['banner_2'])) { $sql .= ", banner_2 = ?"; $params[] = $rutas_imagenes['banner_2']; }
                    $sql .= " WHERE id = ?"; $params[] = $id;
                    
                    $pdo->prepare($sql)->execute($params);
                    $mensaje_ok = "Departamento actualizado.";
                }
            } catch (Exception $e) {
                $mensaje_error .= "Error de Base de Datos: " . $e->getMessage() . "<br>";
            }
        }
    }
}

$departamentos = $pdo->query("SELECT * FROM departamentos")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Administrar Departamentos</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; max-width: 800px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        input, button { padding: 10px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        .btn { background: #2563eb; color: white; border: none; cursor: pointer; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    </style>
</head>
<body>
    <a href="index.php">← Volver al Inicio</a>
    <h2>Gestión de Oficinas / Departamentos</h2>

    <?php if ($mensaje_ok): ?><div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold;"><?= $mensaje_ok ?></div><?php endif; ?>
    <?php if ($mensaje_error): ?><div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold;"><?= $mensaje_error ?></div><?php endif; ?>

    <div class="card">
        <h3>Crear / Editar Oficina</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear" id="form-accion">
            <input type="hidden" name="id" value="" id="form-id">
            
            <label>Nombre del Departamento:</label>
            <input type="text" name="nombre" id="form-nombre" required placeholder="Ej: Registro Social de Hogares">
            
            <label>Banner Superior (Pantalla Pública):</label>
            <input type="file" name="banner_1" accept="image/*">
            
            <label>Banner Inferior (Pantalla Pública):</label>
            <input type="file" name="banner_2" accept="image/*">
            
            <button type="submit" class="btn">Guardar Departamento</button>
        </form>
    </div>

    <div class="card">
        <h3>Departamentos Actuales</h3>
        <table>
            <tr><th>ID</th><th>Nombre</th><th>Imágenes Actuales</th><th>URL Pantalla</th><th>Acción</th></tr>
            <?php foreach ($departamentos as $d): ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td><?= htmlspecialchars($d['nombre']) ?></td>
                <td>
                    <img src="<?= htmlspecialchars($d['banner_1']) ?>" width="50" height="30" style="object-fit: cover;">
                    <img src="<?= htmlspecialchars($d['banner_2']) ?>" width="50" height="30" style="object-fit: cover;">
                </td>
                <td><a href="display.php?dept=<?= $d['id'] ?>" target="_blank">Ver Pantalla</a></td>
                <td>
                    <button onclick="editar(<?= $d['id'] ?>, '<?= htmlspecialchars($d['nombre'], ENT_QUOTES) ?>')">Editar</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

<script>
function editar(id, nombre) {
    document.getElementById('form-accion').value = 'editar';
    document.getElementById('form-id').value = id;
    document.getElementById('form-nombre').value = nombre;
    window.scrollTo(0, 0);
}
</script>
</body>
</html>