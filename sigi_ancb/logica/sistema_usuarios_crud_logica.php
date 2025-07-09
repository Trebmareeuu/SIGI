<?php
// Archivo: logica/sistema_usuarios_crud_logica.php
// Propósito: Lógica para el CRUD de usuarios (Sistemas).

// Comentario: Asegurarse de que las funciones y $pdo estén disponibles.
// Comentario: index.php ya incluye config.php y funciones.php.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CRUD_USUARIOS_SISTEMA', $id_usuario_actual)) {
    mensaje_flash('error_usuarios_crud', 'No tiene permisos para gestionar usuarios.', 'alert-danger');
    redirigir('index.php?vista=dashboard'); // Comentario: Esta redirección es segura aquí.
}

global $pdo; // Comentario: $pdo es global desde config.php.

// Comentario: Variables y Lógica para listar usuarios.
$usuarios = []; // Comentario: Inicializar para que esté disponible en la vista incluso si la carga falla.
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
    // Comentario: No redirigir aquí, permitir que la vista muestre el error.
}

// Comentario: Cargar roles para el formulario de creación/edición.
$roles_disponibles = [];
try {
    $stmt_roles = $pdo->query("SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol ASC");
    $roles_disponibles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar roles: " . $e->getMessage());
    mensaje_flash('error_usuarios_crud', 'Error crítico: No se pudieron cargar los roles para el formulario.', 'alert-danger');
    // Comentario: Considerar si es un error fatal para el formulario.
}


// Comentario: Acción (crear, editar, eliminar).
// Comentario: $_GET['vista'] ya está procesado por index.php.
// Comentario: Aquí se manejan sub-acciones dentro de la vista del CRUD de usuarios.
$accion_crud = $_GET['accion_crud'] ?? 'listar'; // 'listar', 'crear', 'editar', 'eliminar'
$id_usuario_editar = null;
$usuario_para_editar = null; // Comentario: Para poblar el formulario de edición.

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
                $accion_crud = 'listar'; // Comentario: Volver a listar si no se encuentra.
                                        // Comentario: O redirigir, pero como es lógica, es mejor setear y que index.php muestre.
            }
        } catch (PDOException $e) {
            error_log("Error al cargar usuario para editar ID $id_usuario_editar: " . $e->getMessage());
            mensaje_flash('error_usuarios_crud', 'Error al cargar datos del usuario para edición.', 'alert-danger');
            $accion_crud = 'listar';
        }
    } else {
        mensaje_flash('error_usuarios_crud', 'ID de usuario no válido para editar.', 'alert-danger');
        $accion_crud = 'listar';
    }
}

// Comentario: Lógica para CREAR o ACTUALIZAR usuario.
// Comentario: Esta es la sección que causaba problemas por la redirección.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_usuario_nuevo']) || isset($_POST['actualizar_usuario_existente'])) {

        $id_usuario_form = filter_input(INPUT_POST, 'id_usuario_hidden', FILTER_VALIDATE_INT);
        $nombre_usuario_form = sanitizar_entrada($_POST['nombre_usuario'] ?? '');
        $nombres_form = sanitizar_entrada($_POST['nombres'] ?? '');
        $apellidos_form = sanitizar_entrada($_POST['apellidos'] ?? '');
        $email_form = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $id_rol_form = filter_var($_POST['id_rol'] ?? '', FILTER_VALIDATE_INT);
        $cargo_form = sanitizar_entrada($_POST['cargo'] ?? '');
        $estado_form = sanitizar_entrada($_POST['estado'] ?? 'activo');
        $contrasena_form = $_POST['contrasena'] ?? '';
        $confirmar_contrasena_form = $_POST['confirmar_contrasena'] ?? '';

        $errores_form_usuario = [];
        if (empty($nombre_usuario_form)) $errores_form_usuario[] = "El nombre de usuario es obligatorio.";
        if (empty($nombres_form)) $errores_form_usuario[] = "Los nombres son obligatorios.";
        if (empty($apellidos_form)) $errores_form_usuario[] = "Los apellidos son obligatorios.";
        if (empty($email_form) || !filter_var($email_form, FILTER_VALIDATE_EMAIL)) $errores_form_usuario[] = "El correo electrónico no es válido o está vacío.";
        if (empty($id_rol_form)) $errores_form_usuario[] = "Debe seleccionar un rol para el usuario.";

        $actualizar_contrasena = false;
        if (isset($_POST['guardar_usuario_nuevo'])) {
            if (empty($contrasena_form)) $errores_form_usuario[] = "La contraseña es obligatoria para nuevos usuarios.";
            elseif (strlen($contrasena_form) < 8) $errores_form_usuario[] = "La contraseña debe tener al menos 8 caracteres.";
            elseif ($contrasena_form !== $confirmar_contrasena_form) $errores_form_usuario[] = "Las contraseñas no coinciden.";
            else $actualizar_contrasena = true;
        } elseif (isset($_POST['actualizar_usuario_existente']) && !empty($contrasena_form)) {
            if (strlen($contrasena_form) < 8) $errores_form_usuario[] = "La nueva contraseña debe tener al menos 8 caracteres.";
            elseif ($contrasena_form !== $confirmar_contrasena_form) $errores_form_usuario[] = "Las nuevas contraseñas no coinciden.";
            else $actualizar_contrasena = true;
        }

        $id_excluir_check = $id_usuario_form ?: 0;
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
                if (isset($_POST['guardar_usuario_nuevo'])) {
                    $hash_contrasena = hashear_contrasena($contrasena_form);
                    $sql_insert = "INSERT INTO usuarios (nombre_usuario, contrasena, nombres, apellidos, email, id_rol, cargo, estado)
                                   VALUES (:nu, :pass, :n, :a, :e, :rol, :cargo, :est)";
                    $stmt_op = $pdo->prepare($sql_insert);
                    $stmt_op->execute([
                        ':nu' => $nombre_usuario_form, ':pass' => $hash_contrasena, ':n' => $nombres_form, ':a' => $apellidos_form,
                        ':e' => $email_form, ':rol' => $id_rol_form, ':cargo' => $cargo_form, ':est' => $estado_form
                    ]);
                    mensaje_flash('exito_usuarios_crud', 'Usuario creado exitosamente.', 'alert-success');
                    redirigir('index.php?vista=sistema_usuarios_crud'); // Comentario: Redirección segura.

                } elseif (isset($_POST['actualizar_usuario_existente']) && $id_usuario_form) {
                    $params_update = [
                        ':nu' => $nombre_usuario_form, ':n' => $nombres_form, ':a' => $apellidos_form,
                        ':e' => $email_form, ':rol' => $id_rol_form, ':cargo' => $cargo_form, ':est' => $estado_form, ':id_user_upd' => $id_usuario_form
                    ];
                    $sql_update_parts = ["nombre_usuario = :nu", "nombres = :n", "apellidos = :a", "email = :e", "id_rol = :rol", "cargo = :cargo", "estado = :est"];
                    if ($actualizar_contrasena) {
                        $hash_contrasena_nueva = hashear_contrasena($contrasena_form);
                        $sql_update_parts[] = "contrasena = :pass";
                        $params_update[':pass'] = $hash_contrasena_nueva;
                    }
                    $sql_update = "UPDATE usuarios SET " . implode(", ", $sql_update_parts) . " WHERE id_usuario = :id_user_upd";
                    $stmt_op = $pdo->prepare($sql_update);
                    $stmt_op->execute($params_update);
                    mensaje_flash('exito_usuarios_crud', 'Usuario actualizado exitosamente.', 'alert-success');
                    redirigir('index.php?vista=sistema_usuarios_crud'); // Comentario: Redirección segura.
                }
            } catch (PDOException $e) {
                error_log("Error al guardar/actualizar usuario: " . $e->getMessage());
                mensaje_flash('error_form_usuario_submit', 'Error al procesar la solicitud: ' . $e->getMessage(), 'alert-danger');
                // Comentario: No redirigir aquí para que el usuario vea el error en el contexto del formulario.
                // Comentario: La vista se recargará, y los mensajes flash se mostrarán.
                // Comentario: Si es edición, $accion_crud y $usuario_para_editar deben persistir.
                if (isset($_POST['actualizar_usuario_existente'])) {
                    $accion_crud = 'editar'; // Comentario: Para re-mostrar el form de edición.
                    // Comentario: $usuario_para_editar ya está cargado si se llegó por GET.
                    // Comentario: Si falla un POST de edición, los datos del form vendrán de $_POST en la vista.
                } else {
                     $accion_crud = 'crear'; // Comentario: Para re-mostrar el form de creación.
                }
            }
        } else { // Comentario: Hay errores de validación del formulario.
            foreach ($errores_form_usuario as $err_u) {
                mensaje_flash('error_form_usuario_validation', $err_u, 'alert-danger');
            }
            if (isset($_POST['actualizar_usuario_existente'])) {
                $accion_crud = 'editar';
                 // Comentario: Recargar datos del usuario para editar, ya que el POST falló y no queremos perder el contexto.
                if($id_usuario_form) { $id_usuario_editar = $id_usuario_form; /* $usuario_para_editar se recarga arriba */ }
            } else {
                $accion_crud = 'crear';
            }
            // Comentario: No redirigir, dejar que la vista muestre los errores y repoble el formulario con $_POST.
        }
    }
}


// Comentario: Lógica para ELIMINAR usuario.
// Comentario: Esto también debe ocurrir ANTES de cualquier salida HTML.
if ($accion_crud === 'eliminar' && isset($_GET['id_user']) && isset($_GET['confirmar_eliminar'])) {
    $id_usuario_eliminar = filter_var($_GET['id_user'], FILTER_VALIDATE_INT);
    if ($id_usuario_eliminar) {
        if ($id_usuario_eliminar == $id_usuario_actual) {
            mensaje_flash('error_usuarios_crud', 'No puede eliminar su propia cuenta de usuario.', 'alert-warning');
        } else {
            try {
                $stmt_del = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id_user_del");
                $stmt_del->bindParam(':id_user_del', $id_usuario_eliminar, PDO::PARAM_INT);
                $stmt_del->execute();
                if ($stmt_del->rowCount() > 0) {
                    mensaje_flash('exito_usuarios_crud', 'Usuario eliminado exitosamente.', 'alert-success');
                } else {
                    mensaje_flash('error_usuarios_crud', 'No se pudo eliminar el usuario o ya no existía.', 'alert-warning');
                }
            } catch (PDOException $e) {
                error_log("Error al eliminar usuario ID $id_usuario_eliminar: " . $e->getMessage());
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
    redirigir('index.php?vista=sistema_usuarios_crud'); // Comentario: Redirección segura.
}

// Comentario: Fin de logica/sistema_usuarios_crud_logica.php
// Comentario: Las variables $usuarios, $roles_disponibles, $accion_crud, $usuario_para_editar
// Comentario: estarán disponibles para el archivo vistas/sistema_usuarios_crud.php
?>
