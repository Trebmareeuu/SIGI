<?php
// Archivo: logica/correspondencia_registrar_logica.php
// Propósito: Lógica para el registro de correspondencia externa (Secretaría).

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('REGISTRAR_CORRESPONDENCIA_EXTERNA', $id_usuario_actual)) {
    mensaje_flash('error_registrar_ext', 'No tiene permisos para registrar correspondencia externa.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para repoblar el formulario en caso de error.
$fecha_documento_externo = $_POST['fecha_documento_externo'] ?? date('Y-m-d');
$cite_externo = $_POST['cite_externo'] ?? '';
$referencia_externa = $_POST['referencia_externa'] ?? '';
$id_entidad_origen = $_POST['id_entidad_origen'] ?? null;
$nombre_remitente_especifico = $_POST['nombre_remitente_especifico'] ?? '';
$descripcion_contenido = $_POST['descripcion_contenido'] ?? '';
$destinatarios_internos_seleccionados = $_POST['destinatarios_internos'] ?? [];
$prioridad_seleccionada = $_POST['prioridad'] ?? 'normal';
$observaciones_registro = $_POST['observaciones_registro'] ?? '';

// Comentario: Cargar lista de entidades externas.
$lista_entidades = [];
try {
    $stmt_ent = $pdo->query("SELECT id_entidad, nombre_entidad FROM entidades_externas ORDER BY nombre_entidad ASC");
    $lista_entidades = $stmt_ent->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar entidades externas: " . $e->getMessage());
    mensaje_flash('error_registrar_ext_form', 'No se pudo cargar la lista de entidades remitentes.', 'alert-warning');
}

// Comentario: Cargar lista de usuarios internos para derivación.
$lista_usuarios_internos = [];
try {
    $stmt_users = $pdo->query("SELECT id_usuario, nombres, apellidos, cargo FROM usuarios WHERE estado = 'activo' ORDER BY apellidos, nombres");
    $lista_usuarios_internos = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar usuarios internos para registrar externo: " . $e->getMessage());
    mensaje_flash('error_registrar_ext_form', 'No se pudo cargar la lista de destinatarios internos.', 'alert-warning');
}

// Comentario: Procesamiento del formulario.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_correspondencia_externa'])) {
    // Comentario: Re-asignar desde POST para validación.
    $fecha_documento_externo = sanitizar_entrada($_POST['fecha_documento_externo'] ?? date('Y-m-d'));
    $cite_externo = sanitizar_entrada($_POST['cite_externo'] ?? '');
    $referencia_externa = sanitizar_entrada($_POST['referencia_externa'] ?? '');
    $id_entidad_origen = filter_var($_POST['id_entidad_origen'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $nombre_remitente_especifico = sanitizar_entrada($_POST['nombre_remitente_especifico'] ?? '');
    $descripcion_contenido = strip_tags($_POST['descripcion_contenido'] ?? '');
    $destinatarios_internos_seleccionados = $_POST['destinatarios_internos'] ?? [];
    $prioridad_seleccionada = sanitizar_entrada($_POST['prioridad'] ?? 'normal');
    $observaciones_registro = strip_tags($_POST['observaciones_registro'] ?? '');

    $errores_formulario = [];
    if (empty($fecha_documento_externo) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_documento_externo)) $errores_formulario[] = "La fecha del documento externo no es válida.";
    if (empty($referencia_externa)) $errores_formulario[] = "La referencia (asunto) del documento externo es obligatoria.";
    if (empty($id_entidad_origen) && empty($nombre_remitente_especifico)) $errores_formulario[] = "Debe seleccionar una entidad remitente o ingresar un nombre de remitente específico.";
    if (empty($destinatarios_internos_seleccionados)) $errores_formulario[] = "Debe seleccionar al menos un destinatario interno para la derivación inicial.";

    if (empty($errores_formulario)) {
        $pdo->beginTransaction();
        try {
            $stmt_siglas_reg = $pdo->query("SELECT valor_config FROM sistema_configuracion WHERE clave_config = 'SIGLAS_INSTITUCION' LIMIT 1");
            $siglas_inst_reg = $stmt_siglas_reg->fetchColumn() ?: 'ANCB';
            $cite_interno_generado = generar_cite_documento('REC', $siglas_inst_reg);
            if (empty($cite_interno_generado)) throw new Exception("No se pudo generar el CITE interno para el registro.");

            $referencia_final = $referencia_externa;
            if (!empty($nombre_remitente_especifico) && empty($id_entidad_origen)) {
                $referencia_final .= " (Remitente Específico: " . $nombre_remitente_especifico . ")";
            }

            $id_usuario_asignado_principal_interno = (!empty($destinatarios_internos_seleccionados)) ? (int)$destinatarios_internos_seleccionados[0] : null;
            if ($id_usuario_asignado_principal_interno === null) throw new Exception("Destinatario interno principal no definido.");

            $sql_insert_doc = "INSERT INTO documentos (id_usuario_creador, id_usuario_asignado, id_entidad_externa_origen, tipo_documento, cite, referencia, contenido, fecha_documento, estado_documento, prioridad, observaciones, fecha_recepcion_registro)
                               VALUES (:id_uc, :id_ua, :id_eo, 'carta_externa_recibida', :cite, :ref, :cont, :fec_doc, 'derivado', :prio, :obs, NOW())";
            $stmt_insert_doc = $pdo->prepare($sql_insert_doc);
            $observaciones_completas = "CITE Original Externo: " . $cite_externo . ". " . $observaciones_registro;
            $stmt_insert_doc->execute([
                ':id_uc' => $id_usuario_actual, ':id_ua' => $id_usuario_asignado_principal_interno,
                ':id_eo' => $id_entidad_origen ?: null, ':cite' => $cite_interno_generado, ':ref' => $referencia_final,
                ':cont' => $descripcion_contenido, ':fec_doc' => $fecha_documento_externo,
                ':prio' => $prioridad_seleccionada, ':obs' => $observaciones_completas
            ]);
            $id_documento_registrado = $pdo->lastInsertId();

            $detalle_hist_registro = "Correspondencia externa registrada con CITE Interno: $cite_interno_generado. ";
            // ... (lógica para añadir nombre de entidad o remitente específico al historial) ...
            registrar_historial_documento($id_documento_registrado, $id_usuario_actual, 'Registro Externo', $detalle_hist_registro);

            foreach ($destinatarios_internos_seleccionados as $id_dest_interno) {
                // ... (lógica para obtener nombre de destinatario y registrar historial de derivación) ...
                 $stmt_dest_info_reg = $pdo->prepare("SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = :id_dest_info_reg");
                 $stmt_dest_info_reg->bindParam(':id_dest_info_reg', $id_dest_interno, PDO::PARAM_INT);
                 $stmt_dest_info_reg->execute();
                 $dest_info_nombre_reg = $stmt_dest_info_reg->fetchColumn() ?: "ID Usuario $id_dest_interno";
                registrar_historial_documento($id_documento_registrado, $id_usuario_actual, 'Derivación Inicial', "Documento derivado a $dest_info_nombre_reg.", $id_usuario_actual, (int)$id_dest_interno);
            }

            // Comentario: Manejo de archivo adjunto principal.
            if (isset($_FILES['archivo_escaneado']) && $_FILES['archivo_escaneado']['error'] === UPLOAD_ERR_OK) {
                $directorio_subidas_reg = DIR_DOCUMENTOS . date('Y/m/');
                if (!is_dir($directorio_subidas_reg)) mkdir($directorio_subidas_reg, 0755, true);

                $nombre_original_adj_reg = $_FILES['archivo_escaneado']['name'];
                // ... (validaciones de tamaño, tipo) ...
                $extension_adj_reg = strtolower(pathinfo($nombre_original_adj_reg, PATHINFO_EXTENSION));
                $nombre_archivo_servidor_adj_reg = uniqid('docext_' . $id_documento_registrado . '_main_', true) . '.' . $extension_adj_reg;
                $ruta_archivo_servidor_adj_reg = $directorio_subidas_reg . $nombre_archivo_servidor_adj_reg;
                $ruta_relativa_bd_adj_reg = date('Y/m/') . $nombre_archivo_servidor_adj_reg;

                if (move_uploaded_file($_FILES['archivo_escaneado']['tmp_name'], $ruta_archivo_servidor_adj_reg)) {
                    $sql_upd_adj_main = "UPDATE documentos SET ruta_archivo_adjunto = :ruta_adj WHERE id_documento = :id_doc_upd_adj";
                    $stmt_upd_adj_main = $pdo->prepare($sql_upd_adj_main);
                    $stmt_upd_adj_main->execute([':ruta_adj' => $ruta_relativa_bd_adj_reg, ':id_doc_upd_adj' => $id_documento_registrado]);
                } else {
                    throw new Exception("Error al mover el archivo escaneado '$nombre_original_adj_reg'.");
                }
            } elseif (isset($_FILES['archivo_escaneado']) && $_FILES['archivo_escaneado']['error'] !== UPLOAD_ERR_NO_FILE) {
                 throw new Exception("Error al subir el archivo escaneado: Código " . $_FILES['archivo_escaneado']['error']);
            }

            $pdo->commit();
            mensaje_flash('exito_registrar_ext', "Correspondencia externa registrada con CITE Interno: $cite_interno_generado y derivada exitosamente.", 'alert-success');
            redirigir('index.php?vista=documento_detalle&id=' . $id_documento_registrado);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error al registrar correspondencia externa: " . $e->getMessage());
            mensaje_flash('error_registrar_ext_form', 'Error al registrar la correspondencia: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_registrar_ext_form', $error, 'alert-danger');
        }
    }
}

// Comentario: Fin de logica/correspondencia_registrar_logica.php
?>
