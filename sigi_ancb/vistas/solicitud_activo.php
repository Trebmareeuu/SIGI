<?php
// Archivo: vistas/solicitud_activo.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: Formulario para que los usuarios soliciten muebles o equipos (activos) - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $tipo_activo_solicitado, $descripcion_activo, $justificacion_activo,
// Comentario: $tipos_activo_disponibles_form son definidas en logica/solicitud_activo_logica.php
?>
<h2>Solicitud de Activos (Muebles/Equipos)</h2>

<?php
mensaje_flash('error_sol_act');
mensaje_flash('error_sol_act_form');
mensaje_flash('exito_sol_act');
?>

<p>Por favor, complete el formulario para solicitar muebles, equipos u otros activos para su trabajo.</p>

<form action="index.php?vista=solicitud_activo" method="POST" class="validar-js">
    <div class="grupo-formulario">
        <label for="tipo_activo">Tipo de Activo Solicitado:</label>
        <select id="tipo_activo" name="tipo_activo" required>
            <option value="">-- Seleccione un tipo --</option>
            <?php foreach ($tipos_activo_disponibles_form as $clave_tipo => $desc_tipo): ?>
            <option value="<?php echo htmlspecialchars($clave_tipo, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($tipo_activo_solicitado === $clave_tipo) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($desc_tipo, ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="grupo-formulario">
        <label for="descripcion_activo">Descripción Detallada del Activo:</label>
        <textarea id="descripcion_activo" name="descripcion_activo" rows="5" required placeholder="Ej: Silla ergonómica con soporte lumbar, Laptop Core i7 con 16GB RAM, Licencia de Microsoft Office..."><?php echo htmlspecialchars($descripcion_activo, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Sea lo más específico posible (marca, modelo, características técnicas, etc.).</small>
    </div>

    <div class="grupo-formulario">
        <label for="justificacion_activo">Justificación de la Solicitud:</label>
        <textarea id="justificacion_activo" name="justificacion_activo" rows="4" required><?php echo htmlspecialchars($justificacion_activo, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Explique por qué necesita este activo y cómo contribuirá a sus funciones.</small>
    </div>

    <div class="grupo-formulario acciones-formulario">
        <button type="submit" name="enviar_solicitud_activo" class="boton boton-primario">Enviar Solicitud</button>
        <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
    </div>
</form>

<?php
// Comentario: Fin del archivo vistas/solicitud_activo.php (SOLO PRESENTACIÓN)
?>
