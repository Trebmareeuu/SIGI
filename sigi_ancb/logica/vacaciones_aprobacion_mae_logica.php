<?php
// Archivo: logica/vacaciones_aprobacion_mae_logica.php
// Propósito: Lógica para la bandeja de aprobación de vacaciones del MAE.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: MAE.
if (!tiene_permiso('APROBAR_VACACIONES_MAE', $id_usuario_actual)) {
    mensaje_flash('error_vac_aprob_mae', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$solicitudes_pendientes_mae = []; // Comentario: Para la vista.

// Comentario: Paginación.
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$regs_por_pagina = 10;
$offset = ($pagina_actual - 1) * $regs_por_pagina;
$total_regs = 0;

// Comentario: Procesamiento de acciones (aprobar, rechazar).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_aprobacion_vacacion'])) {
    $id_solicitud_accion = filter_input(INPUT_POST, 'id_solicitud', FILTER_VALIDATE_INT);
    $accion = $_POST['accion_aprobacion_vacacion'];
    $motivo_decision_mae = strip_tags($_POST['motivo_decision_mae'] ?? '');

    if ($id_solicitud_accion) {
        $pdo->beginTransaction();
        try {
            // Comentario: Verificar que la solicitud esté en el estado correcto.
            $stmt_check_estado = $pdo->prepare("SELECT estado_solicitud FROM solicitudes WHERE id_solicitud = :id_sol_chk_mae");
            $stmt_check_estado->bindParam(':id_sol_chk_mae', $id_solicitud_accion, PDO::PARAM_INT);
            $stmt_check_estado->execute();
            $estado_actual_sol_mae = $stmt_check_estado->fetchColumn();

            if ($estado_actual_sol_mae !== 'pendiente_aprobacion_mae') {
                throw new Exception("La solicitud no está en estado 'pendiente_aprobacion_mae' o ya fue procesada.");
            }

            $nuevo_estado = '';
            $campo_motivo_rechazo = null;
            $mensaje_historial_detalle = ""; // Comentario: Para un futuro log de historial.
            $mensaje_flash_accion = "";


            if ($accion === 'aprobar_vacacion') {
                $nuevo_estado = 'aprobada';
                $mensaje_historial_detalle = "Solicitud de vacación APROBADA por MAE.";
                if (!empty($motivo_decision_mae)) $mensaje_historial_detalle .= " Comentario MAE: " . $motivo_decision_mae;
                $mensaje_flash_accion = "Solicitud ID $id_solicitud_accion APROBADA.";
            } elseif ($accion === 'rechazar_vacacion') {
                if (empty($motivo_decision_mae)) throw new Exception("El motivo del rechazo es obligatorio para esta acción.");
                $nuevo_estado = 'rechazada';
                $campo_motivo_rechazo = $motivo_decision_mae;
                $mensaje_historial_detalle = "Solicitud de vacación RECHAZADA por MAE. Motivo: " . $motivo_decision_mae;
                $mensaje_flash_accion = "Solicitud ID $id_solicitud_accion RECHAZADA.";
            } else {
                throw new Exception("Acción no válida para la solicitud.");
            }

            $sql_update = "UPDATE solicitudes
                           SET estado_solicitud = :nuevo_estado,
                               id_usuario_aprobador = :id_mae_aprueba,
                               fecha_aprobacion_rechazo = NOW(),
                               motivo_rechazo = :motivo_r,
                               observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nDecisión MAE (', NOW(), '): ', :obs_mae_dec)
                           WHERE id_solicitud = :id_solicitud_upd";

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':nuevo_estado' => $nuevo_estado,
                ':id_mae_aprueba' => $id_usuario_actual,
                ':motivo_r' => $campo_motivo_rechazo,
                ':obs_mae_dec' => $motivo_decision_mae, // Comentario: Se guarda como observación general también.
                ':id_solicitud_upd' => $id_solicitud_accion
            ]);

            // Comentario: registrar_historial_solicitud($id_solicitud_accion, $id_usuario_actual, 'Decisión MAE Vacación', $mensaje_historial_detalle);

            $pdo->commit();
            mensaje_flash('exito_vac_aprob_mae', $mensaje_flash_accion, 'alert-success');
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en acción aprobación vacación MAE (ID Sol: $id_solicitud_accion): " . $e->getMessage());
            mensaje_flash('error_vac_aprob_mae_accion', 'Error al procesar la decisión: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=vacaciones_aprobacion_mae&pagina=' . $pagina_actual);
    } else {
        mensaje_flash('error_vac_aprob_mae_accion', 'ID de solicitud no válido para la acción.', 'alert-danger');
        redirigir('index.php?vista=vacaciones_aprobacion_mae');
    }
}


try {
    $sql_count_mae = "SELECT COUNT(*) FROM solicitudes s WHERE s.tipo_solicitud = 'vacacion' AND s.estado_solicitud = 'pendiente_aprobacion_mae'";
    $stmt_count_mae = $pdo->query($sql_count_mae);
    $total_regs = (int)$stmt_count_mae->fetchColumn();

    $sql_mae = "SELECT s.id_solicitud, s.fecha_solicitud, s.fecha_inicio_vacacion, s.fecha_fin_vacacion, s.dias_solicitados_vacacion,
                   s.descripcion_solicitud, s.observaciones_gestion as obs_secretaria,
                   u.nombres as solicitante_nombres, u.apellidos as solicitante_apellidos, u.cargo as solicitante_cargo
            FROM solicitudes s
            JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
            WHERE s.tipo_solicitud = 'vacacion' AND s.estado_solicitud = 'pendiente_aprobacion_mae'
            ORDER BY s.fecha_solicitud ASC
            LIMIT :limit OFFSET :offset";
    $stmt_mae = $pdo->prepare($sql_mae);
    $stmt_mae->bindParam(':limit', $regs_por_pagina, PDO::PARAM_INT);
    $stmt_mae->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_mae->execute();
    $solicitudes_pendientes_mae = $stmt_mae->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar solicitudes de vacación para aprobación MAE: " . $e->getMessage());
    mensaje_flash('error_vac_aprob_mae', 'Ocurrió un error al cargar las solicitudes. Intente más tarde.', 'alert-danger');
}

$total_paginas = ($regs_por_pagina > 0) ? ceil($total_regs / $regs_por_pagina) : 0;
if ($total_paginas == 0 && $total_regs > 0) $total_paginas = 1;

// Comentario: Fin de logica/vacaciones_aprobacion_mae_logica.php
?>
