<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_examen = (int)$_POST['id_examen'];
    $titulo = sanitize_input($_POST['titulo']);
    $descripcion = sanitize_input($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $nivel = $_POST['nivel'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $duracion = (int)$_POST['duracion'];
    $puntuacion_maxima = (float)$_POST['puntuacion_maxima'];
    $ubicacion = sanitize_input($_POST['ubicacion']);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/examenes.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            UPDATE examenes 
            SET titulo = ?, descripcion = ?, fecha = ?, hora_inicio = ?, duracion = ?,nivel = ?, tipo = ?, ubicacion = ?, puntuacion_maxima = ?
            WHERE id_examen = ?
        ");
        
        if ($stmt->execute([
            $titulo, $descripcion, $fecha, $hora_inicio, $duracion, $nivel,
            $tipo, $ubicacion, $puntuacion_maxima, $id_examen
        ])) {
            set_message('Examen actualizado correctamente', MSG_SUCCESS);
        } else {
            set_message('Error al actualizar el examen', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/examenes.php');
}

redirect('administrador/examenes.php');

?>