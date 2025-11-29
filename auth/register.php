<?php
// ============================================
// auth/register.php
// Sistema de registro de usuarios
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (is_logged_in()) {
    redirect($_SESSION['user_role'] . '/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize_input($_POST['nombre']);
    $apellidos = sanitize_input($_POST['apellidos']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $telefono = sanitize_input($_POST['telefono']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $rol = $_POST['rol'];
    
    // Validaciones
    if (empty($nombre) || empty($apellidos) || empty($email) || empty($password) || empty($rol)) {
        $error = 'Por favor, complete todos los campos obligatorios';
    } elseif (!validate_email($email)) {
        $error = 'El email no es válido';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (!in_array($rol, [ROL_ALUMNO, ROL_PADRE])) {
        $error = 'Rol no válido';
    } else {
        $db = Database::getInstance()->getConnection();
        
        // Verificar si el email ya existe
        $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Este email ya está registrado';
        } else {
            // Hash de la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar usuario
            $stmt = $db->prepare("
                INSERT INTO usuarios (nombre, apellidos, email, password, telefono, fecha_nacimiento, rol) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$nombre, $apellidos, $email, $password_hash, $telefono, $fecha_nacimiento, $rol])) {
                $success = 'Registro exitoso. Ya puedes iniciar sesión.';
                
                // Opcional: Iniciar sesión automáticamente
                $user_id = $db->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = $rol;
                
                // Si es alumno, crear registro de progreso inicial
                if ($rol === ROL_ALUMNO) {
                    $stmt = $db->prepare("
                        INSERT INTO progreso_alumno (id_alumno, nivel_actual, actualizado_por) 
                        VALUES (?, 'A1', 1)
                    ");
                    $stmt->execute([$user_id]);
                }
                
                sleep(2); // Mostrar mensaje de éxito
                redirect($rol . '/dashboard.php');
            } else {
                $error = 'Error al registrar el usuario';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold">Crear Cuenta</h2>
                            <p class="text-muted">Regístrate para acceder al sistema</p>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="apellidos" class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" id="apellidos" name="apellidos" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Contraseña *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Mínimo 6 caracteres</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirm" class="form-label">Confirmar Contraseña *</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="rol" class="form-label">Tipo de Usuario *</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="">Seleccione...</option>
                                    <option value="alumno">Alumno</option>
                                    <option value="padre">Padre/Madre</option>
                                </select>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    Acepto los términos y condiciones
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Registrarse</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
