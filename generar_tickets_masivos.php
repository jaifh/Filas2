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
$cantidad = (int)($input['cantidad'] ?? 10);
$papel = (int)($input['papel'] ?? 80);
$incluirQR = (bool)($input['incluirQR'] ?? true);
$incluirLogo = (bool)($input['incluirLogo'] ?? true);
$formatoRecorte = (bool)($input['formatoRecorte'] ?? true);

// Limitar cantidad por seguridad
if ($cantidad > 500) $cantidad = 500;
if ($cantidad < 1) $cantidad = 1;

try {
    $pdo = getPDO();
    
    // Obtener configuración
    $stmt = $pdo->prepare('SELECT prefijo_cola FROM configuracion WHERE id = 1');
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config) {
        throw new Exception('Configuración no encontrada');
    }

    $prefijo = $config['prefijo_cola'];
    $hoy = date('Y-m-d');
    $ahora = date('H:i:s');
    
    $tickets_generados = [];
    
    $pdo->beginTransaction();
    
    // Obtener siguiente número
    $stmt = $pdo->prepare('
        SELECT COALESCE(MAX(numero), 0) AS max_num
        FROM tickets
        WHERE fecha = ? AND prefijo = ?
        FOR UPDATE
    ');
    $stmt->execute([$hoy, $prefijo]);
    $row = $stmt->fetch();
    $proximo_numero = (int)$row['max_num'] + 1;
    
    // Generar tickets masivamente
    $stmt = $pdo->prepare('
        INSERT INTO tickets (numero, prefijo, fecha, hora_creacion, estado)
        VALUES (?, ?, ?, ?, "ESPERA")
    ');
    
    for ($i = 0; $i < $cantidad; $i++) {
        $numero = $proximo_numero + $i;
        $stmt->execute([$numero, $prefijo, $hoy, $ahora]);
        
        $tickets_generados[] = [
            'id' => $pdo->lastInsertId(),
            'numero' => $numero,
            'prefijo' => $prefijo,
            'codigo' => $prefijo . str_pad($numero, 3, '0', STR_PAD_LEFT)
        ];
    }
    
    $pdo->commit();
    
    // Generar HTML para impresión
    $html = generarHTMLImpresion($tickets_generados, $papel, $incluirQR, $incluirLogo, $formatoRecorte);
    
    // Guardar HTML temporalmente
    $archivo_tmp = __DIR__ . '/temp_print_' . time() . '.html';
    file_put_contents($archivo_tmp, $html);
    
    echo json_encode([
        'success' => true,
        'message' => "$cantidad tickets generados",
        'tickets_count' => $cantidad,
        'print_url' => 'temp_print_' . time() . '.html'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Generar HTML para impresión masiva
 */
function generarHTMLImpresion($tickets, $papel, $incluirQR, $incluirLogo, $formatoRecorte) {
    $ancho = $papel === 80 ? '80mm' : '58mm';
    $margen = $papel === 80 ? '10mm' : '5mm';
    
    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Impresión Masiva de Tickets</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Courier New', monospace;
                background: white;
                padding: 10mm;
            }

            @page {
                size: {$ancho};
                margin: 0;
            }

            .ticket {
                width: {$ancho};
                margin-bottom: 5mm;
                page-break-after: always;
                border: 1px dashed #999;
                padding: {$margen};
                background: white;
            }

            .ticket-header {
                text-align: center;
                font-weight: bold;
                font-size: 12px;
                margin-bottom: 5mm;
                border-bottom: 1px solid #000;
                padding-bottom: 3mm;
            }

            .logo-placeholder {
                text-align: center;
                font-size: 10px;
                color: #666;
                margin-bottom: 3mm;
            }

            .ticket-numero {
                text-align: center;
                font-size: 32px;
                font-weight: bold;
                letter-spacing: 3px;
                margin: 5mm 0;
                padding: 5mm;
                border: 2px solid #000;
                background: #f9f9f9;
            }

            .ticket-info {
                font-size: 9px;
                text-align: center;
                margin: 3mm 0;
            }

            .qr-container {
                text-align: center;
                margin: 5mm 0;
                font-size: 8px;
                color: #666;
            }

            .qr-code {
                max-width: 40mm;
                max-height: 40mm;
                margin: 2mm auto;
            }

            .instrucciones {
                font-size: 8px;
                text-align: center;
                margin-top: 3mm;
                padding-top: 3mm;
                border-top: 1px dashed #999;
            }

            .corte-linea {
                text-align: center;
                font-size: 8px;
                color: #999;
                margin: 2mm 0;
                letter-spacing: 2px;
            }

            @media print {
                body {
                    padding: 0;
                }

                .ticket {
                    border: none;
                    margin-bottom: 0;
                    page-break-after: always;
                    break-after: page;
                }
            }
        </style>
    </head>
    <body>
    HTML;

    foreach ($tickets as $ticket) {
        $codigo = $ticket['codigo'];
        $qr_data = "TICKET|{$codigo}|{$ticket['id']}|" . date('Y-m-d H:i:s');
        $qr_image = generarQRBase64($qr_data);

        $html .= <<<HTML
        <div class="ticket">
            <div class="ticket-header">
                MUNICIPALIDAD
            </div>
        
HTML;

        if ($incluirLogo) {
            $html .= '<div class="logo-placeholder">🏛️ LOGO</div>';
        }

        $html .= <<<HTML
            <div class="corte-linea">✂ ✂ ✂ ✂ ✂ ✂</div>

            <div class="ticket-numero">{$codigo}</div>

            <div class="ticket-info">
                Permisos de Circulación<br>
                Fecha: {$ticket['prefijo']}-{$ticket['numero']}
            </div>

        HTML;

        if ($incluirQR) {
            $html .= <<<HTML
            <div class="qr-container">
                <div>QR de Verificación:</div>
                <img src="{$qr_image}" alt="QR" class="qr-code">
                <div style="font-size: 7px; margin-top: 1mm;">
                    Escanea para verificar<br>autenticidad
                </div>
            </div>
            HTML;
        }

        $html .= <<<HTML
            <div class="instrucciones">
                Espere a ser llamado<br>
                {$_SERVER['REQUEST_TIME_FLOAT']}
            </div>

            <div class="corte-linea">✂ ✂ ✂ ✂ ✂ ✂</div>
        </div>

        HTML;
    }

    $html .= '</body></html>';
    return $html;
}

/**
 * Generar QR en Base64 usando API externa (fallback: texto)
 */
function generarQRBase64($data) {
    try {
        // Usar API de QR externa (qr-server.com es gratis y confiable)
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($data);
        $imagen = file_get_contents($url);
        $base64 = 'data:image/png;base64,' . base64_encode($imagen);
        return $base64;
    } catch (Exception $e) {
        // Fallback: generar placeholder
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg width="150" height="150" xmlns="http://www.w3.org/2000/svg">
                <rect width="150" height="150" fill="white" stroke="black" stroke-width="2"/>
                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="12">
                    [QR]
                </text>
            </svg>'
        );
    }
}
?>