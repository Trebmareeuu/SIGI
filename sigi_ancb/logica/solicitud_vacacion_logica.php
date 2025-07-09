<?php
// Archivo: logica/solicitud_vacacion_logica.php
// Propósito: Lógica para el formulario de solicitud de vacaciones.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('SOLICITAR_VACACION', $id_usuario_actual)) {
    mensaje_flash('error_sol_vac', 'No tiene permisos para solicitar vacaciones.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para repoblar el formulario.
$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$fecha_fin = $_POST['fecha_fin'] ?? '';
$dias_solicitados = $_POST['dias_solicitados'] ?? '';
$descripcion_solicitud = $_POST['descripcion_solicitud'] ?? '';

// Comentario: $dias_disponibles_vacacion = calcular_dias_vacacion_disponibles($id_usuario_actual, $pdo);
// Comentario: Por ahora, un valor estático para la vista. La lógica real de conteo es compleja.
$dias_disponibles_vacacion_display = 20; // Comentario: Solo para mostrar, no para validar rígidamente aún.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitud_vacacion'])) {
    $fecha_inicio = sanitizar_entrada($_POST['fecha_inicio'] ?? '');
    $fecha_fin = sanitizar_entrada($_POST['fecha_fin'] ?? '');
    $dias_solicitados_post = filter_var($_POST['dias_solicitados'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $descripcion_solicitud = strip_tags($_POST['descripcion_solicitud'] ?? '');

    $errores_formulario = [];
    if (empty($fecha_inicio) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_inicio)) $errores_formulario[] = "La fecha de inicio no es válida.";
    if (empty($fecha_fin) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_fin)) $errores_formulario[] = "La fecha de fin no es válida.";
    if ($dias_solicitados_post === false) $errores_formulario[] = "El número de días solicitados no es válido o es menor a 1.";
    if (empty($descripcion_solicitud)) $errores_formulario[] = "La justificación de la solicitud es obligatoria.";

    if (empty($errores_formulario)) {
        $obj_fecha_inicio = null; $obj_fecha_fin = null;
        try { $obj_fecha_inicio = new DateTime($fecha_inicio); } catch (Exception $e) { $errores_formulario[] = "Fecha de inicio inválida."; }
        try { $obj_fecha_fin = new DateTime($fecha_fin); } catch (Exception $e) { $errores_formulario[] = "Fecha de fin inválida."; }

        if ($obj_fecha_inicio && $obj_fecha_fin && $obj_fecha_fin < $obj_fecha_inicio) {
            $errores_formulario[] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
        } else if ($obj_fecha_inicio && $obj_fecha_fin) {
            $intervalo = $obj_fecha_inicio->diff($obj_fecha_fin);
            $dias_calculados_naturales = $intervalo->days + 1;
            // Comentario: Aquí se podría añadir lógica para calcular días hábiles si es necesario.
            // Comentario: Por ahora, se usa el valor ingresado por el usuario, pero se podría forzar el cálculo.
            if ($dias_calculados_naturales != $dias_solicitados_post) {
                // $errores_formulario[] = "El número de días no coincide con el rango de fechas ($dias_calculados_naturales días). Por favor, verifique.";
                // Comentario: Se usará $dias_solicitados_post directamente.
            }
            // Comentario: Validar $dias_solicitados_post contra $dias_disponibles_vacacion (lógica real aquí).
        }
    }

    if (empty($errores_formulario)) {
        try {
            $sql = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud, fecha_inicio_vacacion, fecha_fin_vacacion, dias_solicitados_vacacion, fecha_solicitud)
                    VALUES (:id_usuario, 'vacacion', :descripcion, 'pendiente_revision_secretaria', :fecha_inicio, :fecha_fin, :dias_solicitados, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $id_usuario_actual,
                ':descripcion' => $descripcion_solicitud,
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => $fecha_fin,
                ':dias_solicitados' => $dias_solicitados_post
            ]);

            mensaje_flash('exito_sol_vac', 'Su solicitud de vacación ha sido enviada exitosamente y está pendiente de revisión.', 'alert-success');
            redirigir('index.php?vista=solicitudes_historial');

        } catch (PDOException $e) {
            error_log("Error al guardar solicitud de vacación para usuario ID $id_usuario_actual: " . $e->getMessage());
            mensaje_flash('error_sol_vac_form', 'Ocurrió un error al procesar su solicitud. Intente más tarde.', 'alert-danger');
        }
    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_sol_vac_form', $error, 'alert-danger');
        }
    }
}

// Comentario: Fin de logica/solicitud_vacacion_logica.php
?>
