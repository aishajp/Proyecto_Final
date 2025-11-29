<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener datos del usuario
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $nombre = sanitize_input($_POST['nombre']);
    $apellidos = sanitize_input($_POST['apellidos']);
    $email = sanitize_input($_POST['email']);
    $telefono = sanitize_input($_POST['telefono']);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
    } else {
        // Verificar si el email ya existe (excepto el propio)
        $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            set_message('El email ya está en uso por otro usuario', MSG_ERROR);
        } else {
            $stmt = $db->prepare("
                UPDATE usuarios 
                SET nombre = ?, apellidos = ?, email = ?, telefono = ?
                WHERE id_usuario = ?
            ");
            
            if ($stmt->execute([$nombre, $apellidos, $email, $telefono, $user_id])) {
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_email'] = $email;
                set_message('Perfil actualizado correctamente', MSG_SUCCESS);
                // Recargar datos
                $stmt = $db->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$user_id]);
                $usuario = $stmt->fetch();
            } else {
                set_message('Error al actualizar el perfil', MSG_ERROR);
            }
        }
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_message('Token de seguridad inválido', MSG_ERROR);
    } elseif (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        set_message('Por favor complete todos los campos', MSG_ERROR);
    } elseif ($password_nueva !== $password_confirmar) {
        set_message('Las contraseñas nuevas no coinciden', MSG_ERROR);
    } elseif (strlen($password_nueva) < 6) {
        set_message('La contraseña debe tener al menos 6 caracteres', MSG_ERROR);
    } else {
        // Verificar contraseña actual
        if (password_verify($password_actual, $usuario['password'])) {
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
            
            if ($stmt->execute([$password_hash, $user_id])) {
                set_message('Contraseña actualizada correctamente', MSG_SUCCESS);
            } else {
                set_message('Error al actualizar la contraseña', MSG_ERROR);
            }
        } else {
            set_message('La contraseña actual es incorrecta', MSG_ERROR);
        }
    }
}

// Estadísticas del admin
$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'alumno'");
$total_alumnos = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM clases WHERE id_profesor = $user_id");
$total_clases = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM materiales WHERE subido_por = $user_id");
$total_materiales = $stmt->fetch()['total'];

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Mi Perfil</h1>
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

            <div class="row">
                <!-- Información del perfil -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <i class="bi bi-person-circle" style="font-size: 100px; color: #007bff;"></i>
                            </div>
                            <h4><?php echo escape($usuario['nombre'] . ' ' . $usuario['apellidos']); ?></h4>
                            <p class="text-muted"><?php echo escape($usuario['email']); ?></p>
                            <span class="badge bg-warning text-dark">Administrador</span>
                            <hr>
                            <div class="text-start">
                                <p><i class="bi bi-telephone"></i> <?php echo escape($usuario['telefono'] ?: 'No especificado'); ?></p>
                                <p><i class="bi bi-calendar"></i> Miembro desde: <?php echo format_date($usuario['fecha_registro']); ?></p>
                                <p><i class="bi bi-clock"></i> Último acceso: <?php echo $usuario['ultimo_acceso'] ? format_datetime($usuario['ultimo_acceso']) : 'Nunca'; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas rápidas -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Mis Estadísticas</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Alumnos Totales</span>
                                    <strong><?php echo $total_alumnos; ?></strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Clases Creadas</span>
                                    <strong><?php echo $total_clases; ?></strong>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between">
                                    <span>Materiales Subidos</span>
                                    <strong><?php echo $total_materiales; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formularios de edición -->
                <div class="col-md-8">
                    <!-- Editar información personal -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Información Personal</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" name="nombre" value="<?php echo escape($usuario['nombre']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Apellidos *</label>
                                        <input type="text" class="form-control" name="apellidos" value="<?php echo escape($usuario['apellidos']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo escape($usuario['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" name="telefono" value="<?php echo escape($usuario['telefono']); ?>">
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="actualizar_perfil" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Cambiar contraseña -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Cambiar Contraseña</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Contraseña Actual *</label>
                                    <input type="password" class="form-control" name="password_actual" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nueva Contraseña *</label>
                                    <input type="password" class="form-control" name="password_nueva" required>
                                    <small class="text-muted">Mínimo 6 caracteres</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirmar Nueva Contraseña *</label>
                                    <input type="password" class="form-control" name="password_confirmar" required>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="cambiar_password" class="btn btn-warning">
                                        <i class="bi bi-key"></i> Cambiar Contraseña
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>