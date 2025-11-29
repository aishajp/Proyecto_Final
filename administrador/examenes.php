<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$page_title = 'Gestión de Exámenes';

// Obtener todos los exámenes
$stmt = $db->prepare("
    SELECT e.*, 
           COUNT(DISTINCT r.id_resultado) as total_resultados,
           AVG(r.puntuacion) as promedio_puntuacion
    FROM examenes e
    LEFT JOIN resultados_examenes r ON e.id_examen = r.id_examen
    GROUP BY e.id_examen
    ORDER BY e.fecha DESC, e.hora_inicio DESC
");
$stmt->execute();
$examenes = $stmt->fetchAll();

// Obtener alumnos para registro de calificaciones
$stmt = $db->query("SELECT id_usuario, nombre, apellidos FROM usuarios WHERE rol = 'alumno' AND activo = 1 ORDER BY nombre");
$alumnos = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestión de Exámenes</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoExamen">
                    <i class="bi bi-plus-circle"></i> Nuevo Examen
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

            <!-- Tabla de exámenes -->
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
                                    <th>Duración</th>
                                    <th>Resultados</th>
                                    <th>Promedio</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examenes as $examen): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo escape($examen['titulo']); ?></strong>
                                            <?php if (strtotime($examen['fecha']) > time()): ?>
                                                <span class="badge bg-info ms-2">Próximo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($examen['tipo']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst($examen['nivel']); ?></td>
                                        <td><?php echo format_date($examen['fecha']); ?></td>
                                        <td><?php echo substr($examen['hora_inicio'], 0, 5); ?></td>
                                        <td><?php echo $examen['duracion']; ?> min</td>
                                        <td><?php echo $examen['total_resultados']; ?></td>
                                        <td>
                                            <?php if ($examen['total_resultados'] > 0): ?>
                                                <strong><?php echo number_format($examen['promedio_puntuacion'], 2); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success btn-registrar-nota" 
                                                    data-id="<?php echo $examen['id_examen']; ?>"
                                                    data-titulo="<?php echo escape($examen['titulo']); ?>"
                                                    data-max="<?php echo $examen['puntuacion_maxima']; ?>"
                                                    title="Registrar Calificaciones">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info btn-ver-resultados" 
                                                    data-id="<?php echo $examen['id_examen']; ?>"
                                                    data-titulo="<?php echo escape($examen['titulo']); ?>"
                                                    title="Ver Resultados">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning btn-editar-examen" 
                                                    data-examen='<?php echo json_encode($examen); ?>'
                                                    title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-eliminar-examen" 
                                                    data-id="<?php echo $examen['id_examen']; ?>"
                                                    title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

<!-- Modal Nuevo Examen -->
<div class="modal fade" id="modalNuevoExamen" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Nuevo Examen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="guardar_examen.php">
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
                            <select class="form-select" name="tipo" required>
                                <option value="">Seleccione...</option>
                                <option value="oral">Oral</option>
                                <option value="escrito">Escrito</option>
                                <option value="listening">Listening</option>
                                <option value="completo">Completo</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nivel *</label>
                            <select class="form-select" name="nivel" required>
                                <option value="">Seleccione...</option>
                                <option value="basico">Básico</option>
                                <option value="intermedio">Intermedio</option>
                                <option value="avanzado">Avanzado</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Puntuación Máxima *</label>
                            <input type="number" class="form-control" name="puntuacion_maxima" min="1" step="0.01" value="100" required>
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
                    
                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>
                        <input type="text" class="form-control" name="ubicacion" value="Online">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Examen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Examen -->
<div class="modal fade" id="modalEditarExamen" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Examen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="editar_examen.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_examen" id="edit_id_examen">
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="titulo" id="edit_titulo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo *</label>
                            <select class="form-select" name="tipo" id="edit_tipo" required>
                                <option value="oral">Oral</option>
                                <option value="escrito">Escrito</option>
                                <option value="listening">Listening</option>
                                <option value="completo">Completo</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nivel *</label>
                            <select class="form-select" name="nivel" id="edit_nivel" required>
                                <option value="basico">Básico</option>
                                <option value="intermedio">Intermedio</option>
                                <option value="avanzado">Avanzado</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Puntuación Máxima *</label>
                            <input type="number" class="form-control" name="puntuacion_maxima" id="edit_puntuacion_maxima" min="1" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" id="edit_fecha" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hora Inicio *</label>
                            <input type="time" class="form-control" name="hora_inicio" id="edit_hora_inicio" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duración (min) *</label>
                            <input type="number" class="form-control" name="duracion" id="edit_duracion" min="15" step="15" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>
                        <input type="text" class="form-control" name="ubicacion" id="edit_ubicacion">
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

<!-- Modal Registrar Calificaciones -->
<div class="modal fade" id="modalRegistrarNota" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloRegistrarNota">Registrar Calificaciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="registrar_notas.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_examen" id="nota_id_examen">
                    <input type="hidden" name="puntuacion_maxima" id="nota_puntuacion_maxima">
                    
                    <div class="alert alert-info">
                        <strong>Instrucción:</strong> Introduce las calificaciones para cada alumno. 
                        Deja en blanco los alumnos que no presentaron el examen.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Puntuación</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alumnos as $alumno): ?>
                                    <tr>
                                        <td><?php echo escape($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></td>
                                        <td>
                                            <input type="number" class="form-control" 
                                                   name="puntuaciones[<?php echo $alumno['id_usuario']; ?>]" 
                                                   min="0" step="0.01" placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" 
                                                   name="observaciones[<?php echo $alumno['id_usuario']; ?>]" 
                                                   placeholder="Opcional">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar Notas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Resultados -->
<div class="modal fade" id="modalVerResultados" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloVerResultados">Resultados del Examen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoResultados">
                <!-- Se llenará con AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Editar examen
    document.querySelectorAll('.btn-editar-examen').forEach(btn => {
        btn.addEventListener('click', function() {
            const examen = JSON.parse(this.getAttribute('data-examen'));
            const modal = new bootstrap.Modal(document.getElementById('modalEditarExamen'));
            
            document.getElementById('edit_id_examen').value = examen.id_examen;
            document.getElementById('edit_titulo').value = examen.titulo;
            document.getElementById('edit_descripcion').value = examen.descripcion || '';
            document.getElementById('edit_tipo').value = examen.tipo;
            document.getElementById('edit_nivel').value = examen.nivel;
            document.getElementById('edit_puntuacion_maxima').value = examen.puntuacion_maxima;
            document.getElementById('edit_fecha').value = examen.fecha;
            document.getElementById('edit_hora_inicio').value = examen.hora_inicio.substring(0, 5);
            document.getElementById('edit_duracion').value = examen.duracion;
            document.getElementById('edit_ubicacion').value = examen.ubicacion || '';
            
            modal.show();
        });
    });
    
    // Registrar calificaciones
    document.querySelectorAll('.btn-registrar-nota').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const titulo = this.getAttribute('data-titulo');
            const max = this.getAttribute('data-max');
            const modal = new bootstrap.Modal(document.getElementById('modalRegistrarNota'));
            
            document.getElementById('nota_id_examen').value = id;
            document.getElementById('nota_puntuacion_maxima').value = max;
            document.getElementById('tituloRegistrarNota').textContent = `Registrar Calificaciones - ${titulo}`;
            
            // Limpiar inputs
            document.querySelectorAll('#modalRegistrarNota input[type="number"], #modalRegistrarNota input[type="text"]').forEach(input => {
                input.value = '';
            });
            
            modal.show();
        });
    });
    
    // Ver resultados
    document.querySelectorAll('.btn-ver-resultados').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const titulo = this.getAttribute('data-titulo');
            const modal = new bootstrap.Modal(document.getElementById('modalVerResultados'));
            
            document.getElementById('tituloVerResultados').textContent = `Resultados - ${titulo}`;
            document.getElementById('contenidoResultados').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
            
            // Cargar resultados con AJAX
            fetch(`ver_resultados_examen.php?id=${id}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('contenidoResultados').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-danger">Error al cargar resultados</div>';
                });
            
            modal.show();
        });
    });
    
    // Eliminar examen
    document.querySelectorAll('.btn-eliminar-examen').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            if (confirm('¿Estás seguro de que deseas eliminar este examen?\n\nSe eliminarán también todas las notas asociadas.')) {
                window.location.href = `eliminar_examen.php?id=${id}`;
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>