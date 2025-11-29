<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$page_title = 'Gestión de Clases';

// Filtros
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : 'todas';
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todas';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Construir consulta
$sql = "SELECT c.*, COUNT(r.id_reserva) as total_reservas FROM clases c LEFT JOIN reservas r ON c.id_clase = r.id_clase WHERE 1=1";
$params = [];

if ($tipo_filtro !== 'todas') {
    $sql .= " AND c.tipo = ?";
    $params[] = $tipo_filtro;
}

if ($estado_filtro !== 'todas') {
    $sql .= " AND c.estado = ?";
    $params[] = $estado_filtro;
}

if (!empty($fecha_desde)) {
    $sql .= " AND c.fecha >= ?";
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $sql .= " AND c.fecha <= ?";
    $params[] = $fecha_hasta;
}

$sql .= " GROUP BY c.id_clase ORDER BY c.fecha DESC, c.hora_inicio DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$clases = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestión de Clases</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaClase">
                    <i class="bi bi-plus-circle"></i> Nueva Clase
                </button>
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

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" onchange="this.form.submit()">
                                <option value="todas">Todas</option>
                                <option value="individual" <?php echo $tipo_filtro === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                <option value="grupal" <?php echo $tipo_filtro === 'grupal' ? 'selected' : ''; ?>>Grupal</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select" onchange="this.form.submit()">
                                <option value="todas">Todas</option>
                                <option value="programada" <?php echo $estado_filtro === 'programada' ? 'selected' : ''; ?>>Programada</option>
                                <option value="completada" <?php echo $estado_filtro === 'completada' ? 'selected' : ''; ?>>Completada</option>
                                <option value="cancelada" <?php echo $estado_filtro === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Desde</label>
                            <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de clases -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Nivel</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Plazas</th>
                                    <th>Reservas</th>
                                    <th>Precio</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clases as $clase): ?>
                                    <tr>
                                        <td><strong><?php echo escape($clase['titulo']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                                <?php echo ucfirst($clase['tipo']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst($clase['nivel']); ?></td>
                                        <td><?php echo format_date($clase['fecha']); ?></td>
                                        <td><?php echo substr($clase['hora_inicio'], 0, 5); ?></td>
                                        <td><?php echo $clase['plazas_disponibles']; ?> / <?php echo $clase['plazas_totales']; ?></td>
                                        <td><?php echo $clase['total_reservas']; ?></td>
                                        <td>€<?php echo number_format($clase['precio'], 2); ?></td>
                                        <td>
                                            <?php
                                            $badge_color = 'secondary';
                                            switch ($clase['estado']) {
                                                case 'programada': $badge_color = 'info'; break;
                                                case 'completada': $badge_color = 'success'; break;
                                                case 'cancelada': $badge_color = 'danger'; break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?>">
                                                <?php echo ucfirst($clase['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="ver_clase.php?id=<?php echo $clase['id_clase']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-warning btn-editar-clase" data-id="<?php echo $clase['id_clase']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($clase['estado'] === 'programada'): ?>
                                                <button class="btn btn-sm btn-danger btn-cancelar-clase" data-id="<?php echo $clase['id_clase']; ?>">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Nueva Clase -->
<div class="modal fade" id="modalNuevaClase" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Clase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="guardar_clase.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="titulo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo *</label>
                            <select class="form-select" name="tipo" id="tipo_clase" required>
                                <option value="">Seleccione...</option>
                                <option value="individual">Individual</option>
                                <option value="grupal">Grupal</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nivel *</label>
                            <select class="form-select" name="nivel" required>
                                <option value="">Seleccione...</option>
                                <option value="basico">Básico</option>
                                <option value="intermedio">Intermedio</option>
                                <option value="avanzado">Avanzado</option>
                                <option value="conversacion">Conversación</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Plazas Totales *</label>
                            <input type="number" class="form-control" name="plazas_totales" id="plazas_totales" min="1" value="1" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hora Inicio *</label>
                            <input type="time" class="form-control" name="hora_inicio" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duración (min) *</label>
                            <input type="number" class="form-control" name="duracion" min="15" step="15" value="60" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio (€) *</label>
                            <input type="number" class="form-control" name="precio" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ubicación</label>
                            <input type="text" class="form-control" name="ubicacion" value="Online">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Clase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('tipo_clase').addEventListener('change', function() {
    const plazasInput = document.getElementById('plazas_totales');
    if (this.value === 'individual') {
        plazasInput.value = 1;
        plazasInput.max = 1;
    } else {
        plazasInput.max = 20;
    }
});
</script>

<?php include '../includes/footer.php'; ?>