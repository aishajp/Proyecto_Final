<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$id_clase = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_clase === 0) {
    set_message('ID de clase inválido', MSG_ERROR);
    redirect('administrador/clases.php');
}

$db = Database::getInstance()->getConnection();

// Verificar que la clase existe
$stmt = $db->prepare("SELECT titulo, estado FROM clases WHERE id_clase = ?");
$stmt->execute([$id_clase]);
$clase = $stmt->fetch();

if (!$clase) {
    set_message('Clase no encontrada', MSG_ERROR);
    redirect('administrador/clases.php');
}

// Solo se pueden eliminar clases programadas
if ($clase['estado'] !== 'programada') {
    set_message('Solo se pueden eliminar clases en estado "Programada"', MSG_ERROR);
    redirect('administrador/clases.php');
}

try {
    $db->beginTransaction();
    
    // Cancelar todas las reservas
    $stmt = $db->prepare("
        UPDATE reservas 
        SET estado = 'cancelada', 
            motivo_cancelacion = 'Clase eliminada por el administrador'
        WHERE id_clase = ?
    ");
    $stmt->execute([$id_clase]);
    
    // Notificar a los usuarios afectados
    $stmt = $db->prepare("
        SELECT DISTINCT id_usuario 
        FROM reservas 
        WHERE id_clase = ? AND estado = 'cancelada'
    ");
    $stmt->execute([$id_clase]);
    $usuarios_afectados = $stmt->fetchAll();
    
    foreach ($usuarios_afectados as $usuario) {
        create_notification(
            $usuario['id_usuario'],
            'Clase Cancelada',
            "La clase '{$clase['titulo']}' ha sido eliminada",
            'clase',
            null
        );
    }
    
    // Eliminar la clase
    $stmt = $db->prepare("DELETE FROM clases WHERE id_clase = ?");
    $stmt->execute([$id_clase]);
    
    $db->commit();
    
    set_message("Clase '{$clase['titulo']}' eliminada correctamente", MSG_SUCCESS);
} catch (Exception $e) {
    $db->rollBack();
    set_message('Error al eliminar la clase: ' . $e->getMessage(), MSG_ERROR);
}

redirect('administrador/clases.php');
?>