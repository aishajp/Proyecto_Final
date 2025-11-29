<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tutoria = (int)$_POST['id_tutoria'];
    $motivo_rechazo = sanitize_input($_POST['motivo_rechazo']);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/tutorias.php');
    }
    
    if (empty($motivo_rechazo)) {
        set_message('Debes proporcionar un motivo del rechazo', MSG_ERROR);
        redirect('administrador/tutorias.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Actualizar tutoría
        $stmt = $db->prepare("
            UPDATE tutorias 
            SET estado = 'rechazada',
                notas = ?,
                fecha_respuesta = NOW()
            WHERE id_tutoria = ?
        ");
        
        if ($stmt->execute([$motivo_rechazo, $id_tutoria])) {
            // Obtener datos para notificación
            $stmt = $db->prepare("SELECT id_padre FROM tutorias WHERE id_tutoria = ?");
            $stmt->execute([$id_tutoria]);
            $tutoria = $stmt->fetch();
            
            // Notificar al padre
            create_notification(
                $tutoria['id_padre'],
                'Solicitud de Tutoría Rechazada',
                "Tu solicitud de tutoría ha sido rechazada. Motivo: $motivo_rechazo",
                'tutoria',
                'padre/solicitar_tutoria.php'
            );
            
            set_message('Tutoría rechazada', MSG_SUCCESS);
        } else {
            set_message('Error al rechazar la tutoría', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/tutorias.php');
}

redirect('administrador/tutorias.php');
?>