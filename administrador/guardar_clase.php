<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitize_input($_POST['titulo']);
    $descripcion = sanitize_input($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $nivel = $_POST['nivel'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $duracion = (int)$_POST['duracion'];
    $plazas_totales = (int)$_POST['plazas_totales'];
    $precio = (float)$_POST['precio'];
    $ubicacion = sanitize_input($_POST['ubicacion']);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/clases.php');
    }
    
    // Calcular hora fin
    $hora_fin = date('H:i:s', strtotime($hora_inicio . ' +' . $duracion . ' minutes'));
    
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO clases (titulo, descripcion, tipo, nivel, fecha, hora_inicio, hora_fin, 
                               duracion, plazas_disponibles, plazas_totales, precio, ubicacion, id_profesor)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $titulo, $descripcion, $tipo, $nivel, $fecha, $hora_inicio, $hora_fin,
            $duracion, $plazas_totales, $plazas_totales, $precio, $ubicacion, $_SESSION['user_id']
        ])) {
            set_message('Clase creada correctamente', MSG_SUCCESS);
        } else {
            set_message('Error al crear la clase', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/clases.php');
}
?>