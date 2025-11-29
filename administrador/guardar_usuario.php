<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize_input($_POST['nombre']);
    $apellidos = sanitize_input($_POST['apellidos']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $telefono = sanitize_input($_POST['telefono']);
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $rol = $_POST['rol'];
    $asociar_con = !empty($_POST['asociar_con']) ? (int)$_POST['asociar_con'] : null;
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/usuarios.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Verificar si el email ya existe
    $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        set_message('El email ya está registrado', MSG_ERROR);
        redirect('administrador/usuarios.php');
    }
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            INSERT INTO usuarios (nombre, apellidos, email, password, telefono, fecha_nacimiento, rol, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([$nombre, $apellidos, $email, $password_hash, $telefono, $fecha_nacimiento, $rol]);
        $nuevo_id = $db->lastInsertId();
        
        // Si es alumno, crear progreso inicial
        if ($rol === 'alumno') {
            $stmt = $db->prepare("
                INSERT INTO progreso_alumno (id_alumno, nivel_actual, actualizado_por)
                VALUES (?, 'A1', ?)
            ");
            $stmt->execute([$nuevo_id, $_SESSION['user_id']]);
        }
        
        // Manejar asociación si se proporcionó
        if ($asociar_con) {
            if ($rol === 'alumno') {
                // Asociar con padre
                $stmt = $db->prepare("INSERT INTO relacion_padre_hijo (id_padre, id_alumno) VALUES (?, ?)");
                $stmt->execute([$asociar_con, $nuevo_id]);
            } elseif ($rol === 'padre') {
                // Asociar con alumno
                $stmt = $db->prepare("INSERT INTO relacion_padre_hijo (id_padre, id_alumno) VALUES (?, ?)");
                $stmt->execute([$nuevo_id, $asociar_con]);
            }
        }
        
        $db->commit();
        set_message('Usuario creado correctamente', MSG_SUCCESS);
    } catch (Exception $e) {
        $db->rollBack();
        set_message('Error al crear el usuario: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/usuarios.php');
}

redirect('administrador/usuarios.php');
?>