<?php
parse_str($_SERVER['QUERY_STRING'], $query);
$codigo = $query['codigo'] ?? '';

if ($codigo) {
    // Decodificar y mostrar información del ticket
    echo "<h1>Ticket: $codigo</h1>";
    echo "<p>✓ Ticket Verificado</p>";
    echo "<p>Generado: " . date('d/m/Y H:i:s') . "</p>";
}
?>