<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_material = (int)$_POST['id_material'];
    $alumnos = isset($_POST['alumnos']) ? $_POST['alumnos'] : [];
    $fecha_limite = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/materiales.php');
    }
    
    if (empty($alumnos)) {
        set_message('Debe seleccionar al menos un alumno', MSG_WARNING);
        redirect('administrador/materiales.php');
    }
    
    $db = Database::getInstance()->getConnection();
    $asignados = 0;
    
    try {
        $stmt = $db->prepare("
            INSERT IGNORE INTO material_asignado (id_material, id_alumno, fecha_limite, asignado_por)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($alumnos as $id_alumno) {
            if ($stmt->execute([$id_material, $id_alumno, $fecha_limite, $_SESSION['user_id']])) {
                $asignados++;
                
                // Crear notificación
                create_notification(
                    $id_alumno,
                    'Nuevo Material Disponible',
                    'Te han asignado un nuevo material educativo',
                    'material',
                    'alumno/materiales.php'
                );
            }
        }
        
        set_message("Material asignado a $asignados alumno(s)", MSG_SUCCESS);
    } catch (Exception $e) {
        set_message('Error al asignar el material', MSG_ERROR);
    }
    
    redirect('administrador/materiales.php');
}
?>