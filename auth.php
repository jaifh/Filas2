<?php
// auth.php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function login(string $username, string $password): bool {
    $pdo = getPDO();

    // Columnas reales: username, password_hash, nombre, rol, activo
    $stmt = $pdo->prepare("
        SELECT id, username, password_hash, nombre, rol, activo
        FROM usuarios
        WHERE username = ? AND activo = 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return false;
    }

    // Password viene en password_hash (bcrypt)
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Variables de sesión que usa todo el sistema
    $_SESSION['id']     = (int)$user['id'];
    $_SESSION['nombre'] = $user['nombre'];
    $_SESSION['rol']    = $user['rol'];

    return true;
}

function requireLogin(array $rolesPermitidos = []): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['id'])) {
        header('Location: login.php');
        exit;
    }

    if ($rolesPermitidos) {
        $rol = $_SESSION['rol'] ?? '';
        if (!in_array($rol, $rolesPermitidos, true)) {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }
    }
}

function requireAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Debe estar logueado
    if (empty($_SESSION['id'])) {
        header('Location: login.php');
        exit;
    }

    // Debe tener rol ADMIN
    if (($_SESSION['rol'] ?? '') !== 'ADMIN') {
        http_response_code(403);
        echo 'Acceso permitido solo a ADMIN.';
        exit;
    }
}
