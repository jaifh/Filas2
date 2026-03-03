<?php
// auth.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function login($username, $password) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ? AND activo = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['id']              = $user['id'];
        $_SESSION['username']        = $user['username'];
        $_SESSION['nombre']          = $user['nombre'];
        $_SESSION['rol']             = $user['rol'];
        // Guardamos el departamento (Null si es SUPER-ADMIN)
        $_SESSION['departamento_id'] = $user['departamento_id']; 
        return true;
    }
    return false;
}

function requireLogin($roles_permitidos = []) {
    if (empty($_SESSION['id'])) {
        header('Location: login.php');
        exit;
    }
    if (!empty($roles_permitidos) && !in_array($_SESSION['rol'], $roles_permitidos)) {
        die('Acceso denegado. Rol insuficiente.');
    }
}
?>