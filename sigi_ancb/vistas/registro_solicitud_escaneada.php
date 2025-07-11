<?php
// Archivo: vistas/registro_solicitud_escaneada.php
// Propósito: (Enc. Activos Fijos / Dir. Admin) Formulario para registrar solicitudes de material/activo escaneadas.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('REGISTRAR_SOLICITUD_ESCANEO', $id_usuario_actual)) {
    mensaje_flash('error_reg_sol_esc', 'No tiene permisos para esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Cargar lista de todos los usuarios (empleados) para seleccionar al solicitante.
$lista_todos_los_usuarios_reg_sol = [];
try {
    $stmt_uall_rs = $pdo->query("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo, cargo FROM usuarios WHERE estado = 'activo' ORDER BY apellidos, nombres ASC");
    $lista_todos_los_usuarios_reg_sol = $stmt_uall_rs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar lista de todos los usuarios para registro solicitud escaneada: " . $e->getMessage());
    mensaje_flash('error_reg_sol_esc_form', 'Error al cargar la lista de empleados.', 'alert-warning');
}

// Comentario: Variables para el formulario.
$id_usuario_solicitante_reg_form = $_POST['id_usuario_solicitante_reg'] ?? '';
$tipo_solicitud_reg_form = $_POST['tipo_solicitud_reg'] ?? 'material_escritorio';
$referencia_solicitud_reg_form = $_POST['referencia_solicitud_reg'] ?? '';
$observaciones_solicitud_reg_form = $_POST['observaciones_solicitud_reg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_solicitud_escaneada_submit'])) {
    $id_usuario_solicitante_reg_form = filter_var($_POST['id_usuario_solicitante_reg'] ?? '', FILTER_VALIDATE_INT);
    $tipo_solicitud_reg_form = sanitizar_entrada($_POST['tipo_solicitud_reg'] ?? '');
    $referencia_solicitud_reg_form = sanitizar_entrada($_POST['referencia_solicitud_reg'] ?? '');
    $observaciones_solicitud_reg_form = strip_tags($_POST['observaciones_solicitud_reg'] ?? '');
    $ruta_adjunto_sol_esc_guardada = null;

    $errores_form_reg_sol_esc = [];
    if (empty($id_usuario_solicitante_reg_form)) $errores_form_reg_sol_esc[] = "Debe seleccionar el funcionario solicitante.";
    if (!in_array($tipo_solicitud_reg_form, ['material_escritorio', 'activo_mueble_equipo'])) $errores_form_reg_sol_esc[] = "Tipo de solicitud no válido.";
    if (empty($referencia_solicitud_reg_form)) $errores_form_reg_sol_esc[] = "La referencia/asunto de la solicitud es obligatoria.";
    if (!isset($_FILES['adjunto_solicitud_escaneada']) || $_FILES['adjunto_solicitud_escaneada']['error'] === UPLOAD_ERR_NO_FILE) {
        $errores_form_reg_sol_esc[] = "Debe adjuntar el formulario escaneado.";
    }

    // Comentario: Manejar subida de archivo.
    if (empty($errores_form_reg_sol_esc) && isset($_FILES['adjunto_solicitud_escaneada']) && $_FILES['adjunto_solicitud_escaneada']['error'] === UPLOAD_ERR_OK) {
        $subfolder = ($tipo_solicitud_reg_form === 'material_escritorio') ? 'solicitudes_material/' : 'solicitudes_activo/';
        $directorio_adjuntos_sol_esc = DIR_DOCUMENTOS . $subfolder;
        if (!is_dir($directorio_adjuntos_sol_esc)) mkdir($directorio_adjuntos_sol_esc, 0775, true);

        $nombre_orig_adj_se = $_FILES['adjunto_solicitud_escaneada']['name'];
        $nombre_temp_adj_se = $_FILES['adjunto_solicitud_escaneada']['tmp_name'];
        $tamano_adj_se = $_FILES['adjunto_solicitud_escaneada']['size'];
        $ext_adj_se = strtolower(pathinfo($nombre_orig_adj_se, PATHINFO_EXTENSION));

        if ($ext_adj_se !== 'pdf') $errores_form_reg_sol_esc[] = "El adjunto debe ser un archivo PDF.";
        if ($tamano_adj_se > 5 * 1024 * 1024) $errores_form_reg_sol_esc[] = "El adjunto excede el tamaño máximo de 5MB.";

        if (empty($errores_form_reg_sol_esc)) {
            $nombre_servidor_adj_se = 'sol_esc_' . $tipo_solicitud_reg_form . '_' . $id_usuario_solicitante_reg_form . '_' . time() . '.' . $ext_adj_se;
            $ruta_final_adj_se_serv = $directorio_adjuntos_sol_esc . $nombre_servidor_adj_se;
            if (move_uploaded_file($nombre_temp_adj_se, $ruta_final_adj_se_serv)) {
                $ruta_adjunto_sol_esc_guardada = $subfolder . $nombre_servidor_adj_se;
            } else {
                $errores_form_reg_sol_esc[] = "Error al guardar el archivo adjunto escaneado.";
            }
        }
    } elseif (isset($_FILES['adjunto_solicitud_escaneada']) && $_FILES['adjunto_solicitud_escaneada']['error'] !== UPLOAD_ERR_NO_FILE) {
         $errores_form_reg_sol_esc[] = "Error al subir el archivo adjunto (código: " . $_FILES['adjunto_solicitud_escaneada']['error'] . ").";
    }


    if (empty($errores_form_reg_sol_esc)) {
        try {
            // Comentario: La descripción de la solicitud será la referencia más las observaciones.
            $descripcion_db = "Ref: " . $referencia_solicitud_reg_form;
            if (!empty($observaciones_solicitud_reg_form)) {
                $descripcion_db .= "\nObservaciones del registro: " . $observaciones_solicitud_reg_form;
            }
            // Comentario: El detalle de ítems está en el PDF adjunto.

            $sql_reg_sol_esc = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud, ruta_adjunto_solicitud, fecha_solicitud, observaciones_gestion)
                                VALUES (:id_usr_sol, :tipo_sol, :desc_sol, 'pendiente_aprobacion_admin', :ruta_adj, NOW(), :obs_gest)";
                                // Comentario: En observaciones_gestion se podría poner "Registrado por [Usuario Actual] a partir de formulario físico."
            $stmt_reg_sol_esc = $pdo->prepare($sql_reg_sol_esc);
            $obs_gestion_inicial = "Solicitud registrada por " . ($_SESSION['nombre_usuario_completo'] ?? "Usuario ID ".$id_usuario_actual) . " a partir de formulario físico escaneado.";

            $stmt_reg_sol_esc->execute([
                ':id_usr_sol' => $id_usuario_solicitante_reg_form,
                ':tipo_sol' => $tipo_solicitud_reg_form,
                ':desc_sol' => $descripcion_db,
                ':ruta_adj' => $ruta_adjunto_sol_esc_guardada,
                ':obs_gest' => $obs_gestion_inicial
            ]);
            $id_solicitud_creada_esc = $pdo->lastInsertId();

            mensaje_flash('exito_reg_sol_esc', "Solicitud escaneada (ID: $id_solicitud_creada_esc) registrada y enviada para aprobación.", 'alert-success');
            redirigir('index.php?vista=solicitudes_aprobacion_admin'); // Comentario: O a una página de confirmación.

        } catch (PDOException $e) {
            error_log("Error al registrar solicitud escaneada: " . $e->getMessage());
            mensaje_flash('error_reg_sol_esc', 'Error al registrar la solicitud: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_form_reg_sol_esc as $err_rse) {
            mensaje_flash('error_reg_sol_esc_form', $err_rse, 'alert-danger');
        }
    }
}
?>

<h2>Registrar Solicitud Escaneada (Material/Activo)</h2>
<p>Este formulario permite registrar en el sistema una solicitud de material o activo que fue presentada físicamente (escaneada).</p>

<?php
mensaje_flash('error_reg_sol_esc');
mensaje_flash('error_reg_sol_esc_form');
mensaje_flash('exito_reg_sol_esc');
?>

<div class="card-sigi">
    <form action="index.php?vista=registro_solicitud_escaneada" method="POST" enctype="multipart/form-data" class="validar-js">
        <div class="grupo-formulario">
            <label for="id_usuario_solicitante_reg">Funcionario Solicitante:</label>
            <select id="id_usuario_solicitante_reg" name="id_usuario_solicitante_reg" required>
                <option value="">-- Seleccione el funcionario que presentó la solicitud --</option>
                <?php foreach ($lista_todos_los_usuarios_reg_sol as $usr_s): ?>
                    <option value="<?php echo $usr_s['id_usuario']; ?>" <?php if($id_usuario_solicitante_reg_form == $usr_s['id_usuario']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($usr_s['nombre_completo'] . ($usr_s['cargo'] ? ' (' . $usr_s['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grupo-formulario">
            <label for="tipo_solicitud_reg">Tipo de Solicitud Registrada:</label>
            <select id="tipo_solicitud_reg" name="tipo_solicitud_reg" required>
                <option value="material_escritorio" <?php if($tipo_solicitud_reg_form == 'material_escritorio') echo 'selected'; ?>>Material de Escritorio</option>
                <option value="activo_mueble_equipo" <?php if($tipo_solicitud_reg_form == 'activo_mueble_equipo') echo 'selected'; ?>>Activo (Mueble/Equipo)</option>
            </select>
        </div>

        <div class="grupo-formulario">
            <label for="referencia_solicitud_reg">Referencia / Asunto Principal de la Solicitud:</label>
            <input type="text" id="referencia_solicitud_reg" name="referencia_solicitud_reg" value="<?php echo htmlspecialchars($referencia_solicitud_reg_form, ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255" placeholder="Ej: Material de oficina para Depto. Contabilidad Q2">
        </div>

        <div class="grupo-formulario">
            <label for="adjunto_solicitud_escaneada">Adjuntar Formulario Escaneado (PDF, máx 5MB):</label>
            <input type="file" id="adjunto_solicitud_escaneada" name="adjunto_solicitud_escaneada" accept=".pdf" required>
            <small>Asegúrese de que el formulario esté completo y firmado.</small>
        </div>

        <div class="grupo-formulario">
            <label for="observaciones_solicitud_reg">Observaciones Adicionales del Registro (opcional):</label>
            <textarea id="observaciones_solicitud_reg" name="observaciones_solicitud_reg" rows="3"><?php echo htmlspecialchars($observaciones_solicitud_reg_form, ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="acciones-formulario mt-3">
            <button type="submit" name="registrar_solicitud_escaneada_submit" class="boton boton-primario">Registrar y Enviar a Aprobación</button>
            <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
        </div>
    </form>
</div>

<style>
.card-sigi { /* ... estilos ya definidos ... */ }
/* Comentario: Si se necesitan más estilos específicos para esta vista. */
</style>

<?php
// Comentario: Fin del archivo.
?>
