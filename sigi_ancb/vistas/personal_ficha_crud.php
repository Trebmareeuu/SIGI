<?php
// Archivo: vistas/personal_ficha_crud.php
// Propósito: (Presupuesto) CRUD para gestionar la ficha del personal, incluyendo días de vacación.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CRUD_PERSONAL_FICHA', $id_usuario_actual)) {
    mensaje_flash('error_ficha_crud', 'No tiene permisos para gestionar fichas de personal.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

$accion_ficha_crud = $_GET['accion_ficha_crud'] ?? 'listar';
$id_ficha_editar = null;
$ficha_para_editar = null;
$id_usuario_asociado_ficha = null;

$usuarios_sin_ficha = [];
try {
    $stmt_usf = $pdo->query("SELECT u.id_usuario, CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo, u.cargo
                             FROM usuarios u
                             LEFT JOIN personal_fichas pf ON u.id_usuario = pf.id_usuario
                             WHERE pf.id_ficha_personal IS NULL AND u.estado = 'activo'
                             ORDER BY u.apellidos, u.nombres ASC");
    $usuarios_sin_ficha = $stmt_usf->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar usuarios sin ficha: " . $e->getMessage());
}

if ($accion_ficha_crud === 'editar' && isset($_GET['id_ficha'])) {
    $id_ficha_editar = filter_var($_GET['id_ficha'], FILTER_VALIDATE_INT);
    if ($id_ficha_editar) {
        try {
            $stmt_edit_ficha = $pdo->prepare("SELECT pf.*, u.nombre_usuario, u.nombres as nombres_usr, u.apellidos as apellidos_usr
                                             FROM personal_fichas pf
                                             JOIN usuarios u ON pf.id_usuario = u.id_usuario
                                             WHERE pf.id_ficha_personal = :id_f_ed");
            $stmt_edit_ficha->bindParam(':id_f_ed', $id_ficha_editar, PDO::PARAM_INT);
            $stmt_edit_ficha->execute();
            $ficha_para_editar = $stmt_edit_ficha->fetch(PDO::FETCH_ASSOC);
            if ($ficha_para_editar) {
                $id_usuario_asociado_ficha = $ficha_para_editar['id_usuario'];
            } else {
                mensaje_flash('error_ficha_crud', 'Ficha de personal no encontrada para editar.', 'alert-danger');
                redirigir('index.php?vista=personal_ficha_crud');
            }
        } catch (PDOException $e) {
            error_log("Error al cargar ficha para editar: " . $e->getMessage());
            mensaje_flash('error_ficha_crud', 'Error al cargar datos de la ficha para edición.', 'alert-danger');
            redirigir('index.php?vista=personal_ficha_crud');
        }
    } else {
        mensaje_flash('error_ficha_crud', 'ID de ficha no válido para editar.', 'alert-danger');
        redirigir('index.php?vista=personal_ficha_crud');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['guardar_ficha_nueva']) || isset($_POST['actualizar_ficha_existente']))) {
    $id_ficha_form = filter_input(INPUT_POST, 'id_ficha_hidden', FILTER_VALIDATE_INT);
    $id_usuario_form = filter_var($_POST['id_usuario_ficha'] ?? '', FILTER_VALIDATE_INT);
    $codigo_empleado_form = sanitizar_entrada($_POST['codigo_empleado'] ?? '');
    $fecha_nacimiento_form = sanitizar_entrada($_POST['fecha_nacimiento'] ?? '');
    $ci_numero_form = sanitizar_entrada($_POST['ci_numero'] ?? '');
    $lugar_nacimiento_form = sanitizar_entrada($_POST['lugar_nacimiento'] ?? '');
    $nacionalidad_form = sanitizar_entrada($_POST['nacionalidad'] ?? 'Boliviana');
    $ci_expedido_en_form = sanitizar_entrada($_POST['ci_expedido_en'] ?? '');
    $estado_civil_form = sanitizar_entrada($_POST['estado_civil'] ?? '');
    $domicilio_actual_form = strip_tags($_POST['domicilio_actual'] ?? '');
    $telefono_emergencia_form = sanitizar_entrada($_POST['telefono_emergencia'] ?? '');
    $contacto_emergencia_nombre_form = sanitizar_entrada($_POST['contacto_emergencia_nombre'] ?? '');
    $relacion_contacto_emergencia_form = sanitizar_entrada($_POST['relacion_contacto_emergencia'] ?? '');
    $nivel_educativo_form = sanitizar_entrada($_POST['nivel_educativo'] ?? '');
    $profesion_form = sanitizar_entrada($_POST['profesion'] ?? '');
    $fecha_ingreso_institucion_form = sanitizar_entrada($_POST['fecha_ingreso_institucion'] ?? '');
    $tipo_contrato_form = sanitizar_entrada($_POST['tipo_contrato'] ?? '');
    $salario_base_form_raw = $_POST['salario_base'] ?? null;
    $salario_base_form = ($salario_base_form_raw === '' || is_null($salario_base_form_raw)) ? null : filter_var($salario_base_form_raw, FILTER_VALIDATE_FLOAT);
    // Nuevo campo para días de vacación
    $dias_vacacion_asignados_form_raw = $_POST['dias_vacacion_anuales_asignados'] ?? null;
    $dias_vacacion_asignados_form = ($dias_vacacion_asignados_form_raw === '' || is_null($dias_vacacion_asignados_form_raw)) ? 20 : filter_var($dias_vacacion_asignados_form_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);


    $afp_asociada_form = sanitizar_entrada($_POST['afp_asociada'] ?? '');
    $nua_cua_form = sanitizar_entrada($_POST['nua_cua'] ?? '');
    $grupo_sanguineo_form = sanitizar_entrada($_POST['grupo_sanguineo'] ?? '');
    $alergias_conocidas_form = strip_tags($_POST['alergias_conocidas'] ?? '');
    $observaciones_medicas_form = strip_tags($_POST['observaciones_medicas'] ?? '');

    $errores_form_ficha = [];
    if (empty($id_usuario_form) && $accion_ficha_crud === 'crear') $errores_form_ficha[] = "Debe seleccionar un usuario para asociar la ficha.";
    if (empty($codigo_empleado_form)) $errores_form_ficha[] = "El código de empleado es obligatorio.";
    if (empty($ci_numero_form)) $errores_form_ficha[] = "El número de Cédula de Identidad es obligatorio.";
    if (!empty($fecha_nacimiento_form) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_nacimiento_form)) $errores_form_ficha[] = "Formato de fecha de nacimiento no válido.";
    if (!empty($fecha_ingreso_institucion_form) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_ingreso_institucion_form)) $errores_form_ficha[] = "Formato de fecha de ingreso no válido.";
    if ($salario_base_form === false && !is_null($salario_base_form_raw) && $salario_base_form_raw !== '') $errores_form_ficha[] = "El salario base no es un número válido.";
    if ($dias_vacacion_asignados_form === false) $errores_form_ficha[] = "Los días de vacación asignados deben ser un número entero no negativo.";


    $id_excluir_ficha_check = $id_ficha_form ?: 0;
    try {
        if ($accion_ficha_crud === 'crear' && $id_usuario_form) {
             $stmt_check_user_ficha = $pdo->prepare("SELECT id_ficha_personal FROM personal_fichas WHERE id_usuario = :id_usr_chk");
             $stmt_check_user_ficha->execute([':id_usr_chk' => $id_usuario_form]);
             if ($stmt_check_user_ficha->fetch()) $errores_form_ficha[] = "El usuario seleccionado ya tiene una ficha de personal asignada.";
        }
        $checks_unicidad = [
            'codigo_empleado' => [$codigo_empleado_form, "El código de empleado '$codigo_empleado_form' ya está en uso."],
            'ci_numero' => [$ci_numero_form, "El CI '$ci_numero_form' ya está registrado."],
        ];
        if(!empty($nua_cua_form)){
            $checks_unicidad['nua_cua'] = [$nua_cua_form, "El NUA/CUA '$nua_cua_form' ya está registrado."];
        }
        foreach ($checks_unicidad as $campo_check => $data_check) {
            list($valor_check, $msj_error_check) = $data_check;
            if (!empty($valor_check)) {
                $stmt_check_ficha = $pdo->prepare("SELECT id_ficha_personal FROM personal_fichas WHERE $campo_check = :val AND id_ficha_personal != :id_excluir_f");
                $stmt_check_ficha->execute([':val' => $valor_check, ':id_excluir_f' => $id_excluir_ficha_check]);
                if ($stmt_check_ficha->fetch()) $errores_form_ficha[] = $msj_error_check;
            }
        }
    } catch (PDOException $e) {
        $errores_form_ficha[] = "Error al verificar unicidad de datos de ficha: " . $e->getMessage();
    }

    if (empty($errores_form_ficha)) {
        $params_sql_ficha = [
            ':cod_emp' => $codigo_empleado_form,
            ':fec_nac' => empty($fecha_nacimiento_form) ? null : $fecha_nacimiento_form,
            ':lugar_nac' => $lugar_nacimiento_form, ':nac' => $nacionalidad_form,
            ':ci_num' => $ci_numero_form, ':ci_exp' => $ci_expedido_en_form,
            ':est_civil' => empty($estado_civil_form) ? null : $estado_civil_form,
            ':domicilio' => $domicilio_actual_form, ':tel_emerg' => $telefono_emergencia_form,
            ':contacto_emerg_nom' => $contacto_emergencia_nombre_form, ':rel_contacto_emerg' => $relacion_contacto_emergencia_form,
            ':nivel_edu' => $nivel_educativo_form, ':profesion' => $profesion_form,
            ':fec_ingreso' => empty($fecha_ingreso_institucion_form) ? null : $fecha_ingreso_institucion_form,
            ':tipo_cont' => $tipo_contrato_form,
            ':salario' => $salario_base_form,
            ':dias_vac' => $dias_vacacion_asignados_form, // Nuevo campo
            ':afp' => $afp_asociada_form, ':nua' => $nua_cua_form, ':grupo_sang' => $grupo_sanguineo_form,
            ':alergias' => $alergias_conocidas_form, ':obs_med' => $observaciones_medicas_form
        ];

        try {
            if (isset($_POST['guardar_ficha_nueva'])) {
                $params_sql_ficha[':id_usr'] = $id_usuario_form;
                $sql_insert_ficha = "INSERT INTO personal_fichas (id_usuario, codigo_empleado, fecha_nacimiento, lugar_nacimiento, nacionalidad, ci_numero, ci_expedido_en, estado_civil, domicilio_actual, telefono_emergencia, contacto_emergencia_nombre, relacion_contacto_emergencia, nivel_educativo, profesion, fecha_ingreso_institucion, tipo_contrato, salario_base, dias_vacacion_anuales_asignados, afp_asociada, nua_cua, grupo_sanguineo, alergias_conocidas, observaciones_medicas)
                                     VALUES (:id_usr, :cod_emp, :fec_nac, :lugar_nac, :nac, :ci_num, :ci_exp, :est_civil, :domicilio, :tel_emerg, :contacto_emerg_nom, :rel_contacto_emerg, :nivel_edu, :profesion, :fec_ingreso, :tipo_cont, :salario, :dias_vac, :afp, :nua, :grupo_sang, :alergias, :obs_med)";
                $stmt_op_ficha = $pdo->prepare($sql_insert_ficha);
                $stmt_op_ficha->execute($params_sql_ficha);
                mensaje_flash('exito_ficha_crud', 'Ficha de personal creada exitosamente.', 'alert-success');
            } elseif (isset($_POST['actualizar_ficha_existente']) && $id_ficha_form) {
                $params_sql_ficha[':id_ficha_upd'] = $id_ficha_form;
                $sql_update_ficha = "UPDATE personal_fichas SET
                                        codigo_empleado = :cod_emp, fecha_nacimiento = :fec_nac, lugar_nacimiento = :lugar_nac, nacionalidad = :nac,
                                        ci_numero = :ci_num, ci_expedido_en = :ci_exp, estado_civil = :est_civil, domicilio_actual = :domicilio,
                                        telefono_emergencia = :tel_emerg, contacto_emergencia_nombre = :contacto_emerg_nom, relacion_contacto_emergencia = :rel_contacto_emerg,
                                        nivel_educativo = :nivel_edu, profesion = :profesion, fecha_ingreso_institucion = :fec_ingreso,
                                        tipo_contrato = :tipo_cont, salario_base = :salario, dias_vacacion_anuales_asignados = :dias_vac,
                                        afp_asociada = :afp, nua_cua = :nua,
                                        grupo_sanguineo = :grupo_sang, alergias_conocidas = :alergias, observaciones_medicas = :obs_med
                                     WHERE id_ficha_personal = :id_ficha_upd";
                $stmt_op_ficha = $pdo->prepare($sql_update_ficha);
                $stmt_op_ficha->execute($params_sql_ficha);
                mensaje_flash('exito_ficha_crud', 'Ficha de personal actualizada exitosamente.', 'alert-success');
            }
            redirigir('index.php?vista=personal_ficha_crud');
        } catch (PDOException $e) {
            error_log("Error al guardar/actualizar ficha: " . $e->getMessage());
            mensaje_flash('error_form_ficha_submit', 'Error al procesar la solicitud de la ficha: ' . $e->getMessage(), 'alert-danger');
            if (isset($_POST['actualizar_ficha_existente'])) $accion_ficha_crud = 'editar'; else $accion_ficha_crud = 'crear';
        }
    } else {
        foreach ($errores_form_ficha as $err_f) {
            mensaje_flash('error_form_ficha_validation', $err_f, 'alert-danger');
        }
        if (isset($_POST['actualizar_ficha_existente'])) {
             $accion_ficha_crud = 'editar';
             if($id_ficha_form && !$ficha_para_editar){
                try {
                    $stmt_reload_edit = $pdo->prepare("SELECT pf.*, u.nombre_usuario, u.nombres as nombres_usr, u.apellidos as apellidos_usr FROM personal_fichas pf JOIN usuarios u ON pf.id_usuario = u.id_usuario WHERE pf.id_ficha_personal = :id_f_reload");
                    $stmt_reload_edit->bindParam(':id_f_reload', $id_ficha_form, PDO::PARAM_INT);
                    $stmt_reload_edit->execute();
                    $ficha_para_editar = $stmt_reload_edit->fetch(PDO::FETCH_ASSOC);
                    if($ficha_para_editar) $id_usuario_asociado_ficha = $ficha_para_editar['id_usuario'];
                } catch (PDOException $e_reload) {}
             }
        } else {
             $accion_ficha_crud = 'crear';
        }
    }
}

$fichas_personal = [];
if ($accion_ficha_crud === 'listar') {
    try {
        $sql_listar_fichas = "SELECT pf.id_ficha_personal, pf.codigo_empleado, pf.ci_numero, pf.dias_vacacion_anuales_asignados,
                                     u.nombres, u.apellidos, u.cargo
                              FROM personal_fichas pf
                              JOIN usuarios u ON pf.id_usuario = u.id_usuario
                              ORDER BY u.apellidos, u.nombres ASC";
        $stmt_listar_fichas = $pdo->query($sql_listar_fichas);
        $fichas_personal = $stmt_listar_fichas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al listar fichas de personal: " . $e->getMessage());
        mensaje_flash('error_ficha_crud', 'Error al cargar la lista de fichas de personal.', 'alert-danger');
    }
}
?>
<h2>Gestión de Fichas de Personal</h2>

<?php
mensaje_flash('error_ficha_crud');
mensaje_flash('exito_ficha_crud');
mensaje_flash('error_form_ficha_submit');
mensaje_flash('error_form_ficha_validation');
?>

<?php if ($accion_ficha_crud === 'crear' || ($accion_ficha_crud === 'editar' && $ficha_para_editar)): ?>
    <h3><?php echo ($accion_ficha_crud === 'crear') ? 'Crear Nueva Ficha de Personal' : 'Editar Ficha de: ' . htmlspecialchars(($ficha_para_editar['nombres_usr'] ?? '') . ' ' . ($ficha_para_editar['apellidos_usr'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
    <div class="card-sigi">
        <form action="index.php?vista=personal_ficha_crud<?php echo ($accion_ficha_crud === 'editar' && $id_ficha_editar) ? '&accion_ficha_crud=editar&id_ficha='.$id_ficha_editar : ''; ?>" method="POST" class="validar-js form-ficha-personal">
            <?php if ($accion_ficha_crud === 'editar' && isset($ficha_para_editar['id_ficha_personal'])): ?>
                <input type="hidden" name="id_ficha_hidden" value="<?php echo $ficha_para_editar['id_ficha_personal']; ?>">
            <?php endif; ?>

            <fieldset>
                <legend>Datos del Funcionario</legend>
                <div class="grupo-formulario">
                    <label for="id_usuario_ficha">Usuario del Sistema Asociado:</label>
                    <?php if ($accion_ficha_crud === 'editar' && $id_usuario_asociado_ficha): ?>
                        <input type="hidden" name="id_usuario_ficha" value="<?php echo $id_usuario_asociado_ficha; ?>">
                        <p><strong><?php echo htmlspecialchars(($ficha_para_editar['apellidos_usr'] ?? '') . ', ' . ($ficha_para_editar['nombres_usr'] ?? '') . ' (' . ($ficha_para_editar['nombre_usuario'] ?? '') . ')', ENT_QUOTES, 'UTF-8'); ?></strong> (No editable desde aquí)</p>
                    <?php else: ?>
                        <select id="id_usuario_ficha" name="id_usuario_ficha" required>
                            <option value="">-- Seleccione un usuario --</option>
                            <?php foreach ($usuarios_sin_ficha as $usf): ?>
                                <option value="<?php echo $usf['id_usuario']; ?>" <?php if(($_POST['id_usuario_ficha'] ?? '') == $usf['id_usuario']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($usf['nombre_completo'] . ($usf['cargo'] ? ' (' . $usf['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(empty($usuarios_sin_ficha) && $accion_ficha_crud === 'crear'): ?> <small class="text-danger">No hay usuarios activos sin ficha para asignar. Debe crear usuarios primero o asegurarse que no tengan ya una ficha.</small> <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="grupo-formulario">
                    <label for="codigo_empleado">Código de Empleado:</label>
                    <input type="text" id="codigo_empleado" name="codigo_empleado" value="<?php echo htmlspecialchars($ficha_para_editar['codigo_empleado'] ?? ($_POST['codigo_empleado'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="20">
                </div>
            </fieldset>

            <fieldset>
                <legend>Información Personal</legend>
                <div class="grid-col-2">
                    <div class="grupo-formulario">
                        <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($ficha_para_editar['fecha_nacimiento'] ?? ($_POST['fecha_nacimiento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="grupo-formulario">
                        <label for="lugar_nacimiento">Lugar de Nacimiento:</label>
                        <input type="text" id="lugar_nacimiento" name="lugar_nacimiento" value="<?php echo htmlspecialchars($ficha_para_editar['lugar_nacimiento'] ?? ($_POST['lugar_nacimiento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
                    </div>
                    <div class="grupo-formulario">
                        <label for="nacionalidad">Nacionalidad:</label>
                        <input type="text" id="nacionalidad" name="nacionalidad" value="<?php echo htmlspecialchars($ficha_para_editar['nacionalidad'] ?? ($_POST['nacionalidad'] ?? 'Boliviana'), ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                    </div>
                    <div class="grupo-formulario">
                        <label for="ci_numero">Nro. Cédula Identidad:</label>
                        <input type="text" id="ci_numero" name="ci_numero" value="<?php echo htmlspecialchars($ficha_para_editar['ci_numero'] ?? ($_POST['ci_numero'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="20">
                    </div>
                    <div class="grupo-formulario">
                        <label for="ci_expedido_en">CI Expedido en (Dpto.):</label>
                        <select id="ci_expedido_en" name="ci_expedido_en">
                            <option value="">-- Seleccionar --</option>
                            <?php $deptos = ['LP', 'CB', 'SC', 'OR', 'PT', 'CH', 'TJ', 'BE', 'PD'];
                                  $val_ci_exp = $ficha_para_editar['ci_expedido_en'] ?? ($_POST['ci_expedido_en'] ?? ''); ?>
                            <?php foreach($deptos as $d): ?>
                                <option value="<?php echo $d; ?>" <?php if($val_ci_exp == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grupo-formulario">
                        <label for="estado_civil">Estado Civil:</label>
                        <select id="estado_civil" name="estado_civil">
                            <option value="">-- Seleccionar --</option>
                            <?php $estados_c = ['soltero_a', 'casado_a', 'viudo_a', 'divorciado_a', 'conviviente'];
                                  $val_est_c = $ficha_para_editar['estado_civil'] ?? ($_POST['estado_civil'] ?? ''); ?>
                            <?php foreach($estados_c as $ec): ?>
                                <option value="<?php echo $ec; ?>" <?php if($val_est_c == $ec) echo 'selected'; ?>><?php echo ucfirst(str_replace('_','/',$ec)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grupo-formulario">
                    <label for="domicilio_actual">Domicilio Actual:</label>
                    <textarea id="domicilio_actual" name="domicilio_actual" rows="2"><?php echo htmlspecialchars($ficha_para_editar['domicilio_actual'] ?? ($_POST['domicilio_actual'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend>Información de Contacto de Emergencia</legend>
                 <div class="grid-col-3">
                    <div class="grupo-formulario">
                        <label for="contacto_emergencia_nombre">Nombre Contacto Emergencia:</label>
                        <input type="text" id="contacto_emergencia_nombre" name="contacto_emergencia_nombre" value="<?php echo htmlspecialchars($ficha_para_editar['contacto_emergencia_nombre'] ?? ($_POST['contacto_emergencia_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
                    </div>
                     <div class="grupo-formulario">
                        <label for="relacion_contacto_emergencia">Relación/Parentesco:</label>
                        <input type="text" id="relacion_contacto_emergencia" name="relacion_contacto_emergencia" value="<?php echo htmlspecialchars($ficha_para_editar['relacion_contacto_emergencia'] ?? ($_POST['relacion_contacto_emergencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="50">
                    </div>
                    <div class="grupo-formulario">
                        <label for="telefono_emergencia">Teléfono Contacto Emergencia:</label>
                        <input type="text" id="telefono_emergencia" name="telefono_emergencia" value="<?php echo htmlspecialchars($ficha_para_editar['telefono_emergencia'] ?? ($_POST['telefono_emergencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="30">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Formación y Datos Laborales</legend>
                <div class="grid-col-2">
                    <div class="grupo-formulario">
                        <label for="nivel_educativo">Nivel Educativo Alcanzado:</label>
                        <input type="text" id="nivel_educativo" name="nivel_educativo" value="<?php echo htmlspecialchars($ficha_para_editar['nivel_educativo'] ?? ($_POST['nivel_educativo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
                    </div>
                    <div class="grupo-formulario">
                        <label for="profesion">Profesión:</label>
                        <input type="text" id="profesion" name="profesion" value="<?php echo htmlspecialchars($ficha_para_editar['profesion'] ?? ($_POST['profesion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
                    </div>
                    <div class="grupo-formulario">
                        <label for="fecha_ingreso_institucion">Fecha de Ingreso a la Institución:</label>
                        <input type="date" id="fecha_ingreso_institucion" name="fecha_ingreso_institucion" value="<?php echo htmlspecialchars($ficha_para_editar['fecha_ingreso_institucion'] ?? ($_POST['fecha_ingreso_institucion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                     <div class="grupo-formulario">
                        <label for="tipo_contrato">Tipo de Contrato:</label>
                        <input type="text" id="tipo_contrato" name="tipo_contrato" value="<?php echo htmlspecialchars($ficha_para_editar['tipo_contrato'] ?? ($_POST['tipo_contrato'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                    </div>
                    <div class="grupo-formulario">
                        <label for="salario_base">Salario Base (Bs.):</label>
                        <input type="text" id="salario_base" name="salario_base" value="<?php echo htmlspecialchars($ficha_para_editar['salario_base'] ?? ($_POST['salario_base'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej: 5000.50">
                    </div>
                     <div class="grupo-formulario">
                        <label for="dias_vacacion_anuales_asignados">Días de Vacación Anuales Asignados:</label>
                        <input type="number" id="dias_vacacion_anuales_asignados" name="dias_vacacion_anuales_asignados" value="<?php echo htmlspecialchars($ficha_para_editar['dias_vacacion_anuales_asignados'] ?? ($_POST['dias_vacacion_anuales_asignados'] ?? 20), ENT_QUOTES, 'UTF-8'); ?>" min="0" max="50">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Datos de Seguridad Social y Médicos</legend>
                 <div class="grid-col-3">
                    <div class="grupo-formulario">
                        <label for="afp_asociada">AFP Asociada:</label>
                        <input type="text" id="afp_asociada" name="afp_asociada" value="<?php echo htmlspecialchars($ficha_para_editar['afp_asociada'] ?? ($_POST['afp_asociada'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                    </div>
                    <div class="grupo-formulario">
                        <label for="nua_cua">NUA/CUA:</label>
                        <input type="text" id="nua_cua" name="nua_cua" value="<?php echo htmlspecialchars($ficha_para_editar['nua_cua'] ?? ($_POST['nua_cua'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="50">
                    </div>
                    <div class="grupo-formulario">
                        <label for="grupo_sanguineo">Grupo Sanguíneo y Factor RH:</label>
                        <input type="text" id="grupo_sanguineo" name="grupo_sanguineo" value="<?php echo htmlspecialchars($ficha_para_editar['grupo_sanguineo'] ?? ($_POST['grupo_sanguineo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="10" placeholder="Ej: O+">
                    </div>
                </div>
                <div class="grupo-formulario">
                    <label for="alergias_conocidas">Alergias Conocidas:</label>
                    <textarea id="alergias_conocidas" name="alergias_conocidas" rows="2"><?php echo htmlspecialchars($ficha_para_editar['alergias_conocidas'] ?? ($_POST['alergias_conocidas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="grupo-formulario">
                    <label for="observaciones_medicas">Otras Observaciones Médicas Relevantes:</label>
                    <textarea id="observaciones_medicas" name="observaciones_medicas" rows="2"><?php echo htmlspecialchars($ficha_para_editar['observaciones_medicas'] ?? ($_POST['observaciones_medicas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </fieldset>

            <div class="acciones-formulario mt-3">
                <?php if ($accion_ficha_crud === 'crear'): ?>
                    <button type="submit" name="guardar_ficha_nueva" class="boton boton-primario">Crear Ficha</button>
                <?php else: // Modo Edición ?>
                    <button type="submit" name="actualizar_ficha_existente" class="boton boton-primario">Actualizar Ficha</button>
                <?php endif; ?>
                <a href="index.php?vista=personal_ficha_crud" class="boton boton-secundario">Cancelar</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <p><a href="index.php?vista=personal_ficha_crud&accion_ficha_crud=crear" class="boton boton-exito">Crear Nueva Ficha de Personal</a></p>

    <?php if (empty($fichas_personal)): ?>
        <div class="alert alert-info">No hay fichas de personal registradas.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>Cód. Empleado</th>
                        <th>Apellidos</th>
                        <th>Nombres</th>
                        <th>CI</th>
                        <th>Cargo</th>
                        <th>Días Vac. Asignados</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fichas_personal as $ficha_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ficha_item['codigo_empleado'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ficha_item['apellidos'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ficha_item['nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ficha_item['ci_numero'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ficha_item['cargo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:center;"><?php echo htmlspecialchars($ficha_item['dias_vacacion_anuales_asignados'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="index.php?vista=personal_ficha_detalle&id_ficha=<?php echo $ficha_item['id_ficha_personal']; ?>" class="boton-tabla ver" title="Ver Detalle Ficha">👁️</a>
                                <a href="index.php?vista=personal_ficha_crud&accion_ficha_crud=editar&id_ficha=<?php echo $ficha_item['id_ficha_personal']; ?>" class="boton-tabla editar" title="Editar Ficha">✏️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra_caja); margin-bottom: 1.5rem; }
.card-sigi h3, .form-ficha-personal fieldset legend { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.form-ficha-personal fieldset { border: 1px solid #ddd; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--borde-radio); }
.form-ficha-personal fieldset legend { font-size: 1.1em; font-weight: bold; padding: 0 0.5em; width: auto; }
.grid-col-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
.grid-col-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
.boton-tabla.ver { background-color: var(--color-info); color:white; }
.boton-tabla.editar { background-color: var(--color-advertencia); color:black; }
.text-danger { color: var(--color-error); }
.text-warning { color: var(--color-advertencia); }
</style>

<?php
// Comentario: Fin del archivo vistas/personal_ficha_crud.php
?>
