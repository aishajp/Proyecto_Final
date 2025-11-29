<?php
// ============================================
// alumno/cancelar_clase.php
// Procesar cancelación de clase
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = (int)$_POST['id_reserva'];
    $motivo = sanitize_input($_POST['motivo']);
    $csrf_token = $_POST['csrf_token'];
    
    // Validar CSRF
    if (!validate_csrf_token($csrf_token)) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('alumno/mis_clases.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Verificar que la reserva pertenece al usuario
    $stmt = $db->prepare("SELECT id_clase FROM reservas WHERE id_reserva = ? AND id_usuario = ?");
    $stmt->execute([$id_reserva, $_SESSION['user_id']]);
    $reserva = $stmt->fetch();
    
    if (!$reserva) {
        set_message('Reserva no encontrada', MSG_ERROR);
        redirect('alumno/mis_clases.php');
    }
    
    try {
        $db->beginTransaction();
        
        // Actualizar estado de la reserva
        $stmt = $db->prepare("
            UPDATE reservas 
            SET estado = 'cancelada', 
                cancelada_por = ?, 
                fecha_cancelacion = NOW(),
                motivo_cancelacion = ?
            WHERE id_reserva = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $motivo, $id_reserva]);
        
        // Devolver plaza a la clase
        $stmt = $db->prepare("
            UPDATE clases 
            SET plazas_disponibles = plazas_disponibles + 1 
            WHERE id_clase = ?
        ");
        $stmt->execute([$reserva['id_clase']]);
        
        $db->commit();
        
        set_message('Clase cancelada correctamente', MSG_SUCCESS);
    } catch (Exception $e) {
        $db->rollBack();
        set_message('Error al cancelar la clase', MSG_ERROR);
    }
    
    redirect('alumno/mis_clases.php');
}
?>