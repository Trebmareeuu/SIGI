<?php
// Archivo: vistas/correspondencia_registrar.php
// Propósito: (Secretaria) Formulario para registrar correspondencia externa recibida.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Obtiene el ID del usuario actual.
if (!tiene_permiso('REGISTRAR_CORRESPONDENCIA_EXTERNA', $id_usuario_actual)) {
    mensaje_flash('error_registrar_ext', 'No tiene permisos para registrar correspondencia externa.', 'alert-danger');
    redirigir('index.php?vista=dashboard'); // Comentario: Redirige si no tiene permiso.
}

global $pdo; // Comentario: Acceder a la conexión PDO.

// Comentario: Variables para el formulario.
$fecha_documento_externo = $_POST['fecha_documento_externo'] ?? date('Y-m-d');
$cite_externo = $_POST['cite_externo'] ?? '';
$referencia_externa = $_POST['referencia_externa'] ?? '';
$id_entidad_origen = $_POST['id_entidad_origen'] ?? null;
$nombre_remitente_especifico = $_POST['nombre_remitente_especifico'] ?? ''; // Comentario: Si la entidad es genérica.
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
    $fecha_documento_externo = sanitizar_entrada($_POST['fecha_documento_externo'] ?? date('Y-m-d'));
    $cite_externo = sanitizar_entrada($_POST['cite_externo'] ?? ''); // Comentario: CITE del documento que llega.
    $referencia_externa = sanitizar_entrada($_POST['referencia_externa'] ?? '');
    $id_entidad_origen = filter_var($_POST['id_entidad_origen'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $nombre_remitente_especifico = sanitizar_entrada($_POST['nombre_remitente_especifico'] ?? '');
    $descripcion_contenido = strip_tags($_POST['descripcion_contenido'] ?? ''); // Comentario: Breve descripción o resumen.
    $destinatarios_internos_seleccionados = $_POST['destinatarios_internos'] ?? [];
    $prioridad_seleccionada = sanitizar_entrada($_POST['prioridad'] ?? 'normal');
    $observaciones_registro = strip_tags($_POST['observaciones_registro'] ?? '');

    // Comentario: Validaciones.
    $errores_formulario = [];
    if (empty($fecha_documento_externo) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_documento_externo)) {
        $errores_formulario[] = "La fecha del documento externo no es válida.";
    }
    if (empty($referencia_externa)) {
        $errores_formulario[] = "La referencia (asunto) del documento externo es obligatoria.";
    }
    if (empty($id_entidad_origen) && empty($nombre_remitente_especifico)) {
        $errores_formulario[] = "Debe seleccionar una entidad remitente o ingresar un nombre de remitente específico.";
    }
     if (empty($destinatarios_internos_seleccionados)) {
        $errores_formulario[] = "Debe seleccionar al menos un destinatario interno para la derivación inicial.";
    }

    if (empty($errores_formulario)) {
        $pdo->beginTransaction();
        try {
            // Comentario: 1. Generar CITE interno para este registro.
            $cite_interno_generado = generar_cite_documento('REC'); // Comentario: 'REC' para Recibido.
            if (empty($cite_interno_generado)) {
                throw new Exception("No se pudo generar el CITE interno para el registro.");
            }

            // Comentario: Si se ingresó un remitente específico y no una entidad, se podría crear una entidad "Temporal" o "Varios".
            // Comentario: O añadir el nombre_remitente_especifico a un campo del documento.
            // Comentario: Por ahora, si hay nombre_remitente_especifico, se prioriza sobre id_entidad_origen si este es nulo,
            // Comentario: y se guarda en el campo 'referencia' o 'contenido' de forma descriptiva.
            // Comentario: Una mejor aproximación sería tener un campo 'remitente_texto' en la tabla 'documentos'.
            // Comentario: Para este ejemplo, si hay $nombre_remitente_especifico, $id_entidad_origen puede ser null.

            $referencia_final = $referencia_externa;
            if (!empty($nombre_remitente_especifico) && empty($id_entidad_origen)) {
                // Comentario: Añadir el remitente específico a la referencia si no se seleccionó entidad.
                $referencia_final .= " (Remitente: " . $nombre_remitente_especifico . ")";
            }


            // Comentario: 2. Insertar el documento en la tabla 'documentos'.
            // Comentario: El 'id_usuario_asignado' será el primer destinatario interno seleccionado.
            $id_usuario_asignado_principal_interno = null;
            if (!empty($destinatarios_internos_seleccionados)) {
                $id_usuario_asignado_principal_interno = (int)$destinatarios_internos_seleccionados[0];
            } else {
                 throw new Exception("Es mandatorio seleccionar un destinatario interno."); // Comentario: Doble check.
            }

            $sql_insert_doc = "INSERT INTO documentos (id_usuario_creador, id_usuario_asignado, id_entidad_externa_origen,
                                    tipo_documento, cite, referencia, contenido, fecha_documento, estado_documento, prioridad, observaciones, fecha_recepcion_registro)
                               VALUES (:id_usuario_creador, :id_usuario_asignado, :id_entidad_origen,
                                    :tipo_documento, :cite, :referencia, :contenido, :fecha_documento, :estado_documento, :prioridad, :observaciones, NOW())";
            $stmt_insert_doc = $pdo->prepare($sql_insert_doc);

            $stmt_insert_doc->execute([
                ':id_usuario_creador' => $id_usuario_actual, // Comentario: Quien registra.
                ':id_usuario_asignado' => $id_usuario_asignado_principal_interno,
                ':id_entidad_origen' => $id_entidad_origen, // Comentario: Puede ser null si se usa remitente_especifico.
                ':tipo_documento' => 'carta_externa_recibida',
                ':cite' => $cite_interno_generado, // Comentario: CITE interno del sistema.
                ':referencia' => $referencia_final, // Comentario: Asunto del doc externo.
                ':contenido' => $descripcion_contenido, // Comentario: Resumen o descripción.
                ':fecha_documento' => $fecha_documento_externo,
                ':estado_documento' => 'derivado', // Comentario: Se deriva inmediatamente.
                ':prioridad' => $prioridad_seleccionada,
                ':observaciones' => "CITE Original Externo: " . $cite_externo . ". " . $observaciones_registro
            ]);
            $id_documento_registrado = $pdo->lastInsertId();

            // Comentario: 3. Registrar en historial la recepción y registro.
            $detalle_hist_registro = "Correspondencia externa registrada con CITE Interno: $cite_interno_generado. ";
            if ($id_entidad_origen) {
                $stmt_ent_nom = $pdo->prepare("SELECT nombre_entidad FROM entidades_externas WHERE id_entidad = :id_ent");
                $stmt_ent_nom->bindParam(':id_ent', $id_entidad_origen, PDO::PARAM_INT);
                $stmt_ent_nom->execute();
                $ent_nom = $stmt_ent_nom->fetchColumn();
                $detalle_hist_registro .= "Origen: " . ($ent_nom ?: 'Entidad ID '.$id_entidad_origen) . ". ";
            } elseif (!empty($nombre_remitente_especifico)) {
                 $detalle_hist_registro .= "Remitente Específico: " . $nombre_remitente_especifico . ". ";
            }
            $detalle_hist_registro .= "CITE Externo: $cite_externo.";
            registrar_historial_documento($id_documento_registrado, $id_usuario_actual, 'Registro Externo', $detalle_hist_registro);

            // Comentario: 4. Registrar derivación a destinatarios internos.
            foreach ($destinatarios_internos_seleccionados as $id_dest_interno) {
                $id_dest = (int)$id_dest_interno;
                 // Comentario: Obtener nombre del destinatario para el historial.
                $stmt_dest_info = $pdo->prepare("SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = :id_dest");
                $stmt_dest_info->bindParam(':id_dest', $id_dest, PDO::PARAM_INT);
                $stmt_dest_info->execute();
                $dest_info = $stmt_dest_info->fetch(PDO::FETCH_ASSOC);
                $nombre_destinatario_hist = $dest_info ? $dest_info['nombre_completo'] : "ID Usuario $id_dest";

                registrar_historial_documento($id_documento_registrado, $id_usuario_actual, 'Derivación Inicial', "Documento derivado a $nombre_destinatario_hist.", $id_usuario_actual, $id_dest);

                // Comentario: Si es el primer destinatario, ya está como id_usuario_asignado.
                // Comentario: Si se quiere que el último de la lista sea el asignado, se actualiza aquí.
                // Comentario: O si se permite derivación múltiple donde todos son "asignados" (requiere cambio en BD o tabla de distribución).
                if ($id_dest !== $id_usuario_asignado_principal_interno) {
                    // Comentario: Ejemplo: si se quiere que el último destinatario de la lista quede como el asignado principal
                    // $sqlUpdateAsignado = $pdo->prepare("UPDATE documentos SET id_usuario_asignado = :id_dest_actualizar WHERE id_documento = :id_doc");
                    // $sqlUpdateAsignado->execute([':id_dest_actualizar' => $id_dest, ':id_doc' => $id_documento_registrado]);
                }
            }

            // Comentario: 5. Manejar subida de archivos adjuntos (documento escaneado).
            if (isset($_FILES['archivo_escaneado']) && $_FILES['archivo_escaneado']['error'] === UPLOAD_ERR_OK) {
                $directorio_subidas = DIR_DOCUMENTOS . date('Y/m/');
                if (!is_dir($directorio_subidas)) {
                    mkdir($directorio_subidas, 0775, true);
                }

                $nombre_original_adj = $_FILES['archivo_escaneado']['name'];
                $nombre_temporal_adj = $_FILES['archivo_escaneado']['tmp_name'];
                $tamano_archivo_adj = $_FILES['archivo_escaneado']['size'];
                $tipo_mime_adj = $_FILES['archivo_escaneado']['type'];

                if ($tamano_archivo_adj > 10 * 1024 * 1024) { // Comentario: Máximo 10MB para el escaneado.
                    throw new Exception("El archivo escaneado '$nombre_original_adj' excede el tamaño máximo de 10MB.");
                }
                // Comentario: Validar tipo MIME si es necesario.

                $extension_adj = pathinfo($nombre_original_adj, PATHINFO_EXTENSION);
                $nombre_archivo_servidor_adj = uniqid('docext_' . $id_documento_registrado . '_', true) . '.' . $extension_adj;
                $ruta_archivo_servidor_adj = $directorio_subidas . $nombre_archivo_servidor_adj;
                $ruta_relativa_bd_adj = date('Y/m/') . $nombre_archivo_servidor_adj;

                if (move_uploaded_file($nombre_temporal_adj, $ruta_archivo_servidor_adj)) {
                    // Comentario: Actualizar el campo 'ruta_archivo_adjunto' en la tabla 'documentos' (si es un solo adjunto principal).
                    // Comentario: O insertar en 'documentos_adjuntos' si se manejan múltiples.
                    $sql_upd_adj = "UPDATE documentos SET ruta_archivo_adjunto = :ruta_adj WHERE id_documento = :id_doc";
                    $stmt_upd_adj = $pdo->prepare($sql_upd_adj);
                    $stmt_upd_adj->execute([':ruta_adj' => $ruta_relativa_bd_adj, ':id_doc' => $id_documento_registrado]);

                    // Comentario: Opcionalmente, también registrar en documentos_adjuntos
                    /*
                    $sql_adj = "INSERT INTO documentos_adjuntos (id_documento, nombre_archivo, ruta_archivo, tipo_mime, tamano_archivo, id_usuario_subida)
                                VALUES (:id_doc, :nombre_orig, :ruta_bd, :mime, :tamano, :id_user)";
                    // ... ejecutar ...
                    */
                } else {
                    throw new Exception("Error al mover el archivo escaneado '$nombre_original_adj'.");
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
            mensaje_flash('error_registrar_ext', 'Error al registrar la correspondencia: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_registrar_ext_form', $error, 'alert-danger');
        }
    }
}


?>
<h2>Registrar Correspondencia Externa Recibida</h2>

<?php
mensaje_flash('error_registrar_ext');
mensaje_flash('error_registrar_ext_form');
mensaje_flash('exito_registrar_ext');
?>

<form action="index.php?vista=correspondencia_registrar" method="POST" enctype="multipart/form-data" class="validar-js">
    <fieldset>
        <legend>Datos del Documento Externo</legend>
        <div class="grupo-formulario">
            <label for="fecha_documento_externo">Fecha del Documento Externo:</label>
            <input type="date" id="fecha_documento_externo" name="fecha_documento_externo" value="<?php echo htmlspecialchars($fecha_documento_externo, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="grupo-formulario">
            <label for="cite_externo">CITE / Hoja de Ruta Externa (si aplica):</label>
            <input type="text" id="cite_externo" name="cite_externo" value="<?php echo htmlspecialchars($cite_externo, ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
        </div>

        <div class="grupo-formulario">
            <label for="referencia_externa">Referencia / Asunto del Documento Externo:</label>
            <input type="text" id="referencia_externa" name="referencia_externa" value="<?php echo htmlspecialchars($referencia_externa, ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        </div>

        <div class="grupo-formulario">
            <label for="id_entidad_origen">Entidad Remitente (Seleccionar de la lista):</label>
            <select id="id_entidad_origen" name="id_entidad_origen">
                <option value="">-- Seleccione una entidad --</option>
                <?php foreach ($lista_entidades as $entidad): ?>
                    <option value="<?php echo $entidad['id_entidad']; ?>" <?php echo ($id_entidad_origen == $entidad['id_entidad']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($entidad['nombre_entidad'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grupo-formulario">
             <label for="nombre_remitente_especifico">O Nombre del Remitente Específico (si no está en la lista):</label>
            <input type="text" id="nombre_remitente_especifico" name="nombre_remitente_especifico" value="<?php echo htmlspecialchars($nombre_remitente_especifico, ENT_QUOTES, 'UTF-8'); ?>" maxlength="255">
            <small>Usar si la entidad no existe o es una persona natural no registrada.</small>
        </div>
    </fieldset>

    <fieldset>
        <legend>Contenido y Derivación Interna</legend>
        <div class="grupo-formulario">
            <label for="descripcion_contenido">Breve Descripción / Contenido Principal:</label>
            <textarea id="descripcion_contenido" name="descripcion_contenido" rows="4"><?php echo htmlspecialchars($descripcion_contenido, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="grupo-formulario">
            <label for="destinatarios_internos">Derivar A (Destinatario(s) Interno(s)):</label>
            <select id="destinatarios_internos" name="destinatarios_internos[]" multiple required size="5">
                 <?php foreach ($lista_usuarios_internos as $usuario_int): ?>
                    <option value="<?php echo $usuario_int['id_usuario']; ?>"
                            <?php echo in_array($usuario_int['id_usuario'], $destinatarios_internos_seleccionados) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($usuario_int['apellidos'] . ', ' . $usuario_int['nombres'] . ($usuario_int['cargo'] ? ' (' . $usuario_int['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Seleccione uno o más funcionarios a quienes se derivará inicialmente este documento.</small>
        </div>

        <div class="grupo-formulario">
            <label for="prioridad">Prioridad Asignada:</label>
            <select id="prioridad" name="prioridad">
                <option value="baja" <?php echo ($prioridad_seleccionada === 'baja') ? 'selected' : ''; ?>>Baja</option>
                <option value="normal" <?php echo ($prioridad_seleccionada === 'normal') ? 'selected' : ''; ?>>Normal</option>
                <option value="alta" <?php echo ($prioridad_seleccionada === 'alta') ? 'selected' : ''; ?>>Alta</option>
                <option value="urgente" <?php echo ($prioridad_seleccionada === 'urgente') ? 'selected' : ''; ?>>Urgente</option>
            </select>
        </div>

        <div class="grupo-formulario">
            <label for="archivo_escaneado">Adjuntar Documento Escaneado (PDF, JPG, PNG - Max 10MB):</label>
            <input type="file" id="archivo_escaneado" name="archivo_escaneado" accept=".pdf,.jpg,.jpeg,.png">
        </div>

        <div class="grupo-formulario">
            <label for="observaciones_registro">Observaciones Adicionales del Registro:</label>
            <textarea id="observaciones_registro" name="observaciones_registro" rows="3"><?php echo htmlspecialchars($observaciones_registro, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
    </fieldset>

    <div class="grupo-formulario acciones-formulario mt-3">
        <button type="submit" name="registrar_correspondencia_externa" class="boton boton-primario">Registrar y Derivar</button>
        <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
    </div>
</form>

<?php
// Comentario: Fin del archivo vistas/correspondencia_registrar.php
?>
