<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$page_title = 'Gestión de Tutorías';

// Filtro de estado
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

// Obtener tutorías
$sql = "
    SELECT t.*, 
           up.nombre as padre_nombre, up.apellidos as padre_apellidos, up.email as padre_email, up.telefono as padre_telefono,
           ua.nombre as alumno_nombre, ua.apellidos as alumno_apellidos
    FROM tutorias t
    INNER JOIN usuarios up ON t.id_padre = up.id_usuario
    INNER JOIN usuarios ua ON t.id_alumno = ua.id_usuario
    WHERE 1=1
";

$params = [];

if ($estado_filtro !== 'todos') {
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
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
        SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas
    FROM tutorias
");
$stats = $stmt->fetch();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestión de Tutorías</h1>
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
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3><?php echo $stats['total']; ?></h3>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-warning text-dark">
                        <div class="card-body">
                            <h3><?php echo $stats['pendientes']; ?></h3>
                            <small>Pendientes</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-info text-white">
                        <div class="card-body">
                            <h3><?php echo $stats['confirmadas']; ?></h3>
                            <small>Confirmadas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-success text-white">
                        <div class="card-body">
                            <h3><?php echo $stats['completadas']; ?></h3>
                            <small>Completadas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-danger text-white">
                        <div class="card-body">
                            <h3><?php echo $stats['rechazadas']; ?></h3>
                            <small>Rechazadas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center bg-secondary text-white">
                        <div class="card-body">
                            <h3><?php echo $stats['canceladas']; ?></h3>
                            <small>Canceladas</small>
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
                                <option value="todos" <?php echo $estado_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="pendiente" <?php echo $estado_filtro === 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                                <option value="confirmada" <?php echo $estado_filtro === 'confirmada' ? 'selected' : ''; ?>>Confirmadas</option>
                                <option value="completada" <?php echo $estado_filtro === 'completada' ? 'selected' : ''; ?>>Completadas</option>
                                <option value="rechazada" <?php echo $estado_filtro === 'rechazada' ? 'selected' : ''; ?>>Rechazadas</option>
                                <option value="cancelada" <?php echo $estado_filtro === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="tutorias.php" class="btn btn-secondary w-100">Limpiar</a>
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
                                        <strong><?php echo escape($tutoria['padre_nombre'] . ' ' . $tutoria['padre_apellidos']); ?></strong>
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
                                        <strong>Sobre el alumno:</strong> 
                                        <?php echo escape($tutoria['alumno_nombre'] . ' ' . $tutoria['alumno_apellidos']); ?>
                                    </p>
                                    
                                    <p class="mb-2">
                                        <strong>Motivo:</strong><br>
                                        <?php echo nl2br(escape($tutoria['motivo'])); ?>
                                    </p>
                                    
                                    <hr>
                                    
                                    <?php if ($tutoria['estado'] === 'pendiente'): ?>
                                        <p class="mb-2">
                                            <i class="bi bi-calendar-event text-muted"></i> 
                                            <strong>Fecha solicitada:</strong> 
                                            <?php echo format_date($tutoria['fecha_solicitada']); ?> - 
                                            <?php echo substr($tutoria['hora_solicitada'], 0, 5); ?>
                                        </p>
                                    <?php elseif ($tutoria['estado'] === 'confirmada'): ?>
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
                                    
                                    <p class="mb-2 small text-muted">
                                        <i class="bi bi-telephone"></i> <?php echo escape($tutoria['padre_telefono']); ?> | 
                                        <i class="bi bi-envelope"></i> <?php echo escape($tutoria['padre_email']); ?>
                                    </p>
                                    
                                    <?php if ($tutoria['notas']): ?>
                                        <div class="alert alert-info small mt-2 mb-2">
                                            <strong>Notas:</strong> <?php echo nl2br(escape($tutoria['notas'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-white">
                                    <div class="btn-group w-100" role="group">
                                        <?php if ($tutoria['estado'] === 'pendiente'): ?>
                                            <button class="btn btn-sm btn-success btn-confirmar-tutoria" 
                                                    data-id="<?php echo $tutoria['id_tutoria']; ?>"
                                                    data-fecha="<?php echo $tutoria['fecha_solicitada']; ?>"
                                                    data-hora="<?php echo substr($tutoria['hora_solicitada'], 0, 5); ?>">
                                                <i class="bi bi-check-circle"></i> Confirmar
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-rechazar-tutoria" 
                                                    data-id="<?php echo $tutoria['id_tutoria']; ?>">
                                                <i class="bi bi-x-circle"></i> Rechazar
                                            </button>
                                        <?php elseif ($tutoria['estado'] === 'confirmada'): ?>
                                            <button class="btn btn-sm btn-success btn-completar-tutoria" 
                                                    data-id="<?php echo $tutoria['id_tutoria']; ?>">
                                                <i class="bi bi-check-circle-fill"></i> Marcar Completada
                                            </button>
                                            <button class="btn btn-sm btn-warning btn-cancelar-tutoria" 
                                                    data-id="<?php echo $tutoria['id_tutoria']; ?>">
                                                <i class="bi bi-x-circle"></i> Cancelar
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-info btn-ver-tutoria" 
                                                data-tutoria='<?php echo json_encode($tutoria); ?>'>
                                            <i class="bi bi-eye"></i> Ver
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No hay tutorías con los filtros seleccionados.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal Confirmar Tutoría -->
<div class="modal fade" id="modalConfirmarTutoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Confirmar Tutoría</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="confirmar_tutoria.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_tutoria" id="confirmar_id_tutoria">
                    
                    <div class="alert alert-info">
                        Puedes confirmar la fecha y hora solicitada o proponer una alternativa
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha *</label>
                        <input type="date" class="form-control" name="fecha_confirmada" id="confirmar_fecha" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hora *</label>
                        <input type="time" class="form-control" name="hora_confirmada" id="confirmar_hora" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Duración (minutos)</label>
                        <input type="number" class="form-control" name="duracion" value="30" min="15" step="15">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>
                        <input type="text" class="form-control" name="ubicacion" value="Online">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notas adicionales (opcional)</label>
                        <textarea class="form-control" name="notas" rows="3" placeholder="Información adicional para el padre..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar Tutoría</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Rechazar Tutoría -->
<div class="modal fade" id="modalRechazarTutoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Rechazar Tutoría</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="rechazar_tutoria.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_tutoria" id="rechazar_id_tutoria">
                    
                    <div class="alert alert-warning">
                        <strong>Nota:</strong> El padre recibirá una notificación del rechazo
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo del rechazo *</label>
                        <textarea class="form-control" name="motivo_rechazo" rows="4" 
                                  placeholder="Explica por qué rechazas esta solicitud..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rechazar Tutoría</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Completar Tutoría -->
<div class="modal fade" id="modalCompletarTutoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Marcar Tutoría como Completada</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="completar_tutoria.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_tutoria" id="completar_id_tutoria">
                    
                    <div class="mb-3">
                        <label class="form-label">Resumen de la tutoría (opcional)</label>
                        <textarea class="form-control" name="notas" rows="4" 
                                  placeholder="Puntos tratados, acuerdos, próximos pasos..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Marcar como Completada</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cancelar Tutoría -->
<div class="modal fade" id="modalCancelarTutoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Cancelar Tutoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="cancelar_tutoria.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_tutoria" id="cancelar_id_tutoria">
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo de la cancelación *</label>
                        <textarea class="form-control" name="motivo_cancelacion" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-warning">Cancelar Tutoría</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Tutoría -->
<div class="modal fade" id="modalVerTutoria" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de la Tutoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoVerTutoria">
                <!-- Se llenará con JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Confirmar tutoría
    document.querySelectorAll('.btn-confirmar-tutoria').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const fecha = this.getAttribute('data-fecha');
            const hora = this.getAttribute('data-hora');
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmarTutoria'));
            
            document.getElementById('confirmar_id_tutoria').value = id;
            document.getElementById('confirmar_fecha').value = fecha;
            document.getElementById('confirmar_hora').value = hora;
            
            modal.show();
        });
    });
    
    // Rechazar tutoría
    document.querySelectorAll('.btn-rechazar-tutoria').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const modal = new bootstrap.Modal(document.getElementById('modalRechazarTutoria'));
            
            document.getElementById('rechazar_id_tutoria').value = id;
            modal.show();
        });
    });
    
    // Completar tutoría
    document.querySelectorAll('.btn-completar-tutoria').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const modal = new bootstrap.Modal(document.getElementById('modalCompletarTutoria'));
            
            document.getElementById('completar_id_tutoria').value = id;
            modal.show();
        });
    });
    
    // Cancelar tutoría
    document.querySelectorAll('.btn-cancelar-tutoria').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const modal = new bootstrap.Modal(document.getElementById('modalCancelarTutoria'));
            
            document.getElementById('cancelar_id_tutoria').value = id;
            modal.show();
        });
    });
    
    // Ver detalles
    document.querySelectorAll('.btn-ver-tutoria').forEach(btn => {
        btn.addEventListener('click', function() {
            const tutoria = JSON.parse(this.getAttribute('data-tutoria'));
            const modal = new bootstrap.Modal(document.getElementById('modalVerTutoria'));
            
            const html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información del Padre</h6>
                        <p><strong>Nombre:</strong> ${tutoria.padre_nombre} ${tutoria.padre_apellidos}</p>
                        <p><strong>Email:</strong> ${tutoria.padre_email}</p>
                        <p><strong>Teléfono:</strong> ${tutoria.padre_telefono}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Información del Alumno</h6>
                        <p><strong>Nombre:</strong> ${tutoria.alumno_nombre} ${tutoria.alumno_apellidos}</p>
                    </div>
                </div>
                <hr>
                <h6>Motivo</h6>
                <p>${tutoria.motivo.replace(/\n/g, '<br>')}</p>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Fecha Solicitada</h6>
                        <p>${tutoria.fecha_solicitada} - ${tutoria.hora_solicitada.substring(0, 5)}</p>
                    </div>
                    ${tutoria.fecha_confirmada ? `
                    <div class="col-md-6">
                        <h6>Fecha Confirmada</h6>
                        <p>${tutoria.fecha_confirmada} - ${tutoria.hora_confirmada.substring(0, 5)}</p>
                    </div>
                    ` : ''}
                </div>
                ${tutoria.notas ? `
                <hr>
                <h6>Notas</h6>
                <p>${tutoria.notas.replace(/\n/g, '<br>')}</p>
                ` : ''}
                <hr>
                <p><strong>Estado:</strong> <span class="badge bg-info">${tutoria.estado}</span></p>
                <p><small class="text-muted">Solicitada: ${tutoria.fecha_solicitud}</small></p>
            `;
            
            document.getElementById('contenidoVerTutoria').innerHTML = html;
            modal.show();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>