<?php
// Archivo: logica/documento_detalle_logica.php
// Propósito: Lógica para la vista de detalle de un documento, incluyendo manejo de acciones.

$id_usuario_actual = obtener_id_usuario_actual();
// Comentario: El permiso 'VER_DETALLE_DOCUMENTO' se verifica en index.php antes de cargar la lógica.
// Comentario: Sin embargo, si esta página de lógica se llamara directamente (no debería), se necesitaría aquí.
/*
if (!tiene_permiso('VER_DETALLE_DOCUMENTO', $id_usuario_actual)) {
    mensaje_flash('error_detalle_doc', 'No tiene permisos para ver detalles de documentos.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}
*/

global $pdo;

$id_documento = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_documento) {
    mensaje_flash('error_detalle_doc', 'ID de documento no válido o no proporcionado.', 'alert-danger');
    redirigir('index.php?vista=correspondencia_bandeja');
}

$documento = null;
$historial = [];
$adjuntos = [];
$lista_usuarios_derivacion = []; // Comentario: Para el formulario de derivación.

try {
    $sql_doc = "SELECT d.*,
                       CONCAT(uc.nombres, ' ', uc.apellidos) as nombre_creador, uc.cargo as cargo_creador,
                       CONCAT(ua.nombres, ' ', ua.apellidos) as nombre_asignado, ua.cargo as cargo_asignado,
                       ueo.nombre_entidad as nombre_entidad_origen,
                       ued.nombre_entidad as nombre_entidad_destino
                FROM documentos d
                JOIN usuarios uc ON d.id_usuario_creador = uc.id_usuario
                LEFT JOIN usuarios ua ON d.id_usuario_asignado = ua.id_usuario
                LEFT JOIN entidades_externas ueo ON d.id_entidad_externa_origen = ueo.id_entidad
                LEFT JOIN entidades_externas ued ON d.id_entidad_externa_destino = ued.id_entidad
                WHERE d.id_documento = :id_documento";
    $stmt_doc = $pdo->prepare($sql_doc);
    $stmt_doc->bindParam(':id_documento', $id_documento, PDO::PARAM_INT);
    $stmt_doc->execute();
    $documento = $stmt_doc->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        mensaje_flash('error_detalle_doc', "Documento con ID $id_documento no encontrado.", 'alert-danger');
        redirigir('index.php?vista=correspondencia_bandeja');
    }

    $sql_hist = "SELECT dh.*, CONCAT(u_accion.nombres, ' ', u_accion.apellidos) as nombre_usuario_accion,
                        CONCAT(u_origen.nombres, ' ', u_origen.apellidos) as nombre_usuario_origen,
                        CONCAT(u_destino.nombres, ' ', u_destino.apellidos) as nombre_usuario_destino
                 FROM documentos_historial dh
                 JOIN usuarios u_accion ON dh.id_usuario_accion = u_accion.id_usuario
                 LEFT JOIN usuarios u_origen ON dh.id_usuario_origen = u_origen.id_usuario
                 LEFT JOIN usuarios u_destino ON dh.id_usuario_destino = u_destino.id_usuario
                 WHERE dh.id_documento = :id_documento
                 ORDER BY dh.fecha_accion ASC";
    $stmt_hist = $pdo->prepare($sql_hist);
    $stmt_hist->bindParam(':id_documento', $id_documento, PDO::PARAM_INT);
    $stmt_hist->execute();
    $historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

    $sql_adj = "SELECT da.*, CONCAT(u_subida.nombres, ' ', u_subida.apellidos) as nombre_usuario_subida
                FROM documentos_adjuntos da
                JOIN usuarios u_subida ON da.id_usuario_subida = u_subida.id_usuario
                WHERE da.id_documento = :id_documento
                ORDER BY da.fecha_subida DESC";
    $stmt_adj = $pdo->prepare($sql_adj);
    $stmt_adj->bindParam(':id_documento', $id_documento, PDO::PARAM_INT);
    $stmt_adj->execute();
    $adjuntos = $stmt_adj->fetchAll(PDO::FETCH_ASSOC);

    // Comentario: Cargar usuarios para derivación si el documento está en estado apropiado y el usuario tiene permiso.
    if ($documento && !in_array($documento['estado_documento'], ['finalizado', 'archivado', 'anulado']) && tiene_permiso('DERIVAR_DOCUMENTO', $id_usuario_actual)) {
        $id_excluir_deriv = $documento['id_usuario_asignado'] ?? $id_usuario_actual;
        $stmt_users_deriv = $pdo->prepare("SELECT id_usuario, nombres, apellidos, cargo FROM usuarios WHERE id_usuario != :id_excluir AND estado = 'activo' ORDER BY apellidos, nombres");
        $stmt_users_deriv->bindParam(':id_excluir', $id_excluir_deriv, PDO::PARAM_INT);
        $stmt_users_deriv->execute();
        $lista_usuarios_derivacion = $stmt_users_deriv->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Error al cargar detalle del documento ID $id_documento: " . $e->getMessage());
    mensaje_flash('error_detalle_doc', 'Ocurrió un error al cargar los detalles del documento. Intente más tarde.', 'alert-danger');
    // Comentario: $documento podría ser null, la vista HTML debe manejarlo.
}


// Comentario: Lógica para acciones POST sobre el documento.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_documento']) && $documento) {
    $accion_doc_post = $_POST['accion_documento'];
    $observaciones_accion = strip_tags($_POST['comentario_accion'] ?? ($_POST['comentario_derivacion'] ?? ($_POST['comentario_finalizacion'] ?? ($_POST['comentario_archivado'] ?? ''))));

    $pdo->beginTransaction();
    try {
        $accion_realizada_flash = "";

        if ($accion_doc_post === 'derivar_documento' && tiene_permiso('DERIVAR_DOCUMENTO', $id_usuario_actual)) {
            $id_destinatario_derivacion = filter_input(INPUT_POST, 'id_usuario_derivacion', FILTER_VALIDATE_INT);
            if (!$id_destinatario_derivacion) throw new Exception("Debe seleccionar un destinatario para la derivación.");

            $sql_upd_asig = "UPDATE documentos SET id_usuario_asignado = :id_nuevo_asignado, estado_documento = 'derivado' WHERE id_documento = :id_doc";
            $stmt_upd_asig = $pdo->prepare($sql_upd_asig);
            $stmt_upd_asig->execute([':id_nuevo_asignado' => $id_destinatario_derivacion, ':id_doc' => $id_documento]);

            $stmt_dest_info_dd = $pdo->prepare("SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = :id_dest_dd");
            $stmt_dest_info_dd->bindParam(':id_dest_dd', $id_destinatario_derivacion, PDO::PARAM_INT);
            $stmt_dest_info_dd->execute();
            $nombre_dest_hist_dd = $stmt_dest_info_dd->fetchColumn() ?: "ID Usuario $id_destinatario_derivacion";
            $detalle_hist_deriv_dd = "Documento derivado a $nombre_dest_hist_dd. " . $observaciones_accion;
            registrar_historial_documento($id_documento, $id_usuario_actual, 'Derivación', trim($detalle_hist_deriv_dd), $documento['id_usuario_asignado'], $id_destinatario_derivacion);
            $accion_realizada_flash = "Documento derivado a $nombre_dest_hist_dd.";

        } elseif ($accion_doc_post === 'finalizar_documento' && tiene_permiso('FINALIZAR_DOCUMENTO', $id_usuario_actual)) {
            $sql_upd_fin = "UPDATE documentos SET estado_documento = 'finalizado', id_usuario_asignado = NULL WHERE id_documento = :id_doc"; // Comentario: Quitar asignado al finalizar.
            $stmt_upd_fin = $pdo->prepare($sql_upd_fin);
            $stmt_upd_fin->execute([':id_doc' => $id_documento]);
            registrar_historial_documento($id_documento, $id_usuario_actual, 'Finalización', "Documento marcado como finalizado. " . $observaciones_accion);
            $accion_realizada_flash = "Documento finalizado.";

        } elseif ($accion_doc_post === 'archivar_documento' && tiene_permiso('ARCHIVAR_DOCUMENTO', $id_usuario_actual)) {
            $sql_upd_arc = "UPDATE documentos SET estado_documento = 'archivado', id_usuario_asignado = NULL WHERE id_documento = :id_doc";
            $stmt_upd_arc = $pdo->prepare($sql_upd_arc);
            $stmt_upd_arc->execute([':id_doc' => $id_documento]);
            registrar_historial_documento($id_documento, $id_usuario_actual, 'Archivado', "Documento enviado a archivo. " . $observaciones_accion);
            $accion_realizada_flash = "Documento archivado.";

        } elseif ($accion_doc_post === 'agregar_comentario' && tiene_permiso('COMENTAR_DOCUMENTO', $id_usuario_actual)) { // Comentario: Permiso hipotético.
            if(empty($observaciones_accion)) throw new Exception("El comentario no puede estar vacío.");
            registrar_historial_documento($id_documento, $id_usuario_actual, 'Comentario', $observaciones_accion);
            $accion_realizada_flash = "Comentario agregado.";

        } else {
            throw new Exception("Acción no permitida o desconocida.");
        }

        $pdo->commit();
        mensaje_flash('exito_accion_documento', $accion_realizada_flash, 'alert-success');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error en acción sobre documento ID $id_documento: " . $e->getMessage());
        mensaje_flash('error_accion_documento', 'Error al realizar la acción: ' . $e->getMessage(), 'alert-danger');
    }
    redirigir("index.php?vista=documento_detalle&id=$id_documento");
}


// Comentario: Fin de logica/documento_detalle_logica.php
?>
