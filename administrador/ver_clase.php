<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$id_clase = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_clase === 0) {
    set_message('ID de clase inválido', MSG_ERROR);
    redirect('administrador/clases.php');
}

$db = Database::getInstance()->getConnection();

// Obtener datos de la clase
$stmt = $db->prepare("
    SELECT c.*, u.nombre as profesor_nombre, u.apellidos as profesor_apellidos
    FROM clases c
    LEFT JOIN usuarios u ON c.id_profesor = u.id_usuario
    WHERE c.id_clase = ?
");
$stmt->execute([$id_clase]);
$clase = $stmt->fetch();

if (!$clase) {
    set_message('Clase no encontrada', MSG_ERROR);
    redirect('administrador/clases.php');
}

// Obtener reservas de la clase
$stmt = $db->prepare("
    SELECT r.*, u.nombre, u.apellidos, u.email, u.telefono
    FROM reservas r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.id_clase = ?
    ORDER BY r.fecha_reserva DESC
");
$stmt->execute([$id_clase]);
$reservas = $stmt->fetchAll();

$page_title = 'Ver Clase';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Detalles de la Clase</h1>
                <div class="btn-toolbar">
                    <a href="clases.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                    <?php if ($clase['estado'] === 'programada'): ?>
                        <button class="btn btn-danger" onclick="eliminarClase(<?php echo $clase['id_clase']; ?>)">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
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

            <div class="row">
                <!-- Información de la clase -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?php echo escape($clase['titulo']); ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if ($clase['descripcion']): ?>
                                <p class="text-muted"><?php echo nl2br(escape($clase['descripcion'])); ?></p>
                                <hr>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h6><i class="bi bi-calendar3"></i> Fecha y Hora</h6>
                                    <p><strong><?php echo format_date($clase['fecha']); ?></strong></p>
                                    <p><?php echo substr($clase['hora_inicio'], 0, 5); ?> - <?php echo substr($clase['hora_fin'], 0, 5); ?></p>
                                    <p>Duración: <?php echo $clase['duracion']; ?> minutos</p>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <h6><i class="bi bi-info-circle"></i> Detalles</h6>
                                    <p><strong>Tipo:</strong> 
                                        <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                            <?php echo ucfirst($clase['tipo']); ?>
                                        </span>
                                    </p>
                                    <p><strong>Nivel:</strong> <?php echo ucfirst($clase['nivel']); ?></p>
                                    <p><strong>Ubicación:</strong> <?php echo escape($clase['ubicacion']); ?></p>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <h6><i class="bi bi-people"></i> Plazas</h6>
                                    <p>Disponibles: <strong><?php echo $clase['plazas_disponibles']; ?></strong> / <?php echo $clase['plazas_totales']; ?></p>
                                    <div class="progress">
                                        <?php 
                                        $ocupadas = $clase['plazas_totales'] - $clase['plazas_disponibles'];
                                        $porcentaje = ($ocupadas / $clase['plazas_totales']) * 100;
                                        ?>
                                        <div class="progress-bar" style="width: <?php echo $porcentaje; ?>%">
                                            <?php echo $ocupadas; ?> reservada(s)
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <h6><i class="bi bi-cash"></i> Precio</h6>
                                    <h3 class="text-success">€<?php echo number_format($clase['precio'], 2); ?></h3>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Profesor:</strong> <?php echo escape($clase['profesor_nombre'] . ' ' . $clase['profesor_apellidos']); ?></p>
                                    <p><strong>Estado:</strong> 
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
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Creada:</strong> <?php echo format_datetime($clase['creada_en']); ?></p>
                                    <p><strong>Actualizada:</strong> <?php echo format_datetime($clase['actualizada_en']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Resumen</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Reservas:</span>
                                <strong><?php echo count($reservas); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Confirmadas:</span>
                                <strong class="text-success">
                                    <?php echo count(array_filter($reservas, fn($r) => $r['estado'] === 'confirmada')); ?>
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Canceladas:</span>
                                <strong class="text-danger">
                                    <?php echo count(array_filter($reservas, fn($r) => $r['estado'] === 'cancelada')); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de reservas -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Alumnos Inscritos</h5>
                </div>
                <div class="card-body">
                    <?php if (count($reservas) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Alumno</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Fecha Reserva</th>
                                        <th>Estado</th>
                                        <th>Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservas as $reserva): ?>
                                        <tr>
                                            <td><?php echo escape($reserva['nombre'] . ' ' . $reserva['apellidos']); ?></td>
                                            <td><?php echo escape($reserva['email']); ?></td>
                                            <td><?php echo escape($reserva['telefono']); ?></td>
                                            <td><?php echo format_datetime($reserva['fecha_reserva']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $reserva['estado'] === 'confirmada' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($reserva['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($reserva['asistencia']): ?>
                                                    <span class="badge bg-<?php echo $reserva['asistencia'] === 'presente' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($reserva['asistencia']); ?>
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
                        <p class="text-muted">No hay reservas para esta clase.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
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
            <form method="POST" action="editar_clase.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_clase" value="<?php echo $clase['id_clase']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="titulo" value="<?php echo escape($clase['titulo']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"><?php echo escape($clase['descripcion']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo *</label>
                            <select class="form-select" name="tipo" required>
                                <option value="individual" <?php echo $clase['tipo'] === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                <option value="grupal" <?php echo $clase['tipo'] === 'grupal' ? 'selected' : ''; ?>>Grupal</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nivel *</label>
                            <select class="form-select" name="nivel" required>
                                <option value="basico" <?php echo $clase['nivel'] === 'basico' ? 'selected' : ''; ?>>Básico</option>
                                <option value="intermedio" <?php echo $clase['nivel'] === 'intermedio' ? 'selected' : ''; ?>>Intermedio</option>
                                <option value="avanzado" <?php echo $clase['nivel'] === 'avanzado' ? 'selected' : ''; ?>>Avanzado</option>
                                <option value="conversacion" <?php echo $clase['nivel'] === 'conversacion' ? 'selected' : ''; ?>>Conversación</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Plazas Totales *</label>
                            <input type="number" class="form-control" name="plazas_totales" min="1" value="<?php echo $clase['plazas_totales']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" value="<?php echo $clase['fecha']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hora Inicio *</label>
                            <input type="time" class="form-control" name="hora_inicio" value="<?php echo substr($clase['hora_inicio'], 0, 5); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duración (min) *</label>
                            <input type="number" class="form-control" name="duracion" min="15" step="15" value="<?php echo $clase['duracion']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio (€) *</label>
                            <input type="number" class="form-control" name="precio" min="0" step="0.01" value="<?php echo $clase['precio']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ubicación</label>
                            <input type="text" class="form-control" name="ubicacion" value="<?php echo escape($clase['ubicacion']); ?>">
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
function eliminarClase(id) {
    if (confirm('¿Estás seguro de que deseas eliminar esta clase?\n\nEsta acción NO se puede deshacer.')) {
        window.location.href = `eliminar_clase.php?id=${id}`;
    }
}
</script>

<?php include '../includes/footer.php'; ?>