<?php
// Archivo: vistas/perfil.php
// Propósito: Permite al usuario ver y actualizar su información personal y cambiar su contraseña.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Asumimos que config.php, funciones.php, header.php ya han sido incluidos por index.php
// Comentario: y que la sesión del usuario está activa y verificada.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Obtiene el ID del usuario actual.
if (!$id_usuario_actual) {
    // Comentario: Si por alguna razón no hay ID de usuario, redirigir al login.
    mensaje_flash('error_perfil', 'No se pudo identificar al usuario. Por favor, inicie sesión.', 'alert-danger');
    redirigir('index.php?vista=login');
}

global $pdo; // Comentario: Acceder a la conexión PDO.
$usuario_info = null; // Comentario: Variable para almacenar la información del usuario.
$error_carga = ''; // Comentario: Variable para errores al cargar datos.
$mensaje_exito = ''; // Comentario: Variable para mensajes de éxito.

// Comentario: Cargar datos del usuario actual.
try {
    $stmt = $pdo->prepare("SELECT nombre_usuario, nombres, apellidos, email, cargo, telefono FROM usuarios WHERE id_usuario = :id_usuario");
    $stmt->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
    $stmt->execute();
    $usuario_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario_info) {
        $error_carga = "No se pudieron cargar los datos del perfil. Usuario no encontrado.";
    }
} catch (PDOException $e) {
    error_log("Error al cargar datos del perfil para usuario ID $id_usuario_actual: " . $e->getMessage());
    $error_carga = "Ocurrió un error al cargar sus datos. Intente más tarde.";
}

// Comentario: Procesamiento de la actualización de datos personales.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_datos'])) {
    // Comentario: Validar y sanitizar entradas.
    $nombres = sanitizar_entrada($_POST['nombres'] ?? '');
    $apellidos = sanitizar_entrada($_POST['apellidos'] ?? '');
    $email = sanitizar_entrada($_POST['email'] ?? ''); // Comentario: Validar formato email.
    $telefono = sanitizar_entrada($_POST['telefono'] ?? '');
    $cargo = sanitizar_entrada($_POST['cargo'] ?? ''); // Comentario: El cargo podría no ser editable por el usuario.

    // Comentario: Validaciones básicas.
    if (empty($nombres) || empty($apellidos)) {
        mensaje_flash('error_perfil_datos', 'Los nombres y apellidos son obligatorios.', 'alert-danger');
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        mensaje_flash('error_perfil_datos', 'El formato del correo electrónico no es válido.', 'alert-danger');
    } else {
        // Comentario: Verificar si el email ya existe para otro usuario.
        $email_existe = false;
        if (!empty($email)) {
            try {
                $stmt_email = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id_usuario_actual");
                $stmt_email->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt_email->bindParam(':id_usuario_actual', $id_usuario_actual, PDO::PARAM_INT);
                $stmt_email->execute();
                if ($stmt_email->fetch()) {
                    $email_existe = true;
                }
            } catch (PDOException $e) {
                 error_log("Error al verificar email en perfil: " . $e->getMessage());
                 mensaje_flash('error_perfil_datos', 'Error al verificar el email. Intente más tarde.', 'alert-danger');
                 // Comentario: Para evitar que se actualice con un email duplicado si la verificación falla, podríamos no continuar.
                 // Comentario: O permitir continuar y que la BD falle si hay constraint UNIQUE.
            }
        }

        if ($email_existe) {
            mensaje_flash('error_perfil_datos', 'El correo electrónico ingresado ya está en uso por otro usuario.', 'alert-danger');
        } else {
            // Comentario: Actualizar datos en la base de datos.
            try {
                // Comentario: El cargo usualmente no es editable por el usuario directamente, pero se incluye si el formulario lo permite.
                $sql_update = "UPDATE usuarios SET nombres = :nombres, apellidos = :apellidos, email = :email, telefono = :telefono, cargo = :cargo WHERE id_usuario = :id_usuario";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':nombres', $nombres, PDO::PARAM_STR);
                $stmt_update->bindParam(':apellidos', $apellidos, PDO::PARAM_STR);
                $stmt_update->bindParam(':email', $email, PDO::PARAM_STR); // Comentario: Guardar NULL si está vacío y la BD lo permite.
                $stmt_update->bindParam(':telefono', $telefono, PDO::PARAM_STR);
                $stmt_update->bindParam(':cargo', $cargo, PDO::PARAM_STR); // Comentario: Asegurarse que el usuario tenga permiso para cambiar esto.
                $stmt_update->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    mensaje_flash('exito_perfil_datos', 'Sus datos personales han sido actualizados correctamente.', 'alert-success');
                    // Comentario: Recargar los datos del usuario para mostrar la información actualizada.
                    $stmt->execute(); // Comentario: Re-ejecuta la consulta inicial para obtener datos frescos.
                    $usuario_info = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    mensaje_flash('error_perfil_datos', 'No se pudieron actualizar sus datos. Intente más tarde.', 'alert-danger');
                }
            } catch (PDOException $e) {
                error_log("Error al actualizar datos del perfil para usuario ID $id_usuario_actual: " . $e->getMessage());
                mensaje_flash('error_perfil_datos', 'Ocurrió un error al actualizar sus datos. Intente más tarde.', 'alert-danger');
            }
        }
    }
    // Comentario: Redirigir a la misma página para limpiar el POST y mostrar mensajes flash.
    redirigir('index.php?vista=perfil');
    exit;
}

// Comentario: Procesamiento del cambio de contraseña.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_contrasena'])) {
    $contrasena_actual = $_POST['contrasena_actual'] ?? '';
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $confirmar_nueva_contrasena = $_POST['confirmar_nueva_contrasena'] ?? '';

    if (empty($contrasena_actual) || empty($nueva_contrasena) || empty($confirmar_nueva_contrasena)) {
        mensaje_flash('error_perfil_pass', 'Todos los campos de contraseña son obligatorios.', 'alert-danger');
    } elseif ($nueva_contrasena !== $confirmar_nueva_contrasena) {
        mensaje_flash('error_perfil_pass', 'La nueva contraseña y su confirmación no coinciden.', 'alert-danger');
    } elseif (strlen($nueva_contrasena) < 8) { // Comentario: Ejemplo de política de contraseña mínima.
        mensaje_flash('error_perfil_pass', 'La nueva contraseña debe tener al menos 8 caracteres.', 'alert-danger');
    } else {
        try {
            // Comentario: Obtener la contraseña actual hasheada del usuario.
            $stmt_pass = $pdo->prepare("SELECT contrasena FROM usuarios WHERE id_usuario = :id_usuario");
            $stmt_pass->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
            $stmt_pass->execute();
            $hash_actual = $stmt_pass->fetchColumn();

            if ($hash_actual && verificar_contrasena($contrasena_actual, $hash_actual)) {
                // Comentario: Contraseña actual correcta, proceder a hashear y guardar la nueva.
                $nuevo_hash = hashear_contrasena($nueva_contrasena);

                $stmt_update_pass = $pdo->prepare("UPDATE usuarios SET contrasena = :nueva_contrasena WHERE id_usuario = :id_usuario");
                $stmt_update_pass->bindParam(':nueva_contrasena', $nuevo_hash, PDO::PARAM_STR);
                $stmt_update_pass->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);

                if ($stmt_update_pass->execute()) {
                    mensaje_flash('exito_perfil_pass', 'Su contraseña ha sido cambiada exitosamente.', 'alert-success');
                    // Comentario: Opcional: Forzar logout o enviar notificación por email.
                } else {
                    mensaje_flash('error_perfil_pass', 'No se pudo cambiar la contraseña. Intente más tarde.', 'alert-danger');
                }
            } else {
                mensaje_flash('error_perfil_pass', 'La contraseña actual ingresada es incorrecta.', 'alert-danger');
            }
        } catch (PDOException $e) {
            error_log("Error al cambiar contraseña para usuario ID $id_usuario_actual: " . $e->getMessage());
            mensaje_flash('error_perfil_pass', 'Ocurrió un error al cambiar la contraseña. Intente más tarde.', 'alert-danger');
        }
    }
    // Comentario: Redirigir a la misma página para limpiar el POST y mostrar mensajes flash.
    redirigir('index.php?vista=perfil');
    exit;
}

?>

<h2>Mi Perfil</h2>

<?php if ($error_carga): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_carga, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php
// Comentario: Mostrar mensajes flash para datos personales.
mensaje_flash('error_perfil_datos');
mensaje_flash('exito_perfil_datos');
?>

<?php if ($usuario_info): ?>
<div class="perfil-contenedor">
    <section class="perfil-seccion">
        <h3>Datos Personales</h3>
        <form action="index.php?vista=perfil" method="POST" class="validar-js">
            <div class="grupo-formulario">
                <label for="nombre_usuario_display">Nombre de Usuario (no editable):</label>
                <input type="text" id="nombre_usuario_display" name="nombre_usuario_display" value="<?php echo htmlspecialchars($usuario_info['nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                <!-- Comentario: 'disabled' evita que se envíe, 'readonly' solo evita edición pero se envía. Usar disabled si no se procesa en backend. -->
            </div>

            <div class="grupo-formulario">
                <label for="nombres">Nombres:</label>
                <input type="text" id="nombres" name="nombres" value="<?php echo htmlspecialchars($usuario_info['nombres'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="grupo-formulario">
                <label for="apellidos">Apellidos:</label>
                <input type="text" id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($usuario_info['apellidos'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="grupo-formulario">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario_info['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="grupo-formulario">
                <label for="cargo">Cargo (informativo):</label>
                <input type="text" id="cargo_display" name="cargo_display" value="<?php echo htmlspecialchars($usuario_info['cargo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                 <!-- Comentario: Si el cargo es editable, cambiar el input y el name, y procesar en el backend. -->
                 <!-- <input type="text" id="cargo" name="cargo" value="<?php echo htmlspecialchars($usuario_info['cargo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"> -->
            </div>

            <div class="grupo-formulario">
                <label for="telefono">Teléfono:</label>
                <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($usuario_info['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <!-- Comentario: Campo oculto para identificar la acción -->
            <input type="hidden" name="actualizar_datos" value="1">

            <button type="submit" class="boton">Actualizar Datos</button>
        </form>
    </section>

    <hr class="my-4"> <!-- Comentario: Separador visual -->

    <section class="perfil-seccion">
        <h3>Cambiar Contraseña</h3>
        <?php
        // Comentario: Mostrar mensajes flash para cambio de contraseña.
        mensaje_flash('error_perfil_pass');
        mensaje_flash('exito_perfil_pass');
        ?>
        <form action="index.php?vista=perfil" method="POST" class="validar-js">
            <div class="grupo-formulario">
                <label for="contrasena_actual">Contraseña Actual:</label>
                <input type="password" id="contrasena_actual" name="contrasena_actual" required>
            </div>

            <div class="grupo-formulario">
                <label for="nueva_contrasena">Nueva Contraseña:</label>
                <input type="password" id="nueva_contrasena" name="nueva_contrasena" required minlength="8">
                <small>Mínimo 8 caracteres.</small>
            </div>

            <div class="grupo-formulario">
                <label for="confirmar_nueva_contrasena">Confirmar Nueva Contraseña:</label>
                <input type="password" id="confirmar_nueva_contrasena" name="confirmar_nueva_contrasena" required minlength="8">
            </div>

            <!-- Comentario: Campo oculto para identificar la acción -->
            <input type="hidden" name="cambiar_contrasena" value="1">

            <button type="submit" class="boton">Cambiar Contraseña</button>
        </form>
    </section>
</div>

<?php else: ?>
    <?php if (!$error_carga): // Comentario: Si no hubo error de carga pero no hay $usuario_info (caso improbable si $id_usuario_actual es válido) ?>
        <p>No se encontró información del perfil.</p>
    <?php endif; ?>
<?php endif; ?>

<style>
/* Comentario: Estilos específicos para la página de perfil, si son necesarios. */
.perfil-contenedor {
    /* Comentario: Podría usar display: grid o flex para dos columnas si se desea. */
}
.perfil-seccion {
    background-color: #fdfdfd; /* Comentario: Fondo ligeramente distinto para secciones. */
    padding: 1.5rem; /* Comentario: Padding interno. */
    border: 1px solid #eee; /* Comentario: Borde sutil. */
    border-radius: var(--borde-radio); /* Comentario: Bordes redondeados. */
    margin-bottom: 2rem; /* Comentario: Margen inferior entre secciones. */
}
.perfil-seccion h3 {
    margin-top: 0;
    color: var(--color-primario);
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}
hr.my-4 { /* Comentario: Estilo para un separador más pronunciado si se usa. */
    margin-top: 2rem;
    margin-bottom: 2rem;
    border: 0;
    border-top: 1px solid rgba(0,0,0,.1);
}
</style>

<?php
// Comentario: Fin del archivo vistas/perfil.php
?>
