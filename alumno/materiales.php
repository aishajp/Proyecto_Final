<?php
// alumno/materiales.php - TODOS LOS MATERIALES DISPONIBLES
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Materiales';

// Filtros (solo nivel)
$nivel_filtro = isset($_GET['nivel']) ? $_GET['nivel'] : 'todos';

// Construir consulta - TODOS los materiales activos
$sql = "
    SELECT m.*
    FROM materiales m
    WHERE m.activo = 1
";

$params = [];

if ($nivel_filtro !== 'todos') {
    $sql .= " AND m.nivel = ?";
    $params[] = $nivel_filtro;
}

$sql .= " ORDER BY m.fecha_subida DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$materiales = $stmt->fetchAll();

// Estadísticas
$stmt = $db->query("SELECT COUNT(*) as total FROM materiales WHERE activo = 1");
$total_materiales = $stmt->fetch()['total'];

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_alumno.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Materiales de Estudio</h1>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-10">
                            <label class="form-label">Filtrar por Nivel</label>
                            <select name="nivel" class="form-select" onchange="this.form.submit()">
                                <option value="todos" <?php echo $nivel_filtro === 'todos' ? 'selected' : ''; ?>>Todos los Niveles</option>
                                <option value="basico" <?php echo $nivel_filtro === 'basico' ? 'selected' : ''; ?>>Básico</option>
                                <option value="intermedio" <?php echo $nivel_filtro === 'intermedio' ? 'selected' : ''; ?>>Intermedio</option>
                                <option value="avanzado" <?php echo $nivel_filtro === 'avanzado' ? 'selected' : ''; ?>>Avanzado</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="materiales.php" class="btn btn-secondary w-100">Limpiar</a>
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
                                    
                                    <div class="small text-success mb-2">
                                        <i class="bi bi-check-circle"></i> Disponible para descargar
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <a href="descargar_material.php?id=<?php echo $material['id_material']; ?>" class="btn btn-primary w-100">
                                        <i class="bi bi-download"></i> Descargar Material
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No hay materiales disponibles con los filtros seleccionados.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>