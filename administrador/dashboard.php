<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();

// Estadísticas generales
$stmt = $db->prepare("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'alumno' AND activo = 1");
$stmt->execute();
$total_alumnos = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM clases WHERE fecha >= CURDATE() AND estado = 'programada'");
$stmt->execute();
$clases_programadas = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM tutorias WHERE estado = 'pendiente'");
$stmt->execute();
$tutorias_pendientes = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM clases WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())");
$stmt->execute();
$clases_mes = $stmt->fetch()['total'];

// Próximas clases
$stmt = $db->prepare("
    SELECT c.*, COUNT(r.id_reserva) as reservas_count
    FROM clases c
    LEFT JOIN reservas r ON c.id_clase = r.id_clase AND r.estado = 'confirmada'
    WHERE c.fecha >= CURDATE() AND c.estado = 'programada'
    GROUP BY c.id_clase
    ORDER BY c.fecha ASC, c.hora_inicio ASC
    LIMIT 5
");
$stmt->execute();
$proximas_clases = $stmt->fetchAll();

// Tutorías pendientes
$stmt = $db->prepare("
    SELECT t.*, 
           up.nombre as padre_nombre, up.apellidos as padre_apellidos,
           ua.nombre as alumno_nombre, ua.apellidos as alumno_apellidos
    FROM tutorias t
    INNER JOIN usuarios up ON t.id_padre = up.id_usuario
    INNER JOIN usuarios ua ON t.id_alumno = ua.id_usuario
    WHERE t.estado = 'pendiente'
    ORDER BY t.fecha_solicitud DESC
    LIMIT 5
");
$stmt->execute();
$tutorias = $stmt->fetchAll();

// Alumnos con mejor progreso
$stmt = $db->prepare("
    SELECT u.nombre, u.apellidos, p.promedio_general
    FROM progreso_alumno p
    INNER JOIN usuarios u ON p.id_alumno = u.id_usuario
    ORDER BY p.promedio_general DESC
    LIMIT 5
");
$stmt->execute();
$top_alumnos = $stmt->fetchAll();

// Estadísticas de asistencia del mes
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_clases,
        SUM(CASE WHEN r.asistencia = 'presente' THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN r.asistencia = 'ausente' THEN 1 ELSE 0 END) as ausentes
    FROM reservas r
    INNER JOIN clases c ON r.id_clase = c.id_clase
    WHERE MONTH(c.fecha) = MONTH(CURDATE()) AND YEAR(c.fecha) = YEAR(CURDATE())
    AND c.estado = 'completada'
");
$stmt->execute();
$stats_asistencia = $stmt->fetch();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Panel de Administración</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="text-muted">Bienvenida, <?php echo $_SESSION['user_name']; ?></span>
                </div>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Alumnos</h6>
                            <h2 class="mb-0"><?php echo $total_alumnos; ?></h2>
                            <small>Activos en el sistema</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Clases Programadas</h6>
                            <h2 class="mb-0"><?php echo $clases_programadas; ?></h2>
                            <small>Próximas clases</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h6 class="card-title">Tutorías Pendientes</h6>
                            <h2 class="mb-0"><?php echo $tutorias_pendientes; ?></h2>
                            <small>Por confirmar</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Clases Este Mes</h6>
                            <h2 class="mb-0"><?php echo $clases_mes; ?></h2>
                            <small>Total programadas</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <!-- Próximas clases -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Próximas Clases</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($proximas_clases) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($proximas_clases as $clase): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <strong><?php echo escape($clase['titulo']); ?></strong>
                                                <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                                    <?php echo ucfirst($clase['tipo']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo format_date($clase['fecha']); ?> - 
                                                <?php echo substr($clase['hora_inicio'], 0, 5); ?>
                                            </small>
                                            <br>
                                            <small>
                                                <i class="bi bi-people"></i> <?php echo $clase['reservas_count']; ?> / <?php echo $clase['plazas_totales']; ?> reservas
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No hay clases programadas.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tutorías pendientes -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-chat-dots"></i> Tutorías Pendientes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($tutorias) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($tutorias as $tutoria): ?>
                                        <div class="list-group-item">
                                            <strong><?php echo escape($tutoria['padre_nombre'] . ' ' . $tutoria['padre_apellidos']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                Sobre: <?php echo escape($tutoria['alumno_nombre']); ?> - 
                                                Solicitada: <?php echo format_date($tutoria['fecha_solicitada']); ?>
                                            </small>
                                            <br>
                                            <a href="tutorias.php?id=<?php echo $tutoria['id_tutoria']; ?>" class="btn btn-sm btn-primary mt-2">
                                                Revisar
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No hay tutorías pendientes.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <!-- Top alumnos -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Alumnos por Progreso</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($top_alumnos) > 0): ?>
                                <ul class="list-group">
                                    <?php foreach ($top_alumnos as $index => $alumno): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo ($index + 1); ?>. <?php echo escape($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></strong>
                                            </div>
                                            <span class="badge bg-success rounded-pill">
                                                <?php echo number_format($alumno['promedio_general'], 1); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No hay datos de progreso.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas de asistencia -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Asistencia Este Mes</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($stats_asistencia['total_clases'] > 0): ?>
                                <div class="mb-3">
                                    <h3 class="text-center"><?php echo round(($stats_asistencia['presentes'] / $stats_asistencia['total_clases']) * 100); ?>%</h3>
                                    <p class="text-center text-muted">Tasa de asistencia</p>
                                </div>
                                <div class="row text-center">
                                    <div class="col">
                                        <h4 class="text-success"><?php echo $stats_asistencia['presentes']; ?></h4>
                                        <small class="text-muted">Presentes</small>
                                    </div>
                                    <div class="col">
                                        <h4 class="text-danger"><?php echo $stats_asistencia['ausentes']; ?></h4>
                                        <small class="text-muted">Ausentes</small>
                                    </div>
                                    <div class="col">
                                        <h4><?php echo $stats_asistencia['total_clases']; ?></h4>
                                        <small class="text-muted">Total</small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No hay datos de asistencia este mes.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Accesos Rápidos</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <a href="clases.php" class="btn btn-outline-primary w-100 p-3">
                                <i class="bi bi-calendar-event fs-1"></i>
                                <br>Gestionar Clases
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="usuarios.php" class="btn btn-outline-success w-100 p-3">
                                <i class="bi bi-people fs-1"></i>
                                <br>Gestionar Usuarios
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="materiales.php" class="btn btn-outline-info w-100 p-3">
                                <i class="bi bi-folder fs-1"></i>
                                <br>Gestionar Materiales
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="progreso.php" class="btn btn-outline-warning w-100 p-3">
                                <i class="bi bi-graph-up fs-1"></i>
                                <br>Registrar Progreso
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>