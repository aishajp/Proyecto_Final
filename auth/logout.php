<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (is_logged_in()) {
    log_activity($_SESSION['user_id'], 'logout', 'Cierre de sesión');
}

// Destruir sesión
session_unset();
session_destroy();

// Redirigir al login
redirect('auth/login.php');
?>