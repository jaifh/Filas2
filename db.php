<?php
// db.php
function getPDO() {
    $host = '127.0.0.1'; // O localhost
    $db   = 'filas_muni';
    $user = 'root';      // Tu usuario de BD
    $pass = '198224';          // Tu contraseña de BD
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}
?>