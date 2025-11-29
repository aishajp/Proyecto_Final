<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_alumno = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_alumno === 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM progreso_alumno WHERE id_alumno = ?");
$stmt->execute([$id_alumno]);
$progreso = $stmt->fetch();

echo json_encode([
    'success' => true,
    'progreso' => $progreso
]);
?>