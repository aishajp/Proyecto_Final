<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';


if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_alumno = (int)$_POST['id_alumno'];
    $nivel_actual = $_POST['nivel_actual'];
    $speaking = (float)$_POST['speaking'];
    $listening = (float)$_POST['listening'];
    $reading = (float)$_POST['reading'];
    $writing = (float)$_POST['writing'];
    $grammar = (float)$_POST['grammar'];
    $vocabulary = (float)$_POST['vocabulary'];
    $observaciones = sanitize_input($_POST['observaciones']);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/progreso.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Verificar si existe progreso
        $stmt = $db->prepare("SELECT id_progreso FROM progreso_alumno WHERE id_alumno = ?");
        $stmt->execute([$id_alumno]);
        
        if ($stmt->rowCount() > 0) {
            // Actualizar
            $stmt = $db->prepare("
                UPDATE progreso_alumno 
                SET nivel_actual = ?, speaking = ?, listening = ?, reading = ?, 
                    writing = ?, grammar = ?, vocabulary = ?, observaciones = ?,
                    actualizado_por = ?, fecha_actualizacion = NOW()
                WHERE id_alumno = ?
            ");
            $stmt->execute([
                $nivel_actual, $speaking, $listening, $reading, $writing, $grammar, 
                $vocabulary, $observaciones, $_SESSION['user_id'], $id_alumno
            ]);
        } else {
            // Insertar
            $stmt = $db->prepare("
                INSERT INTO progreso_alumno (id_alumno, nivel_actual, speaking, listening, reading,
                                            writing, grammar, vocabulary, observaciones, actualizado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_alumno, $nivel_actual, $speaking, $listening, $reading, $writing, 
                $grammar, $vocabulary, $observaciones, $_SESSION['user_id']
            ]);
        }
        
        // Crear notificación
        create_notification(
            $id_alumno,
            'Progreso Actualizado',
            'Tu progreso académico ha sido actualizado',
            'progreso',
            'alumno/progreso.php'
        );
        
        set_message('Progreso actualizado correctamente', MSG_SUCCESS);
    } catch (Exception $e) {
        set_message('Error al actualizar el progreso', MSG_ERROR);
    }
    
    redirect('administrador/progreso.php');
}
?>