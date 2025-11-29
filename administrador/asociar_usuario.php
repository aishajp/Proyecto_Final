<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = (int)$_POST['id_usuario'];
    $rol = $_POST['rol'];
    $asociar_con = (int)$_POST['asociar_con'];
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/usuarios.php');
    }
    
    if ($id_usuario === 0 || $asociar_con === 0) {
        set_message('Datos inválidos', MSG_ERROR);
        redirect('administrador/usuarios.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        if ($rol === 'alumno') {
            // Alumno con padre
            $id_alumno = $id_usuario;
            $id_padre = $asociar_con;
        } else {
            // Padre con alumno
            $id_padre = $id_usuario;
            $id_alumno = $asociar_con;
        }
        
        // Verificar que no estén ya asociados
        $stmt = $db->prepare("
            SELECT id_relacion FROM relacion_padre_hijo 
            WHERE id_padre = ? AND id_alumno = ?
        ");
        $stmt->execute([$id_padre, $id_alumno]);
        
        if ($stmt->rowCount() > 0) {
            set_message('Esta asociación ya existe', MSG_WARNING);
        } else {
            $stmt = $db->prepare("
                INSERT INTO relacion_padre_hijo (id_padre, id_alumno) 
                VALUES (?, ?)
            ");
            
            if ($stmt->execute([$id_padre, $id_alumno])) {
                set_message('Asociación creada correctamente', MSG_SUCCESS);
            } else {
                set_message('Error al crear la asociación', MSG_ERROR);
            }
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/usuarios.php');
}

redirect('administrador/usuarios.php');

?>