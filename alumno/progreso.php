<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener progreso actual
$stmt = $db->prepare("SELECT * FROM progreso_alumno WHERE id_alumno = ?");
$stmt->execute([$user_id]);
$progreso = $stmt->fetch();

// Obtener historial de actualizaciones (últimas 10)
$stmt = $db->prepare("
    SELECT p.*, u.nombre as profesor_nombre
    FROM progreso_alumno p
    LEFT JOIN usuarios u ON p.actualizado_por = u.id_usuario
    WHERE p.id_alumno = ?
    ORDER BY p.fecha_actualizacion DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$historial = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_alumno.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Mi Progreso Académico</h1>
            </div>

            <?php if ($progreso): ?>
                <!-- Nivel actual y promedio -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3>Nivel Actual</h3>
                                <h1 class="display-3"><?php echo $progreso['nivel_actual']; ?></h1>
                                <p>Marco Común Europeo de Referencia</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3>Promedio General</h3>
                                <h1 class="display-3"><?php echo number_format($progreso['promedio_general'], 1); ?></h1>
                                <p>Sobre 10 puntos</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Habilidades detalladas -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Habilidades Lingüísticas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Speaking -->
                            <div class="col-md-6 mb-4">
                                <h6>Speaking (Expresión Oral)</h6>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo number_format($progreso['speaking'], 1); ?> / 10</span>
                                    <span><?php echo ($progreso['speaking'] * 10); ?>%</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo ($progreso['speaking'] * 10); ?>%">
                                        <?php echo number_format($progreso['speaking'], 1); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Listening -->
                            <div class="col-md-6 mb-4">
                                <h6>Listening (Comprensión Auditiva)</h6>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo number_format($progreso['listening'], 1); ?> / 10</span>
                                    <span><?php echo ($progreso['listening'] * 10); ?>%</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo ($progreso['listening'] * 10); ?>%">
                                        <?php echo number_format($progreso['listening'], 1); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Reading -->
                            <div class="col-md-6 mb-4">
                                <h6>Reading (Comprensión Lectora)</h6>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo number_format($progreso['reading'], 1); ?> / 10</span>
                                    <span><?php echo ($progreso['reading'] * 10); ?>%</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo ($progreso['reading'] * 10); ?>%">
                                        <?php echo number_format($progreso['reading'], 1); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Writing -->
                            <div class="col-md-6 mb-4">
                                <h6>Writing (Expresión Escrita)</h6>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo number_format($progreso['writing'], 1); ?> / 10</span>
                                    <span><?php echo ($progreso['writing'] * 10); ?>%</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($progreso['writing'] * 10); ?>%">
                                        <?php echo number_format($progreso['writing'], 1); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Grammar -->
                            <div class="col-md-6 mb-4">
                                <h6>Grammar (Gramática)</h6>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo number_format($progreso['grammar'], 1); ?> / 10</span>
                                    <span><?php echo ($progreso['grammar'] * 10); ?>%</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo ($progreso['grammar'] * 10); ?>%">
                                        <?php echo number_format($progreso['grammar'], 1); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Vocabulary -->
                            <div class="col-md-6 mb-4">
                                <h6>Vocabulary (Vocabulario)</h6>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo number_format($progreso['vocabulary'], 1); ?> / 10</span>
                                    <span><?php echo ($progreso['vocabulary'] * 10); ?>%</span>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-secondary" style="width: <?php echo ($progreso['vocabulary'] * 10); ?>%">
                                        <?php echo number_format($progreso['vocabulary'], 1); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <?php if ($progreso['observaciones']): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Observaciones del Profesor</h5>
                        </div>
                        <div class="card-body">
                            <p><?php echo nl2br(escape($progreso['observaciones'])); ?></p>
                            <small class="text-muted">
                                Última actualización: <?php echo format_datetime($progreso['fecha_actualizacion']); ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Aún no tienes progreso registrado. Tu profesor lo actualizará próximamente.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
?>