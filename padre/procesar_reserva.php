<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = (int)$_POST['id_clase'];
    $id_alumno = (int)$_POST['id_alumno'];
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('padre/reservar_clase.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el alumno pertenece al padre
    $stmt = $db->prepare("
        SELECT id_alumno FROM relacion_padre_hijo 
        WHERE id_padre = ? AND id_alumno = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $id_alumno]);
    
    if ($stmt->rowCount() === 0) {
        set_message('No autorizado', MSG_ERROR);
        redirect('padre/reservar_clase.php');
    }
    
    // Verificar plazas disponibles
    $stmt = $db->prepare("SELECT plazas_disponibles FROM clases WHERE id_clase = ?");
    $stmt->execute([$id_clase]);
    $clase = $stmt->fetch();
    
    if (!$clase || $clase['plazas_disponibles'] <= 0) {
        set_message('No hay plazas disponibles', MSG_ERROR);
        redirect('padre/reservar_clase.php');
    }
    
    // Verificar que no esté ya reservada
    $stmt = $db->prepare("
        SELECT id_reserva FROM reservas 
        WHERE id_clase = ? AND id_usuario = ?
    ");
    $stmt->execute([$id_clase, $id_alumno]);
    
    if ($stmt->rowCount() > 0) {
        set_message('Ya tienes una reserva para esta clase', MSG_WARNING);
        redirect('padre/reservar_clase.php');
    }
    
    try {
        $db->beginTransaction();
        
        // Crear reserva
        $stmt = $db->prepare("
            INSERT INTO reservas (id_clase, id_usuario, estado)
            VALUES (?, ?, 'confirmada')
        ");
        $stmt->execute([$id_clase, $id_alumno]);
        
        // Actualizar plazas
        $stmt = $db->prepare("
            UPDATE clases 
            SET plazas_disponibles = plazas_disponibles - 1 
            WHERE id_clase = ?
        ");
        $stmt->execute([$id_clase]);
        
        // Crear notificación
        create_notification(
            $id_alumno,
            'Nueva Clase Reservada',
            'Tu padre/madre ha reservado una nueva clase para ti',
            'clase',
            'alumno/mis_clases.php'
        );
        
        $db->commit();
        set_message('Clase reservada correctamente', MSG_SUCCESS);
    } catch (Exception $e) {
        $db->rollBack();
        set_message('Error al reservar la clase', MSG_ERROR);
    }
    
    redirect('padre/reservar_clase.php');
}
?>