<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener hijos del padre
$stmt = $db->prepare("
    SELECT u.*, rph.id_relacion
    FROM usuarios u
    INNER JOIN relacion_padre_hijo rph ON u.id_usuario = rph.id_alumno
    WHERE rph.id_padre = ?
");
$stmt->execute([$user_id]);
$hijos = $stmt->fetchAll();

// Si no tiene hijos, redirigir
if (count($hijos) === 0) {
    set_message('No tienes hijos vinculados', MSG_ERROR);
    redirect('padre/dashboard.php');
}

// Obtener el hijo seleccionado o el primero por defecto
$id_hijo = isset($_GET['hijo']) ? (int)$_GET['hijo'] : $hijos[0]['id_usuario'];

// Buscar el hijo en el array
$hijo = null;
foreach ($hijos as $h) {
    if ($h['id_usuario'] == $id_hijo) {
        $hijo = $h;
        break;
    }
}

// Si no se encontró, usar el primero
if (!$hijo) {
    $hijo = $hijos[0];
    $id_hijo = $hijo['id_usuario'];
}

// Obtener progreso actual
$stmt = $db->prepare("SELECT * FROM progreso_alumno WHERE id_alumno = ?");
$stmt->execute([$id_hijo]);
$progreso = $stmt->fetch();

// Obtener últimas calificaciones
$stmt = $db->prepare("
    SELECT e.titulo, e.tipo, e.puntuacion_maxima, r.puntuacion, r.fecha_realizacion, r.observaciones
    FROM resultados_examenes r
    INNER JOIN examenes e ON r.id_examen = e.id_examen
    WHERE r.id_alumno = ?
    ORDER BY r.fecha_realizacion DESC
    LIMIT 10
");
$stmt->execute([$id_hijo]);
$calificaciones = $stmt->fetchAll();

// Calcular estadísticas
$total_examenes = count($calificaciones);
$promedio_examenes = 0;
if ($total_examenes > 0) {
    $suma = 0;
    foreach ($calificaciones as $cal) {
        $porcentaje = ($cal['puntuacion'] / $cal['puntuacion_maxima']) * 100;
        $suma += $porcentaje;
    }
    $promedio_examenes = round($suma / $total_examenes, 1);
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_padre.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Progreso de <?php echo escape($hijo['nombre'] . ' ' . $hijo['apellidos']); ?></h1>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>

            <?php if ($progreso): ?>
                <!-- Nivel actual y promedio -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Nivel Actual</h6>
                                <h1 class="display-4"><?php echo $progreso['nivel_actual']; ?></h1>
                                <small>MCER</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Promedio General</h6>
                                <h1 class="display-4"><?php echo number_format($progreso['promedio_general'], 1); ?></h1>
                                <small>Sobre 10</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h6>Exámenes Realizados</h6>
                                <h1 class="display-4"><?php echo $total_examenes; ?></h1>
                                <small>Total</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h6>Promedio Exámenes</h6>
                                <h1 class="display-4"><?php echo $promedio_examenes; ?>%</h1>
                                <small>Media</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GRÁFICAS INTERACTIVAS -->
                <div class="row mb-4">
                    <!-- Gráfica de Radar - Habilidades -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> Habilidades Lingüísticas</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="radarChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfica de Barras - Comparativa -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Progreso vs Objetivo</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="barChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Habilidades detalladas con barras de progreso -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Detalle de Habilidades</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Speaking -->
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6><i class="bi bi-mic"></i> Speaking (Expresión Oral)</h6>
                                    <span class="badge bg-primary"><?php echo number_format($progreso['speaking'], 1); ?> / 10</span>
                                </div>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo ($progreso['speaking'] * 10); ?>%">
                                        <?php echo ($progreso['speaking'] * 10); ?>%
                                    </div>
                                </div>
                            </div>

                            <!-- Listening -->
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6><i class="bi bi-headphones"></i> Listening (Comprensión Auditiva)</h6>
                                    <span class="badge bg-success"><?php echo number_format($progreso['listening'], 1); ?> / 10</span>
                                </div>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo ($progreso['listening'] * 10); ?>%">
                                        <?php echo ($progreso['listening'] * 10); ?>%
                                    </div>
                                </div>
                            </div>

                            <!-- Reading -->
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6><i class="bi bi-book"></i> Reading (Comprensión Lectora)</h6>
                                    <span class="badge bg-info"><?php echo number_format($progreso['reading'], 1); ?> / 10</span>
                                </div>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo ($progreso['reading'] * 10); ?>%">
                                        <?php echo ($progreso['reading'] * 10); ?>%
                                    </div>
                                </div>
                            </div>

                            <!-- Writing -->
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6><i class="bi bi-pen"></i> Writing (Expresión Escrita)</h6>
                                    <span class="badge bg-warning text-dark"><?php echo number_format($progreso['writing'], 1); ?> / 10</span>
                                </div>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($progreso['writing'] * 10); ?>%">
                                        <?php echo ($progreso['writing'] * 10); ?>%
                                    </div>
                                </div>
                            </div>

                            <!-- Grammar -->
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6><i class="bi bi-file-text"></i> Grammar (Gramática)</h6>
                                    <span class="badge bg-danger"><?php echo number_format($progreso['grammar'], 1); ?> / 10</span>
                                </div>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo ($progreso['grammar'] * 10); ?>%">
                                        <?php echo ($progreso['grammar'] * 10); ?>%
                                    </div>
                                </div>
                            </div>

                            <!-- Vocabulary -->
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6><i class="bi bi-chat-square-text"></i> Vocabulary (Vocabulario)</h6>
                                    <span class="badge bg-secondary"><?php echo number_format($progreso['vocabulary'], 1); ?> / 10</span>
                                </div>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar bg-secondary" style="width: <?php echo ($progreso['vocabulary'] * 10); ?>%">
                                        <?php echo ($progreso['vocabulary'] * 10); ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Observaciones del profesor -->
                <?php if ($progreso['observaciones']): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-left-quote"></i> Observaciones del Profesor</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><?php echo nl2br(escape($progreso['observaciones'])); ?></p>
                            <hr>
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> Última actualización: <?php echo format_datetime($progreso['fecha_actualizacion']); ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Historial de Calificaciones -->
                <?php if (count($calificaciones) > 0): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clipboard-data"></i> Historial de Calificaciones</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Examen</th>
                                            <th>Tipo</th>
                                            <th>Fecha</th>
                                            <th>Puntuación</th>
                                            <th>Porcentaje</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($calificaciones as $cal): ?>
                                            <?php 
                                            $porcentaje = ($cal['puntuacion'] / $cal['puntuacion_maxima']) * 100;
                                            $color_badge = $porcentaje >= 90 ? 'success' : ($porcentaje >= 70 ? 'warning' : 'danger');
                                            ?>
                                            <tr>
                                                <td><strong><?php echo escape($cal['titulo']); ?></strong></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst($cal['tipo']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo format_date($cal['fecha_realizacion']); ?></td>
                                                <td>
                                                    <strong><?php echo number_format($cal['puntuacion'], 2); ?></strong> / 
                                                    <?php echo number_format($cal['puntuacion_maxima'], 2); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $color_badge; ?>">
                                                        <?php echo round($porcentaje, 1); ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($cal['observaciones']): ?>
                                                        <button class="btn btn-sm btn-info" 
                                                                data-bs-toggle="tooltip" 
                                                                title="<?php echo escape($cal['observaciones']); ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Aún no hay progreso registrado para este alumno. 
                    La profesora lo actualizará próximamente.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php if ($progreso): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Datos del progreso
    const progreso = {
        speaking: <?php echo $progreso['speaking']; ?>,
        listening: <?php echo $progreso['listening']; ?>,
        reading: <?php echo $progreso['reading']; ?>,
        writing: <?php echo $progreso['writing']; ?>,
        grammar: <?php echo $progreso['grammar']; ?>,
        vocabulary: <?php echo $progreso['vocabulary']; ?>
    };

    // GRÁFICA DE RADAR - Habilidades
    const ctxRadar = document.getElementById('radarChart').getContext('2d');
    const radarChart = new Chart(ctxRadar, {
        type: 'radar',
        data: {
            labels: ['Speaking', 'Listening', 'Reading', 'Writing', 'Grammar', 'Vocabulary'],
            datasets: [{
                label: 'Nivel Actual',
                data: [
                    progreso.speaking,
                    progreso.listening,
                    progreso.reading,
                    progreso.writing,
                    progreso.grammar,
                    progreso.vocabulary
                ],
                backgroundColor: 'rgba(74, 144, 226, 0.2)',
                borderColor: 'rgba(74, 144, 226, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(74, 144, 226, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(74, 144, 226, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 10,
                    ticks: {
                        stepSize: 2
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.r.toFixed(1) + ' / 10';
                        }
                    }
                }
            }
        }
    });

    // GRÁFICA DE BARRAS - Comparativa con objetivo
    const ctxBar = document.getElementById('barChart').getContext('2d');
    const barChart = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['Speaking', 'Listening', 'Reading', 'Writing', 'Grammar', 'Vocabulary'],
            datasets: [
                {
                    label: 'Nivel Actual',
                    data: [
                        progreso.speaking,
                        progreso.listening,
                        progreso.reading,
                        progreso.writing,
                        progreso.grammar,
                        progreso.vocabulary
                    ],
                    backgroundColor: [
                        'rgba(74, 144, 226, 0.8)',
                        'rgba(80, 200, 120, 0.8)',
                        'rgba(52, 152, 219, 0.8)',
                        'rgba(243, 156, 18, 0.8)',
                        'rgba(231, 76, 60, 0.8)',
                        'rgba(149, 165, 166, 0.8)'
                    ],
                    borderColor: [
                        'rgba(74, 144, 226, 1)',
                        'rgba(80, 200, 120, 1)',
                        'rgba(52, 152, 219, 1)',
                        'rgba(243, 156, 18, 1)',
                        'rgba(231, 76, 60, 1)',
                        'rgba(149, 165, 166, 1)'
                    ],
                    borderWidth: 2
                },
                {
                    label: 'Objetivo (10)',
                    data: [10, 10, 10, 10, 10, 10],
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 2,
                    borderDash: [5, 5]
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    ticks: {
                        stepSize: 2
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + ' / 10';
                        }
                    }
                }
            }
        }
    });

    // Inicializar tooltips de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>