<?php
// alumno/detalle_clase.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$id_clase = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_clase === 0) {
    set_message('ID de clase inválido', MSG_ERROR);
    redirect('alumno/mis_clases.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener datos de la clase y verificar que el alumno está inscrito
$stmt = $db->prepare("
    SELECT c.*, r.estado as estado_reserva, r.fecha_reserva,
           u.nombre as profesor_nombre, u.apellidos as profesor_apellidos
    FROM clases c
    INNER JOIN reservas r ON c.id_clase = r.id_clase
    LEFT JOIN usuarios u ON c.id_profesor = u.id_usuario
    WHERE c.id_clase = ? AND r.id_usuario = ?
");
$stmt->execute([$id_clase, $user_id]);
$clase = $stmt->fetch();

if (!$clase) {
    set_message('Clase no encontrada o no tienes acceso', MSG_ERROR);
    redirect('alumno/mis_clases.php');
}

$page_title = 'Detalles de la Clase';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_alumno.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Detalles de la Clase</h1>
                <a href="mis_clases.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?php echo escape($clase['titulo']); ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if ($clase['descripcion']): ?>
                                <p class="text-muted"><?php echo nl2br(escape($clase['descripcion'])); ?></p>
                                <hr>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6><i class="bi bi-calendar3"></i> Fecha y Hora</h6>
                                    <p><strong><?php echo format_date($clase['fecha']); ?></strong></p>
                                    <p><?php echo substr($clase['hora_inicio'], 0, 5); ?> - <?php echo substr($clase['hora_fin'], 0, 5); ?></p>
                                    <p>Duración: <?php echo $clase['duracion']; ?> minutos</p>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <h6><i class="bi bi-info-circle"></i> Detalles</h6>
                                    <p><strong>Tipo:</strong> 
                                        <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                            <?php echo ucfirst($clase['tipo']); ?>
                                        </span>
                                    </p>
                                    <p><strong>Nivel:</strong> <?php echo ucfirst($clase['nivel']); ?></p>
                                    <p><strong>Ubicación:</strong> <?php echo escape($clase['ubicacion']); ?></p>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <h6><i class="bi bi-person"></i> Profesor</h6>
                                    <p><?php echo escape($clase['profesor_nombre'] . ' ' . $clase['profesor_apellidos']); ?></p>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <h6><i class="bi bi-check-circle"></i> Estado</h6>
                                    <?php
                                    $badge_color = 'secondary';
                                    switch ($clase['estado_reserva']) {
                                        case 'confirmada': $badge_color = 'info'; break;
                                        case 'completada': $badge_color = 'success'; break;
                                        case 'cancelada': $badge_color = 'danger'; break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $badge_color; ?> fs-6">
                                        <?php echo ucfirst($clase['estado_reserva']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Información de Reserva</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <small class="text-muted">Fecha de reserva:</small>
                                <p><strong><?php echo format_datetime($clase['fecha_reserva']); ?></strong></p>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Precio:</small>
                                <h4 class="text-success">€<?php echo number_format($clase['precio'], 2); ?></h4>
                            </div>
                            
                            <?php if ($clase['estado_reserva'] === 'confirmada' && strtotime($clase['fecha']) >= time()): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle"></i> 
                                    <small>La clase está confirmada. ¡No olvides asistir!</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>