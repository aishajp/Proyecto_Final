<?php
// ============================================
// padre/progreso_hijo.php
// Ver progreso detallado del hijo
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_PADRE)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

$id_hijo = isset($_GET['hijo']) ? (int)$_GET['hijo'] : 0;

// Verificar que el hijo pertenece al padre
$stmt = $db->prepare("
    SELECT u.*, rph.id_relacion
    FROM usuarios u
    INNER JOIN relacion_padre_hijo rph ON u.id_usuario = rph.id_alumno
    WHERE rph.id_padre = ? AND u.id_usuario = ?
");
$stmt->execute([$user_id, $id_hijo]);
$hijo = $stmt->fetch();

if (!$hijo) {
    set_message('No tienes acceso a este alumno', MSG_ERROR);
    redirect('padre/dashboard.php');
}

// Obtener progreso
$stmt = $db->prepare("SELECT * FROM progreso_alumno WHERE id_alumno = ?");
$stmt->execute([$id_hijo]);
$progreso = $stmt->fetch();

// Obtener últimas calificaciones
$stmt = $db->prepare("
    SELECT e.titulo, e.tipo, e.puntuacion_maxima, r.puntuacion, r.fecha_realizacion
    FROM resultados_examenes r
    INNER JOIN examenes e ON r.id_examen = e.id_examen
    WHERE r.id_alumno = ?
    ORDER BY r.fecha_realizacion DESC
    LIMIT 10
");
$stmt->execute([$id_hijo]);
$calificaciones = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_padre.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Progreso de <?php echo escape($hijo['nombre']); ?></h1>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>

            <?php if ($progreso): ?>
                <!-- Mismo contenido que alumno/progreso.php pero con datos del hijo -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3>Nivel Actual</h3>
                                <h1 class="display-3"><?php echo $progreso['nivel_actual']; ?></h1>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3>Promedio General</h3>
                                <h1 class="display-3"><?php echo number_format($progreso['promedio_general'], 1); ?></h1>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resto del contenido similar a alumno/progreso.php -->
                
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Aún no hay progreso registrado para este alumno.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>