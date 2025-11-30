<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener hijos vinculados
$stmt = $db->prepare("
    SELECT u.*, rph.id_relacion
    FROM usuarios u
    INNER JOIN relacion_padre_hijo rph ON u.id_usuario = rph.id_alumno
    WHERE rph.id_padre = ?
");
$stmt->execute([$user_id]);
$hijos = $stmt->fetchAll();

// Datos del primer hijo (si existe)
$hijo_actual = count($hijos) > 0 ? $hijos[0] : null;
$id_hijo = isset($_GET['hijo']) ? (int)$_GET['hijo'] : ($hijo_actual ? $hijo_actual['id_usuario'] : 0);

if ($id_hijo > 0) {
    // Obtener progreso del hijo
    $stmt = $db->prepare("SELECT * FROM progreso_alumno WHERE id_alumno = ?");
    $stmt->execute([$id_hijo]);
    $progreso = $stmt->fetch();
    
    // Próximas clases del hijo
    $stmt = $db->prepare("
        SELECT c.*, r.estado as estado_reserva
        FROM clases c
        INNER JOIN reservas r ON c.id_clase = r.id_clase
        WHERE r.id_usuario = ? AND c.fecha >= CURDATE() AND c.estado != 'cancelada'
        ORDER BY c.fecha, c.hora_inicio
        LIMIT 5
    ");
    $stmt->execute([$id_hijo]);
    $proximas_clases = $stmt->fetchAll();
    
    // Próximos exámenes
    $stmt = $db->prepare("
        SELECT * FROM examenes 
        WHERE fecha >= CURDATE() 
        ORDER BY fecha ASC 
        LIMIT 3
    ");
    $stmt->execute();
    $proximos_examenes = $stmt->fetchAll();
    
    // Últimas calificaciones
    $stmt = $db->prepare("
        SELECT e.titulo, e.tipo, r.puntuacion, e.puntuacion_maxima, r.fecha_realizacion
        FROM resultados_examenes r
        INNER JOIN examenes e ON r.id_examen = e.id_examen
        WHERE r.id_alumno = ?
        ORDER BY r.fecha_realizacion DESC
        LIMIT 5
    ");
    $stmt->execute([$id_hijo]);
    $ultimas_calificaciones = $stmt->fetchAll();
}

// Mis tutorías programadas
$stmt = $db->prepare("
    SELECT t.*, u.nombre as alumno_nombre, u.apellidos as alumno_apellidos
    FROM tutorias t
    INNER JOIN usuarios u ON t.id_alumno = u.id_usuario
    WHERE t.id_padre = ? AND t.estado IN ('pendiente', 'confirmada')
    ORDER BY t.fecha_confirmada DESC, t.fecha_solicitada DESC
    LIMIT 3
");
$stmt->execute([$user_id]);
$mis_tutorias = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_padre.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Panel de Padre</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="text-muted">Bienvenido, <?php echo $_SESSION['user_name']; ?></span>
                </div>
            </div>

            <?php if (count($hijos) === 0): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No tienes hijos vinculados. Por favor, contacta con la administradora para vincular a tus hijos.
                </div>
            <?php else: ?>
                <!-- Selector de hijo -->
                <?php if (count($hijos) > 1): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <label class="form-label">Ver información de:</label>
                            <select class="form-select" onchange="location.href='dashboard.php?hijo='+this.value">
                                <?php foreach ($hijos as $hijo): ?>
                                    <option value="<?php echo $hijo['id_usuario']; ?>" <?php echo $id_hijo === $hijo['id_usuario'] ? 'selected' : ''; ?>>
                                        <?php echo escape($hijo['nombre'] . ' ' . $hijo['apellidos']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Información del hijo seleccionado -->
                <?php if ($progreso): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Progreso Académico</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-md-3">
                                    <h6>Nivel Actual</h6>
                                    <h2 class="text-primary"><?php echo $progreso['nivel_actual']; ?></h2>
                                </div>
                                <div class="col-md-3">
                                    <h6>Promedio General</h6>
                                    <h2 class="text-success"><?php echo number_format($progreso['promedio_general'], 1); ?></h2>
                                </div>
                                <div class="col-md-3">
                                    <h6>Speaking</h6>
                                    <h2><?php echo number_format($progreso['speaking'], 1); ?></h2>
                                </div>
                                <div class="col-md-3">
                                    <h6>Listening</h6>
                                    <h2><?php echo number_format($progreso['listening'], 1); ?></h2>
                                </div>
                            </div>
                            <div class="text-center">
                                <a href="progreso_hijo.php?hijo=<?php echo $id_hijo; ?>" class="btn btn-primary">
                                    Ver Progreso Completo <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <!-- Próximas clases -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Próximas Clases</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($proximas_clases) > 0): ?>
                                    <ul class="list-group">
                                        <?php foreach ($proximas_clases as $clase): ?>
                                            <li class="list-group-item">
                                                <strong><?php echo escape($clase['titulo']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo format_date($clase['fecha']); ?> - 
                                                    <?php echo substr($clase['hora_inicio'], 0, 5); ?>
                                                </small>
                                                <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?> float-end">
                                                    <?php echo ucfirst($clase['tipo']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="text-center mt-3">
                                        <a href="reservar_clase.php" class="btn btn-sm btn-primary">Reservar Nueva Clase</a>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No hay clases programadas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Próximos exámenes -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="bi bi-alarm"></i> Próximos Exámenes</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($proximos_examenes) > 0): ?>
                                    <ul class="list-group">
                                        <?php foreach ($proximos_examenes as $examen): ?>
                                            <li class="list-group-item">
                                                <strong><?php echo escape($examen['titulo']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo format_date($examen['fecha']); ?> - 
                                                    <?php echo substr($examen['hora_inicio'], 0, 5); ?>
                                                </small>
                                                <span class="badge bg-secondary float-end">
                                                    <?php echo ucfirst($examen['tipo']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">No hay exámenes próximos.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- Últimas calificaciones -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-star"></i> Últimas Calificaciones</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($ultimas_calificaciones) > 0): ?>
                                    <ul class="list-group">
                                        <?php foreach ($ultimas_calificaciones as $calif): ?>
                                            <?php 
                                            $porcentaje = ($calif['puntuacion'] / $calif['puntuacion_maxima']) * 100;
                                            $color = $porcentaje >= 90 ? 'success' : ($porcentaje >= 70 ? 'warning' : 'danger');
                                            ?>
                                            <li class="list-group-item">
                                                <strong><?php echo escape($calif['titulo']); ?></strong>
                                                <span class="badge bg-<?php echo $color; ?> float-end">
                                                    <?php echo number_format($calif['puntuacion'], 1); ?> / <?php echo number_format($calif['puntuacion_maxima'], 1); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo format_date($calif['fecha_realizacion']); ?> - 
                                                    <?php echo ucfirst($calif['tipo']); ?>
                                                </small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">Aún no hay calificaciones.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Mis tutorías -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-chat-dots"></i> Mis Tutorías</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($mis_tutorias) > 0): ?>
                                    <ul class="list-group">
                                        <?php foreach ($mis_tutorias as $tutoria): ?>
                                            <li class="list-group-item">
                                                <strong>Tutoría sobre <?php echo escape($tutoria['alumno_nombre']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php if ($tutoria['estado'] === 'confirmada'): ?>
                                                        <?php echo format_date($tutoria['fecha_confirmada']); ?> - 
                                                        <?php echo substr($tutoria['hora_confirmada'], 0, 5); ?>
                                                    <?php else: ?>
                                                        Pendiente de confirmación
                                                    <?php endif; ?>
                                                </small>
                                                <span class="badge bg-<?php echo $tutoria['estado'] === 'confirmada' ? 'success' : 'warning'; ?> float-end">
                                                    <?php echo ucfirst($tutoria['estado']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="text-center mt-3">
                                        <a href="solicitar_tutoria.php" class="btn btn-sm btn-info">Solicitar Nueva Tutoría</a>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No tienes tutorías programadas.</p>
                                    <a href="solicitar_tutoria.php" class="btn btn-sm btn-info">Solicitar Tutoría</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>