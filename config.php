<?php
declare(strict_types=1);

session_start();

define('DB_HOST', 'mariadb_sistemas');
define('DB_NAME', 'filas_muni');
define('DB_USER', 'user_filas');
define('DB_PASS', '6pYyJNoMN0NR)LN/');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Filas Permisos de Circulación');
define('APP_TIMEZONE', 'America/Santiago');

date_default_timezone_set(APP_TIMEZONE);
