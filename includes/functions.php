<?php

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validar email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Verificar si el usuario está autenticado
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verificar rol del usuario
 */
function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Redirigir a una página
 */
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit();
}

/**
 * Mostrar mensaje de alerta
 */
function set_message($message, $type = MSG_INFO) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

/**
 * Obtener y limpiar mensaje
 */
function get_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'];
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Formatear fecha en español
 */
function format_date($date, $format = 'd/m/Y') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Formatear fecha y hora en español
 */
function format_datetime($datetime) {
    $timestamp = strtotime($datetime);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Calcular edad
 */
function calculate_age($birthdate) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

/**
 * Generar nombre único para archivo
 */
function generate_unique_filename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Validar tipo de archivo
 */
function validate_file_type($file_type) {
    return in_array($file_type, ALLOWED_FILE_TYPES);
}

/**
 * Subir archivo
 */
function upload_file($file, $destination_folder) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'El archivo es demasiado grande'];
    }
    
    if (!validate_file_type($file['type'])) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
    }
    
    $filename = generate_unique_filename($file['name']);
    $destination = $destination_folder . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Error al mover el archivo'];
}

/**
 * Obtener días de la semana en español
 */
function get_dias_semana() {
    return ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
}

/**
 * Obtener meses en español
 */
function get_meses() {
    return [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
}

/**
 * Proteger contra XSS
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar si una fecha es futura
 */
function is_future_date($date) {
    return strtotime($date) > time();
}

/**
 * Calcular porcentaje
 */
function calculate_percentage($value, $total) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100, 2);
}

/**
 * Generar token CSRF
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Limpiar token CSRF
 */
function clear_csrf_token() {
    unset($_SESSION['csrf_token']);
}

/**
 * Logs del sistema - VERSIÓN SEGURA
 */
function log_activity($user_id, $action, $description) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Verificar si la tabla existe
        $stmt = $db->query("SHOW TABLES LIKE 'logs_sistema'");
        
        if ($stmt->rowCount() > 0) {
            $stmt = $db->prepare("INSERT INTO logs_sistema (id_usuario, accion, descripcion, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
        }
    } catch (Exception $e) {
        error_log("Error en log_activity: " . $e->getMessage());
    }
}

/**
 * Crear notificación para usuario
 */
function create_notification($user_id, $titulo, $mensaje, $tipo, $url = null) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO notificaciones (id_usuario, titulo, mensaje, tipo, url) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $titulo, $mensaje, $tipo, $url]);
}

/**
 * Enviar email
 */
function send_email($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . SITE_NAME . ' <noreply@clasesingle.com>' . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Paginar resultados
 */
function paginate($total_records, $records_per_page, $current_page) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'records_per_page' => $records_per_page
    ];
}
?>