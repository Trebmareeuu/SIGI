<?php
// Archivo: logica/personal_ficha_crud_logica.php
// Propósito: Lógica para el CRUD de Fichas de Personal (Presupuesto).

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CRUD_PERSONAL_FICHA', $id_usuario_actual)) {
    mensaje_flash('error_ficha_crud', 'No tiene permisos para gestionar fichas de personal.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Acción (listar, crear, editar).
$accion_ficha_crud = $_GET['accion_ficha_crud'] ?? 'listar';
$id_ficha_editar = null;
$ficha_para_editar = null;
$id_usuario_asociado_ficha = null;

// Comentario: Cargar usuarios que NO tienen ya una ficha de personal (para el select al crear).
$usuarios_sin_ficha = [];
if ($accion_ficha_crud === 'crear' || $accion_ficha_crud === 'editar') { // Comentario: Solo cargar si se va a mostrar el formulario.
    try {
        $sql_usf = "SELECT u.id_usuario, CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo, u.cargo
                    FROM usuarios u
                    LEFT JOIN personal_fichas pf ON u.id_usuario = pf.id_usuario
                    WHERE u.estado = 'activo'";
        // Comentario: Si es edición, incluir al usuario actual de la ficha aunque ya tenga una.
        // Comentario: Si es creación, solo los que no tienen ficha.
        if ($accion_ficha_crud === 'crear') {
            $sql_usf .= " AND pf.id_ficha_personal IS NULL";
        }
        $sql_usf .= " ORDER BY u.apellidos, u.nombres ASC";

        $stmt_usf = $pdo->query($sql_usf);
        $usuarios_sin_ficha = $stmt_usf->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al cargar usuarios para ficha: " . $e->getMessage());
        mensaje_flash('error_ficha_crud', 'Error al cargar la lista de usuarios.', 'alert-warning');
    }
}


if ($accion_ficha_crud === 'editar' && isset($_GET['id_ficha'])) {
    $id_ficha_editar = filter_var($_GET['id_ficha'], FILTER_VALIDATE_INT);
    if ($id_ficha_editar) {
        try {
            // Comentario: Obtener también datos del usuario asociado para mostrar en el form.
            $stmt_edit_ficha = $pdo->prepare("SELECT pf.*, u.nombres as nombres_usr, u.apellidos as apellidos_usr, u.nombre_usuario
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
                $accion_ficha_crud = 'listar'; // Comentario: Volver a listar.
            }
        } catch (PDOException $e) {
            error_log("Error al cargar ficha para editar ID $id_ficha_editar: " . $e->getMessage());
            mensaje_flash('error_ficha_crud', 'Error al cargar datos de la ficha para edición.', 'alert-danger');
            $accion_ficha_crud = 'listar';
        }
    } else {
        mensaje_flash('error_ficha_crud', 'ID de ficha no válido para editar.', 'alert-danger');
        $accion_ficha_crud = 'listar';
    }
}


// Comentario: Lógica para CREAR o ACTUALIZAR ficha.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_ficha_nueva']) || isset($_POST['actualizar_ficha_existente'])) {
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
        $salario_base_form = filter_var(str_replace(',', '.', $_POST['salario_base'] ?? ''), FILTER_VALIDATE_FLOAT); // Comentario: Permitir coma como decimal.
        $afp_asociada_form = sanitizar_entrada($_POST['afp_asociada'] ?? '');
        $nua_cua_form = sanitizar_entrada($_POST['nua_cua'] ?? '');
        $grupo_sanguineo_form = sanitizar_entrada($_POST['grupo_sanguineo'] ?? '');
        $alergias_conocidas_form = strip_tags($_POST['alergias_conocidas'] ?? '');
        $observaciones_medicas_form = strip_tags($_POST['observaciones_medicas'] ?? '');

        $errores_form_ficha = [];
        if (empty($id_usuario_form) && $accion_ficha_crud === 'crear') $errores_form_ficha[] = "Debe seleccionar un usuario para asociar la ficha.";
        if (empty($codigo_empleado_form)) $errores_form_ficha[] = "El código de empleado es obligatorio.";
        if (empty($ci_numero_form)) $errores_form_ficha[] = "El número de Cédula de Identidad es obligatorio.";
        if (!empty($fecha_nacimiento_form) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_nacimiento_form)) $errores_form_ficha[] = "Formato de fecha de nacimiento no válido (YYYY-MM-DD).";
        if (!empty($fecha_ingreso_institucion_form) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_ingreso_institucion_form)) $errores_form_ficha[] = "Formato de fecha de ingreso no válido (YYYY-MM-DD).";
        if ($salario_base_form === false && !is_null($_POST['salario_base']) && $_POST['salario_base'] !== '') $errores_form_ficha[] = "El salario base no es un número válido.";

        $id_excluir_ficha_check = $id_ficha_form ?: 0;
        try {
            if ($accion_ficha_crud === 'crear' || ($accion_ficha_crud === 'editar' && $id_usuario_form != $ficha_para_editar['id_usuario'])) {
                 $stmt_check_id_usr = $pdo->prepare("SELECT id_ficha_personal FROM personal_fichas WHERE id_usuario = :id_usr AND id_ficha_personal != :id_excluir_f");
                 $stmt_check_id_usr->execute([':id_usr' => $id_usuario_form, ':id_excluir_f' => $id_excluir_ficha_check]);
                 if ($stmt_check_id_usr->fetch()) $errores_form_ficha[] = "El usuario seleccionado ya tiene una ficha de personal.";
            }
            if (!empty($codigo_empleado_form)) {
                $stmt_check_cod_emp = $pdo->prepare("SELECT id_ficha_personal FROM personal_fichas WHERE codigo_empleado = :cod_emp AND id_ficha_personal != :id_excluir_f");
                $stmt_check_cod_emp->execute([':cod_emp' => $codigo_empleado_form, ':id_excluir_f' => $id_excluir_ficha_check]);
                if ($stmt_check_cod_emp->fetch()) $errores_form_ficha[] = "El código de empleado '$codigo_empleado_form' ya está en uso.";
            }
            if (!empty($ci_numero_form)) {
                $stmt_check_ci = $pdo->prepare("SELECT id_ficha_personal FROM personal_fichas WHERE ci_numero = :ci_num AND id_ficha_personal != :id_excluir_f");
                $stmt_check_ci->execute([':ci_num' => $ci_numero_form, ':id_excluir_f' => $id_excluir_ficha_check]);
                if ($stmt_check_ci->fetch()) $errores_form_ficha[] = "El CI '$ci_numero_form' ya está registrado.";
            }
             if (!empty($nua_cua_form)) {
                $stmt_check_nua = $pdo->prepare("SELECT id_ficha_personal FROM personal_fichas WHERE nua_cua = :nua AND id_ficha_personal != :id_excluir_f");
                $stmt_check_nua->execute([':nua' => $nua_cua_form, ':id_excluir_f' => $id_excluir_ficha_check]);
                if ($stmt_check_nua->fetch()) $errores_form_ficha[] = "El NUA/CUA '$nua_cua_form' ya está registrado.";
            }
        } catch (PDOException $e) {
            $errores_form_ficha[] = "Error al verificar unicidad de datos de ficha: " . $e->getMessage();
        }

        if (empty($errores_form_ficha)) {
            $params_sql_ficha = [
                ':id_usr' => $id_usuario_form ?: $ficha_para_editar['id_usuario'], // Comentario: En edición, id_usuario no cambia.
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
                ':salario' => ($salario_base_form === false || is_null($salario_base_form) || $salario_base_form === '') ? null : $salario_base_form,
                ':afp' => $afp_asociada_form, ':nua' => $nua_cua_form, ':grupo_sang' => $grupo_sanguineo_form,
                ':alergias' => $alergias_conocidas_form, ':obs_med' => $observaciones_medicas_form
            ];

            try {
                if (isset($_POST['guardar_ficha_nueva'])) {
                    $sql_insert_ficha = "INSERT INTO personal_fichas (id_usuario, codigo_empleado, fecha_nacimiento, lugar_nacimiento, nacionalidad, ci_numero, ci_expedido_en, estado_civil, domicilio_actual, telefono_emergencia, contacto_emergencia_nombre, relacion_contacto_emergencia, nivel_educativo, profesion, fecha_ingreso_institucion, tipo_contrato, salario_base, afp_asociada, nua_cua, grupo_sanguineo, alergias_conocidas, observaciones_medicas, fecha_creacion_ficha, fecha_modificacion_ficha)
                                         VALUES (:id_usr, :cod_emp, :fec_nac, :lugar_nac, :nac, :ci_num, :ci_exp, :est_civil, :domicilio, :tel_emerg, :contacto_emerg_nom, :rel_contacto_emerg, :nivel_edu, :profesion, :fec_ingreso, :tipo_cont, :salario, :afp, :nua, :grupo_sang, :alergias, :obs_med, NOW(), NOW())";
                    $stmt_op_ficha = $pdo->prepare($sql_insert_ficha);
                    $stmt_op_ficha->execute($params_sql_ficha);
                    mensaje_flash('exito_ficha_crud', 'Ficha de personal creada exitosamente.', 'alert-success');
                    redirigir('index.php?vista=personal_ficha_crud');

                } elseif (isset($_POST['actualizar_ficha_existente']) && $id_ficha_form) {
                    // Comentario: En edición, no se cambia id_usuario.
                    unset($params_sql_ficha[':id_usr']);
                    $params_sql_ficha[':id_ficha_upd'] = $id_ficha_form;
                    $sql_update_ficha = "UPDATE personal_fichas SET
                                            codigo_empleado = :cod_emp, fecha_nacimiento = :fec_nac, lugar_nacimiento = :lugar_nac, nacionalidad = :nac,
                                            ci_numero = :ci_num, ci_expedido_en = :ci_exp, estado_civil = :est_civil, domicilio_actual = :domicilio,
                                            telefono_emergencia = :tel_emerg, contacto_emergencia_nombre = :contacto_emerg_nom, relacion_contacto_emergencia = :rel_contacto_emerg,
                                            nivel_educativo = :nivel_edu, profesion = :profesion, fecha_ingreso_institucion = :fec_ingreso,
                                            tipo_contrato = :tipo_cont, salario_base = :salario, afp_asociada = :afp, nua_cua = :nua,
                                            grupo_sanguineo = :grupo_sang, alergias_conocidas = :alergias, observaciones_medicas = :obs_med,
                                            fecha_modificacion_ficha = NOW()
                                         WHERE id_ficha_personal = :id_ficha_upd";
                    $stmt_op_ficha = $pdo->prepare($sql_update_ficha);
                    $stmt_op_ficha->execute($params_sql_ficha);
                    mensaje_flash('exito_ficha_crud', 'Ficha de personal actualizada exitosamente.', 'alert-success');
                    redirigir('index.php?vista=personal_ficha_crud');
                }
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
                 // Comentario: Recargar datos para el form de edición si es necesario o usar $_POST.
                 // Comentario: $ficha_para_editar ya está cargado. Los valores del POST prevalecerán en el HTML.
                 $id_usuario_asociado_ficha = $id_usuario_form ?: ($ficha_para_editar['id_usuario'] ?? null);
            } else {
                 $accion_ficha_crud = 'crear';
            }
        }
    }
}


// Comentario: Lógica para listar fichas (se ejecuta si $accion_ficha_crud es 'listar').
$fichas_personal = [];
if ($accion_ficha_crud === 'listar') {
    try {
        $sql_listar_fichas = "SELECT pf.id_ficha_personal, pf.codigo_empleado, pf.ci_numero,
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

// Comentario: Fin de logica/personal_ficha_crud_logica.php
?>
