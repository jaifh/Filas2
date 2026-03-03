<?php
// dashboard_data.php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();

// KPI generales
$kpi = [];

// Total tickets registrados en logs
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM tickets_logs");
$kpi['total_tickets'] = (int)$stmt->fetch()['total'];

// Tickets de hoy
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM tickets_logs WHERE fecha = CURDATE()");
$stmt->execute();
$kpi['hoy'] = (int)$stmt->fetch()['total'];

// Tickets últimos 7 días
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM tickets_logs WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
$stmt->execute();
$kpi['ultimos_7'] = (int)$stmt->fetch()['total'];

// Tickets por módulo (total)
$sqlModTotal = "
    SELECT m.nombre AS modulo, COUNT(t.id) AS total
    FROM tickets_logs t
    LEFT JOIN modulos m ON t.modulo_id = m.id
    GROUP BY modulo
    ORDER BY total DESC
";
$modTotal = $pdo->query($sqlModTotal)->fetchAll();

// Tickets por día (últimos 14 días)
$sqlDia = "
    SELECT fecha, COUNT(*) AS total
    FROM tickets_logs
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY fecha
    ORDER BY fecha ASC
";
$porDia = $pdo->query($sqlDia)->fetchAll();

// Tickets por módulo y día (últimos 7 días)
$sqlModDia = "
    SELECT 
        DATE(fecha) AS fecha,
        m.nombre AS modulo,
        COUNT(*) AS total
    FROM tickets_logs t
    LEFT JOIN modulos m ON t.modulo_id = m.id
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY fecha, modulo
    ORDER BY fecha ASC, modulo ASC
";
$modDiaRows = $pdo->query($sqlModDia)->fetchAll();

echo json_encode([
    'kpi'      => $kpi,
    'modTotal' => $modTotal,
    'porDia'   => $porDia,
    'modDia'   => $modDiaRows,
]);
