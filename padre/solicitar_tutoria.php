<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Solicitar Tutoría';

// Obtener hijos vinculados
$stmt = $db->prepare("
    SELECT u.*
    FROM usuarios u
    INNER JOIN relacion_padre_hijo rph ON u.id_usuario = rph.id_alumno
    WHERE rph.id_padre = ?
");
$stmt->execute([$user_id]);
$hijos = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_alumno = (int)$_POST['id_alumno'];
    $fecha_solicitada = $_POST['fecha_solicitada'];
    $hora_solicitada = $_POST['hora_solicitada'];
    $motivo = sanitize_input($_POST['motivo']);
    
    // Validaciones
    if (empty($id_alumno) || empty($fecha_solicitada) || empty($hora_solicitada) || empty($motivo)) {
        $error = 'Por favor, complete todos los campos';
    } elseif (!is_future_date($fecha_solicitada)) {
        $error = 'La fecha debe ser futura';
    } else {
        // Verificar que el alumno pertenece al padre
        $stmt = $db->prepare("
            SELECT id_alumno FROM relacion_padre_hijo 
            WHERE id_padre = ? AND id_alumno = ?
        ");
        $stmt->execute([$user_id, $id_alumno]);
        
        if ($stmt->rowCount() === 0) {
            $error = 'Alumno no válido';
        } else {
            // Obtener ID de la profesora (administradora)
            $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE rol = 'administrador' LIMIT 1");
            $stmt->execute();
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Insertar solicitud de tutoría
                $stmt = $db->prepare("
                    INSERT INTO tutorias (id_padre, id_alumno, id_profesor, fecha_solicitada, hora_solicitada, motivo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$user_id, $id_alumno, $admin['id_usuario'], $fecha_solicitada, $hora_solicitada, $motivo])) {
                    // Crear notificación para la administradora
                    create_notification(
                        $admin['id_usuario'],
                        'Nueva Solicitud de Tutoría',
                        'Tienes una nueva solicitud de tutoría pendiente',
                        'tutoria',
                        'admin/tutorias.php'
                    );
                    
                    $success = 'Solicitud de tutoría enviada correctamente. La profesora se pondrá en contacto contigo para confirmar.';
                } else {
                    $error = 'Error al enviar la solicitud';
                }
            } else {
                $error = 'No se encontró la profesora';
            }
        }
    }
}

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
</head>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_padre.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Solicitar Tutoría</h1>
                <a href="mis_tutorias.php" class="btn btn-outline-primary">
                    <i class="bi bi-list"></i> Ver Mis Tutorías
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Nueva Solicitud de Tutoría</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="id_alumno" class="form-label">Alumno *</label>
                                    <select class="form-select" id="id_alumno" name="id_alumno" required>
                                        <option value="">Seleccione un alumno...</option>
                                        <?php foreach ($hijos as $hijo): ?>
                                            <option value="<?php echo $hijo['id_usuario']; ?>">
                                                <?php echo escape($hijo['nombre'] . ' ' . $hijo['apellidos']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_solicitada" class="form-label">Fecha Preferida *</label>
                                        <input type="date" class="form-control" id="fecha_solicitada" name="fecha_solicitada" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="hora_solicitada" class="form-label">Hora Preferida *</label>
                                        <select class="form-select" id="hora_solicitada" name="hora_solicitada" required>
                                            <option value="">Seleccione una hora...</option>
                                            <option value="09:30">09:30</option>
                                            <option value="12:00">12:00</option>
                                            <option value="15:00">15:00</option>
                                            <option value="17:00">17:00</option>
                                            <option value="19:00">19:00</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="motivo" class="form-label">Motivo de la Tutoría *</label>
                                    <textarea class="form-control" id="motivo" name="motivo" rows="5" 
                                              placeholder="Explique brevemente el motivo de la tutoría..." 
                                              data-max-length="500" required></textarea>
                                    <small class="form-text text-muted">Máximo 500 caracteres</small>
                                </div>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    <strong>Nota:</strong> Esta es una solicitud. La profesora revisará tu petición y 
                                    te confirmará la fecha y hora definitiva, o te propondrá una alternativa.
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i> Enviar Solicitud
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</html>

<?php include '../includes/footer.php'; ?>