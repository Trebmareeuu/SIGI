<?php
// Archivo: logica/solicitudes_historial_logica.php
// Propósito: Lógica para mostrar el historial de solicitudes del usuario.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('VER_HISTORIAL_SOLICITUDES_PROPIAS', $id_usuario_actual)) {
    // Comentario: Aunque el menú no lo mostraría, es una doble verificación.
    mensaje_flash('error_hist_sol', 'No tiene permisos para ver el historial de solicitudes.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$solicitudes = []; // Comentario: Para la vista.

// Comentario: Paginación.
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$solicitudes_por_pagina = 10;
$offset = ($pagina_actual - 1) * $solicitudes_por_pagina;
$total_solicitudes = 0;

// Comentario: Lógica para cancelar una solicitud (si se envía desde el historial).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cancelar_solicitud'])) {
    $id_solicitud_cancelar = filter_input(INPUT_POST, 'id_solicitud_a_cancelar', FILTER_VALIDATE_INT);

    if ($id_solicitud_cancelar) {
        try {
            // Comentario: Verificar que la solicitud pertenezca al usuario y esté en estado cancelable.
            $stmt_check_cancel = $pdo->prepare("SELECT estado_solicitud FROM solicitudes WHERE id_solicitud = :id_sol AND id_usuario_solicitante = :id_user");
            $stmt_check_cancel->execute([':id_sol' => $id_solicitud_cancelar, ':id_user' => $id_usuario_actual]);
            $estado_actual_sol_cancel = $stmt_check_cancel->fetchColumn();

            $estados_cancelables_por_usuario = ['pendiente_revision_secretaria', 'pendiente_aprobacion_mae', 'pendiente_aprobacion_admin'];
            if ($estado_actual_sol_cancel && in_array($estado_actual_sol_cancel, $estados_cancelables_por_usuario)) {
                $sql_cancel = "UPDATE solicitudes SET estado_solicitud = 'cancelada',
                               observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nCancelada por el usuario (', NOW(), ').')
                               WHERE id_solicitud = :id_sol_c";
                $stmt_cancel_exec = $pdo->prepare($sql_cancel);
                $stmt_cancel_exec->execute([':id_sol_c' => $id_solicitud_cancelar]);

                // Comentario: Registrar en historial si es necesario.
                // registrar_historial_solicitud($id_solicitud_cancelar, $id_usuario_actual, 'Cancelación Solicitud', 'Usuario canceló la solicitud.');
                mensaje_flash('exito_cancelar_sol', 'Solicitud ID ' . $id_solicitud_cancelar . ' cancelada exitosamente.', 'alert-success');
            } else {
                mensaje_flash('error_cancelar_sol', 'La solicitud no puede ser cancelada o no le pertenece.', 'alert-warning');
            }
        } catch (PDOException $e) {
            error_log("Error al cancelar solicitud ID $id_solicitud_cancelar: " . $e->getMessage());
            mensaje_flash('error_cancelar_sol', 'Error al procesar la cancelación: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=solicitudes_historial&pagina=' . $pagina_actual);
    }
}


try {
    $sql_count = "SELECT COUNT(*) FROM solicitudes WHERE id_usuario_solicitante = :id_usuario";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
    $stmt_count->execute();
    $total_solicitudes = (int)$stmt_count->fetchColumn();

    $sql = "SELECT id_solicitud, tipo_solicitud, fecha_solicitud, estado_solicitud, descripcion_solicitud,
                   fecha_aprobacion_rechazo, motivo_rechazo, observaciones_gestion
            FROM solicitudes
            WHERE id_usuario_solicitante = :id_usuario
            ORDER BY fecha_solicitud DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $solicitudes_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar historial de solicitudes para usuario ID $id_usuario_actual: " . $e->getMessage());
    mensaje_flash('error_hist_sol', 'Ocurrió un error al cargar su historial de solicitudes. Intente más tarde.', 'alert-danger');
}

$total_paginas = ($solicitudes_por_pagina > 0) ? ceil($total_solicitudes / $solicitudes_por_pagina) : 0;
if ($total_paginas == 0 && $total_solicitudes > 0) $total_paginas = 1; // Comentario: Al menos una página si hay resultados.


// Comentario: Funciones de formato (podrían estar en funciones.php si se usan en múltiples vistas).
if (!function_exists('formatear_tipo_solicitud_hist')) {
    function formatear_tipo_solicitud_hist($tipo_bd) {
        $mapa = [
            'vacacion' => 'Vacación', 'material_escritorio' => 'Material de Escritorio',
            'activo_mueble_equipo' => 'Activo (Mueble/Equipo)', 'otro' => 'Otro Tipo'
        ];
        return $mapa[$tipo_bd] ?? ucfirst(str_replace('_', ' ', $tipo_bd));
    }
}
if (!function_exists('formatear_estado_solicitud_hist')) {
    function formatear_estado_solicitud_hist($estado_bd) {
        $mapa_estados = [
            'pendiente_revision_secretaria' => 'Pendiente Revisión (Secretaría)',
            'pendiente_aprobacion_mae' => 'Pendiente Aprobación (MAE)',
            'pendiente_aprobacion_admin' => 'Pendiente Aprobación (Dir. Admin.)',
            'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada',
            'atendida' => 'Atendida/Entregada', 'cancelada' => 'Cancelada por Usuario'
        ];
        return $mapa_estados[$estado_bd] ?? ucfirst(str_replace('_', ' ', $estado_bd));
    }
}

// Comentario: Fin de logica/solicitudes_historial_logica.php
?>
