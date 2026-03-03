<?php
// caja_api.php
require_once __DIR__ . '/auth.php';
requireLogin(['ADMIN','CAJA']);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$accion    = $_POST['accion'] ?? '';
$modulo_id = (int)($_POST['modulo_id'] ?? 0);

if (!$modulo_id || !in_array($accion, ['siguiente','rellamar','finalizar'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

$pdo   = getPDO();
$hoy   = date('Y-m-d');
$ahora = date('H:i:s');

try {
    if ($accion === 'siguiente') {
        $pdo->beginTransaction();

        // Configuración de prefijo y número inicial
        $stmt = $pdo->prepare("SELECT prefijo_cola, numero_inicial FROM configuracion WHERE id = 1");
        $stmt->execute();
        $config = $stmt->fetch();
        if (!$config) {
            throw new RuntimeException('Sin configuración de numeración.');
        }
        $prefijo = $config['prefijo_cola'];
        $inicio  = (int)$config['numero_inicial'];

        // Obtener último número del día (para TODAS las cajas)
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(numero), ?) AS max_num
            FROM tickets
            WHERE fecha = ?
            FOR UPDATE
        ");
        $base = $inicio - 1;
        $stmt->execute([$base, $hoy]);
        $row = $stmt->fetch();
        $nuevo_numero = (int)$row['max_num'] + 1;

        // Opcional: cerrar ticket anterior de este módulo si sigue en LLAMADO/ATENDIENDO
        $stmt = $pdo->prepare("
            UPDATE tickets
            SET estado = 'FINALIZADO', hora_fin = ?
            WHERE fecha = ? AND modulo_id = ?
              AND estado IN ('LLAMADO','ATENDIENDO')
        ");
        $stmt->execute([$ahora, $hoy, $modulo_id]);

        // Crear el nuevo ticket directamente como LLAMADO para este módulo
        $stmt = $pdo->prepare("
            INSERT INTO tickets (numero, prefijo, modulo_id, estado, fecha, hora_creacion, hora_llamado)
            VALUES (?, ?, ?, 'LLAMADO', ?, ?, ?)
        ");
        $stmt->execute([$nuevo_numero, $prefijo, $modulo_id, $hoy, $ahora, $ahora]);

        $pdo->commit();

        $codigo = $prefijo . '-' . str_pad((string)$nuevo_numero, 3, '0', STR_PAD_LEFT);
        echo json_encode(['codigo' => $codigo]);
        exit;
    }

    if ($accion === 'rellamar') {
        // 1. Buscamos el ticket que este módulo tiene activo
        $stmt = $pdo->prepare("
            SELECT id, numero, prefijo
            FROM tickets
            WHERE fecha = ? AND modulo_id = ?
              AND estado IN ('LLAMADO','ATENDIENDO')
            ORDER BY hora_llamado DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$hoy, $modulo_id]);
        $actual = $stmt->fetch();

        if (!$actual) {
            echo json_encode(['error' => 'No hay ticket activo para re-llamar']);
            exit;
        }

        // 2. ACTUALIZAMOS LA HORA (Esto es lo que activa el sonido en el display)
        $stmt = $pdo->prepare("UPDATE tickets SET hora_llamado = ? WHERE id = ?");
        $stmt->execute([$ahora, $actual['id']]);

        $codigo = $actual['prefijo'] . '-' . str_pad((string)$actual['numero'], 3, '0', STR_PAD_LEFT);
        echo json_encode(['codigo' => $codigo]);
        exit;
    }
    if ($accion === 'finalizar') {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id, numero, prefijo
            FROM tickets
            WHERE fecha = ? AND modulo_id = ?
              AND estado IN ('LLAMADO','ATENDIENDO')
            ORDER BY hora_llamado DESC, id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$hoy, $modulo_id]);
        $actual = $stmt->fetch();

        if (!$actual) {
            $pdo->rollBack();
            echo json_encode(['error' => 'No hay ticket para finalizar']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET estado = 'FINALIZADO',
                hora_fin = ?
            WHERE id = ?
        ");
        $stmt->execute([$ahora, $actual['id']]);
        $pdo->commit();

        $codigo = $actual['prefijo'] . '-' . str_pad((string)$actual['numero'], 3, '0', STR_PAD_LEFT);
        echo json_encode(['codigo' => $codigo]);
        exit;
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Error interno en caja_api']);
    exit;
}
