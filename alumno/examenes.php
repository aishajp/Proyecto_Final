<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Exámenes';

// Obtener exámenes próximos
$stmt = $db->prepare("
    SELECT * FROM examenes 
    WHERE fecha >= CURDATE() 
    ORDER BY fecha ASC, hora_inicio ASC
");
$stmt->execute();
$examenes_proximos = $stmt->fetchAll();

// Obtener mis resultados de exámenes
$stmt = $db->prepare("
    SELECT e.*, r.puntuacion, r.observaciones, r.fecha_realizacion
    FROM examenes e
    INNER JOIN resultados_examenes r ON e.id_examen = r.id_examen
    WHERE r.id_alumno = ?
    ORDER BY r.fecha_realizacion DESC
");
$stmt->execute([$user_id]);
$mis_resultados = $stmt->fetchAll();

// Calcular promedio general
$stmt = $db->prepare("
    SELECT AVG((r.puntuacion / e.puntuacion_maxima) * 100) as promedio
    FROM resultados_examenes r
    INNER JOIN examenes e ON r.id_examen = e.id_examen
    WHERE r.id_alumno = ?
");
$stmt->execute([$user_id]);
$promedio_data = $stmt->fetch();
$promedio_general = $promedio_data['promedio'] ? round($promedio_data['promedio'], 2) : 0;

// Estadísticas por tipo de examen
$stmt = $db->prepare("
    SELECT 
        e.tipo,
        COUNT(*) as total,
        AVG((r.puntuacion / e.puntuacion_maxima) * 100) as promedio
    FROM resultados_examenes r
    INNER JOIN examenes e ON r.id_examen = e.id_examen
    WHERE r.id_alumno = ?
    GROUP BY e.tipo
");
$stmt->execute([$user_id]);
$stats_por_tipo = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_alumno.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Exámenes</h1>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Exámenes Realizados</h6>
                            <h2 class="mb-0"><?php echo count($mis_resultados); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Promedio General</h6>
                            <h2 class="mb-0"><?php echo $promedio_general; ?>%</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h6 class="card-title">Próximos Exámenes</h6>
                            <h2 class="mb-0"><?php echo count($examenes_proximos); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Mejor Nota</h6>
                            <h2 class="mb-0">
                                <?php 
                                if (count($mis_resultados) > 0) {
                                    $mejor = max(array_column($mis_resultados, 'puntuacion'));
                                    echo number_format($mejor, 2);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Próximos Exámenes -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-alarm"></i> Próximos Exámenes</h5>
                </div>
                <div class="card-body">
                    <?php if (count($examenes_proximos) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($examenes_proximos as $examen): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo escape($examen['titulo']); ?></h5>
                                        <small>
                                            <span class="badge bg-secondary"><?php echo ucfirst($examen['tipo']); ?></span>
                                            <span class="badge bg-info"><?php echo ucfirst($examen['nivel']); ?></span>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo escape($examen['descripcion']); ?></p>
                                    <div class="row mt-2">
                                        <div class="col-md-3">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3"></i> <strong>Fecha:</strong> <?php echo format_date($examen['fecha']); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i> <strong>Hora:</strong> <?php echo substr($examen['hora_inicio'], 0, 5); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">
                                                <i class="bi bi-hourglass-split"></i> <strong>Duración:</strong> <?php echo $examen['duracion']; ?> min
                                            </small>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">
                                                <i class="bi bi-geo-alt"></i> <strong>Ubicación:</strong> <?php echo escape($examen['ubicacion']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i> No hay exámenes programados próximamente.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rendimiento por tipo de examen -->
            <?php if (count($stats_por_tipo) > 0): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Rendimiento por Tipo de Examen</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($stats_por_tipo as $tipo => $data): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="text-center p-3 border rounded">
                                        <h6 class="text-uppercase"><?php echo $tipo; ?></h6>
                                        <h3 class="mb-0"><?php echo round($data[0]['promedio'], 1); ?>%</h3>
                                        <small class="text-muted"><?php echo $data[0]['total']; ?> examen(es)</small>
                                        <div class="progress mt-2" style="height: 8px;">
                                            <div class="progress-bar" style="width: <?php echo $data[0]['promedio']; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Historial de Resultados -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Historial de Resultados</h5>
                </div>
                <div class="card-body">
                    <?php if (count($mis_resultados) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Examen</th>
                                        <th>Tipo</th>
                                        <th>Nivel</th>
                                        <th>Fecha Realización</th>
                                        <th>Puntuación</th>
                                        <th>Porcentaje</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mis_resultados as $resultado): ?>
                                        <?php 
                                        $porcentaje = ($resultado['puntuacion'] / $resultado['puntuacion_maxima']) * 100;
                                        $color_badge = $porcentaje >= 90 ? 'success' : ($porcentaje >= 70 ? 'warning' : 'danger');
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo escape($resultado['titulo']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo ucfirst($resultado['tipo']); ?></span>
                                            </td>
                                            <td><?php echo ucfirst($resultado['nivel']); ?></td>
                                            <td><?php echo format_date($resultado['fecha_realizacion']); ?></td>
                                            <td>
                                                <strong><?php echo number_format($resultado['puntuacion'], 2); ?></strong> / 
                                                <?php echo number_format($resultado['puntuacion_maxima'], 2); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $color_badge; ?>">
                                                    <?php echo round($porcentaje, 2); ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($resultado['observaciones']): ?>
                                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#modalObservaciones<?php echo $resultado['id_examen']; ?>">
                                                        <i class="bi bi-eye"></i> Ver
                                                    </button>
                                                    
                                                    <!-- Modal Observaciones -->
                                                    <div class="modal fade" id="modalObservaciones<?php echo $resultado['id_examen']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Observaciones - <?php echo escape($resultado['titulo']); ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <?php echo nl2br(escape($resultado['observaciones'])); ?>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i> Aún no has realizado ningún examen.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>