<?php
declare(strict_types=1);

session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'filas_muni');
define('DB_USER', 'root');
define('DB_PASS', '198224');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Filas Permisos de Circulación');
define('APP_TIMEZONE', 'America/Santiago');

date_default_timezone_set(APP_TIMEZONE);
