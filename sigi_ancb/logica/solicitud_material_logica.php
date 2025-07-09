<?php
// Archivo: logica/solicitud_material_logica.php
// Propósito: Lógica para el formulario de solicitud de material de escritorio.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('SOLICITAR_MATERIAL', $id_usuario_actual)) {
    mensaje_flash('error_sol_mat', 'No tiene permisos para solicitar material de escritorio.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para repoblar el formulario.
$descripcion_material = $_POST['descripcion_material'] ?? '';
$justificacion_material = $_POST['justificacion_material'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitud_material'])) {
    // Comentario: Re-asignar desde POST para validación.
    $descripcion_material = strip_tags($_POST['descripcion_material'] ?? '', '<p><br><ul><ol><li>');
    $justificacion_material = strip_tags($_POST['justificacion_material'] ?? '');

    $errores_formulario = [];
    if (empty($descripcion_material)) $errores_formulario[] = "La descripción del material (qué solicita y cantidades) es obligatoria.";
    if (empty($justificacion_material)) $errores_formulario[] = "La justificación de la solicitud es obligatoria.";

    if (empty($errores_formulario)) {
        try {
            $sql = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud, fecha_solicitud)
                    VALUES (:id_usuario, 'material_escritorio', :descripcion, 'pendiente_aprobacion_admin', NOW())";
            $stmt = $pdo->prepare($sql);

            $descripcion_completa = "Materiales Solicitados:\n" . $descripcion_material . "\n\nJustificación:\n" . $justificacion_material;

            $stmt->execute([':id_usuario' => $id_usuario_actual, ':descripcion' => $descripcion_completa]);

            mensaje_flash('exito_sol_mat', 'Su solicitud de material de escritorio ha sido enviada exitosamente y está pendiente de aprobación.', 'alert-success');
            redirigir('index.php?vista=solicitudes_historial');

        } catch (PDOException $e) {
            error_log("Error al guardar solicitud de material para usuario ID $id_usuario_actual: " . $e->getMessage());
            mensaje_flash('error_sol_mat_form', 'Ocurrió un error al procesar su solicitud. Intente más tarde.', 'alert-danger');
            // Comentario: No redirigir, la vista usará los valores de $_POST para repoblar.
        }
    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_sol_mat_form', $error, 'alert-danger');
        }
        // Comentario: No redirigir, la vista usará los valores de $_POST para repoblar.
    }
}

// Comentario: Fin de logica/solicitud_material_logica.php
?>
