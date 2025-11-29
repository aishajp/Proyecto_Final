<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (is_logged_in()) {
    redirect($_SESSION['user_role'] . '/dashboard.php');
}

$errors = [];
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'nombre' => sanitize_input($_POST['nombre']),
        'apellidos' => sanitize_input($_POST['apellidos']),
        'email' => sanitize_input($_POST['email']),
        'telefono' => sanitize_input($_POST['telefono']),
        'fecha_nacimiento' => $_POST['fecha_nacimiento'],
        'rol' => $_POST['rol']
    ];
    
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    // Validar nombre
    $name_validation = validate_name($form_data['nombre'], 'Nombre');
    if (!$name_validation['valid']) {
        $errors[] = $name_validation['error'];
    }
    
    // Validar apellidos
    $surname_validation = validate_name($form_data['apellidos'], 'Apellidos');
    if (!$surname_validation['valid']) {
        $errors[] = $surname_validation['error'];
    }
    
    // Validar email
    if (!validate_email($form_data['email'])) {
        $errors[] = 'El email no es válido';
    }
    
    // Validar teléfono
    $phone_validation = validate_phone($form_data['telefono']);
    if (!$phone_validation['valid']) {
        $errors[] = $phone_validation['error'];
    }
    
    // Validar fecha de nacimiento
    $birthdate_validation = validate_birthdate($form_data['fecha_nacimiento']);
    if (!$birthdate_validation['valid']) {
        $errors[] = $birthdate_validation['error'];
    }
    
    // Validar contraseña
    $password_validation = validate_password($password);
    if (!$password_validation['valid']) {
        $errors = array_merge($errors, $password_validation['errors']);
    }
    
    // Confirmar contraseña
    if ($password !== $password_confirm) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    // Validar rol
    if (!in_array($form_data['rol'], [ROL_ALUMNO, ROL_PADRE])) {
        $errors[] = 'Rol no válido';
    }
    
    // Si no hay errores, procesar registro
    if (empty($errors)) {
        $db = Database::getInstance()->getConnection();
        
        // Verificar si el email ya existe
        $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Este email ya está registrado';
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO usuarios (nombre, apellidos, email, password, telefono, fecha_nacimiento, rol) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([
                    $form_data['nombre'], 
                    $form_data['apellidos'], 
                    $form_data['email'], 
                    $password_hash, 
                    $form_data['telefono'], 
                    $form_data['fecha_nacimiento'], 
                    $form_data['rol']
                ])) {
                    $user_id = $db->lastInsertId();
                    
                    // Si es alumno, crear registro de progreso inicial
                    if ($form_data['rol'] === ROL_ALUMNO) {
                        $stmt = $db->prepare("
                            INSERT INTO progreso_alumno (id_alumno, nivel_actual, actualizado_por) 
                            VALUES (?, 'A1', 1)
                        ");
                        $stmt->execute([$user_id]);
                    }
                    
                    // Log de actividad
                    log_activity($user_id, 'registro', 'Usuario registrado exitosamente');
                    
                    // Iniciar sesión automáticamente
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $form_data['nombre'];
                    $_SESSION['user_email'] = $form_data['email'];
                    $_SESSION['user_role'] = $form_data['rol'];
                    
                    set_message('Registro exitoso. ¡Bienvenido!', MSG_SUCCESS);
                    redirect($form_data['rol'] . '/dashboard.php');
                } else {
                    $errors[] = 'Error al registrar el usuario';
                }
            } catch (Exception $e) {
                $errors[] = 'Error en el servidor. Por favor, inténtelo más tarde';
                error_log("Error en registro: " . $e->getMessage());
            }
        }
    }
}

// Calcular fechas límite para fecha de nacimiento
$min_date = date('Y-m-d', strtotime('-100 years'));
$max_date = date('Y-m-d', strtotime('-3 years'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="<?php echo SITE_URL; ?>assets/css/styles.css" rel="stylesheet">
    <style>
        .password-requirements {
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        .password-requirements li {
            color: #dc3545;
        }
        .password-requirements li.valid {
            color: #198754;
        }
        .password-requirements li.valid:before {
            content: "✓ ";
            font-weight: bold;
        }
        .password-requirements li:before {
            content: "✗ ";
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-book text-primary" style="font-size: 3rem;"></i>
                            <h2 class="fw-bold mt-3">Crear Cuenta</h2>
                            <p class="text-muted">Regístrate para acceder a las clases de inglés</p>
                        </div>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Se encontraron los siguientes errores:</h6>
                                <?php echo format_validation_errors($errors); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?php echo $form_data['nombre'] ?? ''; ?>" 
                                           required maxlength="50" pattern="[a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s\-']+">
                                    <div class="invalid-feedback">Por favor, ingrese un nombre válido</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="apellidos" class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" id="apellidos" name="apellidos" 
                                           value="<?php echo $form_data['apellidos'] ?? ''; ?>" 
                                           required maxlength="50" pattern="[a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s\-']+">
                                    <div class="invalid-feedback">Por favor, ingrese apellidos válidos</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $form_data['email'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Por favor, ingrese un email válido</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" 
                                           value="<?php echo $form_data['telefono'] ?? ''; ?>" 
                                           placeholder="Ej: 612345678" pattern="(\+34)?[6-9]\d{8}">
                                    <small class="text-muted">Formato: 9 dígitos comenzando por 6-9</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                           value="<?php echo $form_data['fecha_nacimiento'] ?? ''; ?>" 
                                           min="<?php echo $min_date; ?>" max="<?php echo $max_date; ?>" required>
                                    <small class="text-muted">Edad mínima: 3 años</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="rol" class="form-label">Tipo de Usuario *</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="">Seleccione...</option>
                                    <option value="alumno" <?php echo ($form_data['rol'] ?? '') === 'alumno' ? 'selected' : ''; ?>>
                                        Alumno - Recibiré las clases
                                    </option>
                                    <option value="padre" <?php echo ($form_data['rol'] ?? '') === 'padre' ? 'selected' : ''; ?>>
                                        Padre/Madre - Gestionaré las clases de mi hijo/a
                                    </option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Contraseña *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <ul class="password-requirements" id="password-req">
                                        <li id="req-length">Mínimo 8 caracteres</li>
                                        <li id="req-uppercase">Una letra mayúscula</li>
                                        <li id="req-lowercase">Una letra minúscula</li>
                                        <li id="req-number">Un número</li>
                                        <li id="req-special">Un carácter especial</li>
                                    </ul>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirm" class="form-label">Confirmar Contraseña *</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                    <div id="password-match" class="mt-2"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    Acepto los <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">términos y condiciones</a> *
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 btn-lg">
                                <i class="bi bi-person-plus"></i> Registrarse
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Términos y Condiciones -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Términos y Condiciones</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Aceptación de Términos</h6>
                    <p>Al registrarse en este sistema, acepta cumplir con estos términos y condiciones.</p>
                    
                    <h6>2. Uso del Servicio</h6>
                    <p>El servicio está diseñado para gestionar clases de inglés. Se compromete a usar el sistema de manera responsable.</p>
                    
                    <h6>3. Privacidad de Datos</h6>
                    <p>Sus datos personales serán tratados conforme a la normativa de protección de datos vigente (RGPD).</p>
                    
                    <h6>4. Cancelaciones</h6>
                    <p>Las cancelaciones de clases deben realizarse con al menos 24 horas de antelación.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación de contraseña en tiempo real
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            // Validar requisitos
            document.getElementById('req-length').classList.toggle('valid', password.length >= 8);
            document.getElementById('req-uppercase').classList.toggle('valid', /[A-Z]/.test(password));
            document.getElementById('req-lowercase').classList.toggle('valid', /[a-z]/.test(password));
            document.getElementById('req-number').classList.toggle('valid', /[0-9]/.test(password));
            document.getElementById('req-special').classList.toggle('valid', /[!@#$%^&*(),.?":{}|<>]/.test(password));
        });
        
        // Validar coincidencia de contraseñas
        document.getElementById('password_confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    matchDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> Las contraseñas coinciden</small>';
                } else {
                    matchDiv.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle"></i> Las contraseñas no coinciden</small>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        });
        
        // Bootstrap form validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>