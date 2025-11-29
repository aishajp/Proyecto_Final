<!DOCTYPE html>
<html lang="es">
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="<?php echo SITE_URL; ?>administrador/dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Gestión</span>
        </h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo SITE_URL; ?>administrador/usuarios.php">
                    <i class="bi bi-people"></i> Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo SITE_URL; ?>administrador/clases.php">
                    <i class="bi bi-calendar-event"></i> Clases
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo SITE_URL; ?>administrador/materiales.php">
                    <i class="bi bi-folder"></i> Materiales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo SITE_URL; ?>administrador/examenes.php">
                    <i class="bi bi-file-earmark-text"></i> Exámenes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo SITE_URL; ?>administrador/tutorias.php">
                    <i class="bi bi-chat-dots"></i> Tutorías
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Seguimiento</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo SITE_URL; ?>administrador/progreso.php">
                    <i class="bi bi-graph-up"></i> Progreso Alumnos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo SITE_URL; ?>administrador/estadisticas.php">
                    <i class="bi bi-bar-chart"></i> Estadísticas
                </a>
            </li>
        </ul>
    </div>
</nav>

</html>