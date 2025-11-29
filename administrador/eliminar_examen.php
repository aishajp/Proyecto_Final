<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$id_examen = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_examen === 0) {
    set_message('ID de examen inválido', MSG_ERROR);
    redirect('administrador/examenes.php');
}

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();
    
    // Eliminar resultados primero (por foreign key)
    $stmt = $db->prepare("DELETE FROM resultados_examenes WHERE id_examen = ?");
    $stmt->execute([$id_examen]);
    
    // Eliminar examen
    $stmt = $db->prepare("DELETE FROM examenes WHERE id_examen = ?");
    $stmt->execute([$id_examen]);
    
    $db->commit();
    
    set_message('Examen y sus calificaciones eliminados correctamente', MSG_SUCCESS);
} catch (Exception $e) {
    $db->rollBack();
    set_message('Error al eliminar: ' . $e->getMessage(), MSG_ERROR);
}

redirect('administrador/examenes.php');

?>