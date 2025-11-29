<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    echo '<div class="alert alert-danger">No autorizado</div>';
    exit;
}

$id_examen = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_examen === 0) {
    echo '<div class="alert alert-danger">ID inválido</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Obtener datos del examen
$stmt = $db->prepare("SELECT * FROM examenes WHERE id_examen = ?");
$stmt->execute([$id_examen]);
$examen = $stmt->fetch();

if (!$examen) {
    echo '<div class="alert alert-danger">Examen no encontrado</div>';
    exit;
}

// Obtener resultados
$stmt = $db->prepare("
    SELECT r.*, u.nombre, u.apellidos
    FROM resultados_examenes r
    INNER JOIN usuarios u ON r.id_alumno = u.id_usuario
    WHERE r.id_examen = ?
    ORDER BY r.puntuacion DESC
");
$stmt->execute([$id_examen]);
$resultados = $stmt->fetchAll();

// Calcular estadísticas
$total = count($resultados);
$suma = array_sum(array_column($resultados, 'puntuacion'));
$promedio = $total > 0 ? $suma / $total : 0;
$aprobados = count(array_filter($resultados, function($r) use ($examen) {
    return ($r['puntuacion'] / $examen['puntuacion_maxima']) * 100 >= 50;
}));
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h4><?php echo $total; ?></h4>
                <small>Total Alumnos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h4><?php echo $aprobados; ?></h4>
                <small>Aprobados</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-danger text-white">
            <div class="card-body">
                <h4><?php echo $total - $aprobados; ?></h4>
                <small>Suspendidos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-info text-white">
            <div class="card-body">
                <h4><?php echo number_format($promedio, 2); ?></h4>
                <small>Promedio</small>
            </div>
        </div>
    </div>
</div>

<?php if ($total > 0): ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Posición</th>
                    <th>Alumno</th>
                    <th>Puntuación</th>
                    <th>Porcentaje</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $posicion = 1;
                foreach ($resultados as $resultado): 
                    $porcentaje = ($resultado['puntuacion'] / $examen['puntuacion_maxima']) * 100;
                    $aprobado = $porcentaje >= 50;
                ?>
                    <tr>
                        <td><strong><?php echo $posicion++; ?>º</strong></td>
                        <td><?php echo escape($resultado['nombre'] . ' ' . $resultado['apellidos']); ?></td>
                        <td><strong><?php echo number_format($resultado['puntuacion'], 2); ?></strong> / <?php echo number_format($examen['puntuacion_maxima'], 2); ?></td>
                        <td>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $aprobado ? 'success' : 'danger'; ?>" 
                                     style="width: <?php echo $porcentaje; ?>%">
                                    <?php echo round($porcentaje, 1); ?>%
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $aprobado ? 'success' : 'danger'; ?>">
                                <?php echo $aprobado ? 'Aprobado' : 'Suspendido'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($resultado['observaciones']): ?>
                                <small><?php echo escape($resultado['observaciones']); ?></small>
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
    <div class="alert alert-info">
        No hay calificaciones registradas para este examen aún.
    </div>
<?php endif; ?>