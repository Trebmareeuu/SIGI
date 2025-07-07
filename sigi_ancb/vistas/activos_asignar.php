<?php
// Archivo: vistas/activos_asignar.php
// Propósito: (Activos Fijos) Formulario para codificar y asignar un nuevo activo, o editar uno existente.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('ASIGNAR_NUEVO_ACTIVO', $id_usuario_actual)) { // Comentario: Mismo permiso para crear/editar.
    mensaje_flash('error_activo_asignar_form', 'No tiene permisos para gestionar activos.', 'alert-danger');
    redirigir('index.php?vista=activos_inventario');
}

global $pdo;

// Comentario: Determinar si es creación o edición.
$modo_edicion = false;
$id_activo_editar = null;
$activo_para_editar = null;
$titulo_pagina_activo = "Registrar Nuevo Activo Fijo";

if (isset($_GET['accion_activo']) && $_GET['accion_activo'] === 'editar' && isset($_GET['id_activo'])) {
    $id_activo_editar = filter_var($_GET['id_activo'], FILTER_VALIDATE_INT);
    if ($id_activo_editar) {
        try {
            $stmt_edit_act = $pdo->prepare("SELECT * FROM activos_fijos WHERE id_activo = :id_act_ed");
            $stmt_edit_act->bindParam(':id_act_ed', $id_activo_editar, PDO::PARAM_INT);
            $stmt_edit_act->execute();
            $activo_para_editar = $stmt_edit_act->fetch(PDO::FETCH_ASSOC);
            if ($activo_para_editar) {
                $modo_edicion = true;
                $titulo_pagina_activo = "Editar Activo Fijo: " . htmlspecialchars($activo_para_editar['codigo_activo'], ENT_QUOTES, 'UTF-8');
            } else {
                mensaje_flash('error_activo_asignar_form', 'Activo no encontrado para editar.', 'alert-danger');
                redirigir('index.php?vista=activos_inventario');
            }
        } catch (PDOException $e) {
            error_log("Error al cargar activo para editar: " . $e->getMessage());
            mensaje_flash('error_activo_asignar_form', 'Error al cargar datos del activo para edición.', 'alert-danger');
            redirigir('index.php?vista=activos_inventario');
        }
    } else {
        mensaje_flash('error_activo_asignar_form', 'ID de activo no válido para editar.', 'alert-danger');
        redirigir('index.php?vista=activos_inventario');
    }
}


// Comentario: Cargar usuarios para el select de responsable.
$lista_usuarios_activos = [];
try {
    $stmt_ua = $pdo->query("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo, cargo FROM usuarios WHERE estado='activo' ORDER BY apellidos, nombres");
    $lista_usuarios_activos = $stmt_ua->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Comentario: Manejar error si es necesario. */ }

// Comentario: Tipos de activo y estados (podrían venir de BD también).
$tipos_activo_form = ['Equipo de Computación', 'Mobiliario', 'Vehículo', 'Maquinaria', 'Software', 'Herramientas', 'Otro'];
$estados_activo_form = ['nuevo', 'bueno', 'regular', 'malo', 'en_reparacion', 'dado_de_baja'];


// Comentario: Procesamiento del formulario (Crear o Actualizar).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['guardar_activo_nuevo']) || isset($_POST['actualizar_activo_existente']))) {
    $id_activo_hidden = filter_input(INPUT_POST, 'id_activo_hidden', FILTER_VALIDATE_INT);
    $codigo_activo_form = strtoupper(sanitizar_entrada($_POST['codigo_activo'] ?? ''));
    $nombre_activo_form = sanitizar_entrada($_POST['nombre_activo'] ?? '');
    $descripcion_detallada_form = strip_tags($_POST['descripcion_detallada'] ?? '');
    $tipo_activo_form = sanitizar_entrada($_POST['tipo_activo'] ?? '');
    $fecha_adquisicion_form = sanitizar_entrada($_POST['fecha_adquisicion'] ?? ''); // Comentario: Validar formato YYYY-MM-DD
    $valor_adquisicion_form = filter_var($_POST['valor_adquisicion'] ?? null, FILTER_VALIDATE_FLOAT);
    $estado_activo_form = sanitizar_entrada($_POST['estado_activo'] ?? 'bueno');
    $id_usuario_responsable_form = filter_var($_POST['id_usuario_responsable'] ?? null, FILTER_VALIDATE_INT);
    $ubicacion_actual_form = sanitizar_entrada($_POST['ubicacion_actual'] ?? '');
    $fecha_asignacion_form = sanitizar_entrada($_POST['fecha_asignacion'] ?? ''); // Comentario: Validar formato YYYY-MM-DD
    $observaciones_activo_form = strip_tags($_POST['observaciones_activo'] ?? '');

    $errores_form_activo = [];
    if (empty($codigo_activo_form)) $errores_form_activo[] = "El código del activo es obligatorio.";
    if (empty($nombre_activo_form)) $errores_form_activo[] = "El nombre/descripción principal del activo es obligatorio.";
    if (empty($tipo_activo_form)) $errores_form_activo[] = "Debe seleccionar un tipo de activo.";
    if (!empty($fecha_adquisicion_form) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_adquisicion_form)) $errores_form_activo[] = "Formato de fecha de adquisición no válido.";
    if ($valor_adquisicion_form === false && !is_null($_POST['valor_adquisicion']) && $_POST['valor_adquisicion'] !== '') $errores_form_activo[] = "El valor de adquisición no es un número válido.";
    if (!empty($fecha_asignacion_form) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_asignacion_form)) $errores_form_activo[] = "Formato de fecha de asignación no válido.";
    if (empty($id_usuario_responsable_form) && !empty($fecha_asignacion_form)) $errores_form_activo[] = "No puede haber fecha de asignación si no hay un responsable asignado.";
    if (!empty($id_usuario_responsable_form) && empty($fecha_asignacion_form) && !$modo_edicion) $errores_form_activo[] = "Si asigna un responsable, debe ingresar la fecha de asignación.";


    // Comentario: Verificar unicidad de codigo_activo (excepto para el mismo activo al editar).
    $id_excluir_activo_check = $id_activo_hidden ?: 0;
    try {
        $stmt_check_cod = $pdo->prepare("SELECT id_activo FROM activos_fijos WHERE codigo_activo = :cod_act AND id_activo != :id_excluir_act");
        $stmt_check_cod->execute([':cod_act' => $codigo_activo_form, ':id_excluir_act' => $id_excluir_activo_check]);
        if ($stmt_check_cod->fetch()) $errores_form_activo[] = "El código de activo '$codigo_activo_form' ya está en uso.";
    } catch (PDOException $e) {
        $errores_form_activo[] = "Error al verificar unicidad del código de activo: " . $e->getMessage();
    }

    if (empty($errores_form_activo)) {
        $params_sql_activo = [
            ':cod' => $codigo_activo_form, ':nom' => $nombre_activo_form, ':desc_det' => $descripcion_detallada_form,
            ':tipo' => $tipo_activo_form, ':fec_adq' => empty($fecha_adquisicion_form) ? null : $fecha_adquisicion_form,
            ':val_adq' => ($valor_adquisicion_form === false || is_null($valor_adquisicion_form)) ? null : $valor_adquisicion_form,
            ':estado' => $estado_activo_form,
            ':id_resp' => empty($id_usuario_responsable_form) ? null : $id_usuario_responsable_form,
            ':ubicacion' => $ubicacion_actual_form,
            ':fec_asig' => (empty($id_usuario_responsable_form) || empty($fecha_asignacion_form)) ? null : $fecha_asignacion_form, // Comentario: Solo guardar fecha_asig si hay responsable.
            ':obs' => $observaciones_activo_form
        ];

        try {
            if (isset($_POST['guardar_activo_nuevo'])) {
                $sql_insert_activo = "INSERT INTO activos_fijos (codigo_activo, nombre_activo, descripcion_detallada, tipo_activo, fecha_adquisicion, valor_adquisicion, estado_activo, id_usuario_responsable, ubicacion_actual, fecha_asignacion, observaciones)
                                      VALUES (:cod, :nom, :desc_det, :tipo, :fec_adq, :val_adq, :estado, :id_resp, :ubicacion, :fec_asig, :obs)";
                $stmt_op_activo = $pdo->prepare($sql_insert_activo);
                $stmt_op_activo->execute($params_sql_activo);
                mensaje_flash('exito_activo_asignar', 'Activo fijo registrado y/o asignado exitosamente.', 'alert-success');
            } elseif (isset($_POST['actualizar_activo_existente']) && $id_activo_hidden) {
                $params_sql_activo[':id_activo_upd'] = $id_activo_hidden;
                $sql_update_activo = "UPDATE activos_fijos SET
                                        codigo_activo = :cod, nombre_activo = :nom, descripcion_detallada = :desc_det, tipo_activo = :tipo,
                                        fecha_adquisicion = :fec_adq, valor_adquisicion = :val_adq, estado_activo = :estado,
                                        id_usuario_responsable = :id_resp, ubicacion_actual = :ubicacion, fecha_asignacion = :fec_asig,
                                        observaciones = :obs
                                      WHERE id_activo = :id_activo_upd";
                $stmt_op_activo = $pdo->prepare($sql_update_activo);
                $stmt_op_activo->execute($params_sql_activo);
                mensaje_flash('exito_activo_asignar', 'Activo fijo actualizado exitosamente.', 'alert-success');
            }
             redirigir('index.php?vista=activos_inventario');
        } catch (PDOException $e) {
            error_log("Error al guardar/actualizar activo: " . $e->getMessage());
            mensaje_flash('error_form_activo_submit', 'Error al procesar la solicitud del activo: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_form_activo as $err_a) {
            mensaje_flash('error_form_activo_validation', $err_a, 'alert-danger');
        }
        if ($modo_edicion) {
            // Comentario: Mantener datos para el formulario de edición.
            // Comentario: $activo_para_editar ya está cargado. Los errores se mostrarán.
        }
    }
}


?>
<h2><?php echo $titulo_pagina_activo; ?></h2>

<?php
mensaje_flash('error_activo_asignar_form');
mensaje_flash('error_form_activo_submit');
mensaje_flash('error_form_activo_validation');
?>

<div class="card-sigi">
    <form action="index.php?vista=activos_asignar<?php echo $modo_edicion ? '&accion_activo=editar&id_activo='.$id_activo_editar : ''; ?>" method="POST" class="validar-js form-activo">
        <?php if ($modo_edicion): ?>
            <input type="hidden" name="id_activo_hidden" value="<?php echo $activo_para_editar['id_activo']; ?>">
        <?php endif; ?>

        <fieldset>
            <legend>Información General del Activo</legend>
            <div class="grid-col-2">
                <div class="grupo-formulario">
                    <label for="codigo_activo">Código del Activo:</label>
                    <input type="text" id="codigo_activo" name="codigo_activo" value="<?php echo htmlspecialchars($activo_para_editar['codigo_activo'] ?? ($_POST['codigo_activo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50" placeholder="Ej: ANCB-AF-EQC-001">
                </div>
                <div class="grupo-formulario">
                    <label for="nombre_activo">Nombre / Descripción Principal:</label>
                    <input type="text" id="nombre_activo" name="nombre_activo" value="<?php echo htmlspecialchars($activo_para_editar['nombre_activo'] ?? ($_POST['nombre_activo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="200">
                </div>
            </div>
            <div class="grupo-formulario">
                <label for="descripcion_detallada">Descripción Detallada (Características, Modelo, Serie, etc.):</label>
                <textarea id="descripcion_detallada" name="descripcion_detallada" rows="3"><?php echo htmlspecialchars($activo_para_editar['descripcion_detallada'] ?? ($_POST['descripcion_detallada'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
             <div class="grid-col-3">
                <div class="grupo-formulario">
                    <label for="tipo_activo">Tipo de Activo:</label>
                    <select id="tipo_activo" name="tipo_activo" required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach($tipos_activo_form as $tipo_af): ?>
                            <option value="<?php echo htmlspecialchars($tipo_af, ENT_QUOTES, 'UTF-8'); ?>" <?php if(($activo_para_editar['tipo_activo'] ?? ($_POST['tipo_activo'] ?? '')) == $tipo_af) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($tipo_af, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grupo-formulario">
                    <label for="fecha_adquisicion">Fecha de Adquisición:</label>
                    <input type="date" id="fecha_adquisicion" name="fecha_adquisicion" value="<?php echo htmlspecialchars($activo_para_editar['fecha_adquisicion'] ?? ($_POST['fecha_adquisicion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="grupo-formulario">
                    <label for="valor_adquisicion">Valor de Adquisición (Bs.):</label>
                    <input type="number" id="valor_adquisicion" name="valor_adquisicion" step="0.01" value="<?php echo htmlspecialchars($activo_para_editar['valor_adquisicion'] ?? ($_POST['valor_adquisicion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Estado y Asignación del Activo</legend>
            <div class="grid-col-3">
                <div class="grupo-formulario">
                    <label for="estado_activo">Estado del Activo:</label>
                    <select id="estado_activo" name="estado_activo" required>
                         <?php foreach($estados_activo_form as $estado_af): ?>
                            <option value="<?php echo $estado_af; ?>" <?php if(($activo_para_editar['estado_activo'] ?? ($_POST['estado_activo'] ?? 'bueno')) == $estado_af) echo 'selected'; ?>>
                                <?php echo ucfirst(str_replace('_',' ',$estado_af)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grupo-formulario">
                    <label for="id_usuario_responsable">Responsable Asignado:</label>
                    <select id="id_usuario_responsable" name="id_usuario_responsable">
                        <option value="">-- Sin Asignar --</option>
                         <?php foreach($lista_usuarios_activos as $usr_a): ?>
                            <option value="<?php echo $usr_a['id_usuario']; ?>" <?php if(($activo_para_editar['id_usuario_responsable'] ?? ($_POST['id_usuario_responsable'] ?? '')) == $usr_a['id_usuario']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($usr_a['nombre_completo'] . ($usr_a['cargo'] ? ' (' . $usr_a['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="grupo-formulario">
                    <label for="fecha_asignacion">Fecha de Asignación (si aplica):</label>
                    <input type="date" id="fecha_asignacion" name="fecha_asignacion" value="<?php echo htmlspecialchars($activo_para_editar['fecha_asignacion'] ?? ($_POST['fecha_asignacion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
             <div class="grupo-formulario">
                <label for="ubicacion_actual">Ubicación Actual del Activo:</label>
                <input type="text" id="ubicacion_actual" name="ubicacion_actual" value="<?php echo htmlspecialchars($activo_para_editar['ubicacion_actual'] ?? ($_POST['ubicacion_actual'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" placeholder="Ej: Oficina Contabilidad, Almacén Central">
            </div>
            <div class="grupo-formulario">
                <label for="observaciones_activo">Observaciones Adicionales:</label>
                <textarea id="observaciones_activo" name="observaciones_activo" rows="3"><?php echo htmlspecialchars($activo_para_editar['observaciones'] ?? ($_POST['observaciones_activo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </fieldset>

        <div class="acciones-formulario mt-3">
            <?php if ($modo_edicion): ?>
                <button type="submit" name="actualizar_activo_existente" class="boton boton-primario">Actualizar Activo</button>
            <?php else: ?>
                <button type="submit" name="guardar_activo_nuevo" class="boton boton-primario">Guardar Nuevo Activo</button>
            <?php endif; ?>
            <a href="index.php?vista=activos_inventario" class="boton boton-secundario">Cancelar / Volver al Inventario</a>
        </div>
    </form>
</div>


<style>
/* Comentario: Estilos heredados de personal_ficha_crud y otros. */
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); margin-bottom: 1.5rem; }
.card-sigi h2, .form-activo fieldset legend { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.form-activo fieldset { border: 1px solid #ddd; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--borde-radio); }
.form-activo fieldset legend { font-size: 1.1em; font-weight: bold; padding: 0 0.5em; width: auto; }
.grid-col-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; }
.grid-col-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
</style>

<?php
// Comentario: Fin del archivo vistas/activos_asignar.php
?>
