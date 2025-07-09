<?php
// Archivo: logica/solicitudes_aprobacion_admin_logica.php
// Propósito: Lógica para la bandeja de aprobación de solicitudes de Dir. Admin.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Dir. Admin.
if (!tiene_permiso('APROBAR_SOLICITUDES_ADMIN', $id_usuario_actual)) {
    mensaje_flash('error_sol_aprob_admin', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$solicitudes_pendientes_admin = []; // Comentario: Para la vista.

// Comentario: Paginación.
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$regs_por_pagina = 10;
$offset = ($pagina_actual - 1) * $regs_por_pagina;
$total_regs = 0;

// Comentario: Procesamiento de acciones (aprobar, rechazar).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_aprobacion_solicitud_admin'])) {
    $id_solicitud_accion = filter_input(INPUT_POST, 'id_solicitud', FILTER_VALIDATE_INT);
    $accion = $_POST['accion_aprobacion_solicitud_admin'];
    $motivo_decision_admin = strip_tags($_POST['motivo_decision_admin'] ?? '');

    if ($id_solicitud_accion) {
        $pdo->beginTransaction();
        try {
            $stmt_check_estado_admin = $pdo->prepare("SELECT tipo_solicitud, estado_solicitud FROM solicitudes WHERE id_solicitud = :id_sol_chk_admin");
            $stmt_check_estado_admin->bindParam(':id_sol_chk_admin', $id_solicitud_accion, PDO::PARAM_INT);
            $stmt_check_estado_admin->execute();
            $solicitud_info_admin = $stmt_check_estado_admin->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud_info_admin || $solicitud_info_admin['estado_solicitud'] !== 'pendiente_aprobacion_admin') {
                throw new Exception("La solicitud no está en estado 'pendiente_aprobacion_admin' o ya fue procesada.");
            }
            $tipo_sol_texto_admin = formatear_tipo_solicitud_hist($solicitud_info_admin['tipo_solicitud']); // Comentario: Reusar función.

            $nuevo_estado = '';
            $campo_motivo_rechazo = null;
            $mensaje_historial_detalle = "";
            $mensaje_flash_accion = "";

            if ($accion === 'aprobar_solicitud_admin') {
                $nuevo_estado = 'aprobada'; // Comentario: O 'aprobada_pendiente_entrega/compra'.
                $mensaje_historial_detalle = "Solicitud de $tipo_sol_texto_admin APROBADA por Dir. Admin.";
                if (!empty($motivo_decision_admin)) $mensaje_historial_detalle .= " Comentario Dir. Admin: " . $motivo_decision_admin;
                $mensaje_flash_accion = "Solicitud ID $id_solicitud_accion APROBADA.";
            } elseif ($accion === 'rechazar_solicitud_admin') {
                if (empty($motivo_decision_admin)) throw new Exception("El motivo del rechazo es obligatorio.");
                $nuevo_estado = 'rechazada';
                $campo_motivo_rechazo = $motivo_decision_admin;
                $mensaje_historial_detalle = "Solicitud de $tipo_sol_texto_admin RECHAZADA por Dir. Admin. Motivo: " . $motivo_decision_admin;
                $mensaje_flash_accion = "Solicitud ID $id_solicitud_accion RECHAZADA.";
            } else {
                throw new Exception("Acción no válida.");
            }

            $sql_update = "UPDATE solicitudes
                           SET estado_solicitud = :nuevo_estado, id_usuario_aprobador = :id_admin_aprueba,
                               fecha_aprobacion_rechazo = NOW(), motivo_rechazo = :motivo_r,
                               observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nDecisión Dir. Admin (', NOW(), '): ', :obs_admin_dec)
                           WHERE id_solicitud = :id_solicitud_upd";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':nuevo_estado' => $nuevo_estado, ':id_admin_aprueba' => $id_usuario_actual,
                ':motivo_r' => $campo_motivo_rechazo, ':obs_admin_dec' => $motivo_decision_admin,
                ':id_solicitud_upd' => $id_solicitud_accion
            ]);

            // Comentario: registrar_historial_solicitud($id_solicitud_accion, $id_usuario_actual, "Decisión Dir. Admin ($tipo_sol_texto_admin)", $mensaje_historial_detalle);

            $pdo->commit();
            mensaje_flash('exito_sol_aprob_admin', $mensaje_flash_accion, 'alert-success');
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en acción aprobación solicitud Dir. Admin (ID Sol: $id_solicitud_accion): " . $e->getMessage());
            mensaje_flash('error_sol_aprob_admin_accion', 'Error al procesar la decisión: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=solicitudes_aprobacion_admin&pagina=' . $pagina_actual);
    } else {
        mensaje_flash('error_sol_aprob_admin_accion', 'ID de solicitud no válido para la acción.', 'alert-danger');
        redirigir('index.php?vista=solicitudes_aprobacion_admin');
    }
}


try {
    $sql_count_admin = "SELECT COUNT(*) FROM solicitudes s
                        WHERE s.tipo_solicitud IN ('material_escritorio', 'activo_mueble_equipo')
                        AND s.estado_solicitud = 'pendiente_aprobacion_admin'";
    $stmt_count_admin = $pdo->query($sql_count_admin);
    $total_regs = (int)$stmt_count_admin->fetchColumn();

    $sql_admin = "SELECT s.id_solicitud, s.fecha_solicitud, s.tipo_solicitud, s.descripcion_solicitud,
                         u.nombres as solicitante_nombres, u.apellidos as solicitante_apellidos, u.cargo as solicitante_cargo
                  FROM solicitudes s
                  JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
                  WHERE s.tipo_solicitud IN ('material_escritorio', 'activo_mueble_equipo')
                  AND s.estado_solicitud = 'pendiente_aprobacion_admin'
                  ORDER BY s.fecha_solicitud ASC
                  LIMIT :limit OFFSET :offset";
    $stmt_admin = $pdo->prepare($sql_admin);
    $stmt_admin->bindParam(':limit', $regs_por_pagina, PDO::PARAM_INT);
    $stmt_admin->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_admin->execute();
    $solicitudes_pendientes_admin = $stmt_admin->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar solicitudes para aprobación Dir. Admin: " . $e->getMessage());
    mensaje_flash('error_sol_aprob_admin', 'Ocurrió un error al cargar las solicitudes. Intente más tarde.', 'alert-danger');
}

$total_paginas = ($regs_por_pagina > 0) ? ceil($total_regs / $regs_por_pagina) : 0;
if ($total_paginas == 0 && $total_regs > 0) $total_paginas = 1;


// Comentario: Reutilizar función de formato de historial_solicitudes.php si no está ya definida.
if (!function_exists('formatear_tipo_solicitud_hist')) { // Comentario: Renombrada para evitar colisión si se incluye en otro lado.
    function formatear_tipo_solicitud_hist($tipo_bd) {
        $mapa = ['vacacion' => 'Vacación', 'material_escritorio' => 'Material de Escritorio', 'activo_mueble_equipo' => 'Activo (Mueble/Equipo)', 'otro' => 'Otro Tipo'];
        return $mapa[$tipo_bd] ?? ucfirst(str_replace('_', ' ', $tipo_bd));
    }
}
// Comentario: Fin de logica/solicitudes_aprobacion_admin_logica.php
?>
