<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$id_material = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_material === 0) {
    set_message('ID de material inválido', MSG_ERROR);
    redirect('administrador/materiales.php');
}

$db = Database::getInstance()->getConnection();

// Obtener datos del material
$stmt = $db->prepare("SELECT * FROM materiales WHERE id_material = ?");
$stmt->execute([$id_material]);
$material = $stmt->fetch();

if (!$material) {
    set_message('Material no encontrado', MSG_ERROR);
    redirect('administrador/materiales.php');
}

try {
    // Eliminar archivo físico
    $file_path = UPLOAD_DIR . 'materiales/' . $material['archivo'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Eliminar de base de datos
    $stmt = $db->prepare("DELETE FROM materiales WHERE id_material = ?");
    $stmt->execute([$id_material]);
    
    log_activity($_SESSION['user_id'], 'eliminar_material', "Material eliminado: {$material['titulo']}");
    set_message('Material eliminado correctamente', MSG_SUCCESS);
} catch (Exception $e) {
    set_message('Error al eliminar el material: ' . $e->getMessage(), MSG_ERROR);
}

redirect('administrador/materiales.php');
?>