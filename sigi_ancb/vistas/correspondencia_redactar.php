<?php
// Archivo: vistas/correspondencia_redactar.php
// Propósito: Formulario para crear/redactar notas o informes internos.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Asumimos que config.php, funciones.php, header.php ya han sido incluidos por index.php
// Comentario: y que la sesión del usuario está activa y verificada.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Obtiene el ID del usuario actual.
if (!tiene_permiso('REDACTAR_CORRESPONDENCIA_INTERNA', $id_usuario_actual)) {
    mensaje_flash('error_redactar', 'No tiene permisos para redactar correspondencia interna.', 'alert-danger');
    redirigir('index.php?vista=dashboard'); // Comentario: Redirige si no tiene permiso.
}

global $pdo; // Comentario: Acceder a la conexión PDO.

// Comentario: Variables para el formulario.
$tipo_documento_seleccionado = $_POST['tipo_documento'] ?? 'nota_interna';
$referencia = $_POST['referencia'] ?? '';
$contenido = $_POST['contenido'] ?? '';
$destinatarios_seleccionados = $_POST['destinatarios'] ?? []; // Comentario: Array de IDs de usuarios destinatarios.
$archivos_adjuntos = []; // Comentario: Se manejará la subida de archivos.
$prioridad_seleccionada = $_POST['prioridad'] ?? 'normal';

// Comentario: Obtener lista de usuarios para el campo 'Destinatarios' (excluyendo al usuario actual).
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
    $tipo_documento_seleccionado = sanitizar_entrada($_POST['tipo_documento'] ?? 'nota_interna');
    $referencia = sanitizar_entrada($_POST['referencia'] ?? '');
    // Comentario: El contenido puede necesitar una sanitización más laxa si se permite HTML (usar librería). Por ahora, simple.
    $contenido = strip_tags($_POST['contenido'] ?? '', '<p><br><ul><ol><li><strong><em><u>'); // Comentario: Permitir algunas etiquetas HTML básicas.
    $destinatarios_seleccionados = $_POST['destinatarios'] ?? []; // Comentario: Array de IDs.
    $prioridad_seleccionada = sanitizar_entrada($_POST['prioridad'] ?? 'normal');
    $fecha_documento = $_POST['fecha_documento'] ?? date('Y-m-d'); // Comentario: Fecha del documento.

    // Comentario: Validaciones.
    $errores_formulario = [];
    if (empty($referencia)) {
        $errores_formulario[] = "La referencia es obligatoria.";
    }
    if (empty($contenido)) {
        $errores_formulario[] = "El contenido del documento es obligatorio.";
    }
    if (empty($destinatarios_seleccionados) && $tipo_documento_seleccionado !== 'informe') { // Comentario: Informes pueden no tener destinatario directo.
        $errores_formulario[] = "Debe seleccionar al menos un destinatario.";
    }
     if (empty($fecha_documento) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_documento)) {
        $errores_formulario[] = "La fecha del documento no es válida.";
    }


    if (empty($errores_formulario)) {
        $pdo->beginTransaction(); // Comentario: Iniciar transacción.
        try {
            // Comentario: 1. Generar CITE.
            $abrev_tipo_doc = '';
            switch ($tipo_documento_seleccionado) {
                case 'nota_interna': $abrev_tipo_doc = 'NIN'; break;
                case 'informe': $abrev_tipo_doc = 'INF'; break;
                case 'memorandum': $abrev_tipo_doc = 'MEM'; break;
                case 'circular': $abrev_tipo_doc = 'CIR'; break;
                default: $abrev_tipo_doc = 'DOC'; break;
            }
            $cite_generado = generar_cite_documento($abrev_tipo_doc);
            if (empty($cite_generado)) {
                throw new Exception("No se pudo generar el CITE para el documento.");
            }

            // Comentario: 2. Insertar el documento principal.
            // Comentario: Para documentos internos, el 'id_usuario_asignado' es el primer destinatario o el principal.
            // Comentario: Si hay múltiples destinatarios, se podría manejar de forma diferente (ej. tabla de distribución).
            // Comentario: Por simplicidad, si es circular o memo a varios, el asignado puede ser null o el creador para seguimiento.
            // Comentario: Si es nota a una persona, esa persona es el asignado.

            $id_usuario_asignado_principal = null;
            if (!empty($destinatarios_seleccionados)) {
                 // Comentario: Si es nota_interna o memorandum a una persona, el asignado es esa persona.
                if (($tipo_documento_seleccionado === 'nota_interna' || $tipo_documento_seleccionado === 'memorandum') && count($destinatarios_seleccionados) === 1) {
                    $id_usuario_asignado_principal = (int)$destinatarios_seleccionados[0];
                }
                // Comentario: Para informes o circulares, el asignado podría ser el jefe del creador o un grupo, o null.
                // Comentario: Por ahora, si hay varios destinatarios o es informe/circular, no se asigna a uno específico en `id_usuario_asignado`.
                // Comentario: La trazabilidad se verá en `documentos_historial`.
            }


            $sql_insert_doc = "INSERT INTO documentos (id_usuario_creador, id_usuario_asignado, tipo_documento, cite, referencia, contenido, fecha_documento, estado_documento, prioridad, fecha_recepcion_registro)
                               VALUES (:id_usuario_creador, :id_usuario_asignado, :tipo_documento, :cite, :referencia, :contenido, :fecha_documento, :estado_documento, :prioridad, NOW())";
            $stmt_insert_doc = $pdo->prepare($sql_insert_doc);
            $estado_inicial = 'derivado'; // Comentario: O 'en_proceso' si es para uno mismo (ej. informe).
            if ($tipo_documento_seleccionado === 'informe' && empty($destinatarios_seleccionados)) {
                $estado_inicial = 'en_redaccion'; // Comentario: Si es un informe sin destinatario, queda en redacción para el creador.
                $id_usuario_asignado_principal = $id_usuario_actual; // Asignado a sí mismo.
            } elseif (empty($destinatarios_seleccionados) && ($tipo_documento_seleccionado === 'nota_interna' || $tipo_documento_seleccionado === 'memorandum')) {
                 // Esto no debería pasar si la validación de destinatarios está activa.
                 // Pero si pasa, lo dejamos en redacción.
                $estado_inicial = 'en_redaccion';
                $id_usuario_asignado_principal = $id_usuario_actual;
            }


            $stmt_insert_doc->execute([
                ':id_usuario_creador' => $id_usuario_actual,
                ':id_usuario_asignado' => $id_usuario_asignado_principal,
                ':tipo_documento' => $tipo_documento_seleccionado,
                ':cite' => $cite_generado,
                ':referencia' => $referencia,
                ':contenido' => $contenido,
                ':fecha_documento' => $fecha_documento,
                ':estado_documento' => $estado_inicial,
                ':prioridad' => $prioridad_seleccionada
            ]);
            $id_documento_creado = $pdo->lastInsertId();

            // Comentario: 3. Registrar en historial la creación.
            registrar_historial_documento($id_documento_creado, $id_usuario_actual, 'Creación y Redacción', "Documento creado con CITE: $cite_generado.");

            // Comentario: 4. Registrar derivación a destinatarios en historial.
            if ($estado_inicial === 'derivado') {
                foreach ($destinatarios_seleccionados as $id_destinatario) {
                    $id_dest = (int)$id_destinatario;
                    // Comentario: Si el documento se asignó al primer destinatario, no duplicar la asignación principal.
                    // Comentario: Pero sí registrar la derivación para todos.
                    if ($id_dest !== $id_usuario_asignado_principal && $id_usuario_asignado_principal !== null) {
                        // Comentario: Actualizar el 'id_usuario_asignado' del documento si es una derivación múltiple y se quiere que el último sea el "dueño".
                        // Comentario: O manejar múltiples asignados en otra tabla. Por ahora, el historial lo registra.
                         $sql_update_asignado = "UPDATE documentos SET id_usuario_asignado = :id_dest WHERE id_documento = :id_doc AND :id_asignado_principal IS NULL";
                         $stmt_update_as = $pdo->prepare($sql_update_asignado);
                         $stmt_update_as->execute([':id_dest' => $id_dest, ':id_doc' => $id_documento_creado, ':id_asignado_principal' => $id_usuario_asignado_principal]);
                    }
                    // Comentario: Obtener nombre del destinatario para el historial.
                    $stmt_dest_info = $pdo->prepare("SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = :id_dest");
                    $stmt_dest_info->bindParam(':id_dest', $id_dest, PDO::PARAM_INT);
                    $stmt_dest_info->execute();
                    $dest_info = $stmt_dest_info->fetch(PDO::FETCH_ASSOC);
                    $nombre_destinatario_hist = $dest_info ? $dest_info['nombre_completo'] : "ID Usuario $id_dest";

                    registrar_historial_documento($id_documento_creado, $id_usuario_actual, 'Derivación', "Documento derivado a $nombre_destinatario_hist.", $id_usuario_actual, $id_dest);
                }
            }


            // Comentario: 5. Manejar subida de archivos adjuntos.
            if (isset($_FILES['archivos_adjuntos']) && count($_FILES['archivos_adjuntos']['name']) > 0) {
                $directorio_subidas = DIR_DOCUMENTOS . date('Y/m/'); // Comentario: Organizar por año/mes.
                if (!is_dir($directorio_subidas)) {
                    mkdir($directorio_subidas, 0775, true); // Comentario: Crear directorio si no existe.
                }

                foreach ($_FILES['archivos_adjuntos']['name'] as $key => $nombre_original) {
                    if ($_FILES['archivos_adjuntos']['error'][$key] === UPLOAD_ERR_OK) {
                        $nombre_temporal = $_FILES['archivos_adjuntos']['tmp_name'][$key];
                        $tamano_archivo = $_FILES['archivos_adjuntos']['size'][$key];
                        $tipo_mime = $_FILES['archivos_adjuntos']['type'][$key];

                        // Comentario: Validar tamaño y tipo de archivo (ejemplo).
                        if ($tamano_archivo > 5 * 1024 * 1024) { // Comentario: Máximo 5MB.
                            throw new Exception("El archivo '$nombre_original' excede el tamaño máximo de 5MB.");
                        }
                        // Comentario: $permitidos_mime = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                        // Comentario: if (!in_array($tipo_mime, $permitidos_mime)) { ... }

                        $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                        $nombre_archivo_servidor = uniqid('doc_' . $id_documento_creado . '_', true) . '.' . $extension;
                        $ruta_archivo_servidor = $directorio_subidas . $nombre_archivo_servidor;
                        $ruta_relativa_bd = date('Y/m/') . $nombre_archivo_servidor; // Comentario: Ruta a guardar en BD.

                        if (move_uploaded_file($nombre_temporal, $ruta_archivo_servidor)) {
                            // Comentario: Insertar en tabla documentos_adjuntos.
                            $sql_adj = "INSERT INTO documentos_adjuntos (id_documento, nombre_archivo, ruta_archivo, tipo_mime, tamano_archivo, id_usuario_subida)
                                        VALUES (:id_doc, :nombre_orig, :ruta_bd, :mime, :tamano, :id_user)";
                            $stmt_adj = $pdo->prepare($sql_adj);
                            $stmt_adj->execute([
                                ':id_doc' => $id_documento_creado,
                                ':nombre_orig' => $nombre_original,
                                ':ruta_bd' => $ruta_relativa_bd,
                                ':mime' => $tipo_mime,
                                ':tamano' => $tamano_archivo,
                                ':id_user' => $id_usuario_actual
                            ]);
                        } else {
                            throw new Exception("Error al mover el archivo subido '$nombre_original'.");
                        }
                    } elseif ($_FILES['archivos_adjuntos']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                        throw new Exception("Error al subir el archivo '$nombre_original': Código " . $_FILES['archivos_adjuntos']['error'][$key]);
                    }
                }
            }

            $pdo->commit(); // Comentario: Confirmar transacción.
            mensaje_flash('exito_redactar', "Documento '$cite_generado' creado y derivado exitosamente.", 'alert-success');
            redirigir('index.php?vista=documento_detalle&id=' . $id_documento_creado); // Comentario: Redirigir al detalle del documento.

        } catch (Exception $e) {
            $pdo->rollBack(); // Comentario: Revertir transacción en caso de error.
            error_log("Error al crear documento interno: " . $e->getMessage());
            mensaje_flash('error_redactar', 'Error al crear el documento: ' . $e->getMessage(), 'alert-danger');
        }

    } else {
        // Comentario: Mostrar errores de validación.
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_redactar_form', $error, 'alert-danger'); // Comentario: Acumular errores para mostrar.
        }
    }
}


?>
<h2>Redactar Correspondencia Interna</h2>

<?php
mensaje_flash('error_redactar');
mensaje_flash('error_redactar_form');
mensaje_flash('exito_redactar');
?>

<form action="index.php?vista=correspondencia_redactar" method="POST" enctype="multipart/form-data" class="validar-js">
    <div class="grupo-formulario">
        <label for="tipo_documento">Tipo de Documento:</label>
        <select id="tipo_documento" name="tipo_documento" required>
            <option value="nota_interna" <?php echo ($tipo_documento_seleccionado === 'nota_interna') ? 'selected' : ''; ?>>Nota Interna</option>
            <option value="informe" <?php echo ($tipo_documento_seleccionado === 'informe') ? 'selected' : ''; ?>>Informe</option>
            <option value="memorandum" <?php echo ($tipo_documento_seleccionado === 'memorandum') ? 'selected' : ''; ?>>Memorándum</option>
            <option value="circular" <?php echo ($tipo_documento_seleccionado === 'circular') ? 'selected' : ''; ?>>Circular</option>
            <!-- Comentario: Añadir más tipos si es necesario -->
        </select>
    </div>

    <div class="grupo-formulario">
        <label for="fecha_documento">Fecha del Documento:</label>
        <input type="date" id="fecha_documento" name="fecha_documento" value="<?php echo htmlspecialchars($_POST['fecha_documento'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>

    <div class="grupo-formulario">
        <label for="referencia">Referencia (Asunto):</label>
        <input type="text" id="referencia" name="referencia" value="<?php echo htmlspecialchars($referencia, ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
    </div>

    <div class="grupo-formulario">
        <label for="destinatarios">Destinatario(s):</label>
        <select id="destinatarios" name="destinatarios[]" multiple required size="5">
            <?php foreach ($lista_usuarios_destinatarios as $usuario_dest): ?>
                <option value="<?php echo $usuario_dest['id_usuario']; ?>"
                        <?php echo in_array($usuario_dest['id_usuario'], $destinatarios_seleccionados) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($usuario_dest['apellidos'] . ', ' . $usuario_dest['nombres'] . ($usuario_dest['cargo'] ? ' (' . $usuario_dest['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Mantenga presionada la tecla Ctrl (o Cmd en Mac) para seleccionar múltiples destinatarios. Para Informes, este campo puede ser opcional si el informe es de naturaleza general o para un superior que no está en la lista.</small>
    </div>

    <div class="grupo-formulario">
        <label for="prioridad">Prioridad:</label>
        <select id="prioridad" name="prioridad">
            <option value="baja" <?php echo ($prioridad_seleccionada === 'baja') ? 'selected' : ''; ?>>Baja</option>
            <option value="normal" <?php echo ($prioridad_seleccionada === 'normal') ? 'selected' : ''; ?>>Normal</option>
            <option value="alta" <?php echo ($prioridad_seleccionada === 'alta') ? 'selected' : ''; ?>>Alta</option>
            <option value="urgente" <?php echo ($prioridad_seleccionada === 'urgente') ? 'selected' : ''; ?>>Urgente</option>
        </select>
    </div>

    <div class="grupo-formulario">
        <label for="contenido">Contenido del Documento:</label>
        <textarea id="contenido" name="contenido" rows="15" required><?php echo htmlspecialchars($contenido, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <!-- Comentario: Considerar un editor de texto enriquecido (WYSIWYG) aquí si se necesita formato complejo. -->
        <!-- Comentario: Por ahora, es un textarea simple. La sanitización en PHP permite algunas etiquetas. -->
    </div>

    <div class="grupo-formulario">
        <label for="archivos_adjuntos">Archivos Adjuntos (opcional):</label>
        <input type="file" id="archivos_adjuntos" name="archivos_adjuntos[]" multiple>
        <small>Puede seleccionar múltiples archivos. Tamaño máximo por archivo: 5MB.</small>
    </div>

    <div class="grupo-formulario acciones-formulario">
        <button type="submit" name="guardar_documento" class="boton boton-primario">Guardar y Enviar</button>
        <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
    </div>
</form>

<style>
/* Comentario: Estilos para mejorar la apariencia del select multiple, si es necesario. */
#destinatarios {
    min-height: 100px; /* Comentario: Para que se vean más opciones sin scroll. */
    border: 1px solid #ced4da;
    border-radius: var(--borde-radio);
    padding: 0.5rem;
}
/* Comentario: Estilos para el editor WYSIWYG si se implementa uno. */
/* Comentario: .editor-toolbar { ... } */
/* Comentario: .editor-content { ... } */
</style>

<!-- Comentario: Si se usa un editor WYSIWYG, incluir su JS aquí. -->
<!-- <script src="path/to/wysiwyg-editor.js"></script> -->
<!-- <script>
    // Comentario: Inicializar el editor WYSIWYG si se usa.
    // if (document.getElementById('contenido')) {
    //     new MyWYSIWYGEditor('#contenido');
    // }
</script> -->

<?php
// Comentario: Fin del archivo vistas/correspondencia_redactar.php
?>
