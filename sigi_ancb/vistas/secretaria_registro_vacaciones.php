<?php
// Archivo: vistas/secretaria_registro_vacaciones.php
// Propósito: (Secretaría) Formulario para registrar vacaciones ya aprobadas (escaneadas).
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Secretaria.
if (!tiene_permiso('REGISTRAR_VACACION_APROBADA', $id_usuario_actual)) {
    mensaje_flash('error_reg_vac_apr', 'No tiene permisos para esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Cargar lista de todos los usuarios (empleados) para seleccionar a quién pertenece la vacación.
$lista_todos_los_usuarios = [];
try {
    $stmt_uall = $pdo->query("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo, cargo FROM usuarios WHERE estado = 'activo' ORDER BY apellidos, nombres ASC");
    $lista_todos_los_usuarios = $stmt_uall->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar lista de todos los usuarios para registro vacación: " . $e->getMessage());
    mensaje_flash('error_reg_vac_apr_form', 'Error al cargar la lista de empleados.', 'alert-warning');
}

// Comentario: Variables para el formulario.
$id_usuario_solicitante_form = $_POST['id_usuario_solicitante_vac'] ?? '';
$fecha_inicio_vac_form = $_POST['fecha_inicio_vac'] ?? '';
$fecha_fin_vac_form = $_POST['fecha_fin_vac'] ?? '';
$dias_tomados_vac_form = $_POST['dias_tomados_vac'] ?? '';
$descripcion_registro_vac_form = $_POST['descripcion_registro_vac'] ?? ''; // Comentario: ej. "Según carta Nro XXX aprobada por MAE".

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_vacacion_aprobada_sec'])) {
    $id_usuario_solicitante_form = filter_var($_POST['id_usuario_solicitante_vac'] ?? '', FILTER_VALIDATE_INT);
    $fecha_inicio_vac_form = sanitizar_entrada($_POST['fecha_inicio_vac'] ?? '');
    $fecha_fin_vac_form = sanitizar_entrada($_POST['fecha_fin_vac'] ?? '');
    $dias_tomados_vac_form = filter_var($_POST['dias_tomados_vac'] ?? '', FILTER_VALIDATE_INT);
    $descripcion_registro_vac_form = strip_tags($_POST['descripcion_registro_vac'] ?? 'Vacación aprobada y registrada por Secretaría.');
    $ruta_adjunto_vac_guardada = null;

    $errores_form_reg_vac = [];
    if (empty($id_usuario_solicitante_form)) $errores_form_reg_vac[] = "Debe seleccionar el funcionario.";
    if (empty($fecha_inicio_vac_form) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_inicio_vac_form)) $errores_form_reg_vac[] = "Fecha de inicio inválida.";
    if (empty($fecha_fin_vac_form) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_fin_vac_form)) $errores_form_reg_vac[] = "Fecha de fin inválida.";
    if ($dias_tomados_vac_form === false || $dias_tomados_vac_form <= 0) $errores_form_reg_vac[] = "Número de días tomados inválido.";
    if (!isset($_FILES['adjunto_vacacion_aprobada']) || $_FILES['adjunto_vacacion_aprobada']['error'] === UPLOAD_ERR_NO_FILE) {
        $errores_form_reg_vac[] = "Debe adjuntar el escaneo de la carta de vacación aprobada.";
    }

    if (empty($errores_form_reg_vac)) {
        try {
            $obj_f_ini = new DateTime($fecha_inicio_vac_form);
            $obj_f_fin = new DateTime($fecha_fin_vac_form);
            if ($obj_f_fin < $obj_f_ini) $errores_form_reg_vac[] = "La fecha de fin no puede ser anterior a la de inicio.";
            // Comentario: Se podría re-calcular días si se desea, o confiar en el dato ingresado.
        } catch (Exception $e) { $errores_form_reg_vac[] = "Error en el formato de fechas."; }
    }

    // Comentario: Manejar subida de archivo.
    if (empty($errores_form_reg_vac) && isset($_FILES['adjunto_vacacion_aprobada']) && $_FILES['adjunto_vacacion_aprobada']['error'] === UPLOAD_ERR_OK) {
        $dir_adj_vac_aprob = DIR_DOCUMENTOS . 'vacaciones_aprobadas/';
        if (!is_dir($dir_adj_vac_aprob)) mkdir($dir_adj_vac_aprob, 0775, true);

        $nombre_orig_adj_va = $_FILES['adjunto_vacacion_aprobada']['name'];
        $nombre_temp_adj_va = $_FILES['adjunto_vacacion_aprobada']['tmp_name'];
        $tamano_adj_va = $_FILES['adjunto_vacacion_aprobada']['size'];
        $ext_adj_va = strtolower(pathinfo($nombre_orig_adj_va, PATHINFO_EXTENSION));

        if ($ext_adj_va !== 'pdf') $errores_form_reg_vac[] = "El adjunto debe ser PDF.";
        if ($tamano_adj_va > 5 * 1024 * 1024) $errores_form_reg_vac[] = "El adjunto excede 5MB.";

        if (empty($errores_form_reg_vac)) {
            $nombre_servidor_adj_va = 'vac_aprob_' . $id_usuario_solicitante_form . '_' . time() . '.' . $ext_adj_va;
            $ruta_final_adj_va_serv = $dir_adj_vac_aprob . $nombre_servidor_adj_va;
            if (move_uploaded_file($nombre_temp_adj_va, $ruta_final_adj_va_serv)) {
                $ruta_adjunto_vac_guardada = 'vacaciones_aprobadas/' . $nombre_servidor_adj_va;
            } else {
                $errores_form_reg_vac[] = "Error al guardar el archivo adjunto de vacación.";
            }
        }
    }

    if (empty($errores_form_reg_vac)) {
        try {
            $sql_reg_vac = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud,
                                          fecha_inicio_vacacion, fecha_fin_vacacion, dias_solicitados_vacacion,
                                          ruta_adjunto_solicitud, id_usuario_aprobador, fecha_aprobacion_rechazo, fecha_solicitud)
                            VALUES (:id_usr_sol, 'vacacion', :desc_reg, 'aprobada',
                                    :f_ini, :f_fin, :dias_tom,
                                    :ruta_adj, :id_aprobador, NOW(), NOW())";
                                    // Comentario: Fecha solicitud = fecha registro por secretaría. id_aprobador podría ser MAE si se tiene su ID, o el de secretaría.
            $stmt_reg_vac = $pdo->prepare($sql_reg_vac);
            $stmt_reg_vac->execute([
                ':id_usr_sol' => $id_usuario_solicitante_form,
                ':desc_reg' => $descripcion_registro_vac_form,
                ':f_ini' => $fecha_inicio_vac_form,
                ':f_fin' => $fecha_fin_vac_form,
                ':dias_tom' => $dias_tomados_vac_form,
                ':ruta_adj' => $ruta_adjunto_vac_guardada,
                ':id_aprobador' => $id_usuario_actual // Comentario: Secretaría registra como si aprobara en el sistema. O se busca ID de MAE.
            ]);
            mensaje_flash('exito_reg_vac_apr', 'Vacación aprobada registrada exitosamente en el sistema.', 'alert-success');
            redirigir('index.php?vista=admin_reporte_vacaciones'); // Comentario: O a otra página de confirmación.
        } catch (PDOException $e) {
            error_log("Error al registrar vacación aprobada: " . $e->getMessage());
            mensaje_flash('error_reg_vac_apr', 'Error al registrar la vacación: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_form_reg_vac as $err_rv) {
            mensaje_flash('error_reg_vac_apr_form', $err_rv, 'alert-danger');
        }
    }
}
?>

<h2>Registrar Vacación Aprobada (Uso de Secretaría)</h2>
<p>Este formulario es para que Secretaría registre en el sistema las solicitudes de vacación que ya han sido aprobadas físicamente por la MAE.</p>

<?php
mensaje_flash('error_reg_vac_apr');
mensaje_flash('error_reg_vac_apr_form');
mensaje_flash('exito_reg_vac_apr');
?>

<div class="card-sigi">
    <form action="index.php?vista=secretaria_registro_vacaciones" method="POST" enctype="multipart/form-data" class="validar-js">
        <div class="grupo-formulario">
            <label for="id_usuario_solicitante_vac">Funcionario que solicitó la vacación:</label>
            <select id="id_usuario_solicitante_vac" name="id_usuario_solicitante_vac" required>
                <option value="">-- Seleccione un funcionario --</option>
                <?php foreach ($lista_todos_los_usuarios as $usr_v): ?>
                    <option value="<?php echo $usr_v['id_usuario']; ?>" <?php if($id_usuario_solicitante_form == $usr_v['id_usuario']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($usr_v['nombre_completo'] . ($usr_v['cargo'] ? ' (' . $usr_v['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid-col-3">
            <div class="grupo-formulario">
                <label for="fecha_inicio_vac">Fecha de Inicio (Según Carta Aprobada):</label>
                <input type="date" id="fecha_inicio_vac" name="fecha_inicio_vac" value="<?php echo htmlspecialchars($fecha_inicio_vac_form, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="grupo-formulario">
                <label for="fecha_fin_vac">Fecha de Fin (Según Carta Aprobada):</label>
                <input type="date" id="fecha_fin_vac" name="fecha_fin_vac" value="<?php echo htmlspecialchars($fecha_fin_vac_form, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="grupo-formulario">
                <label for="dias_tomados_vac">Total Días Calendario Tomados:</label>
                <input type="number" id="dias_tomados_vac" name="dias_tomados_vac" value="<?php echo htmlspecialchars($dias_tomados_vac_form, ENT_QUOTES, 'UTF-8'); ?>" required min="1" max="90">
                <small>Ingresar el número de días calendario exactos de la vacación.</small>
            </div>
        </div>
        <div class="grupo-formulario">
            <label for="descripcion_registro_vac">Descripción/Referencia del Registro:</label>
            <input type="text" id="descripcion_registro_vac" name="descripcion_registro_vac" value="<?php echo htmlspecialchars($descripcion_registro_vac_form, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej: Vacación aprobada según CITE XXX, Carta MAE..." required>
        </div>
        <div class="grupo-formulario">
            <label for="adjunto_vacacion_aprobada">Adjuntar Carta de Vacación Aprobada Escaneada (PDF, máx 5MB):</label>
            <input type="file" id="adjunto_vacacion_aprobada" name="adjunto_vacacion_aprobada" accept=".pdf" required>
        </div>
        <div class="acciones-formulario mt-3">
            <button type="submit" name="registrar_vacacion_aprobada_sec" class="boton boton-primario">Registrar Vacación Aprobada</button>
            <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
        </div>
    </form>
</div>

<style>
.card-sigi { /* ... estilos ya definidos ... */ }
.grid-col-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
</style>
<script>
// Comentario: JS para calcular días si es necesario, similar al de solicitud_vacacion.php
document.addEventListener('DOMContentLoaded', function() {
    const fechaInicioInput = document.getElementById('fecha_inicio_vac');
    const fechaFinInput = document.getElementById('fecha_fin_vac');
    const diasTomadosInput = document.getElementById('dias_tomados_vac');

    function actualizarDiasTomados() {
        if (fechaInicioInput.value && fechaFinInput.value && diasTomadosInput) {
            try {
                const [yearIni, monthIni, dayIni] = fechaInicioInput.value.split('-').map(Number);
                const inicio = new Date(yearIni, monthIni - 1, dayIni);
                const [yearFin, monthFin, dayFin] = fechaFinInput.value.split('-').map(Number);
                const fin = new Date(yearFin, monthFin - 1, dayFin);

                if (fin >= inicio) {
                    const diffTiempo = fin.getTime() - inicio.getTime();
                    const diffDias = Math.ceil(diffTiempo / (1000 * 60 * 60 * 24)) + 1;
                    //diasTomadosInput.value = diffDias; // Comentario: Permitir que Secretaría ingrese el valor de la carta.
                } else {
                    //diasTomadosInput.value = '';
                }
            } catch(e) { /*diasTomadosInput.value = '';*/ }
        }
    }
    if(fechaInicioInput) fechaInicioInput.addEventListener('change', actualizarDiasTomados);
    if(fechaFinInput) fechaFinInput.addEventListener('change', actualizarDiasTomados);
});
</script>
<?php
// Comentario: Fin del archivo.
?>
