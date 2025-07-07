<?php
// Archivo: vistas/archivo_prestamos.php
// Propósito: (Archivo) Módulo para gestionar el préstamo de expedientes físicos.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('GESTIONAR_PRESTAMOS_ARCHIVO', $id_usuario_actual)) {
    mensaje_flash('error_archivo_prestamos', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para filtros y formularios.
$filtro_estado_prestamo = $_GET['filtro_estado_prestamo'] ?? 'solicitado'; // 'solicitado', 'aprobado', 'entregado', 'devuelto', 'vencido', 'todos'
$filtro_id_expediente_prestamo = $_GET['filtro_id_expediente'] ?? '';
$filtro_id_solicitante_prestamo = $_GET['filtro_id_solicitante'] ?? '';

// Comentario: Cargar expedientes (para select de nuevo préstamo) y usuarios (para solicitante).
$expedientes_disponibles_prestamo = []; // Comentario: Solo los que están 'en_archivo'.
$usuarios_solicitantes_prestamo = [];
try {
    $stmt_exp_disp = $pdo->query("SELECT id_expediente, codigo_expediente, nombre_expediente FROM archivo_expedientes WHERE estado_expediente = 'en_archivo' ORDER BY codigo_expediente ASC");
    $expedientes_disponibles_prestamo = $stmt_exp_disp->fetchAll(PDO::FETCH_ASSOC);

    $stmt_usr_sol = $pdo->query("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM usuarios WHERE estado = 'activo' ORDER BY apellidos, nombres ASC");
    $usuarios_solicitantes_prestamo = $stmt_usr_sol->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Comentario: Manejar error. */ }


// Comentario: Procesar NUEVO PRÉSTAMO (Solicitud de préstamo).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_solicitud_prestamo'])) {
    $id_expediente_sol_p = filter_input(INPUT_POST, 'id_expediente_prestamo', FILTER_VALIDATE_INT);
    $id_usuario_solicitante_p = filter_input(INPUT_POST, 'id_usuario_solicitante_prestamo', FILTER_VALIDATE_INT);
    $motivo_prestamo_p = strip_tags($_POST['motivo_prestamo'] ?? '');
    $fecha_devolucion_estimada_p = sanitizar_entrada($_POST['fecha_devolucion_estimada'] ?? ''); // Comentario: Validar formato YYYY-MM-DD.

    $errores_form_prestamo = [];
    if (empty($id_expediente_sol_p)) $errores_form_prestamo[] = "Debe seleccionar un expediente.";
    if (empty($id_usuario_solicitante_p)) $errores_form_prestamo[] = "Debe seleccionar un usuario solicitante.";
    if (empty($motivo_prestamo_p)) $errores_form_prestamo[] = "El motivo del préstamo es obligatorio.";
    if (empty($fecha_devolucion_estimada_p) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_devolucion_estimada_p)) {
        $errores_form_prestamo[] = "La fecha de devolución estimada no es válida.";
    } elseif (new DateTime($fecha_devolucion_estimada_p) < new DateTime(date('Y-m-d'))) {
        $errores_form_prestamo[] = "La fecha de devolución estimada no puede ser en el pasado.";
    }

    // Comentario: Verificar que el expediente esté disponible.
    if (empty($errores_form_prestamo) && $id_expediente_sol_p) {
        $stmt_chk_exp = $pdo->prepare("SELECT estado_expediente FROM archivo_expedientes WHERE id_expediente = :id_e_chk");
        $stmt_chk_exp->bindParam(':id_e_chk', $id_expediente_sol_p, PDO::PARAM_INT);
        $stmt_chk_exp->execute();
        $estado_exp_actual = $stmt_chk_exp->fetchColumn();
        if ($estado_exp_actual !== 'en_archivo') {
            $errores_form_prestamo[] = "El expediente seleccionado no está disponible para préstamo (estado actual: " . ucfirst(str_replace('_',' ',$estado_exp_actual)) . ").";
        }
    }

    if (empty($errores_form_prestamo)) {
        $pdo->beginTransaction();
        try {
            // Comentario: 1. Insertar en archivo_prestamos.
            $sql_insert_prestamo = "INSERT INTO archivo_prestamos (id_expediente, id_usuario_solicitante, motivo_prestamo, fecha_devolucion_estimada, estado_prestamo, id_usuario_aprueba_prestamo, fecha_solicitud_prestamo)
                                    VALUES (:id_e, :id_us, :motivo, :fde, 'solicitado', :id_archivero, NOW())"; // Comentario: id_usuario_aprueba_prestamo es quien registra la solicitud.
            $stmt_insert_prestamo = $pdo->prepare($sql_insert_prestamo);
            $stmt_insert_prestamo->execute([
                ':id_e' => $id_expediente_sol_p, ':id_us' => $id_usuario_solicitante_p, ':motivo' => $motivo_prestamo_p,
                ':fde' => $fecha_devolucion_estimada_p, ':id_archivero' => $id_usuario_actual // Comentario: Quien registra la solicitud (Archivero).
            ]);

            // Comentario: 2. Actualizar estado del expediente a 'solicitud_prestamo'.
            $sql_upd_exp = "UPDATE archivo_expedientes SET estado_expediente = 'solicitud_prestamo' WHERE id_expediente = :id_e_upd";
            $stmt_upd_exp = $pdo->prepare($sql_upd_exp);
            $stmt_upd_exp->bindParam(':id_e_upd', $id_expediente_sol_p, PDO::PARAM_INT);
            $stmt_upd_exp->execute();

            $pdo->commit();
            mensaje_flash('exito_archivo_prestamos', 'Solicitud de préstamo registrada exitosamente.', 'alert-success');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al registrar solicitud de préstamo: " . $e->getMessage());
            mensaje_flash('error_form_prestamo_reg', 'Error al registrar la solicitud: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=archivo_prestamos&filtro_estado_prestamo=solicitado');
    } else {
         foreach ($errores_form_prestamo as $err_p) {
            mensaje_flash('error_form_prestamo_reg', $err_p, 'alert-danger');
        }
    }
}

// Comentario: Procesar ACCIONES sobre préstamos (aprobar, entregar, registrar devolución).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_sobre_prestamo'])) {
    $id_prestamo_accion = filter_input(INPUT_POST, 'id_prestamo_accionable', FILTER_VALIDATE_INT);
    $accion_prestamo = $_POST['accion_sobre_prestamo']; // 'aprobar_prestamo', 'registrar_entrega', 'registrar_devolucion'
    $observaciones_accion_prestamo = strip_tags($_POST['observaciones_accion_prestamo'] ?? '');

    if ($id_prestamo_accion) {
        $pdo->beginTransaction();
        try {
            $prestamo_actual = $pdo->prepare("SELECT * FROM archivo_prestamos WHERE id_prestamo = ?");
            $prestamo_actual->execute([$id_prestamo_accion]);
            $datos_prestamo_actual = $prestamo_actual->fetch(PDO::FETCH_ASSOC);

            if (!$datos_prestamo_actual) throw new Exception("Préstamo no encontrado.");

            $id_expediente_afectado = $datos_prestamo_actual['id_expediente'];
            $nuevo_estado_prestamo = $datos_prestamo_actual['estado_prestamo'];
            $nuevo_estado_expediente = null; // Comentario: Para actualizar la tabla archivo_expedientes.
            $mensaje_flash_accion = "";

            switch ($accion_prestamo) {
                case 'aprobar_prestamo':
                    if ($datos_prestamo_actual['estado_prestamo'] !== 'solicitado') throw new Exception("Solo se pueden aprobar préstamos solicitados.");
                    $nuevo_estado_prestamo = 'aprobado';
                    $sql_upd_prestamo = "UPDATE archivo_prestamos SET estado_prestamo = :nep, fecha_aprobacion_prestamo = NOW(), id_usuario_aprueba_prestamo = :id_ua, observaciones_prestamo = CONCAT(IFNULL(observaciones_prestamo,''), '\nAprobado: ', :obs) WHERE id_prestamo = :idp";
                    $nuevo_estado_expediente = 'en_archivo'; // Comentario: Sigue en archivo hasta que se entrega. O 'prestamo_aprobado'.
                    $mensaje_flash_accion = "Préstamo ID $id_prestamo_accion aprobado.";
                    break;
                case 'registrar_entrega':
                    if ($datos_prestamo_actual['estado_prestamo'] !== 'aprobado') throw new Exception("Solo se pueden entregar préstamos aprobados.");
                    $nuevo_estado_prestamo = 'entregado';
                    $sql_upd_prestamo = "UPDATE archivo_prestamos SET estado_prestamo = :nep, fecha_entrega_prestamo = NOW(), observaciones_prestamo = CONCAT(IFNULL(observaciones_prestamo,''), '\nEntregado: ', :obs) WHERE id_prestamo = :idp";
                    $nuevo_estado_expediente = 'prestado';
                    $mensaje_flash_accion = "Entrega del préstamo ID $id_prestamo_accion registrada.";
                    break;
                case 'registrar_devolucion':
                     if ($datos_prestamo_actual['estado_prestamo'] !== 'entregado' && $datos_prestamo_actual['estado_prestamo'] !== 'vencido') throw new Exception("Solo se pueden registrar devoluciones de préstamos entregados o vencidos.");
                    $nuevo_estado_prestamo = 'devuelto';
                    $sql_upd_prestamo = "UPDATE archivo_prestamos SET estado_prestamo = :nep, fecha_devolucion_real = NOW(), observaciones_devolucion = :obs WHERE id_prestamo = :idp";
                    $nuevo_estado_expediente = 'en_archivo'; // Comentario: Vuelve a estar disponible.
                    // Comentario: Si hubo observaciones en la devolución, el estado del expediente podría ser 'devuelto_con_observacion'.
                    if(!empty($observaciones_accion_prestamo)) $nuevo_estado_expediente = 'devuelto_con_observacion';
                    $mensaje_flash_accion = "Devolución del préstamo ID $id_prestamo_accion registrada.";
                    break;
                default: throw new Exception("Acción desconocida sobre el préstamo.");
            }

            $stmt_upd_prestamo_accion = $pdo->prepare($sql_upd_prestamo);
            $params_upd_prestamo = [':nep' => $nuevo_estado_prestamo, ':obs' => $observaciones_accion_prestamo, ':idp' => $id_prestamo_accion];
            if ($accion_prestamo === 'aprobar_prestamo') $params_upd_prestamo[':id_ua'] = $id_usuario_actual;
            $stmt_upd_prestamo_accion->execute($params_upd_prestamo);

            if ($nuevo_estado_expediente) {
                $stmt_upd_exp_accion = $pdo->prepare("UPDATE archivo_expedientes SET estado_expediente = :nee WHERE id_expediente = :ide");
                $stmt_upd_exp_accion->execute([':nee' => $nuevo_estado_expediente, ':ide' => $id_expediente_afectado]);
            }

            $pdo->commit();
            mensaje_flash('exito_archivo_prestamos', $mensaje_flash_accion, 'alert-success');

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en acción sobre préstamo ID $id_prestamo_accion: " . $e->getMessage());
            mensaje_flash('error_accion_prestamo', 'Error al procesar la acción: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=archivo_prestamos&filtro_estado_prestamo=' . $filtro_estado_prestamo);
    } else {
        mensaje_flash('error_accion_prestamo', 'ID de préstamo no válido para la acción.', 'alert-danger');
        redirigir('index.php?vista=archivo_prestamos');
    }
}


// Comentario: Cargar lista de préstamos con filtros.
$lista_prestamos = [];
$condiciones_lp = [];
$params_lp = [];

if ($filtro_estado_prestamo !== 'todos') {
    $condiciones_lp[] = "ap.estado_prestamo = :estado_p";
    $params_lp[':estado_p'] = $filtro_estado_prestamo;
}
if (!empty($filtro_id_expediente_prestamo) && filter_var($filtro_id_expediente_prestamo, FILTER_VALIDATE_INT)) {
    $condiciones_lp[] = "ap.id_expediente = :id_e_p";
    $params_lp[':id_e_p'] = $filtro_id_expediente_prestamo;
}
if (!empty($filtro_id_solicitante_prestamo) && filter_var($filtro_id_solicitante_prestamo, FILTER_VALIDATE_INT)) {
    $condiciones_lp[] = "ap.id_usuario_solicitante = :id_us_p";
    $params_lp[':id_us_p'] = $filtro_id_solicitante_prestamo;
}
$where_clause_lp = empty($condiciones_lp) ? '' : 'WHERE ' . implode(' AND ', $condiciones_lp);

try {
    $sql_lp = "SELECT ap.*, ae.codigo_expediente, ae.nombre_expediente,
                      CONCAT(us.apellidos, ', ', us.nombres) as nombre_solicitante,
                      CONCAT(ua.apellidos, ', ', ua.nombres) as nombre_archivero_aprueba
               FROM archivo_prestamos ap
               JOIN archivo_expedientes ae ON ap.id_expediente = ae.id_expediente
               JOIN usuarios us ON ap.id_usuario_solicitante = us.id_usuario
               LEFT JOIN usuarios ua ON ap.id_usuario_aprueba_prestamo = ua.id_usuario
               $where_clause_lp
               ORDER BY ap.fecha_solicitud_prestamo DESC"; // Comentario: O por estado, fecha estimada, etc.
    $stmt_lp = $pdo->prepare($sql_lp);
    $stmt_lp->execute($params_lp);
    $lista_prestamos = $stmt_lp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al listar préstamos de archivo: " . $e->getMessage());
    mensaje_flash('error_archivo_prestamos', 'Error al cargar la lista de préstamos.', 'alert-danger');
}


?>
<h2>Gestión de Préstamos de Expedientes de Archivo</h2>

<?php
mensaje_flash('error_archivo_prestamos');
mensaje_flash('exito_archivo_prestamos');
mensaje_flash('error_form_prestamo_reg');
mensaje_flash('error_accion_prestamo');
?>

<div class="card-sigi mb-3">
    <h3>Registrar Nueva Solicitud de Préstamo</h3>
    <form action="index.php?vista=archivo_prestamos" method="POST" class="validar-js form-prestamo">
        <div class="grid-col-2">
            <div class="grupo-formulario">
                <label for="id_expediente_prestamo">Expediente a Prestar:</label>
                <select id="id_expediente_prestamo" name="id_expediente_prestamo" required>
                    <option value="">-- Seleccione un expediente disponible --</option>
                    <?php foreach($expedientes_disponibles_prestamo as $exp_disp): ?>
                        <option value="<?php echo $exp_disp['id_expediente']; ?>">
                            <?php echo htmlspecialchars($exp_disp['codigo_expediente'] . ' - ' . $exp_disp['nombre_expediente'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                 <?php if(empty($expedientes_disponibles_prestamo)): ?><small class="text-warning">No hay expedientes actualmente en estado "En Archivo" para prestar.</small><?php endif; ?>
            </div>
            <div class="grupo-formulario">
                <label for="id_usuario_solicitante_prestamo">Usuario Solicitante:</label>
                <select id="id_usuario_solicitante_prestamo" name="id_usuario_solicitante_prestamo" required>
                     <option value="">-- Seleccione un usuario --</option>
                    <?php foreach($usuarios_solicitantes_prestamo as $usr_sol): ?>
                        <option value="<?php echo $usr_sol['id_usuario']; ?>">
                            <?php echo htmlspecialchars($usr_sol['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grupo-formulario">
            <label for="motivo_prestamo">Motivo del Préstamo:</label>
            <textarea id="motivo_prestamo" name="motivo_prestamo" rows="2" required></textarea>
        </div>
        <div class="grupo-formulario">
            <label for="fecha_devolucion_estimada">Fecha Estimada de Devolución:</label>
            <input type="date" id="fecha_devolucion_estimada" name="fecha_devolucion_estimada" required min="<?php echo date('Y-m-d'); ?>">
        </div>
        <button type="submit" name="registrar_solicitud_prestamo" class="boton boton-primario">Registrar Solicitud</button>
    </form>
</div>


<div class="card-sigi">
    <h3>Listado de Préstamos</h3>
    <form action="index.php" method="GET" class="form-filtros mb-2">
        <input type="hidden" name="vista" value="archivo_prestamos">
        <label for="filtro_estado_prestamo">Estado:</label>
        <select name="filtro_estado_prestamo" id="filtro_estado_prestamo" onchange="this.form.submit()">
            <?php $estados_p_filtro = ['todos', 'solicitado', 'aprobado', 'entregado', 'devuelto', 'vencido', 'cancelado']; ?>
            <?php foreach($estados_p_filtro as $epf): ?>
            <option value="<?php echo $epf; ?>" <?php if($filtro_estado_prestamo == $epf) echo 'selected'; ?>><?php echo ucfirst($epf); ?></option>
            <?php endforeach; ?>
        </select>
        <!-- Comentario: Añadir filtros por ID Expediente y ID Solicitante si es necesario. -->
        <button type="submit" class="boton boton-secundario btn-sm">Filtrar</button>
         <a href="index.php?vista=archivo_prestamos" class="boton boton-info btn-sm">Limpiar Filtros</a>
    </form>

    <?php if(empty($lista_prestamos)): ?>
        <p>No hay préstamos que coincidan con los filtros seleccionados.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID Prést.</th>
                    <th>Expediente (Cód - Nombre)</th>
                    <th>Solicitante</th>
                    <th>Fecha Sol.</th>
                    <th>Fecha Dev. Estimada</th>
                    <th>Estado</th>
                    <th>Acciones / Obs.</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($lista_prestamos as $lp): ?>
                <tr>
                    <td><?php echo $lp['id_prestamo']; ?></td>
                    <td><?php echo htmlspecialchars($lp['codigo_expediente'] . ' - ' . $lp['nombre_expediente'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($lp['nombre_solicitante'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($lp['fecha_solicitud_prestamo'])); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($lp['fecha_devolucion_estimada'])); ?></td>
                    <td><span class="estado-prestamo estado-p-<?php echo $lp['estado_prestamo']; ?>"><?php echo ucfirst($lp['estado_prestamo']); ?></span></td>
                    <td>
                        <form action="index.php?vista=archivo_prestamos&filtro_estado_prestamo=<?php echo $filtro_estado_prestamo; ?>" method="POST" class="form-accion-bandeja">
                            <input type="hidden" name="id_prestamo_accionable" value="<?php echo $lp['id_prestamo']; ?>">
                            <?php if ($lp['estado_prestamo'] === 'solicitado'): ?>
                                <textarea name="observaciones_accion_prestamo" rows="1" placeholder="Obs. Aprobación (opcional)"></textarea>
                                <button type="submit" name="accion_sobre_prestamo" value="aprobar_prestamo" class="boton-tabla exito btn-sm">Aprobar</button>
                            <?php elseif ($lp['estado_prestamo'] === 'aprobado'): ?>
                                <textarea name="observaciones_accion_prestamo" rows="1" placeholder="Obs. Entrega (opcional)"></textarea>
                                <button type="submit" name="accion_sobre_prestamo" value="registrar_entrega" class="boton-tabla info btn-sm">Registrar Entrega</button>
                            <?php elseif ($lp['estado_prestamo'] === 'entregado' || $lp['estado_prestamo'] === 'vencido'): ?>
                                <textarea name="observaciones_accion_prestamo" rows="1" placeholder="Obs. Devolución (ej. estado del exp.)"></textarea>
                                <button type="submit" name="accion_sobre_prestamo" value="registrar_devolucion" class="boton-tabla exito btn-sm">Registrar Devolución</button>
                            <?php else: ?>
                                <small class="text-muted">
                                    <?php if($lp['fecha_aprobacion_prestamo']) echo 'Aprob: '.date('d/m/y',strtotime($lp['fecha_aprobacion_prestamo'])).' por ' . ($lp['nombre_archivero_aprueba'] ?? 'Sistema').'. '; ?>
                                    <?php if($lp['fecha_entrega_prestamo']) echo 'Entreg: '.date('d/m/y',strtotime($lp['fecha_entrega_prestamo'])).'. '; ?>
                                    <?php if($lp['fecha_devolucion_real']) echo 'Dev: '.date('d/m/y',strtotime($lp['fecha_devolucion_real'])).'. '; ?>
                                    <?php if($lp['observaciones_prestamo']) echo 'Obs. Prést: '.htmlspecialchars(mb_substr($lp['observaciones_prestamo'],0,30).'...', ENT_QUOTES, 'UTF-8').'. '; ?>
                                    <?php if($lp['observaciones_devolucion']) echo 'Obs. Dev: '.htmlspecialchars(mb_substr($lp['observaciones_devolucion'],0,30).'...', ENT_QUOTES, 'UTF-8').'. '; ?>
                                </small>
                            <?php endif; ?>
                             <!-- Comentario: Botón para ver detalle completo del préstamo en un modal -->
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
.form-prestamo .grid-col-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.estado-prestamo { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-p-solicitado { background-color: var(--color-advertencia); color: #333;}
.estado-p-aprobado { background-color: var(--color-info); color: #333;}
.estado-p-entregado { background-color: var(--color-primario); }
.estado-p-devuelto { background-color: var(--color-exito); }
.estado-p-vencido { background-color: var(--color-error); }
.estado-p-cancelado { background-color: var(--color-secundario); }
.form-accion-bandeja textarea { width: 98%; font-size: 0.85em; padding: 2px; margin-bottom:3px; }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875em; }
.text-warning { color: #ffc107; }
</style>

<?php
// Comentario: Fin del archivo vistas/archivo_prestamos.php
?>
