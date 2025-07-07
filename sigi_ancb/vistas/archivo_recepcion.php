<?php
// Archivo: vistas/archivo_recepcion.php
// Propósito: (Archivo) Vista para confirmar la recepción de expedientes que fueron archivados (ej. desde una unidad) o devueltos con observación.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('RECEPCIONAR_EXPEDIENTES_ARCHIVO', $id_usuario_actual)) {
    mensaje_flash('error_archivo_recepcion', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Expedientes que están en estados que requieren acción de recepción por Archivo.
// Comentario: Ejemplos de estados: 'para_archivar_fisico', 'devuelto_con_observacion'.
$expedientes_para_recepcionar = [];
try {
    $sql_epr = "SELECT ae.id_expediente, ae.codigo_expediente, ae.nombre_expediente, ae.estado_expediente, ae.ubicacion_fisica_archivo,
                       (SELECT CONCAT(u.apellidos, ', ', u.nombres) FROM usuarios u JOIN documentos_historial dh ON u.id_usuario = dh.id_usuario_accion WHERE dh.id_documento_asociado_exp = ae.id_expediente ORDER BY dh.fecha_accion DESC LIMIT 1) as ultimo_usuario_accion_doc, -- Comentario: Esto es un ejemplo, necesitaría un campo id_documento_asociado_exp en historial o una mejor forma de enlazar.
                       (SELECT MAX(dh.fecha_accion) FROM documentos_historial dh WHERE dh.id_documento_asociado_exp = ae.id_expediente) as fecha_ultima_accion_doc -- Comentario: Ejemplo.
                FROM archivo_expedientes ae
                WHERE ae.estado_expediente IN ('para_archivar_fisico', 'devuelto_con_observacion')
                ORDER BY ae.codigo_expediente ASC"; // Comentario: O por fecha de última acción.
    $stmt_epr = $pdo->query($sql_epr);
    $expedientes_para_recepcionar = $stmt_epr->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar expedientes para recepción en archivo: " . $e->getMessage());
    mensaje_flash('error_archivo_recepcion', 'Error al cargar la lista de expedientes pendientes de recepción.', 'alert-danger');
}

// Comentario: Procesar confirmación de recepción.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_recepcion_expediente'])) {
    $id_expediente_recepcionado = filter_input(INPUT_POST, 'id_expediente_a_recepcionar', FILTER_VALIDATE_INT);
    $nueva_ubicacion_fisica = sanitizar_entrada($_POST['nueva_ubicacion_fisica'] ?? '');
    $observaciones_recepcion_archivo = strip_tags($_POST['observaciones_recepcion_archivo'] ?? '');

    if ($id_expediente_recepcionado) {
        $pdo->beginTransaction();
        try {
            $exp_actual = $pdo->prepare("SELECT estado_expediente FROM archivo_expedientes WHERE id_expediente = ?");
            $exp_actual->execute([$id_expediente_recepcionado]);
            $estado_actual_exp_rec = $exp_actual->fetchColumn();

            if (!$estado_actual_exp_rec || !in_array($estado_actual_exp_rec, ['para_archivar_fisico', 'devuelto_con_observacion'])) {
                throw new Exception("El expediente no está en un estado válido para ser recepcionado en archivo.");
            }

            // Comentario: 1. Actualizar estado del expediente a 'en_archivo'.
            $sql_upd_exp_rec = "UPDATE archivo_expedientes
                                SET estado_expediente = 'en_archivo',
                                    ubicacion_fisica_archivo = :ubicacion,
                                    observaciones = CONCAT(IFNULL(observaciones,''), '\nRecepción Archivo (', NOW(), '): ', :obs_rec_arc)
                                WHERE id_expediente = :id_e_rec";
            $stmt_upd_exp_rec = $pdo->prepare($sql_upd_exp_rec);
            $stmt_upd_exp_rec->execute([
                ':ubicacion' => $nueva_ubicacion_fisica,
                ':obs_rec_arc' => $observaciones_recepcion_archivo,
                ':id_e_rec' => $id_expediente_recepcionado
            ]);

            // Comentario: 2. Registrar en historial del expediente o del documento asociado (si aplica).
            // Comentario: Esto es conceptual, la tabla documentos_historial necesitaría un campo para id_expediente o similar.
            /*
            registrar_historial_documento_o_expediente(
                $id_expediente_recepcionado, // o id_documento_asociado
                $id_usuario_actual,
                'Recepción en Archivo',
                "Expediente recepcionado en archivo. Ubicación: $nueva_ubicacion_fisica. Obs: $observaciones_recepcion_archivo"
            );
            */

            $pdo->commit();
            mensaje_flash('exito_archivo_recepcion', "Recepción del expediente ID $id_expediente_recepcionado confirmada exitosamente.", 'alert-success');
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error al confirmar recepción de expediente ID $id_expediente_recepcionado: " . $e->getMessage());
            mensaje_flash('error_form_recepcion_arc', 'Error al procesar la recepción: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=archivo_recepcion');
    } else {
        mensaje_flash('error_form_recepcion_arc', 'ID de expediente no válido para confirmar recepción.', 'alert-danger');
        redirigir('index.php?vista=archivo_recepcion');
    }
}


// Comentario: Lógica para registrar un NUEVO expediente físico en el sistema (si no existe previamente).
// Comentario: Esto es diferente de recepcionar uno que ya existe y fue "enviado" a archivo.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_nuevo_expediente_fisico'])) {
    $codigo_exp_nuevo = strtoupper(sanitizar_entrada($_POST['codigo_expediente_nuevo'] ?? ''));
    $nombre_exp_nuevo = sanitizar_entrada($_POST['nombre_expediente_nuevo'] ?? '');
    $tipo_exp_nuevo = sanitizar_entrada($_POST['tipo_expediente_nuevo'] ?? '');
    $fecha_creacion_exp_nuevo = sanitizar_entrada($_POST['fecha_creacion_expediente_nuevo'] ?? date('Y-m-d'));
    $ubicacion_fisica_exp_nuevo = sanitizar_entrada($_POST['ubicacion_fisica_exp_nuevo'] ?? '');
    $palabras_clave_exp_nuevo = strip_tags($_POST['palabras_clave_exp_nuevo'] ?? '');
    $observaciones_exp_nuevo = strip_tags($_POST['observaciones_exp_nuevo'] ?? '');

    $errores_form_nuevo_exp = [];
    if (empty($codigo_exp_nuevo)) $errores_form_nuevo_exp[] = "El código del nuevo expediente es obligatorio.";
    if (empty($nombre_exp_nuevo)) $errores_form_nuevo_exp[] = "El nombre/título del nuevo expediente es obligatorio.";
    if (empty($fecha_creacion_exp_nuevo) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_creacion_exp_nuevo)) {
        $errores_form_nuevo_exp[] = "La fecha de creación del expediente no es válida.";
    }

    // Comentario: Verificar unicidad de codigo_expediente.
    if (empty($errores_form_nuevo_exp)) {
        $stmt_chk_cod_exp = $pdo->prepare("SELECT id_expediente FROM archivo_expedientes WHERE codigo_expediente = :cod_e_chk_n");
        $stmt_chk_cod_exp->execute([':cod_e_chk_n' => $codigo_exp_nuevo]);
        if ($stmt_chk_cod_exp->fetch()) {
            $errores_form_nuevo_exp[] = "El código de expediente '$codigo_exp_nuevo' ya existe.";
        }
    }

    if (empty($errores_form_nuevo_exp)) {
        try {
            $sql_insert_nuevo_exp = "INSERT INTO archivo_expedientes
                                     (codigo_expediente, nombre_expediente, tipo_expediente, fecha_creacion_expediente, fecha_archivado_inicial, ubicacion_fisica_archivo, estado_expediente, palabras_clave, observaciones, id_usuario_registra)
                                     VALUES (:cod, :nom, :tipo, :fec_crea, NOW(), :ubic, 'en_archivo', :pal_clave, :obs, :id_user_reg)";
            $stmt_insert_nuevo_exp = $pdo->prepare($sql_insert_nuevo_exp);
            $stmt_insert_nuevo_exp->execute([
                ':cod' => $codigo_exp_nuevo, ':nom' => $nombre_exp_nuevo, ':tipo' => $tipo_exp_nuevo,
                ':fec_crea' => $fecha_creacion_exp_nuevo, ':ubic' => $ubicacion_fisica_exp_nuevo,
                ':pal_clave' => $palabras_clave_exp_nuevo, ':obs' => $observaciones_exp_nuevo,
                ':id_user_reg' => $id_usuario_actual
            ]);
            mensaje_flash('exito_archivo_recepcion', "Nuevo expediente '$codigo_exp_nuevo' registrado en archivo exitosamente.", 'alert-success');
        } catch (PDOException $e) {
            error_log("Error al registrar nuevo expediente físico: " . $e->getMessage());
            mensaje_flash('error_form_nuevo_exp_reg', 'Error al registrar el nuevo expediente: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=archivo_recepcion');
    } else {
        foreach ($errores_form_nuevo_exp as $err_ne) {
            mensaje_flash('error_form_nuevo_exp_reg', $err_ne, 'alert-danger');
        }
    }
}


?>
<h2>Recepción de Expedientes en Archivo Central</h2>

<?php
mensaje_flash('error_archivo_recepcion');
mensaje_flash('exito_archivo_recepcion');
mensaje_flash('error_form_recepcion_arc');
mensaje_flash('error_form_nuevo_exp_reg');
?>

<div class="card-sigi mb-3">
    <h3>Registrar Nuevo Expediente Físico Directamente en Archivo</h3>
    <form action="index.php?vista=archivo_recepcion" method="POST" class="validar-js form-nuevo-expediente">
        <div class="grid-col-3">
            <div class="grupo-formulario">
                <label for="codigo_expediente_nuevo">Código del Expediente:</label>
                <input type="text" id="codigo_expediente_nuevo" name="codigo_expediente_nuevo" required maxlength="100" placeholder="Ej: ANCB-ARCH-CONT-2023-001">
            </div>
            <div class="grupo-formulario">
                <label for="nombre_expediente_nuevo">Nombre/Título del Expediente:</label>
                <input type="text" id="nombre_expediente_nuevo" name="nombre_expediente_nuevo" required maxlength="255">
            </div>
            <div class="grupo-formulario">
                <label for="tipo_expediente_nuevo">Tipo de Expediente (Ej: Contratos, Personal, Resoluciones):</label>
                <input type="text" id="tipo_expediente_nuevo" name="tipo_expediente_nuevo" maxlength="100">
            </div>
        </div>
         <div class="grid-col-2">
            <div class="grupo-formulario">
                <label for="fecha_creacion_expediente_nuevo">Fecha de Creación/Inicio del Expediente:</label>
                <input type="date" id="fecha_creacion_expediente_nuevo" name="fecha_creacion_expediente_nuevo" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="grupo-formulario">
                <label for="ubicacion_fisica_exp_nuevo">Ubicación Física en Archivo:</label>
                <input type="text" id="ubicacion_fisica_exp_nuevo" name="ubicacion_fisica_exp_nuevo" maxlength="255" placeholder="Ej: Estante A, Caja 05, Archivador 3">
            </div>
        </div>
        <div class="grupo-formulario">
            <label for="palabras_clave_exp_nuevo">Palabras Clave (separadas por coma):</label>
            <input type="text" id="palabras_clave_exp_nuevo" name="palabras_clave_exp_nuevo">
        </div>
        <div class="grupo-formulario">
            <label for="observaciones_exp_nuevo">Observaciones Adicionales:</label>
            <textarea id="observaciones_exp_nuevo" name="observaciones_exp_nuevo" rows="2"></textarea>
        </div>
        <button type="submit" name="registrar_nuevo_expediente_fisico" class="boton boton-exito">Registrar Nuevo Expediente</button>
    </form>
</div>


<div class="card-sigi">
    <h3>Expedientes Pendientes de Recepción / Con Observación</h3>
    <?php if (empty($expedientes_para_recepcionar)): ?>
        <p>No hay expedientes actualmente en estado 'Para Archivar Físico' o 'Devuelto con Observación'.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>Cód. Expediente</th>
                        <th>Nombre/Título</th>
                        <th>Estado Actual</th>
                        <th>Última Ubicación Conocida</th>
                        <!-- <th>Última Acción por</th> Comentario: Info de ejemplo. -->
                        <th>Acciones de Recepción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($expedientes_para_recepcionar as $epr): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($epr['codigo_expediente'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                        <td><?php echo htmlspecialchars($epr['nombre_expediente'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="estado-expediente estado-exp-<?php echo $epr['estado_expediente']; ?>"><?php echo ucfirst(str_replace('_',' ',$epr['estado_expediente'])); ?></span></td>
                        <td><?php echo htmlspecialchars($epr['ubicacion_fisica_archivo'] ?? '<em>No especificada</em>', ENT_QUOTES, 'UTF-8'); ?></td>
                        <!-- <td><?php echo htmlspecialchars($epr['ultimo_usuario_accion_doc'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?> <br><small><?php echo $epr['fecha_ultima_accion_doc'] ? date('d/m/Y H:i', strtotime($epr['fecha_ultima_accion_doc'])) : ''; ?></small></td> -->
                        <td>
                            <form action="index.php?vista=archivo_recepcion" method="POST" class="form-accion-bandeja">
                                <input type="hidden" name="id_expediente_a_recepcionar" value="<?php echo $epr['id_expediente']; ?>">
                                <div class="grupo-formulario-sm">
                                    <label for="nuf_<?php echo $epr['id_expediente']; ?>">Nueva Ubicación Física en Archivo:</label>
                                    <input type="text" name="nueva_ubicacion_fisica" id="nuf_<?php echo $epr['id_expediente']; ?>" required placeholder="Ej: Estante B, Caja 10" value="<?php echo htmlspecialchars($epr['ubicacion_fisica_archivo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="grupo-formulario-sm">
                                    <label for="obs_rec_<?php echo $epr['id_expediente']; ?>">Observaciones de Recepción:</label>
                                    <textarea name="observaciones_recepcion_archivo" id="obs_rec_<?php echo $epr['id_expediente']; ?>" rows="1" placeholder="Ej: Completo, Faltan folios, etc."></textarea>
                                </div>
                                <button type="submit" name="confirmar_recepcion_expediente" class="boton-tabla exito btn-sm confirmar-accion" data-mensaje-confirmacion="¿Confirma la recepción de este expediente en archivo con los datos ingresados?">Confirmar Recepción</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>


<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.form-nuevo-expediente .grid-col-2, .form-nuevo-expediente .grid-col-3 { display: grid; gap: 1rem; margin-bottom: 1rem; }
.form-nuevo-expediente .grid-col-2 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
.form-nuevo-expediente .grid-col-3 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
.estado-expediente { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-exp-para_archivar_fisico { background-color: var(--color-info); color: #333; }
.estado-exp-devuelto_con_observacion { background-color: var(--color-advertencia); color: #333;}
.estado-exp-en_archivo { background-color: var(--color-exito); }
.form-accion-bandeja textarea, .form-accion-bandeja input[type="text"] { width: 98%; font-size: 0.85em; padding: 2px; margin-bottom:3px; }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875em; }
.text-warning { color: #ffc107; }
</style>

<?php
// Comentario: Fin del archivo vistas/archivo_recepcion.php
?>
