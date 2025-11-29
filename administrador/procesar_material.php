<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = sanitize_input($_POST['titulo']);
    $descripcion = sanitize_input($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $nivel = $_POST['nivel'];
    $categoria = sanitize_input($_POST['categoria']);
    
    // Validar campos obligatorios
    if (empty($titulo) || empty($tipo) || empty($nivel)) {
        set_message('Por favor complete todos los campos obligatorios', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    $archivo = null;
    $url = null;
    $tamaño_archivo = null;
    
    if ($tipo === 'enlace') {
        // Si es enlace, validar URL
        $url = $_POST['url'];
        if (empty($url)) {
            set_message('Debe proporcionar una URL', MSG_ERROR);
            redirect('administrador/materiales.php');
        }
    } else {
        // Si es archivo, validar que se subió
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
            set_message('Debe seleccionar un archivo', MSG_ERROR);
            redirect('administrador/materiales.php');
        }
        
        if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            set_message('Error al subir el archivo. Código de error: ' . $_FILES['archivo']['error'], MSG_ERROR);
            redirect('administrador/materiales.php');
        }
        
        // Verificar tamaño
        if ($_FILES['archivo']['size'] > MAX_FILE_SIZE) {
            $max_mb = MAX_FILE_SIZE / 1024 / 1024;
            set_message("El archivo es demasiado grande. Máximo: {$max_mb}MB", MSG_ERROR);
            redirect('administrador/materiales.php');
        }
        
        // Obtener extensión y validar tipo
        $file_extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        $file_mime = $_FILES['archivo']['type'];
        
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'mp3', 'mp4', 'avi', 'mov', 'txt', 'zip'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            set_message('Tipo de archivo no permitido. Extensiones válidas: ' . implode(', ', $allowed_extensions), MSG_ERROR);
            redirect('admin/materiales.php');
        }
        
        // Crear carpeta si no existe
        $upload_folder = UPLOAD_DIR . 'materiales/';
        if (!is_dir($upload_folder)) {
            mkdir($upload_folder, 0777, true);
        }
        
        // Generar nombre único para el archivo
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $destination = $upload_folder . $filename;
        
        // Mover archivo
        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destination)) {
            set_message('Error al mover el archivo al servidor. Verifica los permisos de la carpeta uploads/materiales/', MSG_ERROR);
            redirect('admin/materiales.php');
        }
        
        $archivo = $filename;
        $tamaño_archivo = $_FILES['archivo']['size'];
    }
    
    // Insertar en base de datos
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO materiales (titulo, descripcion, tipo, archivo, url, nivel, categoria, tamaño_archivo, subido_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $titulo, 
            $descripcion, 
            $tipo, 
            $archivo, 
            $url, 
            $nivel, 
            $categoria, 
            $tamaño_archivo, 
            $_SESSION['user_id']
        ])) {
            set_message('Material subido correctamente', MSG_SUCCESS);
        } else {
            // Si falla la BD, eliminar el archivo subido
            if ($archivo && file_exists($destination)) {
                unlink($destination);
            }
            set_message('Error al guardar el material en la base de datos', MSG_ERROR);
        }
    } catch (Exception $e) {
        // Si falla, eliminar el archivo subido
        if ($archivo && file_exists($destination)) {
            unlink($destination);
        }
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/materiales.php');
} else {
    redirect('administrador/materiales.php');
}
?>