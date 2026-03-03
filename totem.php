<?php
require_once __DIR__ . '/db.php';

$pdo = getPDO();

// obtener prefijo
$stmt = $pdo->prepare('SELECT prefijo_cola FROM configuracion WHERE id = 1');
$stmt->execute();
$config = $stmt->fetch();
if (!$config) {
    die('No hay configuración de prefijo (tabla configuracion).');
}

$prefijo = $config['prefijo_cola'];
$hoy     = date('Y-m-d');
$ahora   = date('H:i:s');

$pdo->beginTransaction();
try {
    // último número del día
    $stmt = $pdo->prepare('
        SELECT COALESCE(MAX(numero), 0) AS max_num
        FROM tickets
        WHERE fecha = ?
        FOR UPDATE
    ');
    $stmt->execute([$hoy]);
    $row = $stmt->fetch();
    $nuevo_numero = (int)$row['max_num'] + 1;

    // insertar ticket en espera
    $stmt = $pdo->prepare('
        INSERT INTO tickets (numero, prefijo, fecha, hora_creacion, estado)
        VALUES (?, ?, ?, ?, "ESPERA")
    ');
    $stmt->execute([$nuevo_numero, $prefijo, $hoy, $ahora]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Error al generar número: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$codigo_mostrar = $prefijo . '-' . str_pad((string)$nuevo_numero, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Tótem Permisos de Circulación</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    body { margin:0; font-family:sans-serif; text-align:center; background:#003366; color:#fff; }
    .contenedor { padding:2rem; }
    h1 { font-size:3rem; margin-bottom:1rem; }
    .numero { font-size:6rem; margin:2rem 0; font-weight:bold; }
    .btn { margin-top:1rem; padding:0.7rem 1.5rem; font-size:1.1rem; border-radius:999px; border:none; background:#0055aa; color:#fff; cursor:pointer; }
</style>
</head>
<body>
<div class="contenedor">
    <h1>Permisos de Circulación</h1>
    <p>Su número es:</p>
    <div class="numero"><?= htmlspecialchars($codigo_mostrar, ENT_QUOTES, 'UTF-8') ?></div>
    <p>Espere a ser llamado en la pantalla.</p>
    <form method="post">
        <button type="submit" class="btn">Tomar otro número</button>
    </form>
</div>
</body>
</html>
