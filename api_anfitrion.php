<?php
// api_anfitrion.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$tipo = strtoupper($input['tipo'] ?? 'NORMAL');

if (!in_array($tipo, ['NORMAL', 'PREFERENCIAL'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    exit;
}

try {
    $pdo = getPDO();
    $hoy = date('Y-m-d');
    $ahora = date('H:i:s');
    
    $pdo->beginTransaction();
    
    // Obtener configuración
    $stmt = $pdo->query('SELECT prefijo_cola, numero_inicial, prefijo_preferencial, numero_inicial_preferencial FROM configuracion WHERE id = 1 FOR UPDATE');
    $config = $stmt->fetch();
    
    $prefijo = ($tipo === 'PREFERENCIAL') ? $config['prefijo_preferencial'] : $config['prefijo_cola'];
    $inicio  = ($tipo === 'PREFERENCIAL') ? $config['numero_inicial_preferencial'] : $config['numero_inicial'];
    
    // Buscar el último número registrado hoy para este prefijo
    $stmt = $pdo->prepare('SELECT MAX(numero) AS max_num FROM tickets WHERE fecha = ? AND prefijo = ?');
    $stmt->execute([$hoy, $prefijo]);
    $row = $stmt->fetch();
    
    $proximo_numero = ($row['max_num'] !== null) ? (int)$row['max_num'] + 1 : (int)$inicio;
    
    // Registrar el ticket en la base de datos
    $stmt = $pdo->prepare('INSERT INTO tickets (numero, prefijo, tipo, estado, fecha, hora_creacion) VALUES (?, ?, ?, "ESPERA", ?, ?)');
    $stmt->execute([$proximo_numero, $prefijo, $tipo, $hoy, $ahora]);
    
    $pdo->commit();
    
    $codigo_generado = $prefijo . '-' . str_pad($proximo_numero, 3, '0', STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'codigo' => $codigo_generado
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'message' => 'Error de conexión con la BD']);
}
?>