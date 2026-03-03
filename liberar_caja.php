<?php
// liberar_caja.php
require_once __DIR__ . '/auth.php';
requireLogin(['ADMIN','CAJA']);
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo        = getPDO();
$usuario_id = (int)($_SESSION['id'] ?? 0);

if ($usuario_id > 0) {
    // deja libres los módulos que use este usuario
    $stmt = $pdo->prepare("UPDATE modulos SET usuario_en_uso = NULL WHERE usuario_en_uso = ?");
    $stmt->execute([$usuario_id]);

    // saca el modulo_id de la sesión (equipo sin módulo)
    unset($_SESSION['modulo_id']);
}

// vuelve al index para que seleccione módulo
header('Location: index.php');
exit;
