<?php
// Archivo: vistas/solicitud_activo.php
// Propósito: Formulario para que los usuarios soliciten muebles o equipos (activos).
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('SOLICITAR_ACTIVO', $id_usuario_actual)) {
    mensaje_flash('error_sol_act', 'No tiene permisos para solicitar activos (muebles/equipos).', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para el formulario.
$tipo_activo_solicitado = $_POST['tipo_activo'] ?? ''; // Ej: 'mobiliario', 'equipo_computacion', 'otro'
$descripcion_activo = $_POST['descripcion_activo'] ?? ''; // Comentario: Características detalladas del activo.
$justificacion_activo = $_POST['justificacion_activo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitud_activo'])) {
    $tipo_activo_solicitado = sanitizar_entrada($_POST['tipo_activo'] ?? '');
    $descripcion_activo = strip_tags($_POST['descripcion_activo'] ?? '', '<p><br><ul><ol><li>');
    $justificacion_activo = strip_tags($_POST['justificacion_activo'] ?? '');

    $errores_formulario = [];
    if (empty($tipo_activo_solicitado)) {
        $errores_formulario[] = "Debe seleccionar el tipo de activo que solicita.";
    }
    if (empty($descripcion_activo)) {
        $errores_formulario[] = "La descripción del activo (características, modelo, etc.) es obligatoria.";
    }
    if (empty($justificacion_activo)) {
        $errores_formulario[] = "La justificación de la solicitud es obligatoria.";
    }

    if (empty($errores_formulario)) {
        try {
            // Comentario: El estado inicial será 'pendiente_aprobacion_admin'.
            $sql = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud, fecha_solicitud)
                    VALUES (:id_usuario, 'activo_mueble_equipo', :descripcion, 'pendiente_aprobacion_admin', NOW())";
            $stmt = $pdo->prepare($sql);

            // Comentario: Concatenar tipo, descripción y justificación en el campo 'descripcion_solicitud'.
            $descripcion_completa = "Tipo de Activo Solicitado: " . ucfirst(str_replace('_', ' ', $tipo_activo_solicitado)) . "\n";
            $descripcion_completa .= "Descripción Detallada del Activo:\n" . $descripcion_activo . "\n\n";
            $descripcion_completa .= "Justificación:\n" . $justificacion_activo;

            $stmt->execute([
                ':id_usuario' => $id_usuario_actual,
                ':descripcion' => $descripcion_completa
            ]);
            $id_solicitud_creada = $pdo->lastInsertId();

            // Comentario: Registrar en historial (si aplica).
            // registrar_historial_solicitud($id_solicitud_creada, $id_usuario_actual, 'Creación Solicitud Activo', 'Solicitud enviada para aprobación.');

            mensaje_flash('exito_sol_act', 'Su solicitud de activo (mueble/equipo) ha sido enviada exitosamente y está pendiente de aprobación.', 'alert-success');
            redirigir('index.php?vista=solicitudes_historial');

        } catch (PDOException $e) {
            error_log("Error al guardar solicitud de activo: " . $e->getMessage());
            mensaje_flash('error_sol_act', 'Ocurrió un error al procesar su solicitud. Intente más tarde.', 'alert-danger');
        }
    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_sol_act_form', $error, 'alert-danger');
        }
    }
}

?>
<h2>Solicitud de Activos (Muebles/Equipos)</h2>

<?php
mensaje_flash('error_sol_act');
mensaje_flash('error_sol_act_form');
mensaje_flash('exito_sol_act');
?>

<p>Por favor, complete el formulario para solicitar muebles, equipos u otros activos para su trabajo.</p>

<form action="index.php?vista=solicitud_activo" method="POST" class="validar-js">
    <div class="grupo-formulario">
        <label for="tipo_activo">Tipo de Activo Solicitado:</label>
        <select id="tipo_activo" name="tipo_activo" required>
            <option value="">-- Seleccione un tipo --</option>
            <option value="mobiliario_oficina" <?php echo ($tipo_activo_solicitado === 'mobiliario_oficina') ? 'selected' : ''; ?>>Mobiliario de Oficina (silla, escritorio, etc.)</option>
            <option value="equipo_computacion" <?php echo ($tipo_activo_solicitado === 'equipo_computacion') ? 'selected' : ''; ?>>Equipo de Computación (PC, laptop, monitor, impresora, etc.)</option>
            <option value="equipo_comunicacion" <?php echo ($tipo_activo_solicitado === 'equipo_comunicacion') ? 'selected' : ''; ?>>Equipo de Comunicación (teléfono, celular, etc.)</option>
            <option value="software" <?php echo ($tipo_activo_solicitado === 'software') ? 'selected' : ''; ?>>Software o Licencias</option>
            <option value="otro_activo" <?php echo ($tipo_activo_solicitado === 'otro_activo') ? 'selected' : ''; ?>>Otro Tipo de Activo</option>
        </select>
    </div>

    <div class="grupo-formulario">
        <label for="descripcion_activo">Descripción Detallada del Activo:</label>
        <textarea id="descripcion_activo" name="descripcion_activo" rows="5" required placeholder="Ej: Silla ergonómica con soporte lumbar, Laptop Core i7 con 16GB RAM, Licencia de Microsoft Office..."><?php echo htmlspecialchars($descripcion_activo, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Sea lo más específico posible (marca, modelo, características técnicas, etc.).</small>
    </div>

    <div class="grupo-formulario">
        <label for="justificacion_activo">Justificación de la Solicitud:</label>
        <textarea id="justificacion_activo" name="justificacion_activo" rows="4" required><?php echo htmlspecialchars($justificacion_activo, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Explique por qué necesita este activo y cómo contribuirá a sus funciones.</small>
    </div>

    <div class="grupo-formulario acciones-formulario">
        <button type="submit" name="enviar_solicitud_activo" class="boton boton-primario">Enviar Solicitud</button>
        <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
    </div>
</form>

<?php
// Comentario: Fin del archivo vistas/solicitud_activo.php
?>
