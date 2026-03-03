<?php
// dashboard_data.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN', 'ADMIN']);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
    $esSuperAdmin = ($_SESSION['rol'] === 'SUPER-ADMIN');
    $filtro_dept = $_GET['dept'] ?? 'ALL';
    
    if (!$esSuperAdmin) {
        $filtro_dept = $_SESSION['departamento_id'];
    }

    // EL ALIAS "t." ES VITAL AQUÍ PARA EVITAR EL ERROR DE COLUMNA AMBIGUA
    $condicion_dept = "";
    $params = [];
    if ($filtro_dept !== 'ALL') {
        $condicion_dept = " AND t.departamento_id = ? ";
        $params[] = (int)$filtro_dept;
    }

    $hoy = date('Y-m-d');
    $fecha7 = date('Y-m-d', strtotime('-7 days'));
    $fecha30 = date('Y-m-d', strtotime('-30 days'));

    // --- KPI 1: TOTAL DE TICKETS (Histórico + Vivo) ---
    $stmtH = $pdo->prepare("SELECT COUNT(*) FROM tickets_logs t WHERE 1=1" . $condicion_dept);
    $stmtH->execute($params);
    $total_h = (int)$stmtH->fetchColumn();

    $stmtV = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE 1=1" . $condicion_dept);
    $stmtV->execute($params);
    $total_v = (int)$stmtV->fetchColumn();
    
    $total = $total_h + $total_v;

    // --- KPI 2: TICKETS HOY ---
    $stmtHoy = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE t.fecha = ?" . $condicion_dept);
    $stmtHoy->execute(array_merge([$hoy], $params));
    $hoyCount = (int)$stmtHoy->fetchColumn();

    $stmtHoyLog = $pdo->prepare("SELECT COUNT(*) FROM tickets_logs t WHERE t.fecha = ?" . $condicion_dept);
    $stmtHoyLog->execute(array_merge([$hoy], $params));
    $hoyCount += (int)$stmtHoyLog->fetchColumn();

    // --- KPI 3: ÚLTIMOS 7 DÍAS ---
    $stmt7 = $pdo->prepare("SELECT COUNT(*) FROM tickets_logs t WHERE t.fecha >= ?" . $condicion_dept);
    $stmt7->execute(array_merge([$fecha7], $params));
    $ultimos7Count = (int)$stmt7->fetchColumn() + $total_v;

    // --- GRÁFICO 1: POR DÍA (Combinando en vivo e historial) ---
    $sqlDia = "
        SELECT fecha, SUM(total) as total FROM (
            SELECT t.fecha, COUNT(*) as total FROM tickets_logs t WHERE t.fecha >= ? {$condicion_dept} GROUP BY t.fecha
            UNION ALL
            SELECT t.fecha, COUNT(*) as total FROM tickets t WHERE t.fecha >= ? {$condicion_dept} GROUP BY t.fecha
        ) as comb
        GROUP BY fecha ORDER BY fecha ASC
    ";
    $stmtDia = $pdo->prepare($sqlDia);
    $stmtDia->execute(array_merge([$fecha30], $params, [$fecha30], $params));
    $porDiaTodos = $stmtDia->fetchAll(PDO::FETCH_ASSOC);

    // --- GRÁFICO 2: POR MÓDULO (Últimos 7 días) ---
    $sqlMod = "
        SELECT m.nombre as modulo, COUNT(t.id) as total 
        FROM tickets_logs t
        LEFT JOIN modulos m ON t.modulo_id = m.id
        WHERE t.fecha >= ? {$condicion_dept} 
        GROUP BY m.nombre 
        ORDER BY total DESC LIMIT 10
    ";
    $stmtMod = $pdo->prepare($sqlMod);
    $stmtMod->execute(array_merge([$fecha7], $params));
    $modDia = $stmtMod->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'kpi' => [
            'total_tickets' => $total,
            'hoy'           => $hoyCount,
            'ultimos_7'     => $ultimos7Count,
        ],
        'porDia' => $porDiaTodos,
        'modDia' => $modDia
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}