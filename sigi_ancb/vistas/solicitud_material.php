<?php
// Archivo: vistas/solicitud_material.php
// Propósito: Formulario para que los usuarios soliciten material de escritorio.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('SOLICITAR_MATERIAL', $id_usuario_actual)) {
    mensaje_flash('error_sol_mat', 'No tiene permisos para solicitar material de escritorio.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para el formulario.
$descripcion_material = $_POST['descripcion_material'] ?? ''; // Comentario: Detalle de los materiales y cantidades.
$justificacion_material = $_POST['justificacion_material'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitud_material'])) {
    $descripcion_material = strip_tags($_POST['descripcion_material'] ?? '', '<p><br><ul><ol><li>'); // Comentario: Permitir algunas etiquetas.
    $justificacion_material = strip_tags($_POST['justificacion_material'] ?? '');

    $errores_formulario = [];
    if (empty($descripcion_material)) {
        $errores_formulario[] = "La descripción del material (qué solicita y cantidades) es obligatoria.";
    }
    if (empty($justificacion_material)) {
        $errores_formulario[] = "La justificación de la solicitud es obligatoria.";
    }

    if (empty($errores_formulario)) {
        try {
            // Comentario: El estado inicial será 'pendiente_aprobacion_admin' para este tipo de solicitud.
            $sql = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud, fecha_solicitud)
                    VALUES (:id_usuario, 'material_escritorio', :descripcion, 'pendiente_aprobacion_admin', NOW())";
            $stmt = $pdo->prepare($sql);

            // Comentario: La descripción de la solicitud aquí contendrá tanto la lista de materiales como la justificación.
            // Comentario: Se podría concatenar o guardar en campos separados si la BD lo permite (ej. un campo 'items' y otro 'justificacion').
            // Comentario: Por ahora, se concatena en 'descripcion_solicitud'.
            $descripcion_completa = "Materiales Solicitados:\n" . $descripcion_material . "\n\nJustificación:\n" . $justificacion_material;

            $stmt->execute([
                ':id_usuario' => $id_usuario_actual,
                ':descripcion' => $descripcion_completa
            ]);
            $id_solicitud_creada = $pdo->lastInsertId();

            // Comentario: Registrar en historial (si aplica).
            // registrar_historial_solicitud($id_solicitud_creada, $id_usuario_actual, 'Creación Solicitud Material', 'Solicitud enviada para aprobación.');

            mensaje_flash('exito_sol_mat', 'Su solicitud de material de escritorio ha sido enviada exitosamente y está pendiente de aprobación.', 'alert-success');
            redirigir('index.php?vista=solicitudes_historial');

        } catch (PDOException $e) {
            error_log("Error al guardar solicitud de material: " . $e->getMessage());
            mensaje_flash('error_sol_mat', 'Ocurrió un error al procesar su solicitud. Intente más tarde.', 'alert-danger');
        }
    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_sol_mat_form', $error, 'alert-danger');
        }
    }
}

?>
<h2>Solicitud de Material de Escritorio</h2>

<?php
mensaje_flash('error_sol_mat');
mensaje_flash('error_sol_mat_form');
mensaje_flash('exito_sol_mat');
?>

<p>Por favor, detalle los materiales de escritorio que necesita y la justificación para su solicitud.</p>

<form action="index.php?vista=solicitud_material" method="POST" class="validar-js">
    <div class="grupo-formulario">
        <label for="descripcion_material">Materiales Solicitados (Incluir cantidades):</label>
        <textarea id="descripcion_material" name="descripcion_material" rows="6" required placeholder="Ej:
- 2 Bolígrafos azules
- 1 Resma de papel bond tamaño carta
- 1 Caja de clips"><?php echo htmlspecialchars($descripcion_material, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Sea específico con los ítems y las cantidades requeridas.</small>
    </div>

    <div class="grupo-formulario">
        <label for="justificacion_material">Justificación de la Solicitud:</label>
        <textarea id="justificacion_material" name="justificacion_material" rows="4" required><?php echo htmlspecialchars($justificacion_material, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Explique brevemente por qué necesita estos materiales.</small>
    </div>

    <div class="grupo-formulario acciones-formulario">
        <button type="submit" name="enviar_solicitud_material" class="boton boton-primario">Enviar Solicitud</button>
        <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
    </div>
</form>

<?php
// Comentario: Fin del archivo vistas/solicitud_material.php
?>
