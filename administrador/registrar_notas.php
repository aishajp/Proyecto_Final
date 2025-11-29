<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_examen = (int)$_POST['id_examen'];
    $puntuacion_maxima = (float)$_POST['puntuacion_maxima'];
    $puntuaciones = $_POST['puntuaciones'] ?? [];
    $observaciones = $_POST['observaciones'] ?? [];
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
        redirect('administrador/examenes.php');
    }
    
    $db = Database::getInstance()->getConnection();
    $registrados = 0;
    $actualizados = 0;
    
    try {
        $db->beginTransaction();
        
        foreach ($puntuaciones as $id_alumno => $puntuacion) {
            // Solo procesar si hay puntuación
            if (empty($puntuacion) && $puntuacion !== '0') {
                continue;
            }
            
            $puntuacion = (float)$puntuacion;
            
            // Validar que no exceda la máxima
            if ($puntuacion > $puntuacion_maxima) {
                continue;
            }
            
            $observacion = isset($observaciones[$id_alumno]) ? sanitize_input($observaciones[$id_alumno]) : '';
            
            // Verificar si ya existe resultado
            $stmt = $db->prepare("
                SELECT id_resultado FROM resultados_examenes 
                WHERE id_examen = ? AND id_alumno = ?
            ");
            $stmt->execute([$id_examen, $id_alumno]);
            
            if ($stmt->rowCount() > 0) {
                // Actualizar
                $stmt = $db->prepare("
                    UPDATE resultados_examenes 
                    SET puntuacion = ?, observaciones = ?, fecha_realizacion = CURDATE()
                    WHERE id_examen = ? AND id_alumno = ?
                ");
                $stmt->execute([$puntuacion, $observacion, $id_examen, $id_alumno]);
                $actualizados++;
            } else {
                // Insertar
                $stmt = $db->prepare("
                    INSERT INTO resultados_examenes (id_examen, id_alumno, puntuacion, observaciones,fecha_realizacion, registrado_por)
                    VALUES (?, ?, ?, ?, CURDATE(), ?)
                ");
                $stmt->execute([$id_examen, $id_alumno, $puntuacion, $observacion, $_SESSION['user_id']]);
                $registrados++;
                
                // Notificar al alumno
                create_notification(
                    $id_alumno,
                    'Nueva Calificación',
                    'Se ha registrado tu calificación en un examen',
                    'progreso',
                    'alumno/examenes.php'
                );
            }
        }
        
        $db->commit();
        
        $mensaje = "Calificaciones procesadas: $registrados nuevas, $actualizados actualizadas";
        set_message($mensaje, MSG_SUCCESS);
    } catch (Exception $e) {
        $db->rollBack();
        set_message('Error al registrar calificaciones: ' . $e->getMessage(), MSG_ERROR);
    }
    
    redirect('administrador/examenes.php');
}

redirect('administrador/examenes.php');

?>