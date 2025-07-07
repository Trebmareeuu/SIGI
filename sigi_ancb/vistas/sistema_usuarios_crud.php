<?php
// Archivo: vistas/sistema_usuarios_crud.php
// Propósito: (Sistemas) CRUD completo para gestionar usuarios.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CRUD_USUARIOS_SISTEMA', $id_usuario_actual)) {
    mensaje_flash('error_usuarios_crud', 'No tiene permisos para gestionar usuarios.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables y Lógica para listar usuarios.
$usuarios = [];
try {
    $sql_listar = "SELECT u.id_usuario, u.nombre_usuario, u.nombres, u.apellidos, u.email, u.cargo, r.nombre_rol, u.estado
                   FROM usuarios u
                   JOIN roles r ON u.id_rol = r.id_rol
                   ORDER BY u.apellidos, u.nombres ASC";
    $stmt_listar = $pdo->query($sql_listar);
    $usuarios = $stmt_listar->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al listar usuarios: " . $e->getMessage());
    mensaje_flash('error_usuarios_crud', 'Error al cargar la lista de usuarios.', 'alert-danger');
}

// Comentario: Cargar roles para el formulario de creación/edición.
$roles_disponibles = [];
try {
    $stmt_roles = $pdo->query("SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol ASC");
    $roles_disponibles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar roles: " . $e->getMessage());
    // Comentario: Manejar error, quizás no mostrar el formulario si no hay roles.
}


// Comentario: Acción (crear, editar, eliminar).
$accion_crud = $_GET['accion_crud'] ?? 'listar'; // 'listar', 'crear', 'editar', 'eliminar'
$id_usuario_editar = null;
$usuario_para_editar = null;

if ($accion_crud === 'editar' && isset($_GET['id_user'])) {
    $id_usuario_editar = filter_var($_GET['id_user'], FILTER_VALIDATE_INT);
    if ($id_usuario_editar) {
        try {
            $stmt_edit = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id_user");
            $stmt_edit->bindParam(':id_user', $id_usuario_editar, PDO::PARAM_INT);
            $stmt_edit->execute();
            $usuario_para_editar = $stmt_edit->fetch(PDO::FETCH_ASSOC);
            if (!$usuario_para_editar) {
                mensaje_flash('error_usuarios_crud', 'Usuario no encontrado para editar.', 'alert-danger');
                $accion_crud = 'listar'; // Comentario: Volver a listar.
            }
        } catch (PDOException $e) {
            error_log("Error al cargar usuario para editar: " . $e->getMessage());
            mensaje_flash('error_usuarios_crud', 'Error al cargar datos del usuario para edición.', 'alert-danger');
            $accion_crud = 'listar';
        }
    } else {
        mensaje_flash('error_usuarios_crud', 'ID de usuario no válido para editar.', 'alert-danger');
        $accion_crud = 'listar';
    }
}

// Comentario: Lógica para CREAR o ACTUALIZAR usuario.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['guardar_usuario_nuevo']) || isset($_POST['actualizar_usuario_existente']))) {
    $id_usuario_form = filter_input(INPUT_POST, 'id_usuario_hidden', FILTER_VALIDATE_INT); // Comentario: Para edición.
    $nombre_usuario_form = sanitizar_entrada($_POST['nombre_usuario'] ?? '');
    $nombres_form = sanitizar_entrada($_POST['nombres'] ?? '');
    $apellidos_form = sanitizar_entrada($_POST['apellidos'] ?? '');
    $email_form = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $id_rol_form = filter_var($_POST['id_rol'] ?? '', FILTER_VALIDATE_INT);
    $cargo_form = sanitizar_entrada($_POST['cargo'] ?? '');
    $estado_form = sanitizar_entrada($_POST['estado'] ?? 'activo');
    $contrasena_form = $_POST['contrasena'] ?? ''; // Comentario: Solo para creación o si se quiere cambiar.
    $confirmar_contrasena_form = $_POST['confirmar_contrasena'] ?? '';

    $errores_form_usuario = [];
    if (empty($nombre_usuario_form)) $errores_form_usuario[] = "El nombre de usuario es obligatorio.";
    if (empty($nombres_form)) $errores_form_usuario[] = "Los nombres son obligatorios.";
    if (empty($apellidos_form)) $errores_form_usuario[] = "Los apellidos son obligatorios.";
    if (empty($email_form) || !filter_var($email_form, FILTER_VALIDATE_EMAIL)) $errores_form_usuario[] = "El correo electrónico no es válido o está vacío.";
    if (empty($id_rol_form)) $errores_form_usuario[] = "Debe seleccionar un rol para el usuario.";

    // Comentario: Validaciones de contraseña (solo si se está creando o cambiando).
    $actualizar_contrasena = false;
    if (isset($_POST['guardar_usuario_nuevo'])) { // Comentario: Creando nuevo usuario.
        if (empty($contrasena_form)) $errores_form_usuario[] = "La contraseña es obligatoria para nuevos usuarios.";
        elseif (strlen($contrasena_form) < 8) $errores_form_usuario[] = "La contraseña debe tener al menos 8 caracteres.";
        elseif ($contrasena_form !== $confirmar_contrasena_form) $errores_form_usuario[] = "Las contraseñas no coinciden.";
        else $actualizar_contrasena = true;
    } elseif (isset($_POST['actualizar_usuario_existente']) && !empty($contrasena_form)) { // Comentario: Editando y se ingresó nueva contraseña.
        if (strlen($contrasena_form) < 8) $errores_form_usuario[] = "La nueva contraseña debe tener al menos 8 caracteres.";
        elseif ($contrasena_form !== $confirmar_contrasena_form) $errores_form_usuario[] = "Las nuevas contraseñas no coinciden.";
        else $actualizar_contrasena = true;
    }

    // Comentario: Verificar unicidad de nombre_usuario y email (excepto para el propio usuario al editar).
    $id_excluir_check = $id_usuario_form ?: 0; // Comentario: Si es nuevo, no excluir a nadie.
    try {
        $stmt_check_user = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = :nombre_u AND id_usuario != :id_excluir");
        $stmt_check_user->execute([':nombre_u' => $nombre_usuario_form, ':id_excluir' => $id_excluir_check]);
        if ($stmt_check_user->fetch()) $errores_form_usuario[] = "El nombre de usuario '$nombre_usuario_form' ya está en uso.";

        $stmt_check_email = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email_u AND id_usuario != :id_excluir");
        $stmt_check_email->execute([':email_u' => $email_form, ':id_excluir' => $id_excluir_check]);
        if ($stmt_check_email->fetch()) $errores_form_usuario[] = "El correo electrónico '$email_form' ya está en uso.";
    } catch (PDOException $e) {
        $errores_form_usuario[] = "Error al verificar unicidad de datos: " . $e->getMessage();
    }


    if (empty($errores_form_usuario)) {
        try {
            if (isset($_POST['guardar_usuario_nuevo'])) { // Comentario: CREAR.
                $hash_contrasena = hashear_contrasena($contrasena_form);
                $sql_insert = "INSERT INTO usuarios (nombre_usuario, contrasena, nombres, apellidos, email, id_rol, cargo, estado)
                               VALUES (:nu, :pass, :n, :a, :e, :rol, :cargo, :est)";
                $stmt_op = $pdo->prepare($sql_insert);
                $stmt_op->execute([
                    ':nu' => $nombre_usuario_form, ':pass' => $hash_contrasena, ':n' => $nombres_form, ':a' => $apellidos_form,
                    ':e' => $email_form, ':rol' => $id_rol_form, ':cargo' => $cargo_form, ':est' => $estado_form
                ]);
                mensaje_flash('exito_usuarios_crud', 'Usuario creado exitosamente.', 'alert-success');

            } elseif (isset($_POST['actualizar_usuario_existente']) && $id_usuario_form) { // Comentario: ACTUALIZAR.
                if ($actualizar_contrasena) {
                    $hash_contrasena_nueva = hashear_contrasena($contrasena_form);
                    $sql_update = "UPDATE usuarios SET nombre_usuario = :nu, contrasena = :pass, nombres = :n, apellidos = :a, email = :e, id_rol = :rol, cargo = :cargo, estado = :est
                                   WHERE id_usuario = :id_user_upd";
                    $params_update = [
                        ':nu' => $nombre_usuario_form, ':pass' => $hash_contrasena_nueva, ':n' => $nombres_form, ':a' => $apellidos_form,
                        ':e' => $email_form, ':rol' => $id_rol_form, ':cargo' => $cargo_form, ':est' => $estado_form, ':id_user_upd' => $id_usuario_form
                    ];
                } else { // Comentario: Actualizar sin cambiar contraseña.
                    $sql_update = "UPDATE usuarios SET nombre_usuario = :nu, nombres = :n, apellidos = :a, email = :e, id_rol = :rol, cargo = :cargo, estado = :est
                                   WHERE id_usuario = :id_user_upd";
                     $params_update = [
                        ':nu' => $nombre_usuario_form, ':n' => $nombres_form, ':a' => $apellidos_form,
                        ':e' => $email_form, ':rol' => $id_rol_form, ':cargo' => $cargo_form, ':est' => $estado_form, ':id_user_upd' => $id_usuario_form
                    ];
                }
                $stmt_op = $pdo->prepare($sql_update);
                $stmt_op->execute($params_update);
                mensaje_flash('exito_usuarios_crud', 'Usuario actualizado exitosamente.', 'alert-success');
            }
            redirigir('index.php?vista=sistema_usuarios_crud'); // Comentario: Recargar la lista.
        } catch (PDOException $e) {
            error_log("Error al guardar/actualizar usuario: " . $e->getMessage());
            mensaje_flash('error_form_usuario_submit', 'Error al procesar la solicitud: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        // Comentario: Si hay errores, se mantienen los datos en el formulario (si es edición) o se muestran los errores.
        // Comentario: Los mensajes flash se usarán para mostrar los errores.
        foreach ($errores_form_usuario as $err_u) {
            mensaje_flash('error_form_usuario_validation', $err_u, 'alert-danger');
        }
        // Comentario: Si estaba editando, mantener la acción para que el formulario de edición se muestre de nuevo.
        if (isset($_POST['actualizar_usuario_existente'])) {
            $accion_crud = 'editar'; // Comentario: Para que el formulario de edición se vuelva a mostrar con los errores.
            // Comentario: $usuario_para_editar ya debería estar cargado. Si no, recargarlo o usar los datos del POST.
            // Comentario: Para simplificar, se redirige y se pierden los datos del POST si no se manejan con sesión.
            // Comentario: O, mejor, no redirigir y dejar que el formulario se repinte con los valores de $_POST y los errores.
            // Comentario: Esto requiere que el HTML del formulario use los valores de $_POST si están seteados.
            // Comentario: Por ahora, los mensajes flash son la principal vía de feedback de error tras POST.
        }
    }
}


// Comentario: Lógica para ELIMINAR usuario.
if ($accion_crud === 'eliminar' && isset($_GET['id_user']) && isset($_GET['confirmar_eliminar'])) {
    $id_usuario_eliminar = filter_var($_GET['id_user'], FILTER_VALIDATE_INT);
    if ($id_usuario_eliminar) {
        // Comentario: No permitir eliminar al usuario actual.
        if ($id_usuario_eliminar == $id_usuario_actual) {
            mensaje_flash('error_usuarios_crud', 'No puede eliminar su propia cuenta de usuario.', 'alert-warning');
        } else {
            try {
                // Comentario: Considerar qué pasa con los registros asociados a este usuario (FKs).
                // Comentario: Podría ser SET NULL o RESTRICT. Si es RESTRICT, la eliminación fallará si hay dependencias.
                // Comentario: O cambiar el estado a 'eliminado' en lugar de borrar físicamente.
                $stmt_del = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id_user_del");
                $stmt_del->bindParam(':id_user_del', $id_usuario_eliminar, PDO::PARAM_INT);
                $stmt_del->execute();
                if ($stmt_del->rowCount() > 0) {
                    mensaje_flash('exito_usuarios_crud', 'Usuario eliminado exitosamente.', 'alert-success');
                } else {
                    mensaje_flash('error_usuarios_crud', 'No se pudo eliminar el usuario o ya no existía.', 'alert-warning');
                }
            } catch (PDOException $e) {
                error_log("Error al eliminar usuario: " . $e->getMessage());
                // Comentario: Verificar si el error es por restricción de FK.
                if (str_contains($e->getMessage(), "FOREIGN KEY constraint fails")) {
                     mensaje_flash('error_usuarios_crud', 'No se puede eliminar el usuario porque tiene registros asociados (correspondencia, solicitudes, etc.). Considere cambiar su estado a "inactivo" o "bloqueado" en lugar de eliminarlo.', 'alert-danger');
                } else {
                    mensaje_flash('error_usuarios_crud', 'Error al eliminar el usuario: ' . $e->getMessage(), 'alert-danger');
                }
            }
        }
    } else {
        mensaje_flash('error_usuarios_crud', 'ID de usuario no válido para eliminar.', 'alert-danger');
    }
    redirigir('index.php?vista=sistema_usuarios_crud'); // Comentario: Recargar la lista.
}


?>
<h2>Gestión de Usuarios del Sistema</h2>

<?php
mensaje_flash('error_usuarios_crud');
mensaje_flash('exito_usuarios_crud');
mensaje_flash('error_form_usuario_submit');
mensaje_flash('error_form_usuario_validation'); // Comentario: Para errores específicos del form.
?>

<?php if ($accion_crud === 'crear' || ($accion_crud === 'editar' && $usuario_para_editar)): ?>
    <h3><?php echo ($accion_crud === 'crear') ? 'Crear Nuevo Usuario' : 'Editar Usuario: ' . htmlspecialchars($usuario_para_editar['nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
    <div class="card-sigi">
        <form action="index.php?vista=sistema_usuarios_crud" method="POST" class="validar-js">
            <?php if ($accion_crud === 'editar'): ?>
                <input type="hidden" name="id_usuario_hidden" value="<?php echo $usuario_para_editar['id_usuario']; ?>">
            <?php endif; ?>

            <div class="grupo-formulario">
                <label for="nombre_usuario">Nombre de Usuario (para login):</label>
                <input type="text" id="nombre_usuario" name="nombre_usuario" value="<?php echo htmlspecialchars($usuario_para_editar['nombre_usuario'] ?? ($_POST['nombre_usuario'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
            </div>
            <div class="grupo-formulario">
                <label for="nombres">Nombres:</label>
                <input type="text" id="nombres" name="nombres" value="<?php echo htmlspecialchars($usuario_para_editar['nombres'] ?? ($_POST['nombres'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            </div>
            <div class="grupo-formulario">
                <label for="apellidos">Apellidos:</label>
                <input type="text" id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($usuario_para_editar['apellidos'] ?? ($_POST['apellidos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            </div>
            <div class="grupo-formulario">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario_para_editar['email'] ?? ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            </div>
            <div class="grupo-formulario">
                <label for="id_rol">Rol:</label>
                <select id="id_rol" name="id_rol" required>
                    <option value="">-- Seleccione un rol --</option>
                    <?php foreach($roles_disponibles as $rol): ?>
                        <option value="<?php echo $rol['id_rol']; ?>" <?php if (($usuario_para_editar['id_rol'] ?? ($_POST['id_rol'] ?? '')) == $rol['id_rol']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($rol['nombre_rol'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grupo-formulario">
                <label for="cargo">Cargo:</label>
                <input type="text" id="cargo" name="cargo" value="<?php echo htmlspecialchars($usuario_para_editar['cargo'] ?? ($_POST['cargo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
            </div>
            <div class="grupo-formulario">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" <?php echo ($accion_crud === 'crear') ? 'required' : ''; ?> minlength="8">
                <?php if ($accion_crud === 'editar'): ?><small>Dejar en blanco para no cambiar la contraseña actual.</small><?php endif; ?>
            </div>
            <div class="grupo-formulario">
                <label for="confirmar_contrasena">Confirmar Contraseña:</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" <?php echo ($accion_crud === 'crear') ? 'required' : ''; ?> minlength="8">
            </div>
             <div class="grupo-formulario">
                <label for="estado">Estado de la Cuenta:</label>
                <select id="estado" name="estado" required>
                    <option value="activo" <?php if (($usuario_para_editar['estado'] ?? ($_POST['estado'] ?? 'activo')) == 'activo') echo 'selected'; ?>>Activo</option>
                    <option value="inactivo" <?php if (($usuario_para_editar['estado'] ?? '') == 'inactivo') echo 'selected'; ?>>Inactivo</option>
                    <option value="bloqueado" <?php if (($usuario_para_editar['estado'] ?? '') == 'bloqueado') echo 'selected'; ?>>Bloqueado</option>
                </select>
            </div>

            <?php if ($accion_crud === 'crear'): ?>
                <button type="submit" name="guardar_usuario_nuevo" class="boton boton-primario">Crear Usuario</button>
            <?php else: ?>
                <button type="submit" name="actualizar_usuario_existente" class="boton boton-primario">Actualizar Usuario</button>
            <?php endif; ?>
            <a href="index.php?vista=sistema_usuarios_crud" class="boton boton-secundario">Cancelar</a>
        </form>
    </div>
<?php endif; ?>


<?php if ($accion_crud === 'listar'): ?>
    <p><a href="index.php?vista=sistema_usuarios_crud&accion_crud=crear" class="boton boton-exito">Crear Nuevo Usuario</a></p>

    <?php if (empty($usuarios)): ?>
        <div class="alert alert-info">No hay usuarios registrados en el sistema.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombres</th>
                        <th>Apellidos</th>
                        <th>Email</th>
                        <th>Cargo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usr): ?>
                        <tr>
                            <td><?php echo $usr['id_usuario']; ?></td>
                            <td><?php echo htmlspecialchars($usr['nombre_usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['apellidos'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['cargo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['nombre_rol'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="estado-usuario estado-<?php echo $usr['estado']; ?>"><?php echo ucfirst($usr['estado']); ?></span></td>
                            <td>
                                <a href="index.php?vista=sistema_usuarios_crud&accion_crud=editar&id_user=<?php echo $usr['id_usuario']; ?>" class="boton-tabla editar" title="Editar">✏️</a>
                                <?php if ($usr['id_usuario'] != $id_usuario_actual): // Comentario: No permitir auto-eliminación. ?>
                                <a href="index.php?vista=sistema_usuarios_crud&accion_crud=eliminar&id_user=<?php echo $usr['id_usuario']; ?>&confirmar_eliminar=1"
                                   class="boton-tabla eliminar confirmar-accion"
                                   data-mensaje-confirmacion="¿Está seguro de ELIMINAR al usuario '<?php echo htmlspecialchars($usr['nombre_usuario'], ENT_QUOTES, 'UTF-8'); ?>'? Esta acción podría ser irreversible y afectar registros asociados."
                                   title="Eliminar">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); margin-bottom: 1.5rem; }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.estado-usuario { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-activo { background-color: var(--color-exito); }
.estado-inactivo { background-color: var(--color-secundario); }
.estado-bloqueado { background-color: var(--color-error); }
.boton-tabla.editar { background-color: var(--color-advertencia); color:black; }
.boton-tabla.eliminar { background-color: var(--color-error); color:white; }
</style>

<?php
// Comentario: Fin del archivo vistas/sistema_usuarios_crud.php
?>
