<?php
// ============================================
// alumno/mis_clases.php
// Gestión de clases del alumno
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Mis Clases';

// Filtros
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todas';
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : 'todas';

// Construir consulta con filtros
$sql = "
    SELECT c.*, r.id_reserva, r.estado as estado_reserva, r.asistencia, r.fecha_reserva,
           u.nombre as profesor_nombre, u.apellidos as profesor_apellidos
    FROM clases c
    INNER JOIN reservas r ON c.id_clase = r.id_clase
    LEFT JOIN usuarios u ON c.id_profesor = u.id_usuario
    WHERE r.id_usuario = ?
";

$params = [$user_id];

if ($estado_filtro !== 'todas') {
    $sql .= " AND r.estado = ?";
    $params[] = $estado_filtro;
}

if ($tipo_filtro !== 'todas') {
    $sql .= " AND c.tipo = ?";
    $params[] = $tipo_filtro;
}

$sql .= " ORDER BY c.fecha DESC, c.hora_inicio DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$mis_clases = $stmt->fetchAll();

// Estadísticas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN r.estado = 'confirmada' AND c.fecha >= CURDATE() THEN 1 ELSE 0 END) as proximas,
        SUM(CASE WHEN r.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN r.asistencia = 'presente' THEN 1 ELSE 0 END) as asistidas
    FROM reservas r
    INNER JOIN clases c ON r.id_clase = c.id_clase
    WHERE r.id_usuario = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_alumno.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Mis Clases</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="calendario.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-calendar3"></i> Ver Calendario
                    </a>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Clases</h6>
                            <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Próximas</h6>
                            <h2 class="mb-0"><?php echo $stats['proximas']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Completadas</h6>
                            <h2 class="mb-0"><?php echo $stats['completadas']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h6 class="card-title">Asistencia</h6>
                            <h2 class="mb-0"><?php echo $stats['total'] > 0 ? round(($stats['asistidas'] / $stats['total']) * 100) : 0; ?>%</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select" onchange="this.form.submit()">
                                <option value="todas" <?php echo $estado_filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                <option value="confirmada" <?php echo $estado_filtro === 'confirmada' ? 'selected' : ''; ?>>Confirmadas</option>
                                <option value="completada" <?php echo $estado_filtro === 'completada' ? 'selected' : ''; ?>>Completadas</option>
                                <option value="cancelada" <?php echo $estado_filtro === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" onchange="this.form.submit()">
                                <option value="todas" <?php echo $tipo_filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                <option value="individual" <?php echo $tipo_filtro === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                <option value="grupal" <?php echo $tipo_filtro === 'grupal' ? 'selected' : ''; ?>>Grupal</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <a href="mis_clases.php" class="btn btn-secondary">Limpiar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de clases -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Historial de Clases</h5>
                </div>
                <div class="card-body">
                    <?php if (count($mis_clases) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Tipo</th>
                                        <th>Nivel</th>
                                        <th>Profesor</th>
                                        <th>Estado</th>
                                        <th>Asistencia</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mis_clases as $clase): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo escape($clase['titulo']); ?></strong>
                                                <?php if (!empty($clase['descripcion'])): ?>
                                                    <br><small class="text-muted"><?php echo escape(substr($clase['descripcion'], 0, 50)); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo format_date($clase['fecha']); ?></td>
                                            <td><?php echo substr($clase['hora_inicio'], 0, 5); ?> - <?php echo substr($clase['hora_fin'], 0, 5); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                                    <?php echo ucfirst($clase['tipo']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo ucfirst($clase['nivel']); ?></td>
                                            <td><?php echo escape($clase['profesor_nombre'] . ' ' . $clase['profesor_apellidos']); ?></td>
                                            <td>
                                                <?php
                                                $badge_color = 'secondary';
                                                switch ($clase['estado_reserva']) {
                                                    case 'confirmada': $badge_color = 'info'; break;
                                                    case 'completada': $badge_color = 'success'; break;
                                                    case 'cancelada': $badge_color = 'danger'; break;
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badge_color; ?>">
                                                    <?php echo ucfirst($clase['estado_reserva']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($clase['asistencia']): ?>
                                                    <span class="badge bg-<?php echo $clase['asistencia'] === 'presente' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($clase['asistencia']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="detalle_clase.php?id=<?php echo $clase['id_clase']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($clase['estado_reserva'] === 'confirmada' && is_future_date($clase['fecha'])): ?>
                                                    <button class="btn btn-sm btn-danger btn-cancelar" data-id="<?php echo $clase['id_reserva']; ?>" title="Cancelar">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No tienes clases registradas con los filtros seleccionados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal para cancelar clase -->
<div class="modal fade" id="modalCancelar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancelar Reserva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCancelar" method="POST" action="cancelar_clase.php">
                <div class="modal-body">
                    <input type="hidden" name="id_reserva" id="id_reserva_cancelar">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <p>¿Estás seguro de que deseas cancelar esta clase?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo de cancelación (opcional)</label>
                        <textarea class="form-control" name="motivo" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger">Cancelar Clase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalCancelar = new bootstrap.Modal(document.getElementById('modalCancelar'));
    
    document.querySelectorAll('.btn-cancelar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const idReserva = this.getAttribute('data-id');
            document.getElementById('id_reserva_cancelar').value = idReserva;
            modalCancelar.show();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>