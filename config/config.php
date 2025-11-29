<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona horaria
date_default_timezone_set('Europe/Madrid');

// Configuraciones generales
define('SITE_NAME', 'Clases de Inglés');
define('SITE_URL', 'http://localhost/Clases_Ingles/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB en bytes

// Tipos de archivo permitidos
define('ALLOWED_FILE_TYPES', [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'mp3' => 'audio/mpeg',
    'mp4' => 'video/mp4'
]);

// Roles de usuario
define('ROL_ALUMNO', 'alumno');
define('ROL_PADRE', 'padre');
define('ROL_ADMIN', 'administrador');

// Mensajes del sistema
define('MSG_SUCCESS', 'success');
define('MSG_ERROR', 'error');
define('MSG_WARNING', 'warning');
define('MSG_INFO', 'info');
?>