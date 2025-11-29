<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$page_title = 'Estadísticas';

// Estadísticas generales de usuarios
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_usuarios,
        SUM(CASE WHEN rol = 'alumno' THEN 1 ELSE 0 END) as total_alumnos,
        SUM(CASE WHEN rol = 'padre' THEN 1 ELSE 0 END) as total_padres,
        SUM(CASE WHEN rol = 'administrador' THEN 1 ELSE 0 END) as total_admins,
        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as usuarios_activos
    FROM usuarios
");
$stats_usuarios = $stmt->fetch();

// Estadísticas de clases
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_clases,
        SUM(CASE WHEN estado = 'programada' THEN 1 ELSE 0 END) as clases_programadas,
        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as clases_completadas,
        SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as clases_canceladas,
        SUM(CASE WHEN tipo = 'individual' THEN 1 ELSE 0 END) as clases_individuales,
        SUM(CASE WHEN tipo = 'grupal' THEN 1 ELSE 0 END) as clases_grupales
    FROM clases
");
$stats_clases = $stmt->fetch();

// Estadísticas de reservas y asistencia
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_reservas,
        SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as reservas_confirmadas,
        SUM(CASE WHEN asistencia = 'presente' THEN 1 ELSE 0 END) as asistencias,
        SUM(CASE WHEN asistencia = 'ausente' THEN 1 ELSE 0 END) as ausencias
    FROM reservas
");
$stats_reservas = $stmt->fetch();

// Tasa de asistencia
$tasa_asistencia = 0;
if ($stats_reservas['asistencias'] + $stats_reservas['ausencias'] > 0) {
    $tasa_asistencia = ($stats_reservas['asistencias'] / ($stats_reservas['asistencias'] + $stats_reservas['ausencias'])) * 100;
}

// Estadísticas de materiales
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_materiales,
        SUM(descargas) as total_descargas,
        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as materiales_activos
    FROM materiales
");
$stats_materiales = $stmt->fetch();

// Estadísticas de exámenes
$stmt = $db->query("
    SELECT 
        COUNT(DISTINCT e.id_examen) as total_examenes,
        COUNT(r.id_resultado) as total_resultados,
        AVG(r.puntuacion) as promedio_general
    FROM examenes e
    LEFT JOIN resultados_examenes r ON e.id_examen = r.id_examen
");
$stats_examenes = $stmt->fetch();

// Estadísticas de tutorías
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_tutorias,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas
    FROM tutorias
");
$stats_tutorias = $stmt->fetch();

// Top 5 alumnos con mejor promedio
$stmt = $db->query("
    SELECT u.nombre, u.apellidos, p.promedio_general, p.nivel_actual
    FROM progreso_alumno p
    INNER JOIN usuarios u ON p.id_alumno = u.id_usuario
    ORDER BY p.promedio_general DESC
    LIMIT 5
");
$top_alumnos = $stmt->fetchAll();

// Clases más populares (más reservas)
$stmt = $db->query("
    SELECT c.titulo, c.tipo, c.nivel, COUNT(r.id_reserva) as total_reservas
    FROM clases c
    LEFT JOIN reservas r ON c.id_clase = r.id_clase
    GROUP BY c.id_clase
    ORDER BY total_reservas DESC
    LIMIT 5
");
$clases_populares = $stmt->fetchAll();

// Actividad por mes (últimos 6 meses)
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(fecha, '%Y-%m') as mes,
        COUNT(*) as total_clases
    FROM clases
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mes
    ORDER BY mes
");
$actividad_mensual = $stmt->fetchAll();

// Distribución de niveles de alumnos
$stmt = $db->query("
    SELECT 
        nivel_actual,
        COUNT(*) as total
    FROM progreso_alumno
    GROUP BY nivel_actual
    ORDER BY 
        FIELD(nivel_actual, 'A1', 'A2', 'B1', 'B2', 'C1', 'C2')
");
$distribucion_niveles = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Estadísticas del Sistema</h1>
                <div class="btn-toolbar">
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                </div>
            </div>

            <!-- Resumen General -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h4>Resumen General</h4>
                </div>
            </div>

            <div class="row mb-4">
                <!-- Usuarios -->
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h6 class="card-title">Usuarios</h6>
                            <h2><?php echo $stats_usuarios['total_usuarios']; ?></h2>
                            <hr class="bg-white">
                            <small>Alumnos: <?php echo $stats_usuarios['total_alumnos']; ?></small><br>
                            <small>Padres: <?php echo $stats_usuarios['total_padres']; ?></small>
                        </div>
                    </div>
                </div>

                <!-- Clases -->
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h6 class="card-title">Clases</h6>
                            <h2><?php echo $stats_clases['total_clases']; ?></h2>
                            <hr class="bg-white">
                            <small>Completadas: <?php echo $stats_clases['clases_completadas']; ?></small><br>
                            <small>Programadas: <?php echo $stats_clases['clases_programadas']; ?></small>
                        </div>
                    </div>
                </div>

                <!-- Materiales -->
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h6 class="card-title">Materiales</h6>
                            <h2><?php echo $stats_materiales['total_materiales']; ?></h2>
                            <hr class="bg-white">
                            <small>Descargas: <?php echo $stats_materiales['total_descargas']; ?></small><br>
                            <small>Activos: <?php echo $stats_materiales['materiales_activos']; ?></small>
                        </div>
                    </div>
                </div>

                <!-- Tutorías -->
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h6 class="card-title">Tutorías</h6>
                            <h2><?php echo $stats_tutorias['total_tutorias']; ?></h2>
                            <hr class="bg-light">
                            <small>Pendientes: <?php echo $stats_tutorias['pendientes']; ?></small><br>
                            <small>Completadas: <?php echo $stats_tutorias['completadas']; ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <!-- Estadísticas de Asistencia -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Tasa de Asistencia</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h1 class="display-4"><?php echo round($tasa_asistencia); ?>%</h1>
                            </div>
                            <div class="progress mb-3" style="height: 30px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $tasa_asistencia; ?>%">
                                    <?php echo $stats_reservas['asistencias']; ?> presentes
                                </div>
                                <div class="progress-bar bg-danger" style="width: <?php echo 100 - $tasa_asistencia; ?>%">
                                    <?php echo $stats_reservas['ausencias']; ?> ausentes
                                </div>
                            </div>
                            <div class="row text-center">
                                <div class="col">
                                    <h4><?php echo $stats_reservas['total_reservas']; ?></h4>
                                    <small class="text-muted">Total Reservas</small>
                                </div>
                                <div class="col">
                                    <h4 class="text-success"><?php echo $stats_reservas['asistencias']; ?></h4>
                                    <small class="text-muted">Asistencias</small>
                                </div>
                                <div class="col">
                                    <h4 class="text-danger"><?php echo $stats_reservas['ausencias']; ?></h4>
                                    <small class="text-muted">Ausencias</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas de Exámenes -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Rendimiento en Exámenes</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-4">
                                <div class="col">
                                    <h3><?php echo $stats_examenes['total_examenes']; ?></h3>
                                    <small class="text-muted">Exámenes Realizados</small>
                                </div>
                                <div class="col">
                                    <h3><?php echo $stats_examenes['total_resultados']; ?></h3>
                                    <small class="text-muted">Calificaciones</small>
                                </div>
                                <div class="col">
                                    <h3 class="text-primary"><?php echo number_format($stats_examenes['promedio_general'], 2); ?></h3>
                                    <small class="text-muted">Promedio General</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribución de Niveles -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Distribución por Nivel</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($distribucion_niveles) > 0): ?>
                                <?php foreach ($distribucion_niveles as $nivel): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><strong><?php echo $nivel['nivel_actual']; ?></strong></span>
                                            <span><?php echo $nivel['total']; ?> alumno(s)</span>
                                        </div>
                                        <?php 
                                        $porcentaje = ($nivel['total'] / $stats_usuarios['total_alumnos']) * 100;
                                        ?>
                                        <div class="progress">
                                            <div class="progress-bar" style="width: <?php echo $porcentaje; ?>%">
                                                <?php echo round($porcentaje); ?>%
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No hay datos de niveles</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top 5 Alumnos -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Top 5 Alumnos</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($top_alumnos) > 0): ?>
                                <ol class="list-group list-group-numbered">
                                    <?php foreach ($top_alumnos as $alumno): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold"><?php echo escape($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></div>
                                                <small>Nivel: <?php echo $alumno['nivel_actual']; ?></small>
                                            </div>
                                            <span class="badge bg-success rounded-pill">
                                                <?php echo number_format($alumno['promedio_general'], 1); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php else: ?>
                                <p class="text-muted">No hay datos de progreso</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clases Más Populares -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Clases Más Populares</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($clases_populares) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Posición</th>
                                                <th>Clase</th>
                                                <th>Tipo</th>
                                                <th>Nivel</th>
                                                <th>Total Reservas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $pos = 1;
                                            foreach ($clases_populares as $clase): 
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo $pos++; ?>º</strong></td>
                                                    <td><?php echo escape($clase['titulo']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                                            <?php echo ucfirst($clase['tipo']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo ucfirst($clase['nivel']); ?></td>
                                                    <td><strong><?php echo $clase['total_reservas']; ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No hay datos disponibles</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actividad Mensual -->
            <?php if (count($actividad_mensual) > 0): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Actividad de Clases (Últimos 6 Meses)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Mes</th>
                                            <th>Total Clases</th>
                                            <th>Gráfico</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $max_clases = max(array_column($actividad_mensual, 'total_clases'));
                                        foreach ($actividad_mensual as $mes): 
                                            $porcentaje = ($mes['total_clases'] / $max_clases) * 100;
                                        ?>
                                            <tr>
                                                <td><?php echo $mes['mes']; ?></td>
                                                <td><strong><?php echo $mes['total_clases']; ?></strong></td>
                                                <td>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-primary" style="width: <?php echo $porcentaje; ?>%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>