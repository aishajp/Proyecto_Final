<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Historial de Clases';

// Obtener hijos vinculados
$stmt = $db->prepare("
    SELECT u.id_usuario, u.nombre, u.apellidos
    FROM usuarios u
    INNER JOIN relacion_padre_hijo rph ON u.id_usuario = rph.id_alumno
    WHERE rph.id_padre = ?
");
$stmt->execute([$user_id]);
$hijos = $stmt->fetchAll();

$hijo_seleccionado = isset($_GET['hijo']) ? (int)$_GET['hijo'] : (count($hijos) > 0 ? $hijos[0]['id_usuario'] : 0);

// Filtros
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todas';

// Obtener historial de clases del hijo
$sql = "
    SELECT c.*, r.id_reserva, r.estado as estado_reserva, r.asistencia, r.fecha_reserva,
           u.nombre as profesor_nombre, u.apellidos as profesor_apellidos
    FROM clases c
    INNER JOIN reservas r ON c.id_clase = r.id_clase
    LEFT JOIN usuarios u ON c.id_profesor = u.id_usuario
    WHERE r.id_usuario = ?
";

$params = [$hijo_seleccionado];

if ($estado_filtro !== 'todas') {
    $sql .= " AND r.estado = ?";
    $params[] = $estado_filtro;
}

$sql .= " ORDER BY c.fecha DESC, c.hora_inicio DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$clases = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_padre.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Historial de Clases</h1>
            </div>

            <?php if (count($hijos) === 0): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> No tienes hijos vinculados.
                </div>
            <?php else: ?>
                <!-- Selector de hijo -->
                <?php if (count($hijos) > 1): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <label class="form-label">Ver clases de:</label>
                            <select class="form-select" onchange="location.href='historial_clases.php?hijo='+this.value">
                                <?php foreach ($hijos as $hijo): ?>
                                    <option value="<?php echo $hijo['id_usuario']; ?>" <?php echo $hijo_seleccionado === $hijo['id_usuario'] ? 'selected' : ''; ?>>
                                        <?php echo escape($hijo['nombre'] . ' ' . $hijo['apellidos']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="hijo" value="<?php echo $hijo_seleccionado; ?>">
                            <div class="col-md-4">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select" onchange="this.form.submit()">
                                    <option value="todas" <?php echo $estado_filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                    <option value="confirmada" <?php echo $estado_filtro === 'confirmada' ? 'selected' : ''; ?>>Confirmadas</option>
                                    <option value="completada" <?php echo $estado_filtro === 'completada' ? 'selected' : ''; ?>>Completadas</option>
                                    <option value="cancelada" <?php echo $estado_filtro === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <a href="historial_clases.php?hijo=<?php echo $hijo_seleccionado; ?>" class="btn btn-secondary">Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de clases -->
                <div class="card">
                    <div class="card-body">
                        <?php if (count($clases) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th>Fecha</th>
                                            <th>Hora</th>
                                            <th>Tipo</th>
                                            <th>Nivel</th>
                                            <th>Estado</th>
                                            <th>Asistencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clases as $clase): ?>
                                            <tr>
                                                <td><strong><?php echo escape($clase['titulo']); ?></strong></td>
                                                <td><?php echo format_date($clase['fecha']); ?></td>
                                                <td><?php echo substr($clase['hora_inicio'], 0, 5); ?> - <?php echo substr($clase['hora_fin'], 0, 5); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                                        <?php echo ucfirst($clase['tipo']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo ucfirst($clase['nivel']); ?></td>
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
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No hay clases registradas.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>