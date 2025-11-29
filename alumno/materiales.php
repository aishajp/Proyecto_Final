<?php
// ============================================
// alumno/materiales.php
// Visualización y descarga de materiales
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Mis Materiales';

// Filtros
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$nivel_filtro = isset($_GET['nivel']) ? $_GET['nivel'] : 'todos';
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

// Construir consulta
$sql = "
    SELECT m.*, ma.id_asignacion, ma.fecha_asignacion, ma.fecha_limite, 
           ma.completado, ma.fecha_completado,
           u.nombre as asignado_por_nombre
    FROM materiales m
    INNER JOIN material_asignado ma ON m.id_material = ma.id_material
    LEFT JOIN usuarios u ON ma.asignado_por = u.id_usuario
    WHERE ma.id_alumno = ? AND m.activo = 1
";

$params = [$user_id];

if ($tipo_filtro !== 'todos') {
    $sql .= " AND m.tipo = ?";
    $params[] = $tipo_filtro;
}

if ($nivel_filtro !== 'todos') {
    $sql .= " AND m.nivel = ?";
    $params[] = $nivel_filtro;
}

if ($estado_filtro === 'completado') {
    $sql .= " AND ma.completado = 1";
} elseif ($estado_filtro === 'pendiente') {
    $sql .= " AND ma.completado = 0";
}

$sql .= " ORDER BY ma.fecha_asignacion DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$materiales = $stmt->fetchAll();

// Estadísticas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN completado = 1 THEN 1 ELSE 0 END) as completados,
        SUM(CASE WHEN completado = 0 THEN 1 ELSE 0 END) as pendientes
    FROM material_asignado
    WHERE id_alumno = ?
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
                <h1 class="h2">Mis Materiales</h1>
            </div>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Materiales</h6>
                            <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Completados</h6>
                            <h2 class="mb-0"><?php echo $stats['completados']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h6 class="card-title">Pendientes</h6>
                            <h2 class="mb-0"><?php echo $stats['pendientes']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" onchange="this.form.submit()">
                                <option value="todos" <?php echo $tipo_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="pdf" <?php echo $tipo_filtro === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                                <option value="video" <?php echo $tipo_filtro === 'video' ? 'selected' : ''; ?>>Video</option>
                                <option value="audio" <?php echo $tipo_filtro === 'audio' ? 'selected' : ''; ?>>Audio</option>
                                <option value="documento" <?php echo $tipo_filtro === 'documento' ? 'selected' : ''; ?>>Documento</option>
                                <option value="enlace" <?php echo $tipo_filtro === 'enlace' ? 'selected' : ''; ?>>Enlace</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nivel</label>
                            <select name="nivel" class="form-select" onchange="this.form.submit()">
                                <option value="todos" <?php echo $nivel_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="basico" <?php echo $nivel_filtro === 'basico' ? 'selected' : ''; ?>>Básico</option>
                                <option value="intermedio" <?php echo $nivel_filtro === 'intermedio' ? 'selected' : ''; ?>>Intermedio</option>
                                <option value="avanzado" <?php echo $nivel_filtro === 'avanzado' ? 'selected' : ''; ?>>Avanzado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select" onchange="this.form.submit()">
                                <option value="todos" <?php echo $estado_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="pendiente" <?php echo $estado_filtro === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="completado" <?php echo $estado_filtro === 'completado' ? 'selected' : ''; ?>>Completado</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <a href="materiales.php" class="btn btn-secondary">Limpiar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de materiales -->
            <?php if (count($materiales) > 0): ?>
                <div class="row">
                    <?php foreach ($materiales as $material): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title"><?php echo escape($material['titulo']); ?></h5>
                                        <?php if ($material['completado']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Completado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pendiente</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <span class="badge bg-primary"><?php echo ucfirst($material['tipo']); ?></span>
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
                                        <i class="bi bi-calendar"></i> Asignado: <?php echo format_date($material['fecha_asignacion']); ?>
                                    </div>
                                    
                                    <?php if ($material['fecha_limite']): ?>
                                        <div class="small text-danger mb-2">
                                            <i class="bi bi-alarm"></i> Límite: <?php echo format_date($material['fecha_limite']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($material['completado'] && $material['fecha_completado']): ?>
                                        <div class="small text-success mb-2">
                                            <i class="bi bi-check2-circle"></i> Completado: <?php echo format_date($material['fecha_completado']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-white">
                                    <div class="d-flex justify-content-between">
                                        <?php if ($material['tipo'] === 'enlace'): ?>
                                            <a href="<?php echo escape($material['url']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="bi bi-link-45deg"></i> Abrir
                                            </a>
                                        <?php else: ?>
                                            <a href="descargar_material.php?id=<?php echo $material['id_material']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-download"></i> Descargar
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!$material['completado']): ?>
                                            <button class="btn btn-sm btn-success btn-marcar-completado" data-id="<?php echo $material['id_asignacion']; ?>">
                                                <i class="bi bi-check-circle"></i> Marcar como completado
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No tienes materiales asignados con los filtros seleccionados.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-marcar-completado').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const idAsignacion = this.getAttribute('data-id');
            const button = this;
            
            if (confirm('¿Marcar este material como completado?')) {
                fetch('marcar_material_completado.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id_asignacion: idAsignacion })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al marcar como completado');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
