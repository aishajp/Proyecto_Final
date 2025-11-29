<?php
// administrador/materiales.php - SIMPLIFICADO
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$page_title = 'Gestión de Materiales';

// Obtener todos los materiales
$stmt = $db->prepare("
    SELECT m.*, u.nombre as subido_por_nombre
    FROM materiales m
    LEFT JOIN usuarios u ON m.subido_por = u.id_usuario
    ORDER BY m.fecha_subida DESC
");
$stmt->execute();
$materiales = $stmt->fetchAll();

// Obtener alumnos para asignación
$stmt = $db->prepare("SELECT id_usuario, nombre, apellidos FROM usuarios WHERE rol = 'alumno' AND activo = 1 ORDER BY nombre");
$stmt->execute();
$alumnos = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestión de Materiales</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubirMaterial">
                    <i class="bi bi-upload"></i> Subir Material
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

            <!-- Grid de materiales -->
            <div class="row">
                <?php foreach ($materiales as $material): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title"><?php echo escape($material['titulo']); ?></h5>
                                    <span class="badge bg-primary">PDF</span>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge bg-secondary"><?php echo ucfirst($material['nivel']); ?></span>
                                    <?php if ($material['categoria']): ?>
                                        <span class="badge bg-info"><?php echo escape($material['categoria']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($material['descripcion']): ?>
                                    <p class="card-text text-muted small"><?php echo escape(substr($material['descripcion'], 0, 100)); ?>...</p>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-calendar"></i> Subido: <?php echo format_date($material['fecha_subida']); ?>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-download"></i> Descargas: <?php echo $material['descargas']; ?>
                                </div>
                                
                                <?php
                                $stmt_count = $db->prepare("SELECT COUNT(*) as total FROM material_asignado WHERE id_material = ?");
                                $stmt_count->execute([$material['id_material']]);
                                $asignaciones = $stmt_count->fetch()['total'];
                                ?>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-people"></i> Asignado a: <?php echo $asignaciones; ?> alumno(s)
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <button class="btn btn-sm btn-success btn-asignar w-100" data-id="<?php echo $material['id_material']; ?>">
                                    <i class="bi bi-person-plus"></i> Asignar a Alumnos
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modal Subir Material -->
<div class="modal fade" id="modalSubirMaterial" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subir Nuevo Material (PDF)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="procesar_material.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" name="titulo" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3" maxlength="500"></textarea>
                        <small class="text-muted">Opcional - Máximo 500 caracteres</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nivel *</label>
                            <select class="form-select" name="nivel" required>
                                <option value="">Seleccione...</option>
                                <option value="basico">Básico</option>
                                <option value="intermedio">Intermedio</option>
                                <option value="avanzado">Avanzado</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoría</label>
                            <input type="text" class="form-control" name="categoria" placeholder="Ej: Gramática, Vocabulario..." maxlength="50">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Archivo PDF *</label>
                        <input type="file" class="form-control" name="archivo" accept=".pdf" required>
                        <small class="text-muted">Solo archivos PDF</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Importante:</strong> Una vez subido el material, podrás asignarlo a los alumnos que necesites.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Subir Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Asignar Material -->
<div class="modal fade" id="modalAsignar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignar Material a Alumnos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="asignar_material.php">
                <div class="modal-body">
                    <input type="hidden" name="id_material" id="id_material_asignar">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Alumnos *</label>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px;">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="select_all">
                                <label class="form-check-label" for="select_all">
                                    <strong>Seleccionar Todos</strong>
                                </label>
                            </div>
                            <hr>
                            <?php foreach ($alumnos as $alumno): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input alumno-check" type="checkbox" name="alumnos[]" 
                                           value="<?php echo $alumno['id_usuario']; ?>" 
                                           id="alumno_<?php echo $alumno['id_usuario']; ?>">
                                    <label class="form-check-label" for="alumno_<?php echo $alumno['id_usuario']; ?>">
                                        <?php echo escape($alumno['nombre'] . ' ' . $alumno['apellidos']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Asignar Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal asignar
    const modalAsignar = new bootstrap.Modal(document.getElementById('modalAsignar'));
    document.querySelectorAll('.btn-asignar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('id_material_asignar').value = this.getAttribute('data-id');
            // Limpiar checkboxes
            document.querySelectorAll('.alumno-check').forEach(cb => cb.checked = false);
            document.getElementById('select_all').checked = false;
            modalAsignar.show();
        });
    });
    
    // Select all
    document.getElementById('select_all').addEventListener('change', function() {
        document.querySelectorAll('.alumno-check').forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }.bind(this));
    });
});
</script>

<?php include '../includes/footer.php'; ?>