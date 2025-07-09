<?php
// Archivo: logica/correspondencia_redactar_logica.php
// Propósito: Lógica para la redacción de correspondencia interna.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('REDACTAR_CORRESPONDENCIA_INTERNA', $id_usuario_actual)) {
    mensaje_flash('error_redactar', 'No tiene permisos para redactar correspondencia interna.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para el formulario (se usarán para repoblar en caso de error si no hay redirección).
$tipo_documento_seleccionado = $_POST['tipo_documento'] ?? 'nota_interna';
$referencia = $_POST['referencia'] ?? '';
$contenido = $_POST['contenido'] ?? '';
$destinatarios_seleccionados = $_POST['destinatarios'] ?? [];
$prioridad_seleccionada = $_POST['prioridad'] ?? 'normal';
$fecha_documento_form = $_POST['fecha_documento'] ?? date('Y-m-d');


// Comentario: Obtener lista de usuarios para el campo 'Destinatarios'.
$lista_usuarios_destinatarios = [];
try {
    $stmt_users = $pdo->prepare("SELECT id_usuario, nombres, apellidos, cargo FROM usuarios WHERE id_usuario != :id_usuario_actual AND estado = 'activo' ORDER BY apellidos, nombres");
    $stmt_users->bindParam(':id_usuario_actual', $id_usuario_actual, PDO::PARAM_INT);
    $stmt_users->execute();
    $lista_usuarios_destinatarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar lista de usuarios para redactar: " . $e->getMessage());
    mensaje_flash('error_redactar_form', 'No se pudo cargar la lista de destinatarios.', 'alert-warning');
}


// Comentario: Procesamiento del formulario.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_documento'])) {
    // Comentario: Re-asignar desde POST para validación.
    $tipo_documento_seleccionado = sanitizar_entrada($_POST['tipo_documento'] ?? 'nota_interna');
    $referencia = sanitizar_entrada($_POST['referencia'] ?? '');
    $contenido = strip_tags($_POST['contenido'] ?? '', '<p><br><ul><ol><li><strong><em><u><b><i><a><img>'); // Comentario: Ajustar etiquetas permitidas.
    $destinatarios_seleccionados = $_POST['destinatarios'] ?? [];
    $prioridad_seleccionada = sanitizar_entrada($_POST['prioridad'] ?? 'normal');
    $fecha_documento_form = sanitizar_entrada($_POST['fecha_documento'] ?? date('Y-m-d'));

    $errores_formulario = [];
    if (empty($referencia)) $errores_formulario[] = "La referencia es obligatoria.";
    if (empty($contenido)) $errores_formulario[] = "El contenido del documento es obligatorio.";
    if (empty($destinatarios_seleccionados) && !in_array($tipo_documento_seleccionado, ['informe'])) {
        // Comentario: Informes pueden no tener destinatario directo si son para archivo o un flujo diferente.
        // Comentario: Circulares y Memos usualmente sí. Notas internas siempre.
        if ($tipo_documento_seleccionado === 'nota_interna' || $tipo_documento_seleccionado === 'memorandum' || $tipo_documento_seleccionado === 'circular') {
             $errores_formulario[] = "Debe seleccionar al menos un destinatario para este tipo de documento.";
        }
    }
    if (empty($fecha_documento_form) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_documento_form)) {
        $errores_formulario[] = "La fecha del documento no es válida.";
    }

    if (empty($errores_formulario)) {
        $pdo->beginTransaction();
        try {
            $abrev_tipo_doc = '';
            switch ($tipo_documento_seleccionado) {
                case 'nota_interna': $abrev_tipo_doc = 'NIN'; break;
                case 'informe': $abrev_tipo_doc = 'INF'; break;
                case 'memorandum': $abrev_tipo_doc = 'MEM'; break;
                case 'circular': $abrev_tipo_doc = 'CIR'; break;
                default: $abrev_tipo_doc = 'DOC'; break;
            }
            // Comentario: Obtener siglas de la institución desde la configuración.
            $stmt_siglas = $pdo->query("SELECT valor_config FROM sistema_configuracion WHERE clave_config = 'SIGLAS_INSTITUCION' LIMIT 1");
            $siglas_inst = $stmt_siglas->fetchColumn() ?: 'ANCB';
            $cite_generado = generar_cite_documento($abrev_tipo_doc, $siglas_inst);

            if (empty($cite_generado)) throw new Exception("No se pudo generar el CITE para el documento.");

            $id_usuario_asignado_principal = null;
            $estado_inicial = 'en_redaccion'; // Comentario: Por defecto.

            if (!empty($destinatarios_seleccionados)) {
                $estado_inicial = 'derivado'; // Comentario: Si hay destinatarios, se deriva.
                // Comentario: Asignar al primer destinatario como el "responsable" principal en la tabla documentos.
                // Comentario: Las derivaciones a múltiples se manejan en el historial.
                $id_usuario_asignado_principal = (int)$destinatarios_seleccionados[0];
            } else if ($tipo_documento_seleccionado === 'informe') {
                 // Comentario: Si es informe sin destinatarios, queda asignado al creador y en redacción o listo para otro flujo.
                $id_usuario_asignado_principal = $id_usuario_actual;
                $estado_inicial = 'en_redaccion'; // O 'finalizado' si el informe no requiere más acción.
            }


            $sql_insert_doc = "INSERT INTO documentos (id_usuario_creador, id_usuario_asignado, tipo_documento, cite, referencia, contenido, fecha_documento, estado_documento, prioridad, fecha_recepcion_registro)
                               VALUES (:id_uc, :id_ua, :tipo, :cite, :ref, :cont, :fec_doc, :est_doc, :prio, NOW())";
            $stmt_insert_doc = $pdo->prepare($sql_insert_doc);
            $stmt_insert_doc->execute([
                ':id_uc' => $id_usuario_actual, ':id_ua' => $id_usuario_asignado_principal,
                ':tipo' => $tipo_documento_seleccionado, ':cite' => $cite_generado,
                ':ref' => $referencia, ':cont' => $contenido, ':fec_doc' => $fecha_documento_form,
                ':est_doc' => $estado_inicial, ':prio' => $prioridad_seleccionada
            ]);
            $id_documento_creado = $pdo->lastInsertId();

            registrar_historial_documento($id_documento_creado, $id_usuario_actual, 'Creación y Redacción', "Documento interno creado con CITE: $cite_generado.");

            if ($estado_inicial === 'derivado' && !empty($destinatarios_seleccionados)) {
                foreach ($destinatarios_seleccionados as $id_dest) {
                    $id_dest_int = (int)$id_dest;
                    $stmt_dest_info = $pdo->prepare("SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = :id_dest_info");
                    $stmt_dest_info->bindParam(':id_dest_info', $id_dest_int, PDO::PARAM_INT);
                    $stmt_dest_info->execute();
                    $dest_info_nombre = $stmt_dest_info->fetchColumn() ?: "ID Usuario $id_dest_int";
                    registrar_historial_documento($id_documento_creado, $id_usuario_actual, 'Derivación Inicial', "Documento derivado a $dest_info_nombre.", $id_usuario_actual, $id_dest_int);
                }
            }

            // Comentario: Manejar subida de archivos adjuntos (similar a correspondencia_registrar).
            if (isset($_FILES['archivos_adjuntos']) && count($_FILES['archivos_adjuntos']['name']) > 0 && $_FILES['archivos_adjuntos']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                $directorio_subidas = DIR_DOCUMENTOS . date('Y/m/');
                if (!is_dir($directorio_subidas)) mkdir($directorio_subidas, 0755, true);

                foreach ($_FILES['archivos_adjuntos']['name'] as $key => $nombre_original) {
                    if ($_FILES['archivos_adjuntos']['error'][$key] === UPLOAD_ERR_OK) {
                        // ... (lógica de validación de tamaño, tipo, mover archivo) ...
                        // ... (insertar en documentos_adjuntos) ...
                        // Comentario: Esta parte es idéntica a la de `correspondencia_registrar_logica.php` y se puede refactorizar a una función si se desea.
                        // Por ahora, la copio y adapto ligeramente.
                        $nombre_temporal = $_FILES['archivos_adjuntos']['tmp_name'][$key];
                        $tamano_archivo = $_FILES['archivos_adjuntos']['size'][$key];
                        $tipo_mime = $_FILES['archivos_adjuntos']['type'][$key];

                        if ($tamano_archivo > 5 * 1024 * 1024) { // Max 5MB
                            throw new Exception("El archivo '$nombre_original' excede el tamaño máximo de 5MB.");
                        }
                        // Comentario: Añadir validación de tipo MIME si es necesario.

                        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                        $nombre_archivo_servidor = uniqid('doc' . $id_documento_creado . '_adj' . ($key+1) . '_', true) . '.' . $extension;
                        $ruta_archivo_servidor = $directorio_subidas . $nombre_archivo_servidor;
                        $ruta_relativa_bd = date('Y/m/') . $nombre_archivo_servidor;

                        if (move_uploaded_file($nombre_temporal, $ruta_archivo_servidor)) {
                            $sql_adj = "INSERT INTO documentos_adjuntos (id_documento, nombre_archivo, ruta_archivo, tipo_mime, tamano_archivo, id_usuario_subida)
                                        VALUES (:id_doc, :nombre_orig, :ruta_bd, :mime, :tamano, :id_user)";
                            $stmt_adj = $pdo->prepare($sql_adj);
                            $stmt_adj->execute([
                                ':id_doc' => $id_documento_creado, ':nombre_orig' => $nombre_original,
                                ':ruta_bd' => $ruta_relativa_bd, ':mime' => $tipo_mime,
                                ':tamano' => $tamano_archivo, ':id_user' => $id_usuario_actual
                            ]);
                        } else {
                            throw new Exception("Error al mover el archivo subido '$nombre_original'.");
                        }
                    } elseif ($_FILES['archivos_adjuntos']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                        throw new Exception("Error al subir el archivo '$nombre_original': Código " . $_FILES['archivos_adjuntos']['error'][$key]);
                    }
                }
            }


            $pdo->commit();
            mensaje_flash('exito_redactar', "Documento '$cite_generado' creado y derivado exitosamente.", 'alert-success');
            redirigir('index.php?vista=documento_detalle&id=' . $id_documento_creado);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error al crear documento interno: " . $e->getMessage());
            mensaje_flash('error_redactar_form', 'Error al crear el documento: ' . $e->getMessage(), 'alert-danger');
            // Comentario: No redirigir, para que el formulario se repoble con los datos y muestre el error.
            // Comentario: Los valores de $_POST se usarán en la vista para repoblar.
        }

    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_redactar_form', $error, 'alert-danger');
        }
        // Comentario: No redirigir, la vista usará los valores de $_POST para repoblar.
    }
}

// Comentario: Fin de logica/correspondencia_redactar_logica.php
?>
