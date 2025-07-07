<?php
// Archivo: vistas/documento_detalle.php
// Propósito: Muestra los detalles completos de un documento, incluyendo su historial de trazabilidad y adjuntos.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Obtiene el ID del usuario actual.
if (!tiene_permiso('VER_DETALLE_DOCUMENTO', $id_usuario_actual)) { // Comentario: Asumiendo un permiso genérico.
    mensaje_flash('error_detalle_doc', 'No tiene permisos para ver detalles de documentos.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo; // Comentario: Acceder a la conexión PDO.

$id_documento = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Comentario: Obtiene y valida el ID del documento de la URL.

if (!$id_documento) {
    mensaje_flash('error_detalle_doc', 'ID de documento no válido o no proporcionado.', 'alert-danger');
    redirigir('index.php?vista=correspondencia_bandeja'); // Comentario: O al dashboard.
}

$documento = null; // Comentario: Para almacenar los datos del documento.
$historial = []; // Comentario: Para almacenar el historial del documento.
$adjuntos = []; // Comentario: Para almacenar los archivos adjuntos.

try {
    // Comentario: 1. Obtener datos del documento.
    $sql_doc = "SELECT d.*,
                       CONCAT(uc.nombres, ' ', uc.apellidos) as nombre_creador,
                       CONCAT(ua.nombres, ' ', ua.apellidos) as nombre_asignado,
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

    // Comentario: 2. Obtener historial de trazabilidad del documento.
    $sql_hist = "SELECT dh.*, CONCAT(u_accion.nombres, ' ', u_accion.apellidos) as nombre_usuario_accion,
                        CONCAT(u_origen.nombres, ' ', u_origen.apellidos) as nombre_usuario_origen,
                        CONCAT(u_destino.nombres, ' ', u_destino.apellidos) as nombre_usuario_destino
                 FROM documentos_historial dh
                 JOIN usuarios u_accion ON dh.id_usuario_accion = u_accion.id_usuario
                 LEFT JOIN usuarios u_origen ON dh.id_usuario_origen = u_origen.id_usuario
                 LEFT JOIN usuarios u_destino ON dh.id_usuario_destino = u_destino.id_usuario
                 WHERE dh.id_documento = :id_documento
                 ORDER BY dh.fecha_accion ASC"; // Comentario: Orden cronológico.
    $stmt_hist = $pdo->prepare($sql_hist);
    $stmt_hist->bindParam(':id_documento', $id_documento, PDO::PARAM_INT);
    $stmt_hist->execute();
    $historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

    // Comentario: 3. Obtener archivos adjuntos del documento.
    $sql_adj = "SELECT da.*, CONCAT(u_subida.nombres, ' ', u_subida.apellidos) as nombre_usuario_subida
                FROM documentos_adjuntos da
                JOIN usuarios u_subida ON da.id_usuario_subida = u_subida.id_usuario
                WHERE da.id_documento = :id_documento
                ORDER BY da.fecha_subida DESC";
    $stmt_adj = $pdo->prepare($sql_adj);
    $stmt_adj->bindParam(':id_documento', $id_documento, PDO::PARAM_INT);
    $stmt_adj->execute();
    $adjuntos = $stmt_adj->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar detalle del documento ID $id_documento: " . $e->getMessage());
    mensaje_flash('error_detalle_doc', 'Ocurrió un error al cargar los detalles del documento. Intente más tarde.', 'alert-danger');
    // Comentario: $documento podría ser null o parcial, la vista debe manejar esto.
}

// Comentario: Lógica para acciones sobre el documento (derivar, finalizar, etc.)
// Comentario: Esto se manejaría con formularios POST a este mismo script o a index.php con una 'accion'.
// Comentario: Ejemplo: si se envía un POST para derivar.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_documento'])) {
    $accion_doc = $_POST['accion_documento'];

    if ($accion_doc === 'derivar_documento' && tiene_permiso('DERIVAR_DOCUMENTO', $id_usuario_actual)) { // Comentario: Permiso hipotético.
        $id_destinatario_derivacion = filter_input(INPUT_POST, 'id_usuario_derivacion', FILTER_VALIDATE_INT);
        $comentario_derivacion = strip_tags($_POST['comentario_derivacion'] ?? '');

        if ($id_destinatario_derivacion && $documento) {
            $pdo->beginTransaction();
            try {
                // Comentario: Actualizar el id_usuario_asignado del documento.
                $sql_upd_asig = "UPDATE documentos SET id_usuario_asignado = :id_nuevo_asignado, estado_documento = 'derivado'
                                 WHERE id_documento = :id_doc";
                $stmt_upd_asig = $pdo->prepare($sql_upd_asig);
                $stmt_upd_asig->execute([
                    ':id_nuevo_asignado' => $id_destinatario_derivacion,
                    ':id_doc' => $id_documento
                ]);

                // Comentario: Registrar en historial.
                $stmt_dest_info = $pdo->prepare("SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = :id_dest");
                $stmt_dest_info->bindParam(':id_dest', $id_destinatario_derivacion, PDO::PARAM_INT);
                $stmt_dest_info->execute();
                $dest_info = $stmt_dest_info->fetch(PDO::FETCH_ASSOC);
                $nombre_dest_hist = $dest_info ? $dest_info['nombre_completo'] : "ID Usuario $id_destinatario_derivacion";
                $detalle_hist_deriv = "Documento derivado a $nombre_dest_hist.";
                if (!empty($comentario_derivacion)) {
                    $detalle_hist_deriv .= " Comentario: " . $comentario_derivacion;
                }
                registrar_historial_documento($id_documento, $id_usuario_actual, 'Derivación', $detalle_hist_deriv, $documento['id_usuario_asignado'], $id_destinatario_derivacion);

                $pdo->commit();
                mensaje_flash('exito_accion_documento', "Documento derivado exitosamente a $nombre_dest_hist.", 'alert-success');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Error al derivar documento ID $id_documento: " . $e->getMessage());
                mensaje_flash('error_accion_documento', 'Error al derivar el documento: ' . $e->getMessage(), 'alert-danger');
            }
            redirigir("index.php?vista=documento_detalle&id=$id_documento"); // Comentario: Recargar la página.
        } else {
            mensaje_flash('error_accion_documento', 'Debe seleccionar un destinatario para la derivación.', 'alert-danger');
            redirigir("index.php?vista=documento_detalle&id=$id_documento");
        }
    }
    // Comentario: Añadir más acciones como 'finalizar_documento', 'archivar_documento', 'agregar_comentario', etc.
    // Comentario: Cada una con su verificación de permisos y lógica correspondiente.
}


// Comentario: Cargar lista de usuarios para el formulario de derivación (si el usuario actual puede derivar).
$lista_usuarios_derivacion = [];
if ($documento && $documento['estado_documento'] !== 'finalizado' && $documento['estado_documento'] !== 'archivado' && $documento['estado_documento'] !== 'anulado' && tiene_permiso('DERIVAR_DOCUMENTO', $id_usuario_actual)) {
    try {
        // Comentario: Excluir al usuario actualmente asignado (si es el mismo que el actual, o si no hay asignado).
        $id_excluir = $documento['id_usuario_asignado'] ?? $id_usuario_actual;
        $stmt_users_deriv = $pdo->prepare("SELECT id_usuario, nombres, apellidos, cargo FROM usuarios WHERE id_usuario != :id_excluir AND estado = 'activo' ORDER BY apellidos, nombres");
        $stmt_users_deriv->bindParam(':id_excluir', $id_excluir, PDO::PARAM_INT);
        $stmt_users_deriv->execute();
        $lista_usuarios_derivacion = $stmt_users_deriv->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al cargar usuarios para derivación: " . $e->getMessage());
    }
}

?>

<h2>Detalle del Documento</h2>

<?php mensaje_flash('error_detalle_doc'); ?>
<?php mensaje_flash('error_accion_documento'); ?>
<?php mensaje_flash('exito_accion_documento'); ?>

<?php if ($documento): ?>
    <div class="detalle-documento-grid">
        <section class="datos-principales-doc">
            <h3>Datos del Documento</h3>
            <table class="tabla-info-detalle">
                <tr><th>CITE Interno:</th><td><?php echo htmlspecialchars($documento['cite'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Referencia:</th><td><?php echo htmlspecialchars($documento['referencia'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Tipo:</th><td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $documento['tipo_documento'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Fecha del Documento:</th><td><?php echo htmlspecialchars(($documento['fecha_documento'] ? date('d/m/Y', strtotime($documento['fecha_documento'])) : ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Fecha de Registro/Recepción:</th><td><?php echo htmlspecialchars(($documento['fecha_recepcion_registro'] ? date('d/m/Y H:i', strtotime($documento['fecha_recepcion_registro'])) : ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Estado Actual:</th><td><span class="estado-doc estado-<?php echo htmlspecialchars($documento['estado_documento'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $documento['estado_documento'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td></tr>
                <tr><th>Prioridad:</th><td><?php echo htmlspecialchars(ucfirst($documento['prioridad'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Creado por:</th><td><?php echo htmlspecialchars($documento['nombre_creador'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Actualmente Asignado a:</th><td><?php echo htmlspecialchars($documento['nombre_asignado'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php if ($documento['tipo_documento'] === 'carta_externa_recibida' && $documento['nombre_entidad_origen']): ?>
                    <tr><th>Entidad Origen:</th><td><?php echo htmlspecialchars($documento['nombre_entidad_origen'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endif; ?>
                <?php if ($documento['tipo_documento'] === 'carta_externa_enviada' && $documento['nombre_entidad_destino']): ?>
                    <tr><th>Entidad Destino:</th><td><?php echo htmlspecialchars($documento['nombre_entidad_destino'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($documento['observaciones'])): ?>
                    <tr><th>Observaciones Registro:</th><td><?php echo nl2br(htmlspecialchars($documento['observaciones'], ENT_QUOTES, 'UTF-8')); ?></td></tr>
                <?php endif; ?>
            </table>
        </section>

        <section class="contenido-doc">
            <h3>Contenido / Descripción</h3>
            <?php if (!empty($documento['contenido'])): ?>
                <div class="contenido-texto">
                    <?php echo nl2br(htmlspecialchars($documento['contenido'], ENT_QUOTES, 'UTF-8')); // Comentario: O si se permite HTML, usar una librería para sanitizar. ?>
                </div>
            <?php else: ?>
                <p>Este documento no tiene contenido textual principal registrado o es principalmente un adjunto.</p>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($documento['ruta_archivo_adjunto'] || !empty($adjuntos)): ?>
    <section class="adjuntos-doc mt-3">
        <h3>Archivos Adjuntos</h3>
        <ul>
            <?php if ($documento['ruta_archivo_adjunto']): // Comentario: Adjunto principal (si existe este campo en la tabla documentos) ?>
                <li>
                    <a href="<?php echo BASE_URL . 'docs/' . htmlspecialchars($documento['ruta_archivo_adjunto'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                        <?php echo htmlspecialchars(basename($documento['ruta_archivo_adjunto']), ENT_QUOTES, 'UTF-8'); ?> (Principal)
                    </a>
                </li>
            <?php endif; ?>
            <?php foreach ($adjuntos as $adj): ?>
                <li>
                    <a href="<?php echo BASE_URL . 'docs/' . htmlspecialchars($adj['ruta_archivo'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                        <?php echo htmlspecialchars($adj['nombre_archivo'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    (Subido por: <?php echo htmlspecialchars($adj['nombre_usuario_subida'], ENT_QUOTES, 'UTF-8'); ?>
                    el <?php echo date('d/m/Y H:i', strtotime($adj['fecha_subida'])); ?>)
                    <?php if ($adj['tamano_archivo']): ?>
                        - <?php echo round($adj['tamano_archivo'] / 1024, 1); ?> KB
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="historial-doc mt-3">
        <h3>Historial de Trazabilidad</h3>
        <?php if (empty($historial)): ?>
            <p>No hay historial de acciones para este documento.</p>
        <?php else: ?>
            <table class="tabla-datos tabla-historial">
                <thead>
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Acción Realizada Por</th>
                        <th>Tipo de Acción</th>
                        <th>Detalle</th>
                        <th>Usuario Origen</th>
                        <th>Usuario Destino</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($h['fecha_accion'])); ?></td>
                            <td><?php echo htmlspecialchars($h['nombre_usuario_accion'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><strong><?php echo htmlspecialchars($h['tipo_accion'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo nl2br(htmlspecialchars($h['descripcion_detalle'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                            <td><?php echo htmlspecialchars($h['nombre_usuario_origen'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($h['nombre_usuario_destino'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Sección de Acciones -->
    <?php if ($documento['estado_documento'] !== 'finalizado' && $documento['estado_documento'] !== 'archivado' && $documento['estado_documento'] !== 'anulado'): ?>
    <section class="acciones-doc mt-3">
        <h3>Acciones sobre el Documento</h3>

        <?php if (tiene_permiso('DERIVAR_DOCUMENTO', $id_usuario_actual) && ($documento['id_usuario_asignado'] == $id_usuario_actual || $documento['id_usuario_creador'] == $id_usuario_actual) ): ?>
            <form action="index.php?vista=documento_detalle&id=<?php echo $id_documento; ?>" method="POST" class="form-accion-documento">
                <h4>Derivar Documento</h4>
                <input type="hidden" name="accion_documento" value="derivar_documento">
                <div class="grupo-formulario">
                    <label for="id_usuario_derivacion">Derivar a:</label>
                    <select name="id_usuario_derivacion" id="id_usuario_derivacion" required>
                        <option value="">-- Seleccione un usuario --</option>
                        <?php foreach ($lista_usuarios_derivacion as $user_deriv): ?>
                            <option value="<?php echo $user_deriv['id_usuario']; ?>">
                                <?php echo htmlspecialchars($user_deriv['apellidos'] . ', ' . $user_deriv['nombres'] . ($user_deriv['cargo'] ? ' (' . $user_deriv['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grupo-formulario">
                    <label for="comentario_derivacion">Comentario de Derivación (opcional):</label>
                    <textarea name="comentario_derivacion" id="comentario_derivacion" rows="2"></textarea>
                </div>
                <button type="submit" class="boton boton-primario">Derivar</button>
            </form>
        <?php endif; ?>

        <?php if (tiene_permiso('FINALIZAR_DOCUMENTO', $id_usuario_actual) && ($documento['id_usuario_asignado'] == $id_usuario_actual || $documento['id_usuario_creador'] == $id_usuario_actual)): ?>
            <!-- Formulario para Finalizar Documento -->
            <form action="index.php?vista=documento_detalle&id=<?php echo $id_documento; ?>" method="POST" class="form-accion-documento confirmar-accion" data-mensaje-confirmacion="¿Está seguro de que desea finalizar este documento? Una vez finalizado, no podrá ser modificado o derivado nuevamente.">
                <input type="hidden" name="accion_documento" value="finalizar_documento">
                 <div class="grupo-formulario">
                    <label for="comentario_finalizacion">Comentario de Finalización (opcional):</label>
                    <textarea name="comentario_finalizacion" id="comentario_finalizacion" rows="2"></textarea>
                </div>
                <button type="submit" class="boton boton-exito">Finalizar Documento</button>
            </form>
        <?php endif; ?>

        <?php if (tiene_permiso('ARCHIVAR_DOCUMENTO', $id_usuario_actual) && ($documento['id_usuario_asignado'] == $id_usuario_actual || $documento['id_usuario_creador'] == $id_usuario_actual)): ?>
             <!-- Formulario para Archivar Documento -->
            <form action="index.php?vista=documento_detalle&id=<?php echo $id_documento; ?>" method="POST" class="form-accion-documento confirmar-accion" data-mensaje-confirmacion="¿Está seguro de que desea archivar este documento?">
                <input type="hidden" name="accion_documento" value="archivar_documento">
                 <div class="grupo-formulario">
                    <label for="comentario_archivado">Comentario de Archivado (opcional):</label>
                    <textarea name="comentario_archivado" id="comentario_archivado" rows="2"></textarea>
                </div>
                <button type="submit" class="boton boton-secundario">Archivar Documento</button>
            </form>
        <?php endif; ?>

        <!-- Comentario: Más acciones aquí: Añadir comentario, adjuntar más archivos, etc. -->

    </section>
    <?php endif; ?>


<?php else: ?>
    <?php if (empty(mensaje_flash(null,null,null))): // Comentario: Si no hay mensajes flash pendientes, significa que el error_detalle_doc ya se mostró o no hubo error pero no hay doc. ?>
    <p>El documento solicitado no pudo ser cargado o no existe.</p>
    <?php endif; ?>
<?php endif; ?>

<div class="mt-3">
    <a href="javascript:history.back()" class="boton boton-secundario">Volver Atrás</a>
    <a href="index.php?vista=correspondencia_bandeja" class="boton boton-secundario">Ir a Bandeja</a>
</div>

<style>
.detalle-documento-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Comentario: Columnas responsivas. */
    gap: 1.5rem; /* Comentario: Espacio entre secciones. */
    margin-bottom: 1.5rem;
}
.datos-principales-doc, .contenido-doc, .adjuntos-doc, .historial-doc, .acciones-doc {
    background-color: #fdfdfd;
    padding: 1.5rem;
    border: 1px solid #eee;
    border-radius: var(--borde-radio);
}
.datos-principales-doc h3, .contenido-doc h3, .adjuntos-doc h3, .historial-doc h3, .acciones-doc h3 {
    margin-top: 0;
    color: var(--color-primario);
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}
.tabla-info-detalle { width: 100%; border-collapse: collapse; }
.tabla-info-detalle th, .tabla-info-detalle td {
    padding: 0.5rem 0;
    text-align: left;
    border-bottom: 1px dotted #eee;
}
.tabla-info-detalle th { font-weight: bold; width: 30%; color: var(--color-secundario); }
.contenido-texto {
    background-color: #fff;
    padding: 1rem;
    border: 1px solid #f0f0f0;
    border-radius: var(--borde-radio);
    min-height: 100px;
    white-space: pre-wrap; /* Comentario: Para respetar saltos de línea y espacios del nl2br. */
}
.adjuntos-doc ul { list-style: none; padding-left: 0; }
.adjuntos-doc ul li { margin-bottom: 0.5rem; word-break: break-all; }
.tabla-historial th, .tabla-historial td { font-size: 0.9em; }

.form-accion-documento {
    border: 1px solid #ddd;
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: var(--borde-radio);
    background-color: #f9f9f9;
}
.form-accion-documento h4 {
    margin-top: 0;
    margin-bottom: 0.75rem;
    font-size: 1.1em;
    color: var(--color-secundario);
}
/* Re-usar estilos de estado-doc de bandeja */
.estado-doc { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.9em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-en_redaccion { background-color: #6c757d; } .estado-pendiente_revision { background-color: #ffc107; color: #333; }
.estado-derivado { background-color: #0dcaf0; color: #333; } .estado-en_proceso { background-color: #007bff; }
.estado-finalizado { background-color: #198754; } .estado-archivado { background-color: #495057; }
.estado-anulado { background-color: #dc3545; }
</style>

<?php
// Comentario: Fin del archivo vistas/documento_detalle.php
?>
