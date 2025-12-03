<?php
// alumno/calendario.php - CALENDARIO MENSUAL
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ALUMNO)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$page_title = 'Calendario';

// Obtener mes y año actual o seleccionado
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$año = isset($_GET['año']) ? (int)$_GET['año'] : date('Y');

// Validar mes y año
if ($mes < 1 || $mes > 12) $mes = date('n');
if ($año < 2020 || $año > 2030) $año = date('Y');

// Preparar array de eventos por fecha
$eventos = [];

// Obtener CLASES del mes del alumno
$stmt = $db->prepare("
    SELECT c.id_clase, c.fecha, c.hora_inicio, c.titulo, c.tipo, r.estado as estado_reserva
    FROM clases c
    INNER JOIN reservas r ON c.id_clase = r.id_clase
    WHERE r.id_usuario = ?
    AND MONTH(c.fecha) = ?
    AND YEAR(c.fecha) = ?
    AND r.estado = 'confirmada'
    ORDER BY c.fecha, c.hora_inicio
");
$stmt->execute([$user_id, $mes, $año]);
$clases = $stmt->fetchAll();

foreach ($clases as $clase) {
    $fecha = $clase['fecha'];
    if (!isset($eventos[$fecha])) {
        $eventos[$fecha] = [];
    }
    $eventos[$fecha][] = [
        'tipo' => 'clase',
        'titulo' => $clase['titulo'],
        'hora' => substr($clase['hora_inicio'], 0, 5),
        'clase_tipo' => $clase['tipo'],
        'color' => $clase['tipo'] === 'individual' ? 'primary' : 'success'
    ];
}

// Obtener EXÁMENES del mes (todos los exámenes son para todos los alumnos)
$stmt = $db->prepare("
    SELECT e.id_examen, e.fecha, e.hora_inicio, e.titulo, e.tipo
    FROM examenes e
    WHERE MONTH(e.fecha) = ?
    AND YEAR(e.fecha) = ?
    ORDER BY e.fecha, e.hora_inicio
");
$stmt->execute([$mes, $año]);
$examenes = $stmt->fetchAll();

foreach ($examenes as $examen) {
    $fecha = $examen['fecha'];
    if (!isset($eventos[$fecha])) {
        $eventos[$fecha] = [];
    }
    $eventos[$fecha][] = [
        'tipo' => 'examen',
        'titulo' => $examen['titulo'],
        'hora' => substr($examen['hora_inicio'], 0, 5),
        'examen_tipo' => $examen['tipo'],
        'color' => 'warning'
    ];
}

// Calcular navegación de mes
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

// Generar calendario
$primer_dia = mktime(0, 0, 0, $mes, 1, $año);
$dias_en_mes = date('t', $primer_dia);
$dia_semana_inicio = date('N', $primer_dia); // 1 (lunes) a 7 (domingo)

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_alumno.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Mi Calendario</h1>
            </div>

            <!-- Navegación de mes -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
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

            <!-- Leyenda -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Leyenda:</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <span class="badge bg-primary">Clase Individual</span>
                        </div>
                        <div class="col-md-4">
                            <span class="badge bg-success">Clase Grupal</span>
                        </div>
                        <div class="col-md-4">
                            <span class="badge bg-warning text-dark">Examen</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendario -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Lunes</th>
                                    <th>Martes</th>
                                    <th>Miércoles</th>
                                    <th>Jueves</th>
                                    <th>Viernes</th>
                                    <th>Sábado</th>
                                    <th>Domingo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $dia_actual = 1;
                                $semana_iniciada = false;
                                
                                // Generar semanas
                                for ($semana = 0; $semana < 6; $semana++) {
                                    if ($dia_actual > $dias_en_mes) break;
                                    
                                    echo '<tr style="height: 120px;">';
                                    
                                    // Generar días de la semana
                                    for ($dia_semana = 1; $dia_semana <= 7; $dia_semana++) {
                                        // Primera semana: agregar celdas vacías hasta el primer día
                                        if (!$semana_iniciada && $dia_semana < $dia_semana_inicio) {
                                            echo '<td class="bg-light"></td>';
                                            continue;
                                        }
                                        
                                        $semana_iniciada = true;
                                        
                                        // Si ya terminamos el mes
                                        if ($dia_actual > $dias_en_mes) {
                                            echo '<td class="bg-light"></td>';
                                            continue;
                                        }
                                        
                                        // Celda con día
                                        $fecha_completa = sprintf('%04d-%02d-%02d', $año, $mes, $dia_actual);
                                        $es_hoy = ($fecha_completa === date('Y-m-d'));
                                        
                                        echo '<td class="align-top p-2 ' . ($es_hoy ? 'bg-info bg-opacity-10' : '') . '">';
                                        echo '<strong class="' . ($es_hoy ? 'text-info' : '') . '">' . $dia_actual . '</strong>';
                                        
                                        // Mostrar eventos de este día
                                        if (isset($eventos[$fecha_completa])) {
                                            echo '<div class="mt-2">';
                                            foreach ($eventos[$fecha_completa] as $evento) {
                                                $icono = '';
                                                switch($evento['tipo']) {
                                                    case 'clase':
                                                        $icono = 'bi-book';
                                                        break;
                                                    case 'examen':
                                                        $icono = 'bi-file-earmark-text';
                                                        break;
                                                }
                                                
                                                echo '<div class="badge bg-' . $evento['color'] . ' text-start w-100 mb-1 p-2" style="font-size: 0.7rem;">';
                                                echo '<i class="bi ' . $icono . '"></i> ';
                                                echo '<strong>' . $evento['hora'] . '</strong><br>';
                                                echo '<small>' . escape($evento['titulo']) . '</small>';
                                                echo '</div>';
                                            }
                                            echo '</div>';
                                        }
                                        
                                        echo '</td>';
                                        $dia_actual++;
                                    }
                                    
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Resumen del mes -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Resumen del Mes</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $total_clases = 0;
                            $total_examenes = 0;
                            
                            foreach ($eventos as $fecha => $eventos_dia) {
                                foreach ($eventos_dia as $evento) {
                                    if ($evento['tipo'] === 'clase') $total_clases++;
                                    if ($evento['tipo'] === 'examen') $total_examenes++;
                                }
                            }
                            ?>
                            <div class="row text-center">
                                <div class="col-md-6">
                                    <h3 class="text-primary"><?php echo $total_clases; ?></h3>
                                    <small>Clases Programadas</small>
                                </div>
                                <div class="col-md-6">
                                    <h3 class="text-warning"><?php echo $total_examenes; ?></h3>
                                    <small>Exámenes</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>