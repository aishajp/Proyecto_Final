<?php
// ============================================
// alumno/calendario.php
// Calendario de clases del alumno
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener mes y año actual o seleccionado
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$año = isset($_GET['año']) ? (int)$_GET['año'] : date('Y');

// Obtener clases del mes
$stmt = $db->prepare("
    SELECT c.*, r.estado as estado_reserva
    FROM clases c
    INNER JOIN reservas r ON c.id_clase = r.id_clase
    WHERE r.id_usuario = ? 
    AND MONTH(c.fecha) = ? 
    AND YEAR(c.fecha) = ?
    ORDER BY c.fecha, c.hora_inicio
");
$stmt->execute([$user_id, $mes, $año]);
$clases_mes = $stmt->fetchAll();

// Agrupar por fecha
$clases_por_fecha = [];
foreach ($clases_mes as $clase) {
    $fecha = $clase['fecha'];
    if (!isset($clases_por_fecha[$fecha])) {
        $clases_por_fecha[$fecha] = [];
    }
    $clases_por_fecha[$fecha][] = $clase;
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_alumno.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Calendario de Clases</h1>
            </div>

            <!-- Navegación de mes -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <?php
                        $mes_anterior = $mes - 1;
                        $año_anterior = $año;
                        if ($mes_anterior < 1) {
                            $mes_anterior = 12;
                            $año_anterior--;
                        }
                        
                        $mes_siguiente = $mes + 1;
                        $año_siguiente = $año;
                        if ($mes_siguiente > 12) {
                            $mes_siguiente = 1;
                            $año_siguiente++;
                        }
                        
                        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                                 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                        ?>
                        
                        <a href="?mes=<?php echo $mes_anterior; ?>&año=<?php echo $año_anterior; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-chevron-left"></i> Anterior
                        </a>
                        
                        <h3><?php echo $meses[$mes] . ' ' . $año; ?></h3>
                        
                        <a href="?mes=<?php echo $mes_siguiente; ?>&año=<?php echo $año_siguiente; ?>" class="btn btn-outline-primary">
                            Siguiente <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Clases del mes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Clases en <?php echo $meses[$mes]; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (count($clases_mes) > 0): ?>
                        <?php foreach ($clases_por_fecha as $fecha => $clases): ?>
                            <div class="mb-4">
                                <h5 class="border-bottom pb-2">
                                    <i class="bi bi-calendar3"></i> <?php echo format_date($fecha); ?>
                                </h5>
                                <?php foreach ($clases as $clase): ?>
                                    <div class="card mb-2">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <h6><?php echo escape($clase['titulo']); ?></h6>
                                                    <p class="mb-0">
                                                        <i class="bi bi-clock"></i> 
                                                        <?php echo substr($clase['hora_inicio'], 0, 5); ?> - 
                                                        <?php echo substr($clase['hora_fin'], 0, 5); ?>
                                                        (<?php echo $clase['duracion']; ?> min)
                                                    </p>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <span class="badge bg-<?php echo $clase['tipo'] === 'individual' ? 'primary' : 'success'; ?>">
                                                        <?php echo ucfirst($clase['tipo']); ?>
                                                    </span>
                                                    <br>
                                                    <span class="badge bg-<?php echo $clase['estado_reserva'] === 'confirmada' ? 'info' : 'secondary'; ?>">
                                                        <?php echo ucfirst($clase['estado_reserva']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No tienes clases programadas en este mes.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>