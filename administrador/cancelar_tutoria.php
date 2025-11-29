<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tutoria = (int)$_POST['id_tutoria'];
    $motivo_cancelacion = sanitize_input($_POST['motivo_cancelacion']);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/tutorias.php');
    }
    
    if (empty($motivo_cancelacion)) {
        set_message('Debes proporcionar un motivo de cancelación', MSG_ERROR);
        redirect('administrador/tutorias.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Obtener datos de la tutoría
        $stmt = $db->prepare("SELECT id_padre, notas FROM tutorias WHERE id_tutoria = ?");
        $stmt->execute([$id_tutoria]);
        $tutoria = $stmt->fetch();
        
        $notas_actualizadas = $tutoria['notas'] . "\n\nCancelada por el administrador:\n" . $motivo_cancelacion;
        
        // Actualizar tutoría
        $stmt = $db->prepare("
            UPDATE tutorias 
            SET estado = 'cancelada',
                notas = ?
            WHERE id_tutoria = ?
        ");
        
        if ($stmt->execute([$notas_actualizadas, $id_tutoria])) {
            // Notificar al padre
            create_notification(
                $tutoria['id_padre'],
                'Tutoría Cancelada',
                "Tu tutoría ha sido cancelada. Motivo: $motivo_cancelacion",
                'tutoria',
                'padre/solicitar_tutoria.php'
            );
            
            set_message('Tutoría cancelada', MSG_SUCCESS);
        } else {
            set_message('Error al cancelar la tutoría', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/tutorias.php');
}

redirect('administrador/tutorias.php');

?>