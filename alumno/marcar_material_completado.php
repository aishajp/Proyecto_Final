<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_asignacion = isset($input['id_asignacion']) ? (int)$input['id_asignacion'] : 0;

if ($id_asignacion === 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Verificar que la asignación pertenece al alumno
$stmt = $db->prepare("SELECT id_alumno FROM material_asignado WHERE id_asignacion = ?");
$stmt->execute([$id_asignacion]);
$asignacion = $stmt->fetch();

if (!$asignacion || $asignacion['id_alumno'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Marcar como completado
$stmt = $db->prepare("
    UPDATE material_asignado 
    SET completado = 1, fecha_completado = NOW() 
    WHERE id_asignacion = ?
");

if ($stmt->execute([$id_asignacion])) {
    echo json_encode(['success' => true, 'message' => 'Material marcado como completado']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
}
?>