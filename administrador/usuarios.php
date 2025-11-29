<?php

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!is_logged_in() || !has_role(ROL_ADMIN)) {
    redirect('auth/login.php');
}

$db = Database::getInstance()->getConnection();
$page_title = 'Gestión de Usuarios';

// Filtros
$rol_filtro = isset($_GET['rol']) ? $_GET['rol'] : 'todos';
$busqueda = isset($_GET['busqueda']) ? sanitize_input($_GET['busqueda']) : '';

// Construir consulta
$sql = "SELECT * FROM usuarios WHERE 1=1";
$params = [];

if ($rol_filtro !== 'todos') {
    $sql .= " AND rol = ?";
    $params[] = $rol_filtro;
}

if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR apellidos LIKE ? OR email LIKE ?)";
    $search_term = "%$busqueda%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY fecha_registro DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Obtener listados para los selects
$stmt = $db->query("SELECT id_usuario, nombre, apellidos FROM usuarios WHERE rol = 'alumno' AND activo = 1 ORDER BY nombre");
$alumnos_list = $stmt->fetchAll();

$stmt = $db->query("SELECT id_usuario, nombre, apellidos FROM usuarios WHERE rol = 'padre' AND activo = 1 ORDER BY nombre");
$padres_list = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar_admin.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestión de Usuarios</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
                    <i class="bi bi-plus-circle"></i> Nuevo Usuario
                </button>
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

            <!-- Filtros y búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Rol</label>
                            <select name="rol" class="form-select" onchange="this.form.submit()">
                                <option value="todos" <?php echo $rol_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="alumno" <?php echo $rol_filtro === 'alumno' ? 'selected' : ''; ?>>Alumnos</option>
                                <option value="padre" <?php echo $rol_filtro === 'padre' ? 'selected' : ''; ?>>Padres</option>
                                <option value="administrador" <?php echo $rol_filtro === 'administrador' ? 'selected' : ''; ?>>Administradores</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control" name="busqueda" placeholder="Nombre, apellidos o email..." value="<?php echo escape($busqueda); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de usuarios -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Completo</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Rol</th>
                                    <th>Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo $usuario['id_usuario']; ?></td>
                                        <td>
                                            <strong><?php echo escape($usuario['nombre'] . ' ' . $usuario['apellidos']); ?></strong>
                                        </td>
                                        <td><?php echo escape($usuario['email']); ?></td>
                                        <td><?php echo escape($usuario['telefono']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $usuario['rol'] === 'alumno' ? 'primary' : 
                                                    ($usuario['rol'] === 'padre' ? 'info' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($usuario['rol']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_date($usuario['fecha_registro']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info btn-ver-usuario" 
                                                    data-usuario='<?php echo json_encode($usuario); ?>'
                                                    title="Ver detalles">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning btn-editar-usuario" 
                                                    data-usuario='<?php echo json_encode($usuario); ?>'
                                                    title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($usuario['rol'] !== 'administrador'): ?>
                                                <button class="btn btn-sm btn-success btn-asociar-usuario" 
                                                        data-id="<?php echo $usuario['id_usuario']; ?>"
                                                        data-rol="<?php echo $usuario['rol']; ?>"
                                                        data-nombre="<?php echo escape($usuario['nombre'] . ' ' . $usuario['apellidos']); ?>"
                                                        title="Asociar">
                                                    <i class="bi bi-link-45deg"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-eliminar-usuario" 
                                                        data-id="<?php echo $usuario['id_usuario']; ?>"
                                                        data-nombre="<?php echo escape($usuario['nombre'] . ' ' . $usuario['apellidos']); ?>"
                                                        title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Nuevo Usuario (MEJORADO) -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="guardar_usuario.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apellidos *</label>
                            <input type="text" class="form-control" name="apellidos" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol *</label>
                            <select class="form-select" name="rol" id="rol_nuevo" required>
                                <option value="">Seleccione...</option>
                                <option value="alumno">Alumno</option>
                                <option value="padre">Padre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" name="fecha_nacimiento">
                        </div>
                        <div class="col-md-6 mb-3" id="asociacion_nuevo" style="display: none;">
                            <label class="form-label" id="label_asociacion">Asociar con</label>
                            <select class="form-select" name="asociar_con" id="select_asociacion">
                                <option value="">No asociar ahora</option>
                            </select>
                            <small class="text-muted">Opcional - Se puede hacer después</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Usuario -->
<div class="modal fade" id="modalVerUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoVerUsuario">
                <!-- Se llenará con JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="editar_usuario.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_usuario" id="edit_id_usuario">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apellidos *</label>
                            <input type="text" class="form-control" name="apellidos" id="edit_apellidos" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono" id="edit_telefono">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" name="fecha_nacimiento" id="edit_fecha_nacimiento">
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Nota:</strong> Deja la contraseña en blanco si no deseas cambiarla
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña (opcional)</label>
                        <input type="password" class="form-control" name="password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Asociar -->
<div class="modal fade" id="modalAsociar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloAsociar">Asociar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="asociar_usuario.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="id_usuario" id="asociar_id_usuario">
                    <input type="hidden" name="rol" id="asociar_rol">
                    
                    <p id="textoAsociar"></p>
                    
                    <div class="mb-3">
                        <label class="form-label" id="labelAsociar"></label>
                        <select class="form-select" name="asociar_con" id="selectAsociar" required></select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Asociar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const alumnos = <?php echo json_encode($alumnos_list); ?>;
    const padres = <?php echo json_encode($padres_list); ?>;
    
    // Rol nuevo usuario - mostrar campo de asociación
    document.getElementById('rol_nuevo').addEventListener('change', function() {
        const asociacionDiv = document.getElementById('asociacion_nuevo');
        const selectAsociacion = document.getElementById('select_asociacion');
        const labelAsociacion = document.getElementById('label_asociacion');
        
        selectAsociacion.innerHTML = '<option value="">No asociar ahora</option>';
        
        if (this.value === 'alumno') {
            asociacionDiv.style.display = 'block';
            labelAsociacion.textContent = 'Asociar con Padre (opcional)';
            padres.forEach(padre => {
                selectAsociacion.innerHTML += `<option value="${padre.id_usuario}">${padre.nombre} ${padre.apellidos}</option>`;
            });
        } else if (this.value === 'padre') {
            asociacionDiv.style.display = 'block';
            labelAsociacion.textContent = 'Asociar con Alumno (opcional)';
            alumnos.forEach(alumno => {
                selectAsociacion.innerHTML += `<option value="${alumno.id_usuario}">${alumno.nombre} ${alumno.apellidos}</option>`;
            });
        } else {
            asociacionDiv.style.display = 'none';
        }
    });
    
    // Ver usuario
    document.querySelectorAll('.btn-ver-usuario').forEach(btn => {
        btn.addEventListener('click', function() {
            const usuario = JSON.parse(this.getAttribute('data-usuario'));
            const modal = new bootstrap.Modal(document.getElementById('modalVerUsuario'));
            
            const html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nombre:</strong> ${usuario.nombre} ${usuario.apellidos}</p>
                        <p><strong>Email:</strong> ${usuario.email}</p>
                        <p><strong>Teléfono:</strong> ${usuario.telefono || 'No especificado'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Rol:</strong> ${usuario.rol}</p>
                        <p><strong>Fecha Nacimiento:</strong> ${usuario.fecha_nacimiento || 'No especificada'}</p>
                        <p><strong>Registro:</strong> ${usuario.fecha_registro}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('contenidoVerUsuario').innerHTML = html;
            modal.show();
        });
    });
    
    // Editar usuario
    document.querySelectorAll('.btn-editar-usuario').forEach(btn => {
        btn.addEventListener('click', function() {
            const usuario = JSON.parse(this.getAttribute('data-usuario'));
            const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
            
            document.getElementById('edit_id_usuario').value = usuario.id_usuario;
            document.getElementById('edit_nombre').value = usuario.nombre;
            document.getElementById('edit_apellidos').value = usuario.apellidos;
            document.getElementById('edit_email').value = usuario.email;
            document.getElementById('edit_telefono').value = usuario.telefono || '';
            document.getElementById('edit_fecha_nacimiento').value = usuario.fecha_nacimiento || '';
            
            modal.show();
        });
    });
    
    // Asociar usuario
    document.querySelectorAll('.btn-asociar-usuario').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const rol = this.getAttribute('data-rol');
            const nombre = this.getAttribute('data-nombre');
            const modal = new bootstrap.Modal(document.getElementById('modalAsociar'));
            
            document.getElementById('asociar_id_usuario').value = id;
            document.getElementById('asociar_rol').value = rol;
            
            const selectAsociar = document.getElementById('selectAsociar');
            selectAsociar.innerHTML = '';
            
            if (rol === 'alumno') {
                document.getElementById('tituloAsociar').textContent = 'Asociar Alumno con Padre';
                document.getElementById('textoAsociar').textContent = `Selecciona el padre para: ${nombre}`;
                document.getElementById('labelAsociar').textContent = 'Seleccionar Padre';
                
                padres.forEach(padre => {
                    selectAsociar.innerHTML += `<option value="${padre.id_usuario}">${padre.nombre} ${padre.apellidos}</option>`;
                });
            } else if (rol === 'padre') {
                document.getElementById('tituloAsociar').textContent = 'Asociar Padre con Alumno';
                document.getElementById('textoAsociar').textContent = `Selecciona el alumno para: ${nombre}`;
                document.getElementById('labelAsociar').textContent = 'Seleccionar Alumno';
                
                alumnos.forEach(alumno => {
                    selectAsociar.innerHTML += `<option value="${alumno.id_usuario}">${alumno.nombre} ${alumno.apellidos}</option>`;
                });
            }
            
            modal.show();
        });
    });
    
    // Eliminar usuario
    document.querySelectorAll('.btn-eliminar-usuario').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nombre = this.getAttribute('data-nombre');
            
            if (confirm(`¿Estás seguro de que deseas ELIMINAR a ${nombre}?\n\nEsta acción NO se puede deshacer.`)) {
                window.location.href = `eliminar_usuario.php?id=${id}`;
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>