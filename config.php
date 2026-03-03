<?php
// config.php
date_default_timezone_set('America/Santiago'); // Vital para Chile
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>