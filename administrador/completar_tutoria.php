<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tutoria = (int)$_POST['id_tutoria'];
    $notas = sanitize_input($_POST['notas']);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/tutorias.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        // Obtener notas anteriores si existen
        $stmt = $db->prepare("SELECT notas FROM tutorias WHERE id_tutoria = ?");
        $stmt->execute([$id_tutoria]);
        $tutoria = $stmt->fetch();
        
        $notas_finales = $tutoria['notas'];
        if (!empty($notas)) {
            $notas_finales .= "\n\nResumen de la tutoría:\n" . $notas;
        }
        
        // Actualizar tutoría
        $stmt = $db->prepare("
            UPDATE tutorias 
            SET estado = 'completada',
                notas = ?
            WHERE id_tutoria = ?
        ");
        
        if ($stmt->execute([$notas_finales, $id_tutoria])) {
            set_message('Tutoría marcada como completada', MSG_SUCCESS);
        } else {
            set_message('Error al actualizar la tutoría', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/tutorias.php');
}

redirect('administrador/tutorias.php');
?>