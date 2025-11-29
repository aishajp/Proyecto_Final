<?php
// ============================================
// padre/reservar_clase.php
// Reservar clase para el hijo
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Reservar Clase';

// Obtener hijos vinculados
$stmt = $db->prepare("
    SELECT u.*
    FROM usuarios u
    INNER JOIN relacion_padre_hijo rph ON u.id_usuario = rph.id_alumno
    WHERE rph.id_padre = ?
");
$stmt->execute([$user_id]);
$hijos = $stmt->fetchAll();

// Filtros
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : 'todas';
$nivel_filtro = isset($_GET['nivel']) ? $_GET['nivel'] : 'todos';

// Obtener clases disponibles (futuras con plazas)
$sql = "
    SELECT c.*, u.nombre as profesor_nombre, u.apellidos as profesor_apellidos
    FROM clases c
    LEFT JOIN usuarios u ON c.id_profesor = u.id_usuario
    WHERE c.fecha >= CURDATE() 
    AND c.estado = 'programada' 
    AND c.plazas_disponibles > 0
";

$params = [];

if ($tipo_filtro !== 'todas') {
    $sql .= " AND c.tipo = ?";
    $params[] = $tipo_filtro;
}

if ($nivel_filtro !== 'todos') {
    $sql .= " AND c.nivel = ?";
    $params[] = $nivel_filtro;
}

$sql .= " ORDER BY c.fecha ASC, c.hora_inicio ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$clases_disponibles = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_padre.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Reservar Clase</h1>
                <a href="historial_clases.php" class="btn btn-outline-primary">
                    <i class="bi bi-clock-history"></i> Ver Historial
                </a>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Clase</label>
                            <select name="tipo" class="form-select" onchange="this.form.submit()">
                                <option value="todas" <?php echo $tipo_filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                <option value="individual" <?php echo $tipo_filtro === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                <option value="grupal" <?php echo $tipo_filtro === 'grupal' ? 'selected' : ''; ?>>Grupal</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nivel</label>
                            <select name="nivel" class="form-select" onchange="this.form.submit()">
                                <option value="todos" <?php echo $nivel_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="basico" <?php echo $nivel_filtro === 'basico' ? 'selected' : ''; ?>>Básico</option>
                                <option value="intermedio" <?php echo $nivel_filtro === 'intermedio' ? 'selected' : ''; ?>>Intermedio</option>
                                <option value="avanzado" <?php echo $nivel_filtro === 'avanzado' ? 'selected' : ''; ?>>Avanzado</option>
                                <option value="conversacion" <?php echo $nivel_filtro === 'conversacion' ? 'selected' : ''; ?>>Conversación</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <a href="reservar_clase.php" class="btn btn-secondary">Limpiar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Clases disponibles -->
            <?php if (count($clases_disponibles) > 0): ?>
                <div class="row">
                    <?php foreach ($clases_disponibles as $clase): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo escape($clase['titulo']); ?></h5>
                                    
                                    <div class="mb-3">
                                        <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                            <?php echo ucfirst($clase['tipo']); ?>
                                        </span>
                                        <span class="badge bg-secondary"><?php echo ucfirst($clase['nivel']); ?></span>
                                    </div>
                                    
                                    <?php if ($clase['descripcion']): ?>
                                        <p class="card-text text-muted small"><?php echo escape(substr($clase['descripcion'], 0, 80)); ?>...</p>
                                    <?php endif; ?>
                                    
                                    <hr>
                                    
                                    <div class="mb-2">
                                        <i class="bi bi-calendar3"></i> <strong>Fecha:</strong> <?php echo format_date($clase['fecha']); ?>
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-clock"></i> <strong>Hora:</strong> 
                                        <?php echo substr($clase['hora_inicio'], 0, 5); ?> - <?php echo substr($clase['hora_fin'], 0, 5); ?>
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-hourglass-split"></i> <strong>Duración:</strong> <?php echo $clase['duracion']; ?> min
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-people"></i> <strong>Plazas:</strong> 
                                        <?php echo $clase['plazas_disponibles']; ?> / <?php echo $clase['plazas_totales']; ?>
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-currency-euro"></i> <strong>Precio:</strong> €<?php echo number_format($clase['precio'], 2); ?>
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-person"></i> <strong>Profesor:</strong> 
                                        <?php echo escape($clase['profesor_nombre'] . ' ' . $clase['profesor_apellidos']); ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <button class="btn btn-primary w-100 btn-reservar" data-clase-id="<?php echo $clase['id_clase']; ?>">
                                        <i class="bi bi-calendar-check"></i> Reservar Clase
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No hay clases disponibles con los filtros seleccionados.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal para seleccionar hijo -->
<div class="modal fade" id="modalReservar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reservar Clase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formReservar" method="POST" action="procesar_reserva.php">
                <div class="modal-body">
                    <input type="hidden" name="id_clase" id="id_clase_reservar">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Seleccione el alumno:</label>
                        <select class="form-select" name="id_alumno" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($hijos as $hijo): ?>
                                <option value="<?php echo $hijo['id_usuario']; ?>">
                                    <?php echo escape($hijo['nombre'] . ' ' . $hijo['apellidos']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar Reserva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalReservar = new bootstrap.Modal(document.getElementById('modalReservar'));
    
    document.querySelectorAll('.btn-reservar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const claseId = this.getAttribute('data-clase-id');
            document.getElementById('id_clase_reservar').value = claseId;
            modalReservar.show();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>