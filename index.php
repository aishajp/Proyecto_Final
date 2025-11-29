<?php

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Si el usuario ya está autenticado, redirigir a su dashboard
if (is_logged_in()) {
    $role = $_SESSION['user_role'];
    redirect($role . '/dashboard.php');
}

// Si no está autenticado, redirigir al login
redirect('auth/login.php');
?>