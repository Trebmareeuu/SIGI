<?php
// Archivo: logica/vacaciones_control_secretaria_logica.php
// Propósito: Lógica para la bandeja de control de vacaciones de Secretaría.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CONTROLAR_SOLICITUDES_VACACION_SECRETARIA', $id_usuario_actual)) {
    mensaje_flash('error_vac_ctrl_sec', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$solicitudes_pendientes_secretaria = []; // Comentario: Para la vista.

// Comentario: Paginación.
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$regs_por_pagina = 10;
$offset = ($pagina_actual - 1) * $regs_por_pagina;
$total_regs = 0;

// Comentario: Procesamiento de acciones (derivar, observar, rechazar preliminarmente).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_control_vacacion'])) {
    $id_solicitud_accion = filter_input(INPUT_POST, 'id_solicitud', FILTER_VALIDATE_INT);
    $accion = $_POST['accion_control_vacacion'];
    $observaciones_secretaria = strip_tags($_POST['observaciones_secretaria'] ?? '');

    if ($id_solicitud_accion) {
        $pdo->beginTransaction();
        try {
            // Comentario: Obtener datos de la solicitud para el historial.
            $stmt_sol_info = $pdo->prepare("SELECT id_usuario_solicitante FROM solicitudes WHERE id_solicitud = :id_sol_info");
            $stmt_sol_info->bindParam(':id_sol_info', $id_solicitud_accion, PDO::PARAM_INT);
            $stmt_sol_info->execute();
            $sol_info_para_hist = $stmt_sol_info->fetch(PDO::FETCH_ASSOC);

            if(!$sol_info_para_hist) throw new Exception("Solicitud no encontrada para la acción.");

            $nuevo_estado = '';
            $mensaje_historial_detalle = ""; // Comentario: Detalle para la función de historial.
            $mensaje_flash_accion = "";

            if ($accion === 'derivar_a_mae') {
                $nuevo_estado = 'pendiente_aprobacion_mae';
                $mensaje_historial_detalle = "Solicitud revisada y derivada a MAE.";
                if (!empty($observaciones_secretaria)) $mensaje_historial_detalle .= " Obs. Secretaría: " . $observaciones_secretaria;

                $sql_update = "UPDATE solicitudes SET estado_solicitud = :nuevo_estado,
                               observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nRevisión Secretaría (', NOW(), '): ', :obs_sec)
                               WHERE id_solicitud = :id_solicitud AND estado_solicitud = 'pendiente_revision_secretaria'";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([
                    ':nuevo_estado' => $nuevo_estado,
                    ':obs_sec' => $observaciones_secretaria,
                    ':id_solicitud' => $id_solicitud_accion
                ]);
                if($stmt_update->rowCount() == 0) throw new Exception("La solicitud no estaba en estado 'pendiente_revision_secretaria' o no se actualizó.");
                $mensaje_flash_accion = "Solicitud ID $id_solicitud_accion derivada a MAE.";

            } elseif ($accion === 'observar_solicitud') {
                if (empty($observaciones_secretaria)) throw new Exception("Debe ingresar las observaciones para esta acción.");
                // Comentario: Esta acción solo añade una observación, no cambia el estado principal aquí.
                // Comentario: Se podría crear un estado 'observada_secretaria' si se quiere un flujo más complejo.
                $sql_obs = "UPDATE solicitudes SET observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nObservación Secretaría (', NOW(), '): ', :obs_sec)
                            WHERE id_solicitud = :id_sol AND estado_solicitud = 'pendiente_revision_secretaria'";
                $stmt_obs = $pdo->prepare($sql_obs);
                $stmt_obs->execute([':obs_sec' => $observaciones_secretaria, ':id_sol' => $id_solicitud_accion]);
                if($stmt_obs->rowCount() == 0) throw new Exception("No se pudo añadir la observación o la solicitud no está en el estado correcto.");

                $mensaje_historial_detalle = "Secretaría añadió observaciones: " . $observaciones_secretaria;
                $mensaje_flash_accion = "Observaciones añadidas a la solicitud ID $id_solicitud_accion. Aún requiere acción (derivar/rechazar).";

            } elseif ($accion === 'rechazar_preliminar') {
                if (empty($observaciones_secretaria)) throw new Exception("Debe ingresar el motivo del rechazo.");
                $nuevo_estado = 'rechazada'; // Comentario: Rechazo directo por secretaría.
                $mensaje_historial_detalle = "Solicitud rechazada por Secretaría. Motivo: " . $observaciones_secretaria;

                $sql_upd_rechazo = "UPDATE solicitudes SET estado_solicitud = :estado, motivo_rechazo = :motivo,
                                    fecha_aprobacion_rechazo = NOW(), id_usuario_aprobador = :id_user_accion,
                                    observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nRechazo Secretaría (', NOW(), '): ', :obs_sec)
                                    WHERE id_solicitud = :id_sol AND estado_solicitud = 'pendiente_revision_secretaria'";
                $stmt_upd_rechazo = $pdo->prepare($sql_upd_rechazo);
                $stmt_upd_rechazo->execute([
                    ':estado' => $nuevo_estado, ':motivo' => $observaciones_secretaria,
                    ':id_user_accion' => $id_usuario_actual, ':obs_sec' => $observaciones_secretaria,
                    ':id_sol' => $id_solicitud_accion
                ]);
                if($stmt_upd_rechazo->rowCount() == 0) throw new Exception("La solicitud no pudo ser rechazada o no estaba en el estado correcto.");
                $mensaje_flash_accion = "Solicitud ID $id_solicitud_accion ha sido rechazada.";
            } else {
                 throw new Exception("Acción no válida.");
            }

            // Comentario: Registrar en un historial de solicitudes genérico o en documentos_historial si está unificado.
            // Comentario: Por ahora, se asume que la tabla 'solicitudes' tiene los campos necesarios (motivo_rechazo, observaciones_gestion).
            // Comentario: Para un historial más detallado, se necesitaría una función como registrar_historial_solicitud().
            // Ejemplo conceptual: registrar_historial_general($id_solicitud_accion, 'solicitud', $id_usuario_actual, $accion, $mensaje_historial_detalle);


            $pdo->commit();
            mensaje_flash('exito_vac_ctrl_sec', $mensaje_flash_accion, ($accion === 'observar_solicitud' ? 'alert-info' : 'alert-success'));
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en acción control vacación secretaria (ID Sol: $id_solicitud_accion): " . $e->getMessage());
            mensaje_flash('error_vac_ctrl_sec_accion', 'Error al procesar la acción: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=vacaciones_control_secretaria&pagina=' . $pagina_actual);
    } else {
        mensaje_flash('error_vac_ctrl_sec_accion', 'ID de solicitud no válido para la acción.', 'alert-danger');
        redirigir('index.php?vista=vacaciones_control_secretaria');
    }
}


try {
    $sql_count = "SELECT COUNT(*) FROM solicitudes s WHERE s.tipo_solicitud = 'vacacion' AND s.estado_solicitud = 'pendiente_revision_secretaria'";
    $stmt_count = $pdo->query($sql_count); // Comentario: No necesita prepare si no hay params.
    $total_regs = (int)$stmt_count->fetchColumn();

    $sql = "SELECT s.id_solicitud, s.fecha_solicitud, s.fecha_inicio_vacacion, s.fecha_fin_vacacion, s.dias_solicitados_vacacion, s.descripcion_solicitud, s.observaciones_gestion,
                   u.nombres as solicitante_nombres, u.apellidos as solicitante_apellidos, u.cargo as solicitante_cargo
            FROM solicitudes s
            JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
            WHERE s.tipo_solicitud = 'vacacion' AND s.estado_solicitud = 'pendiente_revision_secretaria'
            ORDER BY s.fecha_solicitud ASC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $regs_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $solicitudes_pendientes_secretaria = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar solicitudes de vacación para control secretaría: " . $e->getMessage());
    mensaje_flash('error_vac_ctrl_sec', 'Ocurrió un error al cargar las solicitudes. Intente más tarde.', 'alert-danger');
}

$total_paginas = ($regs_por_pagina > 0) ? ceil($total_regs / $regs_por_pagina) : 0;
if ($total_paginas == 0 && $total_regs > 0) $total_paginas = 1;

// Comentario: Fin de logica/vacaciones_control_secretaria_logica.php
?>
