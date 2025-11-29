<?php
// administrador/clases.php - CON RESTRICCIONES
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

// Contar total de alumnos activos (para límite de plazas)
$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'alumno' AND activo = 1");
$total_alumnos = $stmt->fetch()['total'];

// Fecha y hora mínima permitida (1 hora desde ahora)
$min_datetime = date('Y-m-d H:i', strtotime('+1 hour'));
$min_date = date('Y-m-d');
$min_time = date('H:i', strtotime('+1 hour'));

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
                                    <th>Precio</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clases as $clase): 
                                    $clase_pasada = strtotime($clase['fecha'] . ' ' . $clase['hora_inicio']) < time();
                                ?>
                                    <tr class="<?php echo $clase_pasada ? 'table-secondary' : ''; ?>">
                                        <td>
                                            <strong><?php echo escape($clase['titulo']); ?></strong>
                                            <?php if ($clase_pasada): ?>
                                                <span class="badge bg-secondary">Caducada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                                <?php echo ucfirst($clase['tipo']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst($clase['nivel']); ?></td>
                                        <td><?php echo format_date($clase['fecha']); ?></td>
                                        <td><?php echo substr($clase['hora_inicio'], 0, 5); ?></td>
                                        <td><?php echo $clase['plazas_disponibles']; ?> / <?php echo $clase['plazas_totales']; ?></td>
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
                                            <a href="ver_clase.php?id=<?php echo $clase['id_clase']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (!$clase_pasada && $clase['estado'] === 'programada'): ?>
                                                <button class="btn btn-sm btn-warning btn-editar-clase" 
                                                        data-clase='<?php echo json_encode($clase); ?>'
                                                        title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-eliminar-clase" 
                                                        data-id="<?php echo $clase['id_clase']; ?>"
                                                        data-titulo="<?php echo escape($clase['titulo']); ?>"
                                                        title="Eliminar">
                                                    <i class="bi bi-trash"></i>
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
            <form method="POST" action="guardar_clase.php" id="formNuevaClase">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="titulo" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3" maxlength="500"></textarea>
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
                            <input type="number" class="form-control" name="plazas_totales" id="plazas_totales" 
                                   min="1" max="<?php echo $total_alumnos; ?>" value="1" required>
                            <small class="text-muted">Máximo: <?php echo $total_alumnos; ?> alumnos</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong><i class="bi bi-exclamation-triangle"></i> Importante:</strong>
                        La clase debe programarse con al menos 1 hora de antelación desde este momento.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" id="fecha_clase" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hora Inicio *</label>
                            <input type="time" class="form-control" name="hora_inicio" id="hora_inicio" required>
                            <small class="text-muted" id="hora_minima_text"></small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duración (min) *</label>
                            <select class="form-select" name="duracion" required>
                                <option value="">Seleccione...</option>
                                <option value="30">30 minutos</option>
                                <option value="45">45 minutos</option>
                                <option value="60" selected>60 minutos</option>
                                <option value="90">90 minutos</option>
                                <option value="120">120 minutos</option>
                            </select>
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

<!-- Modal Editar Clase -->
<div class="modal fade" id="modalEditarClase" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Clase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="editar_clase.php" id="formEditarClase">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_clase" id="edit_id_clase">
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="titulo" id="edit_titulo" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="3" maxlength="500"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo *</label>
                            <select class="form-select" name="tipo" id="edit_tipo" required>
                                <option value="individual">Individual</option>
                                <option value="grupal">Grupal</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nivel *</label>
                            <select class="form-select" name="nivel" id="edit_nivel" required>
                                <option value="basico">Básico</option>
                                <option value="intermedio">Intermedio</option>
                                <option value="avanzado">Avanzado</option>
                                <option value="conversacion">Conversación</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Plazas Totales *</label>
                            <input type="number" class="form-control" name="plazas_totales" id="edit_plazas_totales" 
                                   min="1" max="<?php echo $total_alumnos; ?>" required>
                            <small class="text-muted">Máximo: <?php echo $total_alumnos; ?> alumnos</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong><i class="bi bi-exclamation-triangle"></i> Importante:</strong>
                        La clase debe programarse con al menos 1 hora de antelación desde este momento.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" id="edit_fecha" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hora Inicio *</label>
                            <input type="time" class="form-control" name="hora_inicio" id="edit_hora_inicio" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duración (min) *</label>
                            <select class="form-select" name="duracion" id="edit_duracion" required>
                                <option value="30">30 minutos</option>
                                <option value="45">45 minutos</option>
                                <option value="60">60 minutos</option>
                                <option value="90">90 minutos</option>
                                <option value="120">120 minutos</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio (€) *</label>
                            <input type="number" class="form-control" name="precio" id="edit_precio" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ubicación</label>
                            <input type="text" class="form-control" name="ubicacion" id="edit_ubicacion">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Limitar plazas según tipo de clase
    document.getElementById('tipo_clase')?.addEventListener('change', function() {
        const plazasInput = document.getElementById('plazas_totales');
        if (this.value === 'individual') {
            plazasInput.value = 1;
            plazasInput.max = 1;
        } else {
            plazasInput.max = <?php echo $total_alumnos; ?>;
        }
    });
    
    // Validar fecha y hora en tiempo real para NUEVA clase
    const fechaInput = document.getElementById('fecha_clase');
    const horaInput = document.getElementById('hora_inicio');
    const horaMinText = document.getElementById('hora_minima_text');
    
    function validarFechaHora() {
        const fechaSeleccionada = fechaInput.value;
        const horaSeleccionada = horaInput.value;
        const hoy = '<?php echo date('Y-m-d'); ?>';
        const ahora = new Date();
        const horaMinima = new Date(ahora.getTime() + 60 * 60 * 1000); // +1 hora
        
        if (fechaSeleccionada === hoy) {
            const horaMin = horaMinima.toTimeString().substring(0, 5);
            horaInput.min = horaMin;
            horaMinText.textContent = `Hora mínima hoy: ${horaMin}`;
            horaMinText.classList.add('text-danger');
        } else {
            horaInput.removeAttribute('min');
            horaMinText.textContent = '';
        }
    }
    
    fechaInput?.addEventListener('change', validarFechaHora);
    horaInput?.addEventListener('focus', validarFechaHora);
    
    // Validar al enviar formulario NUEVA clase
    document.getElementById('formNuevaClase')?.addEventListener('submit', function(e) {
        const fecha = document.getElementById('fecha_clase').value;
        const hora = document.getElementById('hora_inicio').value;
        const fechaHoraClase = new Date(fecha + ' ' + hora);
        const fechaHoraMinima = new Date(Date.now() + 60 * 60 * 1000);
        
        if (fechaHoraClase < fechaHoraMinima) {
            e.preventDefault();
            alert('Error: La clase debe programarse con al menos 1 hora de antelación desde este momento.');
            return false;
        }
    });
    
    // Validar al enviar formulario EDITAR clase
    document.getElementById('formEditarClase')?.addEventListener('submit', function(e) {
        const fecha = document.getElementById('edit_fecha').value;
        const hora = document.getElementById('edit_hora_inicio').value;
        const fechaHoraClase = new Date(fecha + ' ' + hora);
        const fechaHoraMinima = new Date(Date.now() + 60 * 60 * 1000);
        
        if (fechaHoraClase < fechaHoraMinima) {
            e.preventDefault();
            alert('Error: La clase debe programarse con al menos 1 hora de antelación desde este momento.');
            return false;
        }
    });
    
    // Editar clase
    document.querySelectorAll('.btn-editar-clase').forEach(btn => {
        btn.addEventListener('click', function() {
            const clase = JSON.parse(this.getAttribute('data-clase'));
            const modal = new bootstrap.Modal(document.getElementById('modalEditarClase'));
            
            document.getElementById('edit_id_clase').value = clase.id_clase;
            document.getElementById('edit_titulo').value = clase.titulo;
            document.getElementById('edit_descripcion').value = clase.descripcion || '';
            document.getElementById('edit_tipo').value = clase.tipo;
            document.getElementById('edit_nivel').value = clase.nivel;
            document.getElementById('edit_plazas_totales').value = clase.plazas_totales;
            document.getElementById('edit_fecha').value = clase.fecha;
            document.getElementById('edit_hora_inicio').value = clase.hora_inicio.substring(0, 5);
            document.getElementById('edit_duracion').value = clase.duracion;
            document.getElementById('edit_precio').value = clase.precio;
            document.getElementById('edit_ubicacion').value = clase.ubicacion || '';
            
            modal.show();
        });
    });
    
    // Eliminar clase
    document.querySelectorAll('.btn-eliminar-clase').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const titulo = this.getAttribute('data-titulo');
            
            if (confirm(`¿Estás segura de que deseas ELIMINAR la clase "${titulo}"?\n\nEsta acción NO se puede deshacer y se cancelarán todas las reservas.`)) {
                window.location.href = `eliminar_clase.php?id=${id}`;
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>