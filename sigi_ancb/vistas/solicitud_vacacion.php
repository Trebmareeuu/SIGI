<?php
// Archivo: vistas/solicitud_vacacion.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: Formulario para que los usuarios soliciten vacaciones - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $fecha_inicio, $fecha_fin, $dias_solicitados, $descripcion_solicitud,
// Comentario: $dias_disponibles_vacacion_display son definidas en logica/solicitud_vacacion_logica.php
?>
<h2>Solicitud de Vacación</h2>

<?php
mensaje_flash('error_sol_vac');
mensaje_flash('error_sol_vac_form');
mensaje_flash('exito_sol_vac');
?>

<p>Por favor, complete el siguiente formulario para solicitar sus vacaciones.</p>
<p>Días de vacación disponibles (referencial): <strong><?php echo $dias_disponibles_vacacion_display; ?></strong>.</p>
<!-- Comentario: Este conteo de días disponibles es solo un ejemplo, la lógica real sería más compleja. -->

<form action="index.php?vista=solicitud_vacacion" method="POST" class="validar-js">
    <div class="grupo-formulario">
        <label for="fecha_inicio">Fecha de Inicio de Vacaciones:</label>
        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio, ENT_QUOTES, 'UTF-8'); ?>" required
               min="<?php echo date('Y-m-d', strtotime('+2 day')); // Comentario: Mínimo 2 días en el futuro. ?>">
    </div>

    <div class="grupo-formulario">
        <label for="fecha_fin">Fecha de Fin de Vacaciones:</label>
        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>

    <div class="grupo-formulario">
        <label for="dias_solicitados">Número de Días Solicitados:</label>
        <input type="number" id="dias_solicitados" name="dias_solicitados" value="<?php echo htmlspecialchars($dias_solicitados, ENT_QUOTES, 'UTF-8'); ?>" required min="1" max="90">
        <small>Ingrese el número total de días calendario de vacación que desea tomar.</small>
    </div>

    <div class="grupo-formulario">
        <label for="descripcion_solicitud">Justificación / Motivo de la Solicitud:</label>
        <textarea id="descripcion_solicitud" name="descripcion_solicitud" rows="4" required><?php echo htmlspecialchars($descripcion_solicitud, ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <div class="grupo-formulario acciones-formulario">
        <button type="submit" name="enviar_solicitud_vacacion" class="boton boton-primario">Enviar Solicitud</button>
        <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
    </div>
</form>

<script>
// Comentario: Script para calcular días o validar fechas en cliente (opcional, ya que el backend valida).
document.addEventListener('DOMContentLoaded', function() {
    const fechaInicioInput = document.getElementById('fecha_inicio');
    const fechaFinInput = document.getElementById('fecha_fin');
    const diasSolicitadosInput = document.getElementById('dias_solicitados');

    function actualizarMinFechaFin() {
        if (fechaInicioInput.value) {
            fechaFinInput.min = fechaInicioInput.value;
             // Comentario: Si fecha_fin es menor que la nueva fecha_inicio, limpiarla o ajustarla.
            if (fechaFinInput.value && fechaFinInput.value < fechaInicioInput.value) {
                fechaFinInput.value = fechaInicioInput.value;
            }
        }
    }

    function calcularDiasSolicitados() {
        if (fechaInicioInput.value && fechaFinInput.value) {
            try {
                const inicio = new Date(fechaInicioInput.value + 'T00:00:00'); // Comentario: Asegurar que se tome el inicio del día.
                const fin = new Date(fechaFinInput.value + 'T00:00:00');

                if (fin >= inicio) {
                    const diffTiempo = fin.getTime() - inicio.getTime();
                    const diffDias = Math.ceil(diffTiempo / (1000 * 60 * 60 * 24)) + 1;
                    if (diasSolicitadosInput) { // Comentario: Actualizar el campo si se desea.
                       // diasSolicitadosInput.value = diffDias;
                    }
                } else {
                    // if (diasSolicitadosInput) diasSolicitadosInput.value = '';
                }
            } catch(e) {
                // console.error("Error parseando fechas: ", e);
                // if (diasSolicitadosInput) diasSolicitadosInput.value = '';
            }
        }
    }

    if (fechaInicioInput) {
        fechaInicioInput.addEventListener('change', function() {
            actualizarMinFechaFin();
            calcularDiasSolicitados();
        });
        // Comentario: Ejecutar al cargar por si hay valores POST.
        actualizarMinFechaFin();
    }
    if (fechaFinInput) {
        fechaFinInput.addEventListener('change', calcularDiasSolicitados);
    }

    // Comentario: Validación en cliente al enviar (adicional a la del servidor).
    const form = document.querySelector('form.validar-js[action="index.php?vista=solicitud_vacacion"]'); // Comentario: Ser más específico.
    if (form && fechaInicioInput && fechaFinInput && diasSolicitadosInput) {
        form.addEventListener('submit', function(event){
            let erroresCliente = [];
            if (!fechaInicioInput.value) erroresCliente.push("Fecha de inicio es requerida.");
            if (!fechaFinInput.value) erroresCliente.push("Fecha de fin es requerida.");
            if (!diasSolicitadosInput.value || parseInt(diasSolicitadosInput.value) < 1) erroresCliente.push("Días solicitados debe ser al menos 1.");

            if (fechaInicioInput.value && fechaFinInput.value) {
                try {
                    const inicio = new Date(fechaInicioInput.value);
                    const fin = new Date(fechaFinInput.value);
                    if (fin < inicio) {
                        erroresCliente.push("La fecha de fin no puede ser anterior a la fecha de inicio.");
                    }
                     // Comentario: Podría haber una validación más estricta entre días y rango de fechas si se desea.
                } catch (e) {
                    erroresCliente.push("Formato de fecha inválido.");
                }
            }

            if (erroresCliente.length > 0) {
                alert("Por favor corrija los siguientes errores:\n- " + erroresCliente.join("\n- "));
                event.preventDefault();
            }
        });
    }
});
</script>

<?php
// Comentario: Fin del archivo vistas/solicitud_vacacion.php (SOLO PRESENTACIÓN)
?>
