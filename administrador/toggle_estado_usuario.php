<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$id_usuario = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_usuario > 0) {
    $db = Database::getInstance()->getConnection();
    
    // Obtener estado actual
    $stmt = $db->prepare("SELECT activo FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        $nuevo_estado = $usuario['activo'] ? 0 : 1;
        
        $stmt = $db->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?");
        if ($stmt->execute([$nuevo_estado, $id_usuario])) {
            $mensaje = $nuevo_estado ? 'Usuario activado' : 'Usuario desactivado';
            set_message($mensaje, MSG_SUCCESS);
        } else {
            set_message('Error al cambiar el estado', MSG_ERROR);
        }
    }
}

redirect('administrador/usuarios.php');


?>