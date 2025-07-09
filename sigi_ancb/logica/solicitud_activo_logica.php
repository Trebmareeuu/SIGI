<?php
// Archivo: logica/solicitud_activo_logica.php
// Propósito: Lógica para el formulario de solicitud de activos (muebles/equipos).

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('SOLICITAR_ACTIVO', $id_usuario_actual)) {
    mensaje_flash('error_sol_act', 'No tiene permisos para solicitar activos (muebles/equipos).', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para repoblar el formulario.
$tipo_activo_solicitado = $_POST['tipo_activo'] ?? '';
$descripcion_activo = $_POST['descripcion_activo'] ?? '';
$justificacion_activo = $_POST['justificacion_activo'] ?? '';

// Comentario: Tipos de activo para el select (podrían venir de una tabla de configuración).
$tipos_activo_disponibles_form = [
    'mobiliario_oficina' => 'Mobiliario de Oficina (silla, escritorio, etc.)',
    'equipo_computacion' => 'Equipo de Computación (PC, laptop, monitor, impresora, etc.)',
    'equipo_comunicacion' => 'Equipo de Comunicación (teléfono, celular, etc.)',
    'software' => 'Software o Licencias',
    'otro_activo' => 'Otro Tipo de Activo'
];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitud_activo'])) {
    // Comentario: Re-asignar desde POST para validación.
    $tipo_activo_solicitado = sanitizar_entrada($_POST['tipo_activo'] ?? '');
    $descripcion_activo = strip_tags($_POST['descripcion_activo'] ?? '', '<p><br><ul><ol><li>');
    $justificacion_activo = strip_tags($_POST['justificacion_activo'] ?? '');

    $errores_formulario = [];
    if (empty($tipo_activo_solicitado) || !array_key_exists($tipo_activo_solicitado, $tipos_activo_disponibles_form)) {
        $errores_formulario[] = "Debe seleccionar un tipo de activo válido.";
    }
    if (empty($descripcion_activo)) $errores_formulario[] = "La descripción del activo (características, modelo, etc.) es obligatoria.";
    if (empty($justificacion_activo)) $errores_formulario[] = "La justificación de la solicitud es obligatoria.";

    if (empty($errores_formulario)) {
        try {
            $sql = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud, fecha_solicitud)
                    VALUES (:id_usuario, 'activo_mueble_equipo', :descripcion, 'pendiente_aprobacion_admin', NOW())";
            $stmt = $pdo->prepare($sql);

            $descripcion_completa = "Tipo de Activo Solicitado: " . ($tipos_activo_disponibles_form[$tipo_activo_solicitado] ?? $tipo_activo_solicitado) . "\n";
            $descripcion_completa .= "Descripción Detallada del Activo:\n" . $descripcion_activo . "\n\n";
            $descripcion_completa .= "Justificación:\n" . $justificacion_activo;

            $stmt->execute([':id_usuario' => $id_usuario_actual, ':descripcion' => $descripcion_completa]);

            mensaje_flash('exito_sol_act', 'Su solicitud de activo (mueble/equipo) ha sido enviada exitosamente y está pendiente de aprobación.', 'alert-success');
            redirigir('index.php?vista=solicitudes_historial');

        } catch (PDOException $e) {
            error_log("Error al guardar solicitud de activo para usuario ID $id_usuario_actual: " . $e->getMessage());
            mensaje_flash('error_sol_act_form', 'Ocurrió un error al procesar su solicitud. Intente más tarde.', 'alert-danger');
        }
    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_sol_act_form', $error, 'alert-danger');
        }
    }
}

// Comentario: Fin de logica/solicitud_activo_logica.php
?>
