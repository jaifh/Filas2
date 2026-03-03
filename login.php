<?php
// login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, lo mando a index
if (!empty($_SESSION['id'])) {
    header('Location: index.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error          = '';
$username_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Sesión expirada, vuelva a intentar.';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        $username_value = $user;

        if ($user !== '' && $pass !== '' && login($user, $pass)) {
            session_regenerate_id(true);
            header('Location: index.php'); // luego selecciona módulo
            exit;
        } else {
            $error = 'Usuario o contraseña inválidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Login - Filas Permisos de Circulación</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: #f0f2f5;
        color: #222;
    }
    .wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .card {
        background: #ffffff;
        padding: 2rem 2.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        max-width: 400px;
        width: 100%;
    }
    h1 {
        margin-top: 0;
        margin-bottom: 0.25rem;
        font-size: 1.7rem;
        text-align: center;
        color: #003366;
    }
    .subtitulo {
        text-align: center;
        margin-bottom: 1.5rem;
        font-size: 0.95rem;
        color: #666;
    }
    label {
        display: block;
        margin-bottom: 0.8rem;
        font-size: 0.95rem;
    }
    input[type="text"],
    input[type="password"] {
        width: 100%;
        padding: 0.55rem 0.75rem;
        margin-top: 0.25rem;
        border-radius: 0.4rem;
        border: 1px solid #ccd0d5;
        font-size: 0.95rem;
    }
    input:focus {
        outline: none;
        border-color: #0055aa;
        box-shadow: 0 0 0 2px rgba(0,85,170,0.1);
    }
    .btn {
        width: 100%;
        padding: 0.7rem 1rem;
        border-radius: 999px;
        border: none;
        background: #0055aa;
        color: #ffffff;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        margin-top: 0.5rem;
    }
    .btn:hover {
        background: #003f80;
    }
    .error {
        background: #ffdddd;
        border: 1px solid #e88;
        color: #a00;
        padding: 0.6rem 0.8rem;
        border-radius: 0.4rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }
    .nota {
        margin-top: 1rem;
        font-size: 0.8rem;
        color: #777;
        text-align: center;
    }
</style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <h1>Ingreso a Cajas</h1>
        <div class="subtitulo">Permisos de Circulación</div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <label>
                Usuario
                <input type="text" name="username" required
                       value="<?= htmlspecialchars($username_value, ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label>
                Contraseña
                <input type="password" name="password" required>
            </label>

            <button type="submit" class="btn">Ingresar</button>
        </form>

        <div class="nota">
            <a href="display.php" target="_blank"> PANTALLA LLAMADOS </a><br>
            Uso exclusivo del personal autorizado de cajas / módulos.
        </div>
    </div>
</div>
</body>
</html>
