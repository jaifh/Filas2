<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
    $hoy = date('Y-m-d');

    // Agregamos 'FINALIZADO' para que el historial no se vacíe
   $dept_id = (int)($_GET['dept'] ?? 1);
    
    $stmt = $pdo->prepare("
        SELECT t.id, t.prefijo, t.numero, t.estado, t.hora_llamado, m.nombre AS modulo
        FROM tickets t
        LEFT JOIN modulos m ON t.modulo_id = m.id
        WHERE t.fecha = ? AND t.departamento_id = ? AND t.estado IN ('LLAMADO','ATENDIENDO','FINALIZADO') 
        ORDER BY t.hora_llamado DESC, t.id DESC
        LIMIT 15
    ");
    $stmt->execute([$hoy, $dept_id]);
    $tickets = $stmt->fetchAll();

    echo json_encode(['tickets' => $tickets]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener datos para display']);
}