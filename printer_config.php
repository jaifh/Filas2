<?php
/**
 * Configuración de impresoras térmicas ESCPOS
 * Compatible con impresoras 58mm y 80mm
 */

class PrinterConfig {
    // Tipos de papel
    const PAPER_58MM = 32;
    const PAPER_80MM = 48;
    
    // Tipo de conexión a impresora
    const PRINTER_TYPE = 'file';  // Opciones: 'network', 'usb', 'file' (cambiar según tu setup)
    const PRINTER_IP = '192.168.1.100';
    const PRINTER_PORT = 9100;
    const USB_DEVICE = '/dev/usb/lp0';
    const OUTPUT_FILE = __DIR__ . '/tickets_output.txt';
    
    /**
     * Obtener configuración de papel
     */
    public static function getPaperConfig($paper_size = self::PAPER_80MM) {
        return [
            'width' => $paper_size,
            'chars_per_line' => $paper_size,
        ];
    }
    
    /**
     * Códigos ESCPOS estándar
     */
    public static function getESCPOS() {
        return [
            'ESC' => chr(27),
            'GS' => chr(29),
            
            'ALIGN_LEFT' => chr(27) . 'a' . chr(0),
            'ALIGN_CENTER' => chr(27) . 'a' . chr(1),
            'ALIGN_RIGHT' => chr(27) . 'a' . chr(2),
            
            'SIZE_NORMAL' => chr(29) . '!' . chr(0),
            'SIZE_DOUBLE' => chr(29) . '!' . chr(17),
            'SIZE_TRIPLE' => chr(29) . '!' . chr(34),
            'SIZE_QUAD' => chr(29) . '!' . chr(51),
            
            'BOLD_ON' => chr(27) . 'E' . chr(1),
            'BOLD_OFF' => chr(27) . 'E' . chr(0),
            
            'LF' => chr(10),
            'CR' => chr(13),
            'FF' => chr(12),
            
            'PARTIAL_CUT' => chr(29) . 'V' . chr(1),
            'FULL_CUT' => chr(29) . 'V' . chr(0),
            'RESET' => chr(27) . '@',
        ];
    }
}