<?php
// admin_departamentos.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN']);
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$mensaje_ok = ''; $mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        
        // Subida de imágenes
        $rutas_imagenes = [];
        $directorio = __DIR__ . '/assets/img/';
        if (!is_dir($directorio)) mkdir($directorio, 0777, true);

        for ($i = 1; $i <= 2; $i++) {
            $input_name = 'banner_' . $i;
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                $nombre_archivo = "dept_{$nombre}_banner{$i}_" . time() . ".$ext";
                move_uploaded_file($_FILES[$input_name]['tmp_name'], $directorio . $nombre_archivo);
                $rutas_imagenes[$input_name] = 'assets/img/' . $nombre_archivo;
            }
        }

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
            $mensaje_error = "Error: " . $e->getMessage();
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

    <?php if ($mensaje_ok): ?><div style="color: green; font-weight: bold;"><?= $mensaje_ok ?></div><?php endif; ?>
    <?php if ($mensaje_error): ?><div style="color: red; font-weight: bold;"><?= $mensaje_error ?></div><?php endif; ?>

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
            <tr><th>ID</th><th>Nombre</th><th>URL Pantalla</th><th>Acción</th></tr>
            <?php foreach ($departamentos as $d): ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td><?= htmlspecialchars($d['nombre']) ?></td>
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