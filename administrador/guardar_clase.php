<?php
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
    $plazas_totales = (int)$_POST['plazas_totales'];
    $precio = (float)$_POST['precio'];
    $ubicacion = sanitize_input($_POST['ubicacion']);
    
    // Validar CSRF
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/clases.php');
    }
    
    // VALIDACIONES BÁSICAS
    if (empty($titulo) || empty($tipo) || empty($nivel) || empty($fecha) || empty($hora_inicio)) {
        $errors[] = 'Todos los campos obligatorios deben estar completos';
    }
    
    // Validar tipo
    if (!in_array($tipo, ['individual', 'grupal'])) {
        $errors[] = 'Tipo de clase no válido';
    }
    
    // Validar nivel
    if (!in_array($nivel, ['basico', 'intermedio', 'avanzado', 'conversacion'])) {
        $errors[] = 'Nivel no válido';
    }
    
    // Validar duración
    $duration_validation = validate_duration($duracion);
    if (!$duration_validation['valid']) {
        $errors[] = $duration_validation['error'];
    }
    
    // Validar precio
    $price_validation = validate_price($precio);
    if (!$price_validation['valid']) {
        $errors[] = $price_validation['error'];
    }
    
    // Validar plazas
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'alumno' AND activo = 1");
    $total_alumnos = $stmt->fetch()['total'];
    
    if ($plazas_totales < 1 || $plazas_totales > $total_alumnos) {
        $errors[] = "Las plazas deben estar entre 1 y $total_alumnos (total de alumnos activos)";
    }
    
    if ($tipo === 'individual' && $plazas_totales != 1) {
        $errors[] = 'Las clases individuales solo pueden tener 1 plaza';
    }
    
    // VALIDACIÓN CRÍTICA: Fecha y hora mínima (1 hora desde ahora)
    $fecha_hora_clase = strtotime($fecha . ' ' . $hora_inicio);
    $fecha_hora_minima = time() + 3600; // +1 hora
    
    if ($fecha_hora_clase < $fecha_hora_minima) {
        $min_datetime = date('d/m/Y H:i', $fecha_hora_minima);
        $errors[] = "La clase debe programarse con al menos 1 hora de antelación. Fecha/hora mínima: $min_datetime";
    }
    
    // Si hay errores, mostrarlos
    if (!empty($errors)) {
        set_message('Errores al crear la clase:<br>' . format_validation_errors($errors), MSG_ERROR);
        redirect('administrador/clases.php');
    }
    
    // Calcular hora fin
    $hora_fin = date('H:i:s', strtotime($hora_inicio . ' +' . $duracion . ' minutes'));
    
    try {
        $stmt = $db->prepare("
            INSERT INTO clases (titulo, descripcion, tipo, nivel, fecha, hora_inicio, hora_fin, 
                               duracion, plazas_disponibles, plazas_totales, precio, ubicacion, id_profesor)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $titulo, $descripcion, $tipo, $nivel, $fecha, $hora_inicio, $hora_fin,
            $duracion, $plazas_totales, $plazas_totales, $precio, $ubicacion, $_SESSION['user_id']
        ])) {
            log_activity($_SESSION['user_id'], 'crear_clase', "Clase creada: $titulo - $fecha $hora_inicio");
            set_message('Clase creada correctamente', MSG_SUCCESS);
        } else {
            set_message('Error al crear la clase', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/clases.php');
}

redirect('administrador/clases.php');
?>