<?php
// ============================================
// alumno/descargar_material.php
// Descarga de material
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$id_material = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_material === 0) {
    set_message('Material no encontrado', MSG_ERROR);
    redirect('alumno/materiales.php');
}

$db = Database::getInstance()->getConnection();

// Verificar que el material está asignado al alumno
$stmt = $db->prepare("
    SELECT m.* 
    FROM materiales m
    INNER JOIN material_asignado ma ON m.id_material = ma.id_material
    WHERE m.id_material = ? AND ma.id_alumno = ? AND m.activo = 1
");
$stmt->execute([$id_material, $_SESSION['user_id']]);
$material = $stmt->fetch();

if (!$material) {
    set_message('No tienes permiso para acceder a este material', MSG_ERROR);
    redirect('alumno/materiales.php');
}

// Incrementar contador de descargas
$stmt = $db->prepare("UPDATE materiales SET descargas = descargas + 1 WHERE id_material = ?");
$stmt->execute([$id_material]);

// Construir ruta del archivo
$file_path = UPLOAD_DIR . 'materiales/' . $material['archivo'];

if (file_exists($file_path)) {
    // Establecer headers para descarga
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($material['archivo']) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Limpiar buffer de salida
    ob_clean();
    flush();
    
    // Leer y enviar el archivo
    readfile($file_path);
    exit;
} else {
    set_message('El archivo no existe', MSG_ERROR);
    redirect('alumno/materiales.php');
}
?>