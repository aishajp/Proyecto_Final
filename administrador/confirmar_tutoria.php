<?php
// ============================================
// admin/confirmar_tutoria.php
// Confirmar una tutoría solicitada
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tutoria = (int)$_POST['id_tutoria'];
    $fecha_confirmada = $_POST['fecha_confirmada'];
    $hora_confirmada = $_POST['hora_confirmada'];
    $duracion = (int)$_POST['duracion'];
    $ubicacion = sanitize_input($_POST['ubicacion']);
    $notas = sanitize_input($_POST['notas']);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/tutorias.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Actualizar tutoría
        $stmt = $db->prepare("
            UPDATE tutorias 
            SET estado = 'confirmada',
                fecha_confirmada = ?,
                hora_confirmada = ?,
                duracion = ?,
                ubicacion = ?,
                notas = ?,
                fecha_respuesta = NOW()
            WHERE id_tutoria = ?
        ");
        
        if ($stmt->execute([$fecha_confirmada, $hora_confirmada, $duracion, $ubicacion, $notas, $id_tutoria])) {
            // Obtener datos para notificación
            $stmt = $db->prepare("SELECT id_padre FROM tutorias WHERE id_tutoria = ?");
            $stmt->execute([$id_tutoria]);
            $tutoria = $stmt->fetch();
            
            // Notificar al padre
            create_notification(
                $tutoria['id_padre'],
                'Tutoría Confirmada',
                "Tu solicitud de tutoría ha sido confirmada para el $fecha_confirmada a las $hora_confirmada",
                'tutoria',
                'padre/mis_tutorias.php'
            );
            
            set_message('Tutoría confirmada correctamente', MSG_SUCCESS);
        } else {
            set_message('Error al confirmar la tutoría', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/tutorias.php');
}

redirect('administrador/tutorias.php');

?>