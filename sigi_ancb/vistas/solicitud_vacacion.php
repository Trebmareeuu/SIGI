<?php
// Archivo: vistas/solicitud_vacacion.php
// Propósito: Formulario para que los usuarios soliciten vacaciones.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('SOLICITAR_VACACION', $id_usuario_actual)) {
    mensaje_flash('error_sol_vac', 'No tiene permisos para solicitar vacaciones.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para el formulario.
$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$fecha_fin = $_POST['fecha_fin'] ?? '';
$dias_solicitados = $_POST['dias_solicitados'] ?? '';
$descripcion_solicitud = $_POST['descripcion_solicitud'] ?? ''; // Comentario: Justificación.

// Comentario: Podría haber lógica para calcular días disponibles de vacación del usuario.
// Comentario: $dias_disponibles_vacacion = calcular_dias_vacacion_disponibles($id_usuario_actual, $pdo);
$dias_disponibles_vacacion = 20; // Comentario: Ejemplo estático.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitud_vacacion'])) {
    $fecha_inicio = sanitizar_entrada($_POST['fecha_inicio'] ?? '');
    $fecha_fin = sanitizar_entrada($_POST['fecha_fin'] ?? '');
    $dias_solicitados_post = filter_var($_POST['dias_solicitados'] ?? '', FILTER_VALIDATE_INT);
    $descripcion_solicitud = strip_tags($_POST['descripcion_solicitud'] ?? '');

    $errores_formulario = [];
    if (empty($fecha_inicio) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_inicio)) {
        $errores_formulario[] = "La fecha de inicio no es válida.";
    }
    if (empty($fecha_fin) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_fin)) {
        $errores_formulario[] = "La fecha de fin no es válida.";
    }
    if ($dias_solicitados_post === false || $dias_solicitados_post <= 0) {
        $errores_formulario[] = "El número de días solicitados no es válido.";
    }
    if (empty($descripcion_solicitud)) {
        $errores_formulario[] = "La justificación de la solicitud es obligatoria.";
    }

    // Comentario: Validar coherencia de fechas y días.
    if (empty($errores_formulario)) {
        $obj_fecha_inicio = new DateTime($fecha_inicio);
        $obj_fecha_fin = new DateTime($fecha_fin);

        if ($obj_fecha_fin < $obj_fecha_inicio) {
            $errores_formulario[] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
        } else {
            // Comentario: Calcular diferencia de días (ejemplo simple, podría necesitar lógica de días hábiles).
            $intervalo = $obj_fecha_inicio->diff($obj_fecha_fin);
            $dias_calculados = $intervalo->days + 1; // Comentario: Incluye el día de inicio.

            if ($dias_calculados != $dias_solicitados_post) {
                // Comentario: Permitir una pequeña discrepancia o pedir al usuario que verifique.
                // $errores_formulario[] = "El número de días solicitados ($dias_solicitados_post) no coincide con el rango de fechas ($dias_calculados días).";
                // Comentario: Por ahora, confiamos en el número de días que el usuario pone, pero se podría recalcular.
                $dias_solicitados = $dias_solicitados_post; // Usar el valor del input.
            } else {
                 $dias_solicitados = $dias_calculados;
            }

            // Comentario: Validar contra días disponibles (si se implementa).
            // if ($dias_solicitados > $dias_disponibles_vacacion) {
            //    $errores_formulario[] = "No tiene suficientes días de vacación disponibles (disponibles: $dias_disponibles_vacacion).";
            // }
        }
    }


    if (empty($errores_formulario)) {
        try {
            $sql = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud, fecha_inicio_vacacion, fecha_fin_vacacion, dias_solicitados_vacacion, fecha_solicitud)
                    VALUES (:id_usuario, 'vacacion', :descripcion, 'pendiente_revision_secretaria', :fecha_inicio, :fecha_fin, :dias_solicitados, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $id_usuario_actual,
                ':descripcion' => $descripcion_solicitud,
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => $fecha_fin,
                ':dias_solicitados' => $dias_solicitados // Usar el valor validado o recalculado.
            ]);
            $id_solicitud_creada = $pdo->lastInsertId();

            // Comentario: Registrar en un historial de solicitudes si existe esa tabla, o en documentos_historial si se unifica.
            // registrar_historial_solicitud($id_solicitud_creada, $id_usuario_actual, 'Creación Solicitud Vacación', 'Solicitud enviada para revisión.');

            mensaje_flash('exito_sol_vac', 'Su solicitud de vacación ha sido enviada exitosamente y está pendiente de revisión.', 'alert-success');
            redirigir('index.php?vista=solicitudes_historial'); // Comentario: Redirigir al historial de solicitudes.

        } catch (PDOException $e) {
            error_log("Error al guardar solicitud de vacación: " . $e->getMessage());
            mensaje_flash('error_sol_vac', 'Ocurrió un error al procesar su solicitud. Intente más tarde.', 'alert-danger');
        }
    } else {
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_sol_vac_form', $error, 'alert-danger');
        }
    }
}

?>
<h2>Solicitud de Vacación</h2>

<?php
mensaje_flash('error_sol_vac');
mensaje_flash('error_sol_vac_form');
mensaje_flash('exito_sol_vac');
?>

<p>Por favor, complete el siguiente formulario para solicitar sus vacaciones.</p>
<!-- <p>Días de vacación disponibles: <strong><?php echo $dias_disponibles_vacacion; ?></strong>.</p> -->

<form action="index.php?vista=solicitud_vacacion" method="POST" class="validar-js">
    <div class="grupo-formulario">
        <label for="fecha_inicio">Fecha de Inicio de Vacaciones:</label>
        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio, ENT_QUOTES, 'UTF-8'); ?>" required
               min="<?php echo date('Y-m-d', strtotime('+1 day')); // Comentario: No permitir fechas pasadas o hoy. ?>">
    </div>

    <div class="grupo-formulario">
        <label for="fecha_fin">Fecha de Fin de Vacaciones:</label>
        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>

    <div class="grupo-formulario">
        <label for="dias_solicitados">Número de Días Solicitados:</label>
        <input type="number" id="dias_solicitados" name="dias_solicitados" value="<?php echo htmlspecialchars($dias_solicitados, ENT_QUOTES, 'UTF-8'); ?>" required min="1" max="30">
        <!-- Comentario: max podría ser $dias_disponibles_vacacion -->
        <small>Ingrese el número total de días de vacación que desea tomar (incluyendo fines de semana si aplican a su cómputo).</small>
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

    function actualizarDias() {
        if (fechaInicioInput.value && fechaFinInput.value) {
            const inicio = new Date(fechaInicioInput.value);
            const fin = new Date(fechaFinInput.value);

            if (fin >= inicio) {
                // Comentario: Cálculo simple de días naturales. Podría ser más complejo (días hábiles).
                const diffTiempo = Math.abs(fin - inicio);
                const diffDias = Math.ceil(diffTiempo / (1000 * 60 * 60 * 24)) + 1; // Comentario: +1 para incluir el día de inicio.

                // Comentario: Actualizar el campo de días si se desea, o solo usarlo para validación.
                // diasSolicitadosInput.value = diffDias;

                // Comentario: Poner la fecha de fin mínima a partir de la fecha de inicio.
                fechaFinInput.min = fechaInicioInput.value;

            } else {
                // Comentario: Fecha fin es anterior a fecha inicio.
                // diasSolicitadosInput.value = ''; // Limpiar o mostrar error.
            }
        }
    }

    if (fechaInicioInput) fechaInicioInput.addEventListener('change', actualizarDias);
    if (fechaFinInput) fechaFinInput.addEventListener('change', actualizarDias);

    // Comentario: Validar que fecha_fin no sea anterior a fecha_inicio al enviar.
    const form = document.querySelector('form.validar-js');
    if (form && fechaInicioInput && fechaFinInput) {
        form.addEventListener('submit', function(event){
            const inicio = new Date(fechaInicioInput.value);
            const fin = new Date(fechaFinInput.value);
            if (fin < inicio) {
                alert("La fecha de fin no puede ser anterior a la fecha de inicio.");
                event.preventDefault();
            }
            // Comentario: También validar que los días ingresados coincidan con el rango, si se desea ser estricto.
            // const diasIngresados = parseInt(diasSolicitadosInput.value, 10);
            // const diffTiempo = Math.abs(fin - inicio);
            // const diffDiasCalculados = Math.ceil(diffTiempo / (1000 * 60 * 60 * 24)) + 1;
            // if (diasIngresados !== diffDiasCalculados && inicio <= fin) {
            //     if(!confirm(`El rango de fechas es de ${diffDiasCalculados} días, pero ingresó ${diasIngresados} días. ¿Desea continuar con ${diasIngresados} días?`)){
            //         event.preventDefault();
            //     }
            // }
        });
    }
});
</script>

<?php
// Comentario: Fin del archivo vistas/solicitud_vacacion.php
?>
