<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = (int)$_POST['id_usuario'];
    $nombre = sanitize_input($_POST['nombre']);
    $apellidos = sanitize_input($_POST['apellidos']);
    $email = sanitize_input($_POST['email']);
    $telefono = sanitize_input($_POST['telefono']);
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    $asociar_con = !empty($_POST['asociar_con']) ? (int)$_POST['asociar_con'] : null;
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/usuarios.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Verificar si el email ya existe (excepto el propio)
    $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
    $stmt->execute([$email, $id_usuario]);
    
    if ($stmt->rowCount() > 0) {
        set_message('El email ya está en uso por otro usuario', MSG_ERROR);
        redirect('administrador/usuarios.php');
    }
    
    try {
        $db->beginTransaction();
        
        // Actualizar datos básicos
        if ($password) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE usuarios 
                SET nombre = ?, apellidos = ?, email = ?, telefono = ?, fecha_nacimiento = ?, password = ?
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombre, $apellidos, $email, $telefono, $fecha_nacimiento, $password_hash, $id_usuario]);
        } else {
            $stmt = $db->prepare("
                UPDATE usuarios 
                SET nombre = ?, apellidos = ?, email = ?, telefono = ?, fecha_nacimiento = ?
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombre, $apellidos, $email, $telefono, $fecha_nacimiento, $id_usuario]);
        }
        
        // Manejar asociación si se proporcionó
        if ($asociar_con) {
            // Obtener el rol del usuario editado
            $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_usuario]);
            $rol = $stmt->fetch()['rol'];
            
            if ($rol === 'alumno') {
                // Asociar alumno con padre
                // Primero eliminar asociaciones anteriores
                $stmt = $db->prepare("DELETE FROM relacion_padre_hijo WHERE id_alumno = ?");
                $stmt->execute([$id_usuario]);
                
                // Crear nueva asociación
                $stmt = $db->prepare("INSERT INTO relacion_padre_hijo (id_padre, id_alumno) VALUES (?, ?)");
                $stmt->execute([$asociar_con, $id_usuario]);
            } elseif ($rol === 'padre') {
                // Asociar padre con alumno
                $stmt = $db->prepare("
                    INSERT IGNORE INTO relacion_padre_hijo (id_padre, id_alumno) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$id_usuario, $asociar_con]);
            }
        }
        
        $db->commit();
        set_message('Usuario actualizado correctamente', MSG_SUCCESS);
    } catch (Exception $e) {
        $db->rollBack();
        set_message('Error al actualizar: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/usuarios.php');
}

redirect('administrador/usuarios.php');
?>