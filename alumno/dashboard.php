<?php
// alumno/dashboard.php - SIN ASISTENCIA
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener próximas clases del alumno - SIN ASISTENCIA
$stmt = $db->prepare("
    SELECT c.*, r.estado as estado_reserva
    FROM clases c
    INNER JOIN reservas r ON c.id_clase = r.id_clase
    WHERE r.id_usuario = ? AND c.fecha >= CURDATE() AND c.estado != 'cancelada'
    ORDER BY c.fecha, c.hora_inicio
    LIMIT 5
");
$stmt->execute([$user_id]);
$proximas_clases = $stmt->fetchAll();

// Obtener próximos exámenes
$stmt = $db->prepare("
    SELECT * FROM examenes 
    WHERE fecha >= CURDATE() 
    ORDER BY fecha, hora_inicio 
    LIMIT 3
");
$stmt->execute();
$proximos_examenes = $stmt->fetchAll();

// Obtener progreso del alumno
$stmt = $db->prepare("SELECT * FROM progreso_alumno WHERE id_alumno = ?");
$stmt->execute([$user_id]);
$progreso = $stmt->fetch();

// Obtener materiales recientes (sin asignación por alumno)
$stmt = $db->prepare("
    SELECT *
    FROM materiales
    WHERE activo = 1
    ORDER BY id_material DESC
    LIMIT 5
");
$stmt->execute();
$materiales_recientes = $stmt->fetchAll();

// Estadísticas del alumno - SIN ASISTENCIA
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_clases,
        SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as clases_completadas
    FROM reservas r
    INNER JOIN clases c ON r.id_clase = c.id_clase
    WHERE r.id_usuario = ?
");
$stmt->execute([$user_id]);
$estadisticas = $stmt->fetch();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_alumno.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Panel de Alumno</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="text-muted">Bienvenido, <?php echo $_SESSION['user_name']; ?></span>
                </div>
            </div>
            
            <!-- Tarjetas de estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Clases</h5>
                            <h2><?php echo $estadisticas['total_clases']; ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Completadas</h5>
                            <h2><?php echo $estadisticas['clases_completadas']; ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Nivel Actual</h5>
                            <h2><?php echo $progreso['nivel_actual'] ?? 'N/A'; ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Promedio</h5>
                            <h2><?php echo number_format($progreso['promedio_general'] ?? 0, 1); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Próximas clases -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Próximas Clases</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($proximas_clases) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Título</th>
                                                <th>Fecha</th>
                                                <th>Hora</th>
                                                <th>Tipo</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($proximas_clases as $clase): ?>
                                                <tr>
                                                    <td><?php echo escape($clase['titulo']); ?></td>
                                                    <td><?php echo format_date($clase['fecha']); ?></td>
                                                    <td><?php echo substr($clase['hora_inicio'], 0, 5); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                                            <?php echo ucfirst($clase['tipo']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo ucfirst($clase['estado_reserva']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No tienes clases programadas próximamente.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Próximos exámenes -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">Próximos Exámenes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($proximos_examenes) > 0): ?>
                                <ul class="list-group">
                                    <?php foreach ($proximos_examenes as $examen): ?>
                                        <li class="list-group-item">
                                            <h6><?php echo escape($examen['titulo']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo format_date($examen['fecha']); ?> 
                                                - <?php echo substr($examen['hora_inicio'], 0, 5); ?>
                                            </small>
                                            <br>
                                            <span class="badge bg-secondary"><?php echo $examen['tipo']; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No hay exámenes programados.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mi progreso -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Mi Progreso</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($progreso): ?>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <h6>Speaking</h6>
                                            <h3><?php echo number_format($progreso['speaking'], 1); ?></h3>
                                            <div class="progress">
                                                <div class="progress-bar bg-primary" style="width: <?php echo $progreso['speaking'] * 10; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <h6>Listening</h6>
                                            <h3><?php echo number_format($progreso['listening'], 1); ?></h3>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width: <?php echo $progreso['listening'] * 10; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <h6>Reading</h6>
                                            <h3><?php echo number_format($progreso['reading'], 1); ?></h3>
                                            <div class="progress">
                                                <div class="progress-bar bg-info" style="width: <?php echo $progreso['reading'] * 10; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <h6>Writing</h6>
                                            <h3><?php echo number_format($progreso['writing'], 1); ?></h3>
                                            <div class="progress">
                                                <div class="progress-bar bg-warning" style="width: <?php echo $progreso['writing'] * 10; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <h6>Grammar</h6>
                                            <h3><?php echo number_format($progreso['grammar'], 1); ?></h3>
                                            <div class="progress">
                                                <div class="progress-bar bg-danger" style="width: <?php echo $progreso['grammar'] * 10; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <h6>Vocabulary</h6>
                                            <h3><?php echo number_format($progreso['vocabulary'], 1); ?></h3>
                                            <div class="progress">
                                                <div class="progress-bar bg-secondary" style="width: <?php echo $progreso['vocabulary'] * 10; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Aún no tienes progreso registrado.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Materiales recientes -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Materiales Recientes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($materiales_recientes) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
    <thead>
    <tr>
        <th>Título</th>
        <th>Tipo</th>
        <th>Nivel</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($materiales_recientes as $material): ?>
        <tr>
            <td><?php echo escape($material['titulo']); ?></td>
            <td><span class="badge bg-primary"><?php echo $material['tipo']; ?></span></td>
            <td><?php echo $material['nivel']; ?></td>
        </tr>
    <?php endforeach; ?>
</tbody>


                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No tienes materiales asignados.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>