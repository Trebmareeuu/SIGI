<?php
// Archivo: vistas/correspondencia_redactar.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: Formulario para crear/redactar notas o informes internos - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $tipo_documento_seleccionado, $referencia, $contenido, $destinatarios_seleccionados,
// Comentario: $prioridad_seleccionada, $fecha_documento_form, $lista_usuarios_destinatarios
// Comentario: son definidas en logica/correspondencia_redactar_logica.php
?>
<h2>Redactar Correspondencia Interna</h2>

<?php
// Comentario: Mostrar mensajes flash.
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
        </select>
    </div>

    <div class="grupo-formulario">
        <label for="fecha_documento">Fecha del Documento:</label>
        <input type="date" id="fecha_documento" name="fecha_documento" value="<?php echo htmlspecialchars($fecha_documento_form, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>

    <div class="grupo-formulario">
        <label for="referencia">Referencia (Asunto):</label>
        <input type="text" id="referencia" name="referencia" value="<?php echo htmlspecialchars($referencia, ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
    </div>

    <div class="grupo-formulario">
        <label for="destinatarios">Destinatario(s):</label>
        <select id="destinatarios" name="destinatarios[]" multiple="multiple" size="5" class="select-multiple-dest">
            <?php foreach ($lista_usuarios_destinatarios as $usuario_dest): ?>
                <option value="<?php echo $usuario_dest['id_usuario']; ?>"
                        <?php echo in_array($usuario_dest['id_usuario'], $destinatarios_seleccionados) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($usuario_dest['apellidos'] . ', ' . $usuario_dest['nombres'] . ($usuario_dest['cargo'] ? ' (' . $usuario_dest['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Mantenga presionada la tecla Ctrl (o Cmd en Mac) para seleccionar múltiples destinatarios. Para Informes, este campo puede ser opcional.</small>
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
        <textarea id="contenido" name="contenido" rows="15" required class="editor-wysiwyg-basico"><?php echo htmlspecialchars($contenido, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Puede usar algunas etiquetas HTML básicas para formato: &lt;p&gt;, &lt;br&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;u&gt;, &lt;b&gt;, &lt;i&gt;, &lt;a href='...'&gt;, &lt;img src='...'&gt;. Para formato más complejo, considere un editor externo.</small>
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
/* Comentario: Estilos específicos para esta vista (si son necesarios). */
.select-multiple-dest {
    min-height: 120px; /* Comentario: Para que se vean más opciones. */
    border: 1px solid #ced4da;
    border-radius: var(--borde-radio);
    padding: 0.5rem;
    width: 100%; /* Comentario: Ocupar ancho del contenedor. */
}
/* Comentario: Si se usa un editor WYSIWYG real, sus estilos irían aquí o en su propio CSS. */
.editor-wysiwyg-basico {
    font-family: 'Courier New', Courier, monospace; /* Comentario: Ejemplo para diferenciar de un WYSIWYG. */
}
</style>

<?php
// Comentario: Fin del archivo vistas/correspondencia_redactar.php (SOLO PRESENTACIÓN)
?>
