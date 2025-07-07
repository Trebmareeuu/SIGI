<?php
// Archivo: vistas/mensajero_hoja_ruta.php
// Propósito: (Mensajero) Interfaz simple para ver entregas asignadas y subir descargos (fotos).
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Este es el Mensajero.
if (!tiene_permiso('VER_HOJA_RUTA_MENSAJERO', $id_usuario_actual)) {
    mensaje_flash('error_hoja_ruta', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Lógica para obtener la hoja de ruta del día o documentos asignados al mensajero.
// Comentario: Esto dependerá de cómo se asignen las tareas de mensajería.
// Comentario: Opción 1: Una tabla 'hoja_ruta_mensajeria' con tareas.
// Comentario: Opción 2: Documentos en estado 'para_entrega_externa' asignados al mensajero.
// Comentario: Asumiremos Opción 2 por ahora, donde documentos de tipo 'carta_externa_enviada'
// Comentario: en estado 'pendiente_entrega' (o similar) y asignados al mensajero son su hoja de ruta.

$entregas_pendientes = [];
$fecha_actual_hr = date('Y-m-d'); // Comentario: Hoja de ruta para hoy por defecto.
// Comentario: Podría haber un selector de fecha si el mensajero puede ver rutas pasadas/futuras.

try {
    $sql_hr = "SELECT d.id_documento, d.cite, d.referencia, d.estado_documento,
                      ee.nombre_entidad as entidad_destino_nombre, ee.direccion as entidad_destino_direccion,
                      ee.contacto_principal as entidad_contacto_nombre, ee.telefono_contacto as entidad_contacto_telefono,
                      d.observaciones as observaciones_entrega,
                      (SELECT MAX(dh.ruta_archivo) FROM documentos_adjuntos dh WHERE dh.id_documento = d.id_documento AND dh.nombre_archivo LIKE 'descargo_%') as ruta_ultimo_descargo
               FROM documentos d
               JOIN entidades_externas ee ON d.id_entidad_externa_destino = ee.id_entidad
               WHERE d.id_usuario_asignado = :id_mensajero
               AND d.tipo_documento = 'carta_externa_enviada'
               AND d.estado_documento IN ('pendiente_entrega', 'intento_fallido_entrega')
               -- AND DATE(d.fecha_asignacion_mensajero) = :fecha_ruta -- Comentario: Si hay fecha de asignación para ruta.
               ORDER BY ee.nombre_entidad ASC, d.id_documento ASC";
    $stmt_hr = $pdo->prepare($sql_hr);
    $stmt_hr->execute([
        ':id_mensajero' => $id_usuario_actual,
        // ':fecha_ruta' => $fecha_actual_hr // Comentario: Si se filtra por fecha.
    ]);
    $entregas_pendientes = $stmt_hr->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar hoja de ruta para mensajero ID $id_usuario_actual: " . $e->getMessage());
    mensaje_flash('error_hoja_ruta', 'Ocurrió un error al cargar su hoja de ruta. Intente más tarde.', 'alert-danger');
}


// Comentario: Procesamiento de subida de descargo.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_descargo']) && isset($_FILES['foto_descargo'])) {
    $id_documento_descargo = filter_input(INPUT_POST, 'id_documento_para_descargo', FILTER_VALIDATE_INT);
    $estado_entrega_descargo = sanitizar_entrada($_POST['estado_entrega'] ?? 'entregado_exitosamente'); // 'entregado_exitosamente', 'intento_fallido_entrega'
    $observaciones_descargo = strip_tags($_POST['observaciones_descargo'] ?? '');

    if ($id_documento_descargo && $_FILES['foto_descargo']['error'] === UPLOAD_ERR_OK) {
        $pdo->beginTransaction();
        try {
            $directorio_descargos = DIR_DOCUMENTOS . 'descargos_mensajeria/' . date('Y/m/');
            if (!is_dir($directorio_descargos)) {
                mkdir($directorio_descargos, 0775, true);
            }

            $nombre_original_desc = $_FILES['foto_descargo']['name'];
            $nombre_temporal_desc = $_FILES['foto_descargo']['tmp_name'];
            $tamano_archivo_desc = $_FILES['foto_descargo']['size'];
            $tipo_mime_desc = $_FILES['foto_descargo']['type'];

            // Comentario: Validar tamaño y tipo (ej. solo imágenes).
            $permitidos_mime_img = ['image/jpeg', 'image/png', 'image/gif'];
            if ($tamano_archivo_desc > 5 * 1024 * 1024) { // Comentario: Max 5MB.
                throw new Exception("La foto de descargo '$nombre_original_desc' excede el tamaño máximo de 5MB.");
            }
            if (!in_array(strtolower($tipo_mime_desc), $permitidos_mime_img)) {
                 throw new Exception("El archivo '$nombre_original_desc' no es una imagen válida (JPG, PNG, GIF). Tipo detectado: $tipo_mime_desc");
            }

            $extension_desc = pathinfo($nombre_original_desc, PATHINFO_EXTENSION);
            $nombre_archivo_servidor_desc = 'descargo_doc' . $id_documento_descargo . '_user' . $id_usuario_actual . '_' . time() . '.' . $extension_desc;
            $ruta_archivo_servidor_desc = $directorio_descargos . $nombre_archivo_servidor_desc;
            $ruta_relativa_bd_desc = 'descargos_mensajeria/' . date('Y/m/') . $nombre_archivo_servidor_desc;

            if (move_uploaded_file($nombre_temporal_desc, $ruta_archivo_servidor_desc)) {
                // Comentario: 1. Guardar el descargo en 'documentos_adjuntos'.
                $sql_adj_desc = "INSERT INTO documentos_adjuntos (id_documento, nombre_archivo, ruta_archivo, tipo_mime, tamano_archivo, id_usuario_subida)
                                 VALUES (:id_doc, :nombre_orig, :ruta_bd, :mime, :tamano, :id_user)";
                $stmt_adj_desc = $pdo->prepare($sql_adj_desc);
                $stmt_adj_desc->execute([
                    ':id_doc' => $id_documento_descargo,
                    ':nombre_orig' => "descargo_" . $nombre_original_desc, // Comentario: Prefijo para identificarlo.
                    ':ruta_bd' => $ruta_relativa_bd_desc,
                    ':mime' => $tipo_mime_desc,
                    ':tamano' => $tamano_archivo_desc,
                    ':id_user' => $id_usuario_actual
                ]);

                // Comentario: 2. Actualizar estado del documento principal.
                $nuevo_estado_doc_entrega = '';
                $mensaje_hist_entrega = '';
                if ($estado_entrega_descargo === 'entregado_exitosamente') {
                    $nuevo_estado_doc_entrega = 'finalizado'; // Comentario: O 'entregado'. Asumimos finalizado cierra el flujo.
                    $mensaje_hist_entrega = "Documento entregado exitosamente por mensajero. Descargo adjuntado.";
                } elseif ($estado_entrega_descargo === 'intento_fallido_entrega') {
                    $nuevo_estado_doc_entrega = 'intento_fallido_entrega'; // Comentario: Para reintentar o que secretaría reprograme.
                    $mensaje_hist_entrega = "Intento de entrega fallido. Mensajero adjuntó evidencia/descargo.";
                }
                if (!empty($observaciones_descargo)) {
                    $mensaje_hist_entrega .= " Observaciones Mensajero: " . $observaciones_descargo;
                }

                if (!empty($nuevo_estado_doc_entrega)) {
                    $sql_upd_doc_estado = "UPDATE documentos
                                           SET estado_documento = :estado_nuevo,
                                               observaciones = CONCAT(IFNULL(observaciones,''), '\nDescargo Mensajería (', NOW(), '): ', :obs_desc_doc)
                                           WHERE id_documento = :id_doc_upd";
                    $stmt_upd_doc_estado = $pdo->prepare($sql_upd_doc_estado);
                    $stmt_upd_doc_estado->execute([
                        ':estado_nuevo' => $nuevo_estado_doc_entrega,
                        ':obs_desc_doc' => $observaciones_descargo ?: ($estado_entrega_descargo === 'entregado_exitosamente' ? 'Entregado.' : 'Intento fallido.'),
                        ':id_doc_upd' => $id_documento_descargo
                    ]);
                }

                // Comentario: 3. Registrar en historial del documento.
                registrar_historial_documento($id_documento_descargo, $id_usuario_actual, 'Entrega Mensajería', $mensaje_hist_entrega);

                $pdo->commit();
                mensaje_flash('exito_hoja_ruta', "Descargo para documento ID $id_documento_descargo subido y registrado exitosamente.", 'alert-success');
            } else {
                throw new Exception("Error al mover la foto de descargo al servidor.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error al subir descargo para doc ID $id_documento_descargo: " . $e->getMessage());
            mensaje_flash('error_descargo_upload', 'Error al procesar el descargo: ' . $e->getMessage(), 'alert-danger');
        }

    } elseif ($id_documento_descargo) {
        mensaje_flash('error_descargo_upload', 'Error al subir la foto de descargo: Código ' . ($_FILES['foto_descargo']['error'] ?? 'Desconocido') . '. Asegúrese de seleccionar un archivo.', 'alert-danger');
    } else {
         mensaje_flash('error_descargo_upload', 'ID de documento no válido para el descargo.', 'alert-danger');
    }
    redirigir('index.php?vista=mensajero_hoja_ruta');
}


?>
<h2>Hoja de Ruta de Mensajería (Entregas Pendientes)</h2>
<p>A continuación se listan los documentos externos pendientes de entrega asignados a usted.</p>

<?php
mensaje_flash('error_hoja_ruta');
mensaje_flash('exito_hoja_ruta');
mensaje_flash('error_descargo_upload');
?>

<?php if (empty($entregas_pendientes)): ?>
    <div class="alert alert-info">No tiene entregas pendientes en su hoja de ruta actual.</div>
<?php else: ?>
    <div class="hoja-ruta-grid">
        <?php foreach ($entregas_pendientes as $entrega): ?>
            <div class="card-entrega <?php if($entrega['estado_documento'] === 'intento_fallido_entrega') echo 'entrega-fallida-previa'; ?>">
                <h4>Entrega a: <?php echo htmlspecialchars($entrega['entidad_destino_nombre'], ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><strong>Documento CITE:</strong> <?php echo htmlspecialchars($entrega['cite'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Referencia:</strong> <?php echo htmlspecialchars($entrega['referencia'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Dirección:</strong> <?php echo htmlspecialchars($entrega['entidad_destino_direccion'] ?? 'No especificada', ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Contacto:</strong> <?php echo htmlspecialchars($entrega['entidad_contacto_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                   (Tel: <?php echo htmlspecialchars($entrega['entidad_contacto_telefono'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>)</p>
                <?php if (!empty($entrega['observaciones_entrega'])): ?>
                    <p><strong>Observaciones Adicionales:</strong> <?php echo nl2br(htmlspecialchars($entrega['observaciones_entrega'], ENT_QUOTES, 'UTF-8')); ?></p>
                <?php endif; ?>
                 <?php if ($entrega['estado_documento'] === 'intento_fallido_entrega'): ?>
                    <p class="alerta-fallo"><strong>¡ATENCIÓN! Hubo un intento de entrega previo fallido para este documento.</strong></p>
                <?php endif; ?>

                <hr>
                <h5>Registrar Descargo de Entrega:</h5>
                <form action="index.php?vista=mensajero_hoja_ruta" method="POST" enctype="multipart/form-data" class="form-descargo">
                    <input type="hidden" name="id_documento_para_descargo" value="<?php echo $entrega['id_documento']; ?>">
                    <div class="grupo-formulario-sm">
                        <label for="estado_entrega_<?php echo $entrega['id_documento']; ?>">Estado de la Entrega:</label>
                        <select name="estado_entrega" id="estado_entrega_<?php echo $entrega['id_documento']; ?>" required>
                            <option value="entregado_exitosamente">Entregado Exitosamente</option>
                            <option value="intento_fallido_entrega">Intento Fallido (No se pudo entregar)</option>
                        </select>
                    </div>
                    <div class="grupo-formulario-sm">
                        <label for="foto_descargo_<?php echo $entrega['id_documento']; ?>">Foto del Descargo (Recibo sellado, fachada, etc.):</label>
                        <input type="file" name="foto_descargo" id="foto_descargo_<?php echo $entrega['id_documento']; ?>" accept="image/*" required>
                    </div>
                     <div class="grupo-formulario-sm">
                        <label for="observaciones_descargo_<?php echo $entrega['id_documento']; ?>">Observaciones de la Entrega/Intento:</label>
                        <textarea name="observaciones_descargo" id="observaciones_descargo_<?php echo $entrega['id_documento']; ?>" rows="2" placeholder="Ej: Entregado a recepción, Sr. X. / Nadie atendió. / Dirección incorrecta."></textarea>
                    </div>
                    <button type="submit" name="subir_descargo" class="boton boton-primario btn-sm">Subir Descargo</button>
                </form>
                <?php if ($entrega['ruta_ultimo_descargo']): ?>
                    <p class="mt-2"><small>Último descargo subido:
                        <a href="<?php echo BASE_URL . 'docs/' . htmlspecialchars($entrega['ruta_ultimo_descargo'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                            Ver Imagen <img src="<?php echo BASE_URL . 'docs/' . htmlspecialchars($entrega['ruta_ultimo_descargo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Miniatura descargo" style="max-height:30px; vertical-align:middle;">
                        </a></small>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.hoja-ruta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; }
.card-entrega { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-entrega h4 { margin-top: 0; color: var(--color-primario); }
.card-entrega h5 { margin-top: 1rem; margin-bottom: 0.5rem; color: var(--color-secundario); font-size: 1em; border-top: 1px dashed #ccc; padding-top: 0.75rem; }
.card-entrega p { margin-bottom: 0.5rem; font-size: 0.9em; }
.form-descargo .grupo-formulario-sm { margin-bottom: 0.75rem; }
.form-descargo .grupo-formulario-sm label { display: block; font-size: 0.85em; margin-bottom: 0.2rem; }
.form-descargo input[type="file"], .form-descargo select, .form-descargo textarea { width: 100%; padding: 0.4rem; font-size: 0.9em; }
.btn-sm { padding: 0.3rem 0.6rem; font-size: 0.9em; }
.entrega-fallida-previa { border-left: 5px solid var(--color-advertencia); }
.alerta-fallo { color: var(--color-error); font-weight: bold; background-color: #f8d7da; padding: 5px; border-radius: 3px;}
</style>

<?php
// Comentario: Fin del archivo vistas/mensajero_hoja_ruta.php
?>
