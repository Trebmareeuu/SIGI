<?php
// Archivo: vistas/documento_detalle.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: Muestra los detalles completos de un documento - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $documento, $historial, $adjuntos, $lista_usuarios_derivacion, $id_usuario_actual
// Comentario: son definidas en logica/documento_detalle_logica.php
?>

<h2>Detalle del Documento</h2>

<?php
mensaje_flash('error_detalle_doc');
mensaje_flash('error_accion_documento');
mensaje_flash('exito_accion_documento');
?>

<?php if ($documento): ?>
    <div class="detalle-documento-grid">
        <section class="datos-principales-doc card-sigi">
            <h3>Datos del Documento</h3>
            <table class="tabla-info-detalle">
                <tr><th>CITE Interno:</th><td><?php echo htmlspecialchars($documento['cite'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Referencia:</th><td><?php echo htmlspecialchars($documento['referencia'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Tipo:</th><td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $documento['tipo_documento'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Fecha del Documento:</th><td><?php echo htmlspecialchars(($documento['fecha_documento'] ? date('d/m/Y', strtotime($documento['fecha_documento'])) : ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Fecha de Registro/Recepción:</th><td><?php echo htmlspecialchars(($documento['fecha_recepcion_registro'] ? date('d/m/Y H:i', strtotime($documento['fecha_recepcion_registro'])) : ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Estado Actual:</th><td><span class="estado-doc estado-<?php echo htmlspecialchars($documento['estado_documento'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $documento['estado_documento'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td></tr>
                <tr><th>Prioridad:</th><td><?php echo htmlspecialchars(ucfirst($documento['prioridad'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><th>Creado por:</th><td><?php echo htmlspecialchars($documento['nombre_creador'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($documento['cargo_creador'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>)</td></tr>
                <tr><th>Actualmente Asignado a:</th><td><?php echo htmlspecialchars($documento['nombre_asignado'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?> <?php echo $documento['nombre_asignado'] ? ('(' . htmlspecialchars($documento['cargo_asignado'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . ')') : ''; ?></td></tr>
                <?php if ($documento['tipo_documento'] === 'carta_externa_recibida' && !empty($documento['nombre_entidad_origen'])): ?>
                    <tr><th>Entidad Origen:</th><td><?php echo htmlspecialchars($documento['nombre_entidad_origen'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endif; ?>
                <?php if ($documento['tipo_documento'] === 'carta_externa_enviada' && !empty($documento['nombre_entidad_destino'])): ?>
                    <tr><th>Entidad Destino:</th><td><?php echo htmlspecialchars($documento['nombre_entidad_destino'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($documento['observaciones'])): ?>
                    <tr><th>Observaciones Registro:</th><td><?php echo nl2br(htmlspecialchars($documento['observaciones'], ENT_QUOTES, 'UTF-8')); ?></td></tr>
                <?php endif; ?>
            </table>
        </section>

        <section class="contenido-doc card-sigi">
            <h3>Contenido / Descripción</h3>
            <?php if (!empty($documento['contenido'])): ?>
                <div class="contenido-texto">
                    <?php
                    // Comentario: Para mostrar HTML almacenado de forma segura, se necesitaría una librería sanitizadora robusta.
                    // Comentario: Por ahora, si se permitió HTML básico con strip_tags en la entrada, nl2br puede ser suficiente.
                    // Comentario: Si el contenido es texto plano, htmlspecialchars es más seguro.
                    // Comentario: Asumiendo que el contenido guardado ya fue sanitizado o es texto plano.
                    echo nl2br(htmlspecialchars($documento['contenido'], ENT_QUOTES, 'UTF-8'));
                    // Si se guardó HTML permitido: echo $documento['contenido']; (con el riesgo que conlleva si no se sanitizó bien)
                    ?>
                </div>
            <?php else: ?>
                <p>Este documento no tiene contenido textual principal registrado o es principalmente un adjunto.</p>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($documento['ruta_archivo_adjunto'] || !empty($adjuntos)): ?>
    <section class="adjuntos-doc mt-3 card-sigi">
        <h3>Archivos Adjuntos</h3>
        <ul>
            <?php if ($documento['ruta_archivo_adjunto']): ?>
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

    <section class="historial-doc mt-3 card-sigi">
        <h3>Historial de Trazabilidad</h3>
        <?php if (empty($historial)): ?>
            <p>No hay historial de acciones para este documento.</p>
        <?php else: ?>
            <div class="table-responsive">
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
            </div>
        <?php endif; ?>
    </section>

    <?php if ($documento && !in_array($documento['estado_documento'], ['finalizado', 'archivado', 'anulado'])): ?>
    <section class="acciones-doc mt-3 card-sigi">
        <h3>Acciones sobre el Documento</h3>

        <?php if (tiene_permiso('DERIVAR_DOCUMENTO', $id_usuario_actual) && ($documento['id_usuario_asignado'] == $id_usuario_actual || $documento['id_usuario_creador'] == $id_usuario_actual || tiene_permiso('REGISTRAR_CORRESPONDENCIA_EXTERNA', $id_usuario_actual) ) ): // Comentario: Permitir a creador o asignado o secretaria (si registra) ?>
            <form action="index.php?vista=documento_detalle&id=<?php echo $id_documento; ?>" method="POST" class="form-accion-documento validar-js">
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
            <form action="index.php?vista=documento_detalle&id=<?php echo $id_documento; ?>" method="POST" class="form-accion-documento confirmar-accion" data-mensaje-confirmacion="¿Está seguro de que desea finalizar este documento? Una vez finalizado, no podrá ser modificado o derivado nuevamente.">
                <input type="hidden" name="accion_documento" value="finalizar_documento">
                 <div class="grupo-formulario">
                    <label for="comentario_finalizacion">Comentario de Finalización (opcional):</label>
                    <textarea name="comentario_finalizacion" id="comentario_finalizacion" rows="2"></textarea>
                </div>
                <button type="submit" class="boton boton-exito">Finalizar Documento</button>
            </form>
        <?php endif; ?>

        <?php if (tiene_permiso('ARCHIVAR_DOCUMENTO', $id_usuario_actual) && ($documento['id_usuario_asignado'] == $id_usuario_actual || $documento['id_usuario_creador'] == $id_usuario_actual || tiene_permiso('REGISTRAR_CORRESPONDENCIA_EXTERNA', $id_usuario_actual)) ): ?>
            <form action="index.php?vista=documento_detalle&id=<?php echo $id_documento; ?>" method="POST" class="form-accion-documento confirmar-accion" data-mensaje-confirmacion="¿Está seguro de que desea archivar este documento?">
                <input type="hidden" name="accion_documento" value="archivar_documento">
                 <div class="grupo-formulario">
                    <label for="comentario_archivado">Comentario de Archivado (opcional):</label>
                    <textarea name="comentario_archivado" id="comentario_archivado" rows="2"></textarea>
                </div>
                <button type="submit" class="boton boton-secundario">Archivar Documento</button>
            </form>
        <?php endif; ?>

        <?php if (tiene_permiso('COMENTAR_DOCUMENTO', $id_usuario_actual)): /* Permiso hipotético */ ?>
            <form action="index.php?vista=documento_detalle&id=<?php echo $id_documento; ?>" method="POST" class="form-accion-documento validar-js">
                <h4>Agregar Comentario</h4>
                <input type="hidden" name="accion_documento" value="agregar_comentario">
                <div class="grupo-formulario">
                    <label for="comentario_accion">Comentario:</label>
                    <textarea name="comentario_accion" id="comentario_accion" rows="3" required></textarea>
                </div>
                <button type="submit" class="boton boton-info">Agregar Comentario</button>
            </form>
        <?php endif; ?>

    </section>
    <?php endif; ?>


<?php else: ?>
    <?php if(empty(mensaje_flash(null,null,null))): ?>
    <p>El documento solicitado no pudo ser cargado o no existe.</p>
    <?php endif; ?>
<?php endif; ?>

<div class="mt-3">
    <a href="javascript:history.back()" class="boton boton-secundario">Volver Atrás</a>
    <a href="index.php?vista=correspondencia_bandeja" class="boton boton-secundario">Ir a Bandeja</a>
</div>

<style>
/* Comentario: Estilos específicos para esta vista (si son necesarios y no están en estilos.css global). */
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.detalle-documento-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.datos-principales-doc h3, .contenido-doc h3, .adjuntos-doc h3, .historial-doc h3, .acciones-doc h3 {
    margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem;
}
.tabla-info-detalle { width: 100%; border-collapse: collapse; }
.tabla-info-detalle th, .tabla-info-detalle td {
    padding: 0.5rem 0.3rem; text-align: left; border-bottom: 1px dotted #eee; vertical-align: top;
}
.tabla-info-detalle th { font-weight: bold; width: 30%; color: var(--color-secundario); }
.contenido-texto { background-color: #fff; padding: 1rem; border: 1px solid #f0f0f0; border-radius: var(--borde-radio); min-height: 100px; white-space: pre-wrap; }
.adjuntos-doc ul { list-style: none; padding-left: 0; }
.adjuntos-doc ul li { margin-bottom: 0.5rem; word-break: break-all; }
.tabla-historial th, .tabla-historial td { font-size: 0.9em; }
.form-accion-documento { border: 1px solid #ddd; padding: 1rem; margin-bottom: 1rem; border-radius: var(--borde-radio); background-color: #f9f9f9;}
.form-accion-documento h4 { margin-top: 0; margin-bottom: 0.75rem; font-size: 1.1em; color: var(--color-secundario); }
.estado-doc { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.9em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-en_redaccion { background-color: #6c757d; } .estado-pendiente_revision { background-color: #ffc107; color: #333; }
.estado-derivado { background-color: #0dcaf0; color: #333; } .estado-en_proceso { background-color: #007bff; }
.estado-finalizado { background-color: #198754; } .estado-archivado { background-color: #495057; }
.estado-anulado { background-color: #dc3545; }
</style>

<?php
// Comentario: Fin del archivo vistas/documento_detalle.php (SOLO PRESENTACIÓN)
?>
