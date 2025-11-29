<?php
// administrador/procesar_material.php - SIMPLIFICADO
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitize_input($_POST['titulo']);
    $descripcion = sanitize_input($_POST['descripcion']);
    $nivel = $_POST['nivel'];
    $categoria = sanitize_input($_POST['categoria']);
    
    // Validar campos obligatorios
    if (empty($titulo) || empty($nivel)) {
        set_message('Por favor complete todos los campos obligatorios', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    // Validar que se subió archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
        set_message('Debe seleccionar un archivo PDF', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        set_message('Error al subir el archivo', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    // Verificar que es PDF
    $file_extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    $file_mime = $_FILES['archivo']['type'];
    
    if ($file_extension !== 'pdf' || ($file_mime !== 'application/pdf' && $file_mime !== 'application/x-pdf')) {
        set_message('Solo se permiten archivos PDF', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    // Crear carpeta si no existe
    $upload_folder = UPLOAD_DIR . 'materiales/';
    if (!is_dir($upload_folder)) {
        mkdir($upload_folder, 0777, true);
    }
    
    // Generar nombre único para el archivo
    $filename = uniqid() . '_' . time() . '.pdf';
    $destination = $upload_folder . $filename;
    
    // Mover archivo
    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destination)) {
        set_message('Error al guardar el archivo en el servidor', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    $tamaño_archivo = $_FILES['archivo']['size'];
    
    // Insertar en base de datos
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO materiales (titulo, descripcion, tipo, archivo, nivel, categoria, tamaño_archivo, subido_por)
            VALUES (?, ?, 'pdf', ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $titulo, 
            $descripcion, 
            $filename, 
            $nivel, 
            $categoria, 
            $tamaño_archivo, 
            $_SESSION['user_id']
        ])) {
            log_activity($_SESSION['user_id'], 'subir_material', "Material subido: $titulo");
            set_message('Material subido correctamente', MSG_SUCCESS);
        } else {
            // Si falla la BD, eliminar el archivo subido
            if (file_exists($destination)) {
                unlink($destination);
            }
            set_message('Error al guardar el material en la base de datos', MSG_ERROR);
        }
    } catch (Exception $e) {
        // Si falla, eliminar el archivo subido
        if (file_exists($destination)) {
            unlink($destination);
        }
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/materiales.php');
} else {
    redirect('administrador/materiales.php');
}
?>