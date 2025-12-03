<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Si ya está autenticado, redirigir al dashboard
if (is_logged_in()) {
    $role = $_SESSION['user_role'];
    redirect($role . '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, complete todos los campos';
    } else {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Iniciar sesión
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['rol'];
            
            // Actualizar último acceso
            $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
            $stmt->execute([$user['id_usuario']]);
            
            // Log de actividad
            log_activity($user['id_usuario'], 'login', 'Inicio de sesión exitoso');
            
            // Redirigir según el rol
            redirect($user['rol'] . '/dashboard.php');
        } else {
            $error = 'Email o contraseña incorrectos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold">Clases de Inglés</h2>
                            <p class="text-muted">Inicia sesión en tu cuenta</p>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Recordarme</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>