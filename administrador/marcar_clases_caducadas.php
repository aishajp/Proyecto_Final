<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = Database::getInstance()->getConnection();

try {
    // Marcar como completadas las clases que ya pasaron y están en estado "programada"
    $stmt = $db->prepare("
        UPDATE clases 
        SET estado = 'completada' 
        WHERE estado = 'programada' 
        AND CONCAT(fecha, ' ', hora_fin) < NOW()
    ");
    
    $stmt->execute();
    $clases_actualizadas = $stmt->rowCount();
    
    if ($clases_actualizadas > 0) {
        error_log("Clases caducadas automáticamente: $clases_actualizadas");
    }
    
} catch (Exception $e) {
    error_log("Error al marcar clases caducadas: " . $e->getMessage());
}

// Si se ejecuta directamente desde navegador (solo para admin)
if (isset($_SESSION['user_id']) && has_role(ROL_ADMIN)) {
    set_message("Se han marcado $clases_actualizadas clase(s) como completadas automáticamente", MSG_INFO);
    redirect('administrador/clases.php');
}
?>