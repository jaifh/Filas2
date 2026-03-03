<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$fecha = $input['fecha'] ?? date('Y-m-d');
$prefijo = $input['prefijo'] ?? '';
$estado = $input['estado'] ?? '';
$papel = (int)($input['papel'] ?? 80);

try {
    $pdo = getPDO();
    
    $query = 'SELECT id, numero, prefijo, fecha FROM tickets WHERE fecha = ?';
    $params = [$fecha];
    
    if ($prefijo) {
        $query .= ' AND prefijo = ?';
        $params[] = $prefijo;
    }
    
    if ($estado) {
        $query .= ' AND estado = ?';
        $params[] = $estado;
    }
    
    $query .= ' ORDER BY numero ASC';
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tickets)) {
        throw new Exception('No se encontraron tickets');
    }
    
    // Procesar tickets para impresión
    $tickets_procesados = array_map(function($t) {
        return [
            'id' => $t['id'],
            'numero' => $t['numero'],
            'prefijo' => $t['prefijo'],
            'codigo' => $t['prefijo'] . str_pad($t['numero'], 3, '0', STR_PAD_LEFT)
        ];
    }, $tickets);
    
    // Generar HTML
    $html = generarHTMLImpresion($tickets_procesados, $papel, true, true, true);
    
    $archivo_tmp = __DIR__ . '/temp_reprint_' . time() . '.html';
    file_put_contents($archivo_tmp, $html);
    
    echo json_encode([
        'success' => true,
        'tickets_count' => count($tickets),
        'print_url' => 'temp_reprint_' . time() . '.html'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Reutilizar función de generación
function generarHTMLImpresion($tickets, $papel, $incluirQR, $incluirLogo, $formatoRecorte) {
    $ancho = $papel === 80 ? '80mm' : '58mm';
    
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>';
    $html .= 'body{font-family:Courier;margin:0;padding:10mm;background:white}';
    $html .= '@page{size:' . $ancho . ';margin:0}';
    $html .= '.ticket{width:' . $ancho . ';margin-bottom:5mm;page-break-after:always;border:1px dashed #999;padding:10mm;text-align:center}';
    $html .= '.numero{font-size:32px;font-weight:bold;letter-spacing:3px;margin:10mm 0;padding:5mm;border:2px solid #000}';
    $html .= '.info{font-size:9px;margin:5mm 0}';
    $html .= '.qr{max-width:40mm;margin:5mm auto}';
    $html .= '</style></head><body>';
    
    foreach ($tickets as $t) {
        $codigo = $t['codigo'];
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($codigo);
        
        $html .= '<div class="ticket">';
        $html .= '<div style="font-weight:bold;margin-bottom:5mm;">MUNICIPALIDAD</div>';
        $html .= '<div style="font-size:8px;margin-bottom:5mm;">✂ ✂ ✂ ✂ ✂ ✂</div>';
        $html .= '<div class="numero">' . $codigo . '</div>';
        $html .= '<div class="info">Permisos de Circulación</div>';
        
        if ($incluirQR) {
            $html .= '<div class="info">Código de Verificación:</div>';
            $html .= '<img src="' . $qr_url . '" class="qr" alt="QR">';
        }
        
        $html .= '<div style="font-size:8px;margin-top:5mm;border-top:1px dashed #999;padding-top:3mm;">';
        $html .= 'Espere a ser llamado';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</body></html>';
    return $html;
}
?>