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
/**
 * Validar contraseña segura
 * Debe tener: mínimo 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial
 */
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "La contraseña debe tener al menos 8 caracteres";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Debe contener al menos una letra mayúscula";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Debe contener al menos una letra minúscula";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Debe contener al menos un número";
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Debe contener al menos un carácter especial (!@#$%^&*(),.?\":{}|<>)";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validar fecha de nacimiento
 * Debe ser realista: entre 100 años atrás y hoy
 */
function validate_birthdate($date) {
    if (empty($date)) {
        return ['valid' => false, 'error' => 'La fecha de nacimiento es obligatoria'];
    }
    
    $birth = new DateTime($date);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    
    // Fecha futura
    if ($birth > $today) {
        return ['valid' => false, 'error' => 'La fecha de nacimiento no puede ser futura'];
    }
    
    // Menor de 3 años (demasiado joven para clases)
    if ($age < 3) {
        return ['valid' => false, 'error' => 'El alumno debe tener al menos 3 años'];
    }
    
    // Mayor de 100 años
    if ($age > 100) {
        return ['valid' => false, 'error' => 'La fecha de nacimiento no es válida'];
    }
    
    return ['valid' => true];
}

/**
 * Validar fecha de clase/examen
 * No puede ser en el pasado ni más de 1 año en el futuro
 */
function validate_class_date($date) {
    if (empty($date)) {
        return ['valid' => false, 'error' => 'La fecha es obligatoria'];
    }
    
    $class_date = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0); // Resetear hora para comparar solo fechas
    
    $one_year_future = clone $today;
    $one_year_future->modify('+1 year');
    
    if ($class_date < $today) {
        return ['valid' => false, 'error' => 'La fecha no puede ser en el pasado'];
    }
    
    if ($class_date > $one_year_future) {
        return ['valid' => false, 'error' => 'La fecha no puede ser más de un año en el futuro'];
    }
    
    return ['valid' => true];
}

/**
 * Validar teléfono español
 */
function validate_phone($phone) {
    if (empty($phone)) {
        return ['valid' => true]; // El teléfono es opcional
    }
    
    // Eliminar espacios y caracteres no numéricos excepto +
    $phone_clean = preg_replace('/[^\d+]/', '', $phone);
    
    // Validar formato español: 9 dígitos o +34 seguido de 9 dígitos
    if (preg_match('/^(\+34)?[6-9]\d{8}$/', $phone_clean)) {
        return ['valid' => true];
    }
    
    return ['valid' => false, 'error' => 'El teléfono debe ser un número español válido (9 dígitos)'];
}

/**
 * Sanitizar y validar hora
 */
function validate_time($time) {
    if (empty($time)) {
        return ['valid' => false, 'error' => 'La hora es obligatoria'];
    }
    
    // Formato HH:MM
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
        return ['valid' => false, 'error' => 'Formato de hora inválido (HH:MM)'];
    }
    
    return ['valid' => true];
}

/**
 * Validar duración de clase
 */
function validate_duration($minutes) {
    if (!is_numeric($minutes) || $minutes < 15 || $minutes > 240) {
        return ['valid' => false, 'error' => 'La duración debe estar entre 15 y 240 minutos'];
    }
    
    // Solo múltiplos de 15
    if ($minutes % 15 !== 0) {
        return ['valid' => false, 'error' => 'La duración debe ser múltiplo de 15 minutos'];
    }
    
    return ['valid' => true];
}

/**
 * Validar precio
 */
function validate_price($price) {
    if (!is_numeric($price) || $price < 0) {
        return ['valid' => false, 'error' => 'El precio debe ser un número positivo'];
    }
    
    if ($price > 1000) {
        return ['valid' => false, 'error' => 'El precio parece demasiado alto'];
    }
    
    return ['valid' => true];
}

/**
 * Validar puntuación de examen
 */
function validate_score($score, $max_score) {
    if (!is_numeric($score) || $score < 0) {
        return ['valid' => false, 'error' => 'La puntuación debe ser un número positivo'];
    }
    
    if ($score > $max_score) {
        return ['valid' => false, 'error' => 'La puntuación no puede superar el máximo'];
    }
    
    return ['valid' => true];
}

/**
 * Validar nombre/apellidos
 */
function validate_name($name, $field = 'Nombre') {
    if (empty($name)) {
        return ['valid' => false, 'error' => "$field es obligatorio"];
    }
    
    if (strlen($name) < 2) {
        return ['valid' => false, 'error' => "$field debe tener al menos 2 caracteres"];
    }
    
    if (strlen($name) > 50) {
        return ['valid' => false, 'error' => "$field no puede superar los 50 caracteres"];
    }
    
    // Solo letras, espacios, guiones y apóstrofes
    if (!preg_match("/^[a-záéíóúñü\s\-']+$/iu", $name)) {
        return ['valid' => false, 'error' => "$field contiene caracteres no válidos"];
    }
    
    return ['valid' => true];
}

/**
 * Generar mensaje de error HTML
 */
function format_validation_errors($errors) {
    if (empty($errors)) {
        return '';
    }
    
    $html = '<ul class="mb-0">';
    foreach ($errors as $error) {
        $html .= '<li>' . escape($error) . '</li>';
    }
    $html .= '</ul>';
    
    return $html;
}

/**
 * Validar archivo subido
 */
function validate_uploaded_file($file, $allowed_types = [], $max_size = MAX_FILE_SIZE) {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => false, 'error' => 'No se ha seleccionado ningún archivo'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Error al subir el archivo'];
    }
    
    if ($file['size'] > $max_size) {
        $max_mb = $max_size / 1024 / 1024;
        return ['valid' => false, 'error' => "El archivo supera el tamaño máximo de {$max_mb}MB"];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!empty($allowed_types) && !in_array($extension, $allowed_types)) {
        return ['valid' => false, 'error' => 'Tipo de archivo no permitido'];
    }
    
    return ['valid' => true];
}

?>