<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Mis Tutorías';

// Filtro de estado
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todas';

// Construir consulta
$sql = "
    SELECT t.*, 
           ua.nombre as alumno_nombre, ua.apellidos as alumno_apellidos,
           up.nombre as profesor_nombre, up.apellidos as profesor_apellidos
    FROM tutorias t
    INNER JOIN usuarios ua ON t.id_alumno = ua.id_usuario
    LEFT JOIN usuarios up ON t.id_profesor = up.id_usuario
    WHERE t.id_padre = ?
";

$params = [$user_id];

if ($estado_filtro !== 'todas') {
    $sql .= " AND t.estado = ?";
    $params[] = $estado_filtro;
}

$sql .= " ORDER BY 
    CASE t.estado
        WHEN 'pendiente' THEN 1
        WHEN 'confirmada' THEN 2
        WHEN 'completada' THEN 3
        WHEN 'rechazada' THEN 4
        WHEN 'cancelada' THEN 5
    END,
    t.fecha_confirmada DESC,
    t.fecha_solicitada DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tutorias = $stmt->fetchAll();

// Estadísticas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas
    FROM tutorias
    WHERE id_padre = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_padre.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Mis Tutorías</h1>
                <a href="solicitar_tutoria.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Solicitar Tutoría
                </a>
            </div>

            <?php
            $msg = get_message();
            if ($msg):
            ?>
                <div class="alert alert-<?php echo $msg['type']; ?> alert-dismissible fade show">
                    <?php echo $msg['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $stats['total']; ?></h3>
                            <small>Total Tutorías</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h3><?php echo $stats['pendientes']; ?></h3>
                            <small>Pendientes</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $stats['confirmadas']; ?></h3>
                            <small>Confirmadas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $stats['completadas']; ?></h3>
                            <small>Completadas</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-10">
                            <label class="form-label">Filtrar por Estado</label>
                            <select name="estado" class="form-select" onchange="this.form.submit()">
                                <option value="todas" <?php echo $estado_filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                <option value="pendiente" <?php echo $estado_filtro === 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                                <option value="confirmada" <?php echo $estado_filtro === 'confirmada' ? 'selected' : ''; ?>>Confirmadas</option>
                                <option value="completada" <?php echo $estado_filtro === 'completada' ? 'selected' : ''; ?>>Completadas</option>
                                <option value="rechazada" <?php echo $estado_filtro === 'rechazada' ? 'selected' : ''; ?>>Rechazadas</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="mis_tutorias.php" class="btn btn-secondary w-100">Limpiar</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de tutorías -->
            <?php if (count($tutorias) > 0): ?>
                <div class="row">
                    <?php foreach ($tutorias as $tutoria): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 <?php echo $tutoria['estado'] === 'pendiente' ? 'border-warning' : ''; ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-person"></i> 
                                        <strong><?php echo escape($tutoria['alumno_nombre'] . ' ' . $tutoria['alumno_apellidos']); ?></strong>
                                    </span>
                                    <?php
                                    $badge_colors = [
                                        'pendiente' => 'warning',
                                        'confirmada' => 'info',
                                        'completada' => 'success',
                                        'rechazada' => 'danger',
                                        'cancelada' => 'secondary'
                                    ];
                                    $color = $badge_colors[$tutoria['estado']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($tutoria['estado']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2">
                                        <strong>Motivo:</strong><br>
                                        <?php echo nl2br(escape($tutoria['motivo'])); ?>
                                    </p>
                                    
                                    <hr>
                                    
                                    <p class="mb-2">
                                        <i class="bi bi-calendar-event text-muted"></i> 
                                        <strong>Fecha solicitada:</strong> 
                                        <?php echo format_date($tutoria['fecha_solicitada']); ?> - 
                                        <?php echo substr($tutoria['hora_solicitada'], 0, 5); ?>
                                    </p>
                                    
                                    <?php if ($tutoria['estado'] === 'confirmada' && $tutoria['fecha_confirmada']): ?>
                                        <p class="mb-2">
                                            <i class="bi bi-calendar-check text-success"></i> 
                                            <strong>Fecha confirmada:</strong> 
                                            <?php echo format_date($tutoria['fecha_confirmada']); ?> - 
                                            <?php echo substr($tutoria['hora_confirmada'], 0, 5); ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="bi bi-geo-alt"></i> 
                                            <strong>Ubicación:</strong> <?php echo escape($tutoria['ubicacion']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($tutoria['notas']): ?>
                                        <div class="alert alert-info small mt-2 mb-2">
                                            <strong>Notas:</strong> <?php echo nl2br(escape($tutoria['notas'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <small class="text-muted">
                                        Solicitada el: <?php echo format_datetime($tutoria['fecha_solicitud']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No tienes tutorías con los filtros seleccionados.
                    <hr>
                    <a href="solicitar_tutoria.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle"></i> Solicitar mi primera tutoría
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>