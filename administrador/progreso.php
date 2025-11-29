<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$page_title = 'Gestión de Progreso';

// Obtener todos los alumnos con su progreso
$stmt = $db->prepare("
    SELECT u.*, p.*
    FROM usuarios u
    LEFT JOIN progreso_alumno p ON u.id_usuario = p.id_alumno
    WHERE u.rol = 'alumno' AND u.activo = 1
    ORDER BY u.nombre, u.apellidos
");
$stmt->execute();
$alumnos = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestión de Progreso de Alumnos</h1>
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

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Progreso de Alumnos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Nivel</th>
                                    <th>Speaking</th>
                                    <th>Listening</th>
                                    <th>Reading</th>
                                    <th>Writing</th>
                                    <th>Grammar</th>
                                    <th>Vocabulary</th>
                                    <th>Promedio</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alumnos as $alumno): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo escape($alumno['nombre'] . ' ' . $alumno['apellidos']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($alumno['nivel_actual']): ?>
                                                <span class="badge bg-primary"><?php echo $alumno['nivel_actual']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Sin evaluar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $alumno['speaking'] ? number_format($alumno['speaking'], 1) : '-'; ?></td>
                                        <td><?php echo $alumno['listening'] ? number_format($alumno['listening'], 1) : '-'; ?></td>
                                        <td><?php echo $alumno['reading'] ? number_format($alumno['reading'], 1) : '-'; ?></td>
                                        <td><?php echo $alumno['writing'] ? number_format($alumno['writing'], 1) : '-'; ?></td>
                                        <td><?php echo $alumno['grammar'] ? number_format($alumno['grammar'], 1) : '-'; ?></td>
                                        <td><?php echo $alumno['vocabulary'] ? number_format($alumno['vocabulary'], 1) : '-'; ?></td>
                                        <td>
                                            <?php if ($alumno['promedio_general']): ?>
                                                <strong><?php echo number_format($alumno['promedio_general'], 1); ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary btn-actualizar-progreso" 
                                                    data-id="<?php echo $alumno['id_usuario']; ?>"
                                                    data-nombre="<?php echo escape($alumno['nombre'] . ' ' . $alumno['apellidos']); ?>">
                                                <i class="bi bi-pencil"></i> Actualizar
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

<!-- Modal Actualizar Progreso -->
<div class="modal fade" id="modalProgreso" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProgresoTitle">Actualizar Progreso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="actualizar_progreso.php">
                <div class="modal-body">
                    <input type="hidden" name="id_alumno" id="id_alumno_progreso">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Nivel Actual *</label>
                        <select class="form-select" name="nivel_actual" required>
                            <option value="">Seleccione...</option>
                            <option value="A1">A1 - Principiante</option>
                            <option value="A2">A2 - Elemental</option>
                            <option value="B1">B1 - Intermedio</option>
                            <option value="B2">B2 - Intermedio Alto</option>
                            <option value="C1">C1 - Avanzado</option>
                            <option value="C2">C2 - Maestría</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Speaking (0-10)</label>
                            <input type="number" class="form-control" name="speaking" min="0" max="10" step="0.1" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Listening (0-10)</label>
                            <input type="number" class="form-control" name="listening" min="0" max="10" step="0.1" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reading (0-10)</label>
                            <input type="number" class="form-control" name="reading" min="0" max="10" step="0.1" value="0">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Writing (0-10)</label>
                            <input type="number" class="form-control" name="writing" min="0" max="10" step="0.1" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Grammar (0-10)</label>
                            <input type="number" class="form-control" name="grammar" min="0" max="10" step="0.1" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Vocabulary (0-10)</label>
                            <input type="number" class="form-control" name="vocabulary" min="0" max="10" step="0.1" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3" placeholder="Comentarios sobre el progreso del alumno..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Progreso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalProgreso = new bootstrap.Modal(document.getElementById('modalProgreso'));
    
    document.querySelectorAll('.btn-actualizar-progreso').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const alumnoId = this.getAttribute('data-id');
            const alumnoNombre = this.getAttribute('data-nombre');
            
            document.getElementById('id_alumno_progreso').value = alumnoId;
            document.getElementById('modalProgresoTitle').textContent = 'Actualizar Progreso - ' + alumnoNombre;
            
            // Cargar datos actuales del alumno
            fetch(`get_progreso.php?id=${alumnoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.progreso) {
                        const form = document.querySelector('#modalProgreso form');
                        form.querySelector('[name="nivel_actual"]').value = data.progreso.nivel_actual || '';
                        form.querySelector('[name="speaking"]').value = data.progreso.speaking || 0;
                        form.querySelector('[name="listening"]').value = data.progreso.listening || 0;
                        form.querySelector('[name="reading"]').value = data.progreso.reading || 0;
                        form.querySelector('[name="writing"]').value = data.progreso.writing || 0;
                        form.querySelector('[name="grammar"]').value = data.progreso.grammar || 0;
                        form.querySelector('[name="vocabulary"]').value = data.progreso.vocabulary || 0;
                        form.querySelector('[name="observaciones"]').value = data.progreso.observaciones || '';
                    }
                });
            
            modalProgreso.show();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>