<?php
// administrador/editar_clase.php - CON VALIDACIONES
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    $id_clase = (int)$_POST['id_clase'];
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
    
    $db = Database::getInstance()->getConnection();
    
    // Verificar que la clase existe y no ha pasado
    $stmt = $db->prepare("SELECT * FROM clases WHERE id_clase = ?");
    $stmt->execute([$id_clase]);
    $clase_actual = $stmt->fetch();
    
    if (!$clase_actual) {
        set_message('Clase no encontrada', MSG_ERROR);
        redirect('administrador/clases.php');
    }
    
    // Verificar que la clase no ha pasado
    $fecha_hora_actual = strtotime($clase_actual['fecha'] . ' ' . $clase_actual['hora_inicio']);
    if ($fecha_hora_actual < time()) {
        set_message('No se puede editar una clase que ya ha pasado', MSG_ERROR);
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
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'alumno' AND activo = 1");
    $total_alumnos = $stmt->fetch()['total'];
    
    if ($plazas_totales < 1 || $plazas_totales > $total_alumnos) {
        $errors[] = "Las plazas deben estar entre 1 y $total_alumnos (total de alumnos activos)";
    }
    
    if ($tipo === 'individual' && $plazas_totales != 1) {
        $errors[] = 'Las clases individuales solo pueden tener 1 plaza';
    }
    
    // Verificar que las plazas totales no sean menores a las reservas confirmadas
    $stmt = $db->prepare("
        SELECT COUNT(*) as reservas_confirmadas 
        FROM reservas 
        WHERE id_clase = ? AND estado = 'confirmada'
    ");
    $stmt->execute([$id_clase]);
    $reservas_confirmadas = $stmt->fetch()['reservas_confirmadas'];
    
    if ($plazas_totales < $reservas_confirmadas) {
        $errors[] = "No puedes reducir las plazas a menos de $reservas_confirmadas (reservas ya confirmadas)";
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
        set_message('Errores al editar la clase:<br>' . format_validation_errors($errors), MSG_ERROR);
        redirect('administrador/ver_clase.php?id=' . $id_clase);
    }
    
    // Calcular hora fin
    $hora_fin = date('H:i:s', strtotime($hora_inicio . ' +' . $duracion . ' minutes'));
    
    // Calcular nuevas plazas disponibles
    $diferencia = $plazas_totales - $clase_actual['plazas_totales'];
    $nuevas_plazas_disponibles = $clase_actual['plazas_disponibles'] + $diferencia;
    
    // No puede ser negativo
    if ($nuevas_plazas_disponibles < 0) {
        $nuevas_plazas_disponibles = 0;
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE clases 
            SET titulo = ?, descripcion = ?, tipo = ?, nivel = ?, fecha = ?, 
                hora_inicio = ?, hora_fin = ?, duracion = ?, plazas_totales = ?, 
                plazas_disponibles = ?, precio = ?, ubicacion = ?
            WHERE id_clase = ?
        ");
        
        if ($stmt->execute([
            $titulo, $descripcion, $tipo, $nivel, $fecha, $hora_inicio, $hora_fin,
            $duracion, $plazas_totales, $nuevas_plazas_disponibles, $precio, $ubicacion, $id_clase
        ])) {
            log_activity($_SESSION['user_id'], 'editar_clase', "Clase editada: $titulo (ID: $id_clase)");
            set_message('Clase actualizada correctamente', MSG_SUCCESS);
        } else {
            set_message('Error al actualizar la clase', MSG_ERROR);
        }
    } catch (Exception $e) {
        set_message('Error: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/ver_clase.php?id=' . $id_clase);
}

redirect('administrador/clases.php');
?>