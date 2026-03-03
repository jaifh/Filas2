<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/printer_config.php';

class ThermalTicketPrinter {
    private $pdo;
    private $escpos;
    private $paper_width;
    private $printer_output = '';
    
    public function __construct($paper_size = PrinterConfig::PAPER_80MM) {
        $this->pdo = getPDO();
        $this->escpos = PrinterConfig::getESCPOS();
        $config = PrinterConfig::getPaperConfig($paper_size);
        $this->paper_width = $config['chars_per_line'];
    }
    
    /**
     * Generar ticket completo para impresora térmica
     */
    public function generateTicket($ticket_id) {
        $stmt = $this->pdo->prepare('
            SELECT t.id, t.numero, t.prefijo, t.fecha, t.hora_creacion
            FROM tickets t
            WHERE t.id = ?
        ');
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            throw new Exception('Ticket no encontrado');
        }
        
        $codigo = $ticket['prefijo'] . str_pad($ticket['numero'], 3, '0', STR_PAD_LEFT);
        
        // Construir comando ESCPOS
        $output = '';
        
        // Inicializar impresora
        $output .= $this->escpos['ESC'] . '@';  // Reset
        
        // Encabezado
        $output .= $this->escpos['ALIGN_CENTER'];
        $output .= $this->escpos['SIZE_LARGE'];
        $output .= $this->escpos['BOLD_ON'];
        $output .= $this->center('MUNICIPALIDAD') . $this->escpos['LF'];
        $output .= $this->escpos['SIZE_MEDIUM'];
        $output .= $this->center('Permisos de Circulación') . $this->escpos['LF'];
        $output .= $this->escpos['BOLD_OFF'];
        $output .= $this->escpos['SIZE_SMALL'];
        
        $output .= str_repeat('=', $this->paper_width) . $this->escpos['LF'];
        $output .= $this->escpos['LF'];
        
        // Número principal (GRANDE)
        $output .= $this->escpos['ALIGN_CENTER'];
        $output .= $this->escpos['SIZE_XLARGE'];
        $output .= $this->escpos['BOLD_ON'];
        $output .= $codigo . $this->escpos['LF'];
        $output .= $this->escpos['BOLD_OFF'];
        $output .= $this->escpos['LF'];
        
        // QR Code (necesita biblioteca)
        $qr_data = $this->generateQRCode($codigo, $ticket_id);
        if ($qr_data) {
            $output .= $this->printQRCode($qr_data);
        }
        
        $output .= $this->escpos['LF'];
        
        // Información del ticket
        $output .= $this->escpos['ALIGN_CENTER'];
        $output .= $this->escpos['SIZE_SMALL'];
        $output .= str_repeat('=', $this->paper_width) . $this->escpos['LF'];
        $output .= $this->escpos['LF'];
        $output .= 'Fecha: ' . date('d/m/Y', strtotime($ticket['fecha'])) . $this->escpos['LF'];
        $output .= 'Hora: ' . $ticket['hora_creacion'] . $this->escpos['LF'];
        $output .= 'Número ID: ' . $ticket['id'] . $this->escpos['LF'];
        $output .= $this->escpos['LF'];
        
        // Instrucciones
        $output .= str_repeat('-', $this->paper_width) . $this->escpos['LF'];
        $output .= $this->escpos['ALIGN_CENTER'];
        $output .= 'Espere a ser llamado' . $this->escpos['LF'];
        $output .= 'por pantalla o audio' . $this->escpos['LF'];
        $output .= $this->escpos['LF'];
        
        // Footer
        $output .= 'Generado: ' . date('d/m/Y H:i:s') . $this->escpos['LF'];
        $output .= $this->escpos['LF'];
        $output .= str_repeat('=', $this->paper_width) . $this->escpos['LF'];
        
        // Corte de papel
        $output .= $this->escpos['PARTIAL_CUT'];
        
        return $output;
    }
    
    /**
     * Centrar texto
     */
    private function center($text) {
        $text_len = strlen(mb_convert_encoding($text, 'UTF-8'));
        $spaces = floor(($this->paper_width - $text_len) / 2);
        return str_repeat(' ', max(0, $spaces)) . $text;
    }
    
    /**
     * Generar código QR
     */
    private function generateQRCode($codigo, $ticket_id) {
        // Intenta usar la librería si está disponible
        try {
            $qr_text = "TICKET|{$codigo}|{$ticket_id}|" . date('Y-m-d H:i:s');
            return $qr_text;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Imprimir QR (comando ESCPOS para módulo QR)
     * Requiere impresora con módulo QR
     */
    private function printQRCode($data) {
        $output = '';
        $output .= $this->escpos['ALIGN_CENTER'];
        
        // Comando ESCPOS para QR (modelo común)
        $qr_length = strlen($data);
        
        // GS ( k - Comando para QR
        $output .= chr(29) . '(' . 'k' . 
                   chr($qr_length & 0xFF) . chr(($qr_length >> 8) & 0xFF) . 
                   '49' . '0' . $data;
        
        // Imprimir QR
        $output .= chr(29) . '(' . 'k' . chr(3) . chr(0) . 
                   '49' . '2' . chr(67);  // 67 = tamaño módulo
        
        $output .= $this->escpos['LF'];
        
        return $output;
    }
    
    /**
     * Enviar a impresora
     */
    public function printToDevice($content) {
        $printer_type = PrinterConfig::PRINTER_TYPE;
        
        switch ($printer_type) {
            case 'network':
                return $this->printToNetworkPrinter($content);
            case 'usb':
                return $this->printToUSBPrinter($content);
            case 'file':
            default:
                return $this->printToFile($content);
        }
    }
    
    /**
     * Imprimir a través de red (IP:Puerto)
     */
    private function printToNetworkPrinter($content) {
        try {
            $socket = @fsockopen(
                PrinterConfig::PRINTER_IP,
                PrinterConfig::PRINTER_PORT,
                $errno,
                $errstr,
                5  // timeout 5 segundos
            );
            
            if (!$socket) {
                throw new Exception("No se pudo conectar a impresora: {$errstr}");
            }
            
            fwrite($socket, $content);
            fclose($socket);
            
            return ['success' => true, 'message' => 'Impreso exitosamente'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Imprimir a través de USB (Linux)
     */
    private function printToUSBPrinter($content) {
        try {
            $file = @fopen(PrinterConfig::USB_DEVICE, 'wb');
            
            if (!$file) {
                throw new Exception('No se puede acceder a dispositivo USB');
            }
            
            fwrite($file, $content);
            fclose($file);
            
            return ['success' => true, 'message' => 'Impreso a USB'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Imprimir a archivo (para pruebas)
     */
    private function printToFile($content) {
        try {
            file_put_contents(
                PrinterConfig::OUTPUT_FILE,
                $content . "\n\n",
                FILE_APPEND
            );
            return ['success' => true, 'message' => 'Guardado en archivo de prueba'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Generar HTML para vista previa
     */
    public function generateHTMLPreview($ticket_id) {
        $stmt = $this->pdo->prepare('
            SELECT t.id, t.numero, t.prefijo, t.fecha, t.hora_creacion
            FROM tickets t
            WHERE t.id = ?
        ');
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            return null;
        }
        
        $codigo = $ticket['prefijo'] . str_pad($ticket['numero'], 3, '0', STR_PAD_LEFT);
        
        return <<<HTML
        <div style="
            width: 80mm;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 10px;
            background: white;
            margin: 0 auto;
        ">
            <div style="text-align: center; font-weight: bold; font-size: 18px;">
                MUNICIPALIDAD
            </div>
            <div style="text-align: center; font-size: 14px; margin-bottom: 10px;">
                Permisos de Circulación
            </div>
            <hr>
            
            <div style="text-align: center; font-size: 48px; font-weight: bold; margin: 20px 0;">
                {$codigo}
            </div>
            
            <hr style="border: 1px dashed #000;">
            
            <div style="text-align: center; font-size: 11px;">
                Fecha: {$ticket['fecha']}<br>
                Hora: {$ticket['hora_creacion']}<br>
                ID: {$ticket['id']}
            </div>
            
            <div style="text-align: center; margin: 15px 0; font-size: 11px;">
                ────────────────<br>
                Espere a ser llamado<br>
                por pantalla o audio<br>
                ────────────────
            </div>
            
            <div style="text-align: center; font-size: 10px;">
                {$_SERVER['HTTP_HOST']}<br>
                {$_SERVER['REQUEST_TIME']}
            </div>
        </div>
        HTML;
    }
}

// API para imprimir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ticket_id = $input['ticket_id'] ?? null;
    $action = $input['action'] ?? 'print';
    
    try {
        $printer = new ThermalTicketPrinter(
            $input['paper_size'] ?? PrinterConfig::PAPER_80MM
        );
        
        if ($action === 'preview') {
            $html = $printer->generateHTMLPreview($ticket_id);
            echo json_encode(['success' => true, 'html' => $html]);
        } else {
            $ticket_content = $printer->generateTicket($ticket_id);
            $result = $printer->printToDevice($ticket_content);
            echo json_encode($result);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}