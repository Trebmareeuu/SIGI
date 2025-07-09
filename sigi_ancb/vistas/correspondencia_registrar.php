<?php
// Archivo: vistas/correspondencia_registrar.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: (Secretaria) Formulario para registrar correspondencia externa recibida - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $fecha_documento_externo, $cite_externo, $referencia_externa, $id_entidad_origen,
// Comentario: $nombre_remitente_especifico, $descripcion_contenido, $destinatarios_internos_seleccionados,
// Comentario: $prioridad_seleccionada, $observaciones_registro, $lista_entidades, $lista_usuarios_internos
// Comentario: son definidas en logica/correspondencia_registrar_logica.php
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
                    <option value="<?php echo $entidad['id_entidad']; ?>" <?php if(is_numeric($id_entidad_origen) && $id_entidad_origen == $entidad['id_entidad']) echo 'selected'; ?>>
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
            <select id="destinatarios_internos" name="destinatarios_internos[]" multiple="multiple" required size="5" class="select-multiple-dest">
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
            <!-- Comentario: Podría ser 'required' si siempre se debe adjuntar el escaneado. -->
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

<style>
/* Comentario: Estilos específicos para esta vista (si son necesarios). */
fieldset {
    border: 1px solid #ddd;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: var(--borde-radio);
}
fieldset legend {
    font-size: 1.1em;
    font-weight: bold;
    padding: 0 0.5em;
    width: auto; /* Comentario: Para que el borde no corte la leyenda. */
    color: var(--color-primario);
}
.select-multiple-dest {
    min-height: 120px;
    border: 1px solid #ced4da;
    border-radius: var(--borde-radio);
    padding: 0.5rem;
    width: 100%;
}
</style>

<?php
// Comentario: Fin del archivo vistas/correspondencia_registrar.php (SOLO PRESENTACIÓN)
?>
