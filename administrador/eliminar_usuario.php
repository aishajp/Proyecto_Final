<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$id_usuario = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_usuario === 0) {
    set_message('ID de usuario inválido', MSG_ERROR);
    redirect('administrador/usuarios.php');
}

// No permitir eliminar al propio usuario
if ($id_usuario === $_SESSION['user_id']) {
    set_message('No puedes eliminar tu propio usuario', MSG_ERROR);
    redirect('administrador/usuarios.php');
}

$db = Database::getInstance()->getConnection();

// Verificar que no sea administrador
$stmt = $db->prepare("SELECT rol, nombre, apellidos FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch();

if (!$usuario) {
    set_message('Usuario no encontrado', MSG_ERROR);
    redirect('administrador/usuarios.php');
}

if ($usuario['rol'] === 'administrador') {
    set_message('No se puede eliminar a un administrador', MSG_ERROR);
    redirect('administrador/usuarios.php');
}

try {
    $db->beginTransaction();

    
    // Eliminar progreso
    $stmt = $db->prepare("DELETE FROM progreso_alumno WHERE id_alumno = ?");
    $stmt->execute([$id_usuario]);
    
    // Eliminar relaciones padre-hijo
    $stmt = $db->prepare("DELETE FROM relacion_padre_hijo WHERE id_padre = ? OR id_alumno = ?");
    $stmt->execute([$id_usuario, $id_usuario]);
    
    // Cancelar reservas futuras
    $stmt = $db->prepare("
        UPDATE reservas 
        SET estado = 'cancelada', motivo_cancelacion = 'Usuario eliminado'
        WHERE id_usuario = ?
    ");
    $stmt->execute([$id_usuario]);
    
    // Finalmente eliminar el usuario
    $stmt = $db->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    
    $db->commit();
    
    set_message("Usuario '{$usuario['nombre']} {$usuario['apellidos']}' eliminado correctamente", MSG_SUCCESS);
} catch (Exception $e) {
    $db->rollBack();
    set_message('Error al eliminar el usuario: ' . $e->getMessage(), MSG_ERROR);
}

redirect('administrador/usuarios.php');
?>