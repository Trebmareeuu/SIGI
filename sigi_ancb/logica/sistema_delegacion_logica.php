<?php
// Archivo: logica/sistema_delegacion_logica.php
// Propósito: Lógica para el panel de Delegación de Autoridad (MAE).

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Este es el MAE.
if (!tiene_permiso('DELEGAR_AUTORIDAD_SISTEMA', $id_usuario_actual)) {
    mensaje_flash('error_delegacion', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Cargar usuarios a quienes se puede delegar.
$usuarios_delegables = [];
try {
    $stmt_ud = $pdo->prepare("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo, cargo
                              FROM usuarios
                              WHERE id_usuario != :id_mae_actual AND estado = 'activo'
                              ORDER BY apellidos, nombres ASC");
    $stmt_ud->bindParam(':id_mae_actual', $id_usuario_actual, PDO::PARAM_INT);
    $stmt_ud->execute();
    $usuarios_delegables = $stmt_ud->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar usuarios para delegación: " . $e->getMessage());
    mensaje_flash('error_delegacion_form', 'Error al cargar la lista de usuarios delegables.', 'alert-danger');
}

// Comentario: Lista de permisos que el MAE puede delegar.
$permisos_delegables_mae = [
    'APROBAR_VACACIONES_MAE' => 'Aprobar/Rechazar Solicitudes de Vacación (como MAE)',
    'APROBAR_SOLICITUDES_ADMIN' => 'Aprobar/Rechazar Solicitudes de Material/Activo (como si fuera Dir. Admin)',
    // Comentario: Añadir más permisos específicos.
];
ksort($permisos_delegables_mae);


// Comentario: Procesamiento del formulario de nueva delegación.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_delegacion'])) {
    $id_usuario_delegado_form = filter_input(INPUT_POST, 'id_usuario_delegado', FILTER_VALIDATE_INT);
    $fecha_inicio_delegacion_form_raw = $_POST['fecha_inicio_delegacion'] ?? '';
    $fecha_fin_delegacion_form_raw = $_POST['fecha_fin_delegacion'] ?? '';
    $permisos_delegados_form = $_POST['permisos_delegados'] ?? [];
    $motivo_delegacion_form = strip_tags($_POST['motivo_delegacion'] ?? '');

    $errores_form_deleg = [];
    if (empty($id_usuario_delegado_form)) $errores_form_deleg[] = "Debe seleccionar un usuario a quien delegar.";

    $obj_fecha_inicio_del = null;
    $obj_fecha_fin_del = null;

    try {
        if(empty($fecha_inicio_delegacion_form_raw)) throw new Exception();
        $obj_fecha_inicio_del = new DateTime($fecha_inicio_delegacion_form_raw);
    } catch (Exception $_){
        $errores_form_deleg[] = "La fecha y hora de inicio de la delegación no son válidas.";
    }
    try {
        if(empty($fecha_fin_delegacion_form_raw)) throw new Exception();
        $obj_fecha_fin_del = new DateTime($fecha_fin_delegacion_form_raw);
    } catch (Exception $_){
        $errores_form_deleg[] = "La fecha y hora de fin de la delegación no son válidas.";
    }

    if (empty($permisos_delegados_form)) $errores_form_deleg[] = "Debe seleccionar al menos un permiso para delegar.";
    if (empty($motivo_delegacion_form)) $errores_form_deleg[] = "El motivo de la delegación es obligatorio.";

    if ($obj_fecha_inicio_del && $obj_fecha_fin_del && $obj_fecha_fin_del <= $obj_fecha_inicio_del) {
        $errores_form_deleg[] = "La fecha de fin debe ser posterior a la fecha de inicio.";
    }

    // Comentario: Validar que los permisos seleccionados estén en la lista de delegables.
    if (!empty($permisos_delegados_form)) {
        foreach ($permisos_delegados_form as $perm_del_check) {
            if (!array_key_exists($perm_del_check, $permisos_delegables_mae)) {
                $errores_form_deleg[] = "Se seleccionó un permiso no delegable: " . htmlspecialchars($perm_del_check, ENT_QUOTES, 'UTF-8');
            }
        }
    }

    if (empty($errores_form_deleg)) {
        try {
            $permisos_delegados_json = json_encode($permisos_delegados_form);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error al codificar permisos delegados a JSON: " . json_last_error_msg());
            }

            $sql_insert_del = "INSERT INTO sistema_delegaciones (id_usuario_delegante, id_usuario_delegado, fecha_inicio_delegacion, fecha_fin_delegacion, permisos_delegados, motivo_delegacion, estado_delegacion)
                               VALUES (:id_mae, :id_delegado, :f_ini, :f_fin, :perm_json, :motivo, 'activa')";
            $stmt_insert_del = $pdo->prepare($sql_insert_del);
            $stmt_insert_del->execute([
                ':id_mae' => $id_usuario_actual,
                ':id_delegado' => $id_usuario_delegado_form,
                ':f_ini' => $obj_fecha_inicio_del->format('Y-m-d H:i:s'),
                ':f_fin' => $obj_fecha_fin_del->format('Y-m-d H:i:s'),
                ':perm_json' => $permisos_delegados_json,
                ':motivo' => $motivo_delegacion_form
            ]);
            mensaje_flash('exito_delegacion', 'Delegación de autoridad creada exitosamente.', 'alert-success');
            redirigir('index.php?vista=sistema_delegacion');
        } catch (Exception $e) {
            error_log("Error al crear delegación: " . $e->getMessage());
            mensaje_flash('error_delegacion_form', 'Error al crear la delegación: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
         foreach ($errores_form_deleg as $err_d) {
            mensaje_flash('error_delegacion_form', $err_d, 'alert-danger');
        }
    }
}

// Comentario: Lógica para cancelar una delegación activa.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_delegacion'])) {
    $id_delegacion_cancelar = filter_input(INPUT_POST, 'id_delegacion_a_cancelar', FILTER_VALIDATE_INT);
    if ($id_delegacion_cancelar) {
        try {
            $sql_cancel = "UPDATE sistema_delegaciones SET estado_delegacion = 'cancelada'
                           WHERE id_delegacion = :id_del_can AND id_usuario_delegante = :id_mae_can AND estado_delegacion = 'activa'";
            $stmt_cancel = $pdo->prepare($sql_cancel);
            $stmt_cancel->execute([':id_del_can' => $id_delegacion_cancelar, ':id_mae_can' => $id_usuario_actual]);
            if ($stmt_cancel->rowCount() > 0) {
                mensaje_flash('exito_delegacion', 'Delegación cancelada exitosamente.', 'alert-success');
            } else {
                mensaje_flash('error_delegacion', 'No se pudo cancelar la delegación (puede que no exista, no sea suya, o ya no esté activa).', 'alert-warning');
            }
        } catch (PDOException $e) {
            error_log("Error al cancelar delegación ID $id_delegacion_cancelar: " . $e->getMessage());
            mensaje_flash('error_delegacion', 'Error al cancelar la delegación: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=sistema_delegacion');
    }
}


// Comentario: Cargar delegaciones activas y pasadas hechas por este MAE (para mostrar en la vista).
$delegaciones_mae = [];
try {
    $stmt_list_del = $pdo->prepare("SELECT sd.*, CONCAT(ud.apellidos, ', ', ud.nombres) as nombre_delegado
                                    FROM sistema_delegaciones sd
                                    JOIN usuarios ud ON sd.id_usuario_delegado = ud.id_usuario
                                    WHERE sd.id_usuario_delegante = :id_mae_actual_list
                                    ORDER BY sd.fecha_inicio_delegacion DESC");
    $stmt_list_del->bindParam(':id_mae_actual_list', $id_usuario_actual, PDO::PARAM_INT);
    $stmt_list_del->execute();
    $delegaciones_mae = $stmt_list_del->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al listar delegaciones del MAE: " . $e->getMessage());
    mensaje_flash('error_delegacion', 'Error al cargar el historial de delegaciones.', 'alert-danger');
}

// Comentario: Fin de logica/sistema_delegacion_logica.php
?>
