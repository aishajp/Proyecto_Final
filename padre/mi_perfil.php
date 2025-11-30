<?php
// padre/mi_perfil.php - REDIRIGE A DASHBOARD
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

// Redirigir directamente al dashboard
redirect('padre/dashboard.php');
?>