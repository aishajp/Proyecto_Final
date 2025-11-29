<?php
// administrador/guardar_examen.php - CON RESTRICCIÓN HORARIA
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    $titulo = sanitize_input($_POST['titulo']);
    $descripcion = sanitize_input($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $nivel = $_POST['nivel'];
    $fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'];
    $duracion = (int)$_POST['duracion'];
    $puntuacion_maxima = (float)$_POST['puntuacion_maxima'];
    $ubicacion = sanitize_input($_POST['ubicacion']);
    
    // Validar CSRF
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/examenes.php');
    }
    
    // VALIDACIONES BÁSICAS
    if (empty($titulo) || empty($tipo) || empty($nivel) || empty($fecha) || empty($hora_inicio)) {
        $errors[] = 'Todos los campos obligatorios deben estar completos';
    }
    
    // Validar tipo
    if (!in_array($tipo, ['oral', 'escrito', 'listening', 'completo'])) {
        $errors[] = 'Tipo de examen no válido';
    }
    
    // Validar nivel
    if (!in_array($nivel, ['basico', 'intermedio', 'avanzado'])) {
        $errors[] = 'Nivel no válido';
    }
    
    // Validar duración
    $duration_validation = validate_duration($duracion);
    if (!$duration_validation['valid']) {
        $errors[] = $duration_validation['error'];
    }
    
    // Validar puntuación máxima
    if ($puntuacion_maxima <= 0 || $puntuacion_maxima > 1000) {
        $errors[] = 'La puntuación máxima debe estar entre 1 y 1000';
    }
    
    // VALIDACIÓN CRÍTICA: Hora entre 09:30 y 19:00
    $hora_parts = explode(':', $hora_inicio);
    $hora_decimal = $hora_parts[0] + ($hora_parts[1] / 60);
    $hora_min = 9.5; // 09:30
    $hora_max = 19.0; // 19:00
    
    if ($hora_decimal < $hora_min || $hora_decimal > $hora_max) {
        $errors[] = 'La hora del examen debe estar entre las 09:30 y las 19:00';
    }
    
    // Validar que la fecha no sea en el pasado
    if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
        $errors[] = 'La fecha del examen no puede ser en el pasado';
    }
    
    // Si hay errores, mostrarlos
    if (!empty($errors)) {
        set_message('Errores al crear el examen:<br>' . format_validation_errors($errors), MSG_ERROR);
        redirect('administrador/examenes.php');
    }
    
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO examenes (titulo, descripcion, fecha, hora_inicio, duracion, nivel, tipo, ubicacion, puntuacion_maxima, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $titulo, $descripcion, $fecha, $hora_inicio, $duracion, $nivel, 
            $tipo, $ubicacion, $puntuacion_maxima, $_SESSION['user_id']
        ])) {
            log_activity($_SESSION['user_id'], 'crear_examen', "Examen creado: $titulo - $fecha $hora_inicio");
            set_message('Examen creado correctamente', MSG_SUCCESS);
        } else {
            set_message('Error al crear el examen', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/examenes.php');
}

redirect('administrador/examenes.php');
?>