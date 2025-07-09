<?php
// Archivo: logica/pagos_gestor_logica.php
// Propósito: Lógica para el Gestor de Pagos (Dir. Admin).

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('GESTIONAR_PAGOS', $id_usuario_actual)) {
    mensaje_flash('error_gestor_pagos', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para repoblar formularios.
$descripcion_pago_rec_form = $_POST['descripcion_pago_rec'] ?? '';
$proveedor_pago_rec_form = $_POST['proveedor_pago_rec'] ?? '';
$monto_pago_rec_form = $_POST['monto_pago_rec'] ?? '';
// ... (más variables para el primer formulario) ...
$id_pago_recurrente_hist_form = $_POST['id_pago_recurrente_hist'] ?? '';
$descripcion_pago_efectuado_form = $_POST['descripcion_pago_efectuado'] ?? '';
// ... (más variables para el segundo formulario) ...


// Comentario: Procesamiento de nuevo pago recurrente.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_pago_recurrente'])) {
    $descripcion_pago = sanitizar_entrada($_POST['descripcion_pago_rec'] ?? '');
    $proveedor_pago = sanitizar_entrada($_POST['proveedor_pago_rec'] ?? '');
    $monto_pago = filter_var(str_replace(',', '.', $_POST['monto_pago_rec'] ?? ''), FILTER_VALIDATE_FLOAT);
    $moneda_pago = sanitizar_entrada($_POST['moneda_pago_rec'] ?? 'BOB');
    $frecuencia_pago = sanitizar_entrada($_POST['frecuencia_pago_rec'] ?? 'mensual');
    $dia_pago_estimado = filter_var($_POST['dia_pago_estimado_rec'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
    $fecha_proximo_pago = sanitizar_entrada($_POST['fecha_proximo_pago_rec'] ?? '');
    $estado_pago_rec = sanitizar_entrada($_POST['estado_pago_rec'] ?? 'activo');
    $observaciones_pago_rec = strip_tags($_POST['observaciones_pago_rec'] ?? '');

    $errores_form_rec = [];
    if (empty($descripcion_pago)) $errores_form_rec[] = "La descripción del pago es obligatoria.";
    if ($monto_pago === false || $monto_pago <= 0) $errores_form_rec[] = "El monto del pago no es válido.";
    if (empty($fecha_proximo_pago) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_proximo_pago)) {
        $errores_form_rec[] = "La fecha del próximo pago no es válida (YYYY-MM-DD).";
    }
    // Comentario: Validar día de pago si la frecuencia no es 'unico'.
    if ($frecuencia_pago !== 'unico' && ($dia_pago_estimado === false || $dia_pago_estimado < 1 || $dia_pago_estimado > 31) && !empty($_POST['dia_pago_estimado_rec'])) {
        $errores_form_rec[] = "El día estimado de pago debe ser entre 1 y 31.";
    } elseif ($frecuencia_pago === 'unico') {
        $dia_pago_estimado = null; // Comentario: No aplica para pagos únicos.
    }


    if (empty($errores_form_rec)) {
        try {
            $sql_insert_rec = "INSERT INTO pagos_recurrentes (descripcion_pago, proveedor, monto_pago, moneda, frecuencia_pago, dia_pago_estimado, fecha_proximo_pago, estado, observaciones, id_usuario_registra)
                               VALUES (:desc, :prov, :monto, :moneda, :frec, :dia, :fecha_prox, :est, :obs, :id_user)";
            $stmt_insert_rec = $pdo->prepare($sql_insert_rec);
            $stmt_insert_rec->execute([
                ':desc' => $descripcion_pago, ':prov' => $proveedor_pago, ':monto' => $monto_pago, ':moneda' => $moneda_pago,
                ':frec' => $frecuencia_pago, ':dia' => $dia_pago_estimado, ':fecha_prox' => $fecha_proximo_pago,
                ':est' => $estado_pago_rec, ':obs' => $observaciones_pago_rec, ':id_user' => $id_usuario_actual
            ]);
            mensaje_flash('exito_gestor_pagos', 'Nuevo pago recurrente guardado exitosamente.', 'alert-success');
            redirigir('index.php?vista=pagos_gestor');
        } catch (PDOException $e) {
            error_log("Error al guardar pago recurrente: " . $e->getMessage());
            mensaje_flash('error_form_pago_rec', 'Error al guardar el pago recurrente: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_form_rec as $err) {
            mensaje_flash('error_form_pago_rec', $err, 'alert-danger');
        }
        // Comentario: No redirigir para que la vista muestre errores y repoble el form.
        // Comentario: Las variables para repoblar ya están seteadas arriba.
    }
}

// Comentario: Procesamiento de registro de pago efectuado.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago_efectuado'])) {
    $id_pago_recurrente_hist = filter_input(INPUT_POST, 'id_pago_recurrente_hist', FILTER_VALIDATE_INT) ?: null;
    $descripcion_pago_efectuado = sanitizar_entrada($_POST['descripcion_pago_efectuado'] ?? '');
    $monto_efectivamente_pagado = filter_var(str_replace(',', '.', $_POST['monto_efectivamente_pagado'] ?? ''), FILTER_VALIDATE_FLOAT);
    $fecha_efectiva_pago = sanitizar_entrada($_POST['fecha_efectiva_pago'] ?? date('Y-m-d'));
    $metodo_pago = sanitizar_entrada($_POST['metodo_pago'] ?? '');
    $referencia_comprobante = sanitizar_entrada($_POST['referencia_comprobante'] ?? '');
    $observaciones_pago_efectuado = strip_tags($_POST['observaciones_pago_efectuado'] ?? '');

    $errores_form_hist = [];
    if (empty($descripcion_pago_efectuado)) $errores_form_hist[] = "La descripción del pago efectuado es obligatoria.";
    if ($monto_efectivamente_pagado === false || $monto_efectivamente_pagado <= 0) $errores_form_hist[] = "El monto pagado no es válido.";
    if (empty($fecha_efectiva_pago) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_efectiva_pago)) {
        $errores_form_hist[] = "La fecha de pago no es válida (YYYY-MM-DD).";
    }

    if (empty($errores_form_hist)) {
        $pdo->beginTransaction();
        try {
            $sql_insert_hist = "INSERT INTO pagos_historial (id_pago_recurrente, descripcion_pago_efectuado, monto_efectivamente_pagado, fecha_efectiva_pago, metodo_pago, referencia_comprobante, observaciones, id_usuario_gestor_pago, fecha_registro_sistema)
                                VALUES (:id_rec, :desc_efec, :monto_efec, :fecha_efec, :metodo, :ref, :obs, :id_user, NOW())";
            $stmt_insert_hist = $pdo->prepare($sql_insert_hist);
            $stmt_insert_hist->execute([
                ':id_rec' => $id_pago_recurrente_hist, ':desc_efec' => $descripcion_pago_efectuado,
                ':monto_efec' => $monto_efectivamente_pagado, ':fecha_efec' => $fecha_efectiva_pago,
                ':metodo' => $metodo_pago, ':ref' => $referencia_comprobante, ':obs' => $observaciones_pago_efectuado,
                ':id_user' => $id_usuario_actual
            ]);

            if ($id_pago_recurrente_hist) {
                $stmt_freq = $pdo->prepare("SELECT frecuencia_pago, fecha_proximo_pago, dia_pago_estimado FROM pagos_recurrentes WHERE id_pago_recurrente = :id_rec_freq");
                $stmt_freq->bindParam(':id_rec_freq', $id_pago_recurrente_hist, PDO::PARAM_INT);
                $stmt_freq->execute();
                $pago_rec_info = $stmt_freq->fetch(PDO::FETCH_ASSOC);

                if ($pago_rec_info) {
                    if ($pago_rec_info['frecuencia_pago'] === 'unico') {
                         $pdo->prepare("UPDATE pagos_recurrentes SET estado = 'finalizado', fecha_proximo_pago = NULL WHERE id_pago_recurrente = ?")->execute([$id_pago_recurrente_hist]);
                    } else {
                        // Comentario: Calcular nueva fecha_proximo_pago.
                        $fecha_base_calculo = new DateTime($fecha_efectiva_pago); // Comentario: Usar fecha efectiva del pago actual.
                        $intervalo_str = '';
                        switch($pago_rec_info['frecuencia_pago']){
                            case 'mensual': $intervalo_str = '+1 month'; break;
                            case 'bimestral': $intervalo_str = '+2 months'; break;
                            case 'trimestral': $intervalo_str = '+3 months'; break;
                            case 'semestral': $intervalo_str = '+6 months'; break;
                            case 'anual': $intervalo_str = '+1 year'; break;
                        }
                        if($intervalo_str){
                            $fecha_base_calculo->modify($intervalo_str);
                            // Comentario: Si hay dia_pago_estimado, ajustar al día de ese mes.
                            if($pago_rec_info['dia_pago_estimado']){
                                $dia_estimado = (int)$pago_rec_info['dia_pago_estimado'];
                                $ultimo_dia_mes_calculado = (int)$fecha_base_calculo->format('t');
                                $dia_final = min($dia_estimado, $ultimo_dia_mes_calculado);
                                $fecha_base_calculo->setDate($fecha_base_calculo->format('Y'), $fecha_base_calculo->format('m'), $dia_final);
                            }
                            $nueva_fecha_prox_pago = $fecha_base_calculo->format('Y-m-d');
                            $pdo->prepare("UPDATE pagos_recurrentes SET fecha_proximo_pago = ? WHERE id_pago_recurrente = ?")->execute([$nueva_fecha_prox_pago, $id_pago_recurrente_hist]);
                        }
                    }
                }
            }
            $pdo->commit();
            mensaje_flash('exito_gestor_pagos', 'Pago efectuado registrado exitosamente.', 'alert-success');
            redirigir('index.php?vista=pagos_gestor');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al registrar pago efectuado: " . $e->getMessage());
            mensaje_flash('error_form_pago_hist', 'Error al registrar el pago efectuado: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
         foreach ($errores_form_hist as $err) {
            mensaje_flash('error_form_pago_hist', $err, 'alert-danger');
        }
    }
}

// Comentario: Variables para filtros y carga de datos para la vista.
$filtro_estado_recurrente = $_GET['filtro_estado_recurrente'] ?? 'activo';
$filtro_proveedor = $_GET['filtro_proveedor'] ?? '';

$pagos_recurrentes = [];
$condiciones_rec = [];
$params_rec = [];

if ($filtro_estado_recurrente !== 'todos') {
    $condiciones_rec[] = "pr.estado = :estado_rec";
    $params_rec[':estado_rec'] = $filtro_estado_recurrente;
}
if (!empty($filtro_proveedor)) {
    $condiciones_rec[] = "pr.proveedor LIKE :proveedor_rec";
    $params_rec[':proveedor_rec'] = '%' . $filtro_proveedor . '%';
}
$where_clause_rec = empty($condiciones_rec) ? '' : 'WHERE ' . implode(' AND ', $condiciones_rec);

try {
    $sql_rec = "SELECT pr.*, u.nombre_usuario as usuario_registra_nombre
                FROM pagos_recurrentes pr
                LEFT JOIN usuarios u ON pr.id_usuario_registra = u.id_usuario
                $where_clause_rec
                ORDER BY pr.fecha_proximo_pago ASC, pr.descripcion_pago ASC";
    $stmt_rec = $pdo->prepare($sql_rec);
    $stmt_rec->execute($params_rec);
    $pagos_recurrentes = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar pagos recurrentes: " . $e->getMessage());
    mensaje_flash('error_gestor_pagos', 'Error al cargar la lista de pagos recurrentes.', 'alert-danger');
}

$historial_pagos = [];
try {
    $sql_hist_list = "SELECT ph.*, pr.descripcion_pago as desc_pago_recurrente, u.nombre_usuario as usuario_gestor_nombre
                      FROM pagos_historial ph
                      LEFT JOIN pagos_recurrentes pr ON ph.id_pago_recurrente = pr.id_pago_recurrente
                      LEFT JOIN usuarios u ON ph.id_usuario_gestor_pago = u.id_usuario
                      ORDER BY ph.fecha_efectiva_pago DESC, ph.id_historial_pago DESC
                      LIMIT 20";
    $stmt_hist_list = $pdo->query($sql_hist_list);
    $historial_pagos = $stmt_hist_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar historial de pagos: " . $e->getMessage());
    mensaje_flash('error_gestor_pagos', 'Error al cargar el historial de pagos efectuados.', 'alert-danger');
}

// Comentario: Fin de logica/pagos_gestor_logica.php
?>
