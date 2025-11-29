<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = (int)$_POST['id_clase'];
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
        redirect('admin/ver_clase.php?id=' . $id_clase);
    }
    
    // Calcular hora fin
    $hora_fin = date('H:i:s', strtotime($hora_inicio . ' +' . $duracion . ' minutes'));
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Obtener plazas disponibles actuales
        $stmt = $db->prepare("SELECT plazas_disponibles, plazas_totales FROM clases WHERE id_clase = ?");
        $stmt->execute([$id_clase]);
        $clase_actual = $stmt->fetch();
        
        // Calcular nuevas plazas disponibles
        $diferencia = $plazas_totales - $clase_actual['plazas_totales'];
        $nuevas_plazas_disponibles = $clase_actual['plazas_disponibles'] + $diferencia;
        
        // No puede ser negativo
        if ($nuevas_plazas_disponibles < 0) {
            $nuevas_plazas_disponibles = 0;
        }
        
        $stmt = $db->prepare("
            UPDATE clases 
            SET titulo = ?, descripcion = ?, tipo = ?, nivel = ?, fecha = ?, 
                hora_inicio = ?, hora_fin = ?, duracion = ?, plazas_totales = ?, 
                plazas_disponibles = ?, precio = ?, ubicacion = ?
            WHERE id_clase = ?
        ");
        
        if ($stmt->execute([
            $titulo, $descripcion, $tipo, $nivel, $fecha, $hora_inicio, $hora_fin,
            $duracion, $plazas_totales, $nuevas_plazas_disponibles, $precio, $ubicacion, $id_clase
        ])) {
            set_message('Clase actualizada correctamente', MSG_SUCCESS);
        } else {
            set_message('Error al actualizar la clase', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/ver_clase.php?id=' . $id_clase);
}

redirect('administrador/clases.php');
?>