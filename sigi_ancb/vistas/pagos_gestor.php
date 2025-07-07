<?php
// Archivo: vistas/pagos_gestor.php
// Propósito: (Dir. Admin) Módulo para gestionar pagos recurrentes y ver alertas de próximos pagos.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('GESTIONAR_PAGOS', $id_usuario_actual)) {
    mensaje_flash('error_gestor_pagos', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para filtros y formulario.
$filtro_estado_recurrente = $_GET['filtro_estado_recurrente'] ?? 'activo'; // 'activo', 'inactivo', 'todos'
$filtro_proveedor = $_GET['filtro_proveedor'] ?? '';

// Comentario: Procesamiento de nuevo pago recurrente.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_pago_recurrente'])) {
    $descripcion_pago = sanitizar_entrada($_POST['descripcion_pago_rec'] ?? '');
    $proveedor_pago = sanitizar_entrada($_POST['proveedor_pago_rec'] ?? '');
    $monto_pago = filter_var($_POST['monto_pago_rec'] ?? 0, FILTER_VALIDATE_FLOAT);
    $moneda_pago = sanitizar_entrada($_POST['moneda_pago_rec'] ?? 'BOB');
    $frecuencia_pago = sanitizar_entrada($_POST['frecuencia_pago_rec'] ?? 'mensual');
    $dia_pago_estimado = filter_var($_POST['dia_pago_estimado_rec'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
    $fecha_proximo_pago = sanitizar_entrada($_POST['fecha_proximo_pago_rec'] ?? ''); // Validar formato YYYY-MM-DD
    $estado_pago_rec = sanitizar_entrada($_POST['estado_pago_rec'] ?? 'activo');
    $observaciones_pago_rec = strip_tags($_POST['observaciones_pago_rec'] ?? '');

    $errores_form_rec = [];
    if (empty($descripcion_pago)) $errores_form_rec[] = "La descripción del pago es obligatoria.";
    if ($monto_pago === false || $monto_pago <= 0) $errores_form_rec[] = "El monto del pago no es válido.";
    if (empty($fecha_proximo_pago) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_proximo_pago)) {
        $errores_form_rec[] = "La fecha del próximo pago no es válida.";
    }
    // Comentario: Más validaciones...

    if (empty($errores_form_rec)) {
        try {
            $sql_insert_rec = "INSERT INTO pagos_recurrentes
                                (descripcion_pago, proveedor, monto_pago, moneda, frecuencia_pago, dia_pago_estimado, fecha_proximo_pago, estado, observaciones, id_usuario_registra)
                                VALUES (:desc, :prov, :monto, :moneda, :frec, :dia, :fecha_prox, :est, :obs, :id_user)";
            $stmt_insert_rec = $pdo->prepare($sql_insert_rec);
            $stmt_insert_rec->execute([
                ':desc' => $descripcion_pago, ':prov' => $proveedor_pago, ':monto' => $monto_pago, ':moneda' => $moneda_pago,
                ':frec' => $frecuencia_pago, ':dia' => $dia_pago_estimado, ':fecha_prox' => $fecha_proximo_pago,
                ':est' => $estado_pago_rec, ':obs' => $observaciones_pago_rec, ':id_user' => $id_usuario_actual
            ]);
            mensaje_flash('exito_gestor_pagos', 'Nuevo pago recurrente guardado exitosamente.', 'alert-success');
        } catch (PDOException $e) {
            error_log("Error al guardar pago recurrente: " . $e->getMessage());
            mensaje_flash('error_form_pago_rec', 'Error al guardar el pago recurrente: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=pagos_gestor'); // Comentario: Recargar para limpiar POST.
    } else {
        foreach ($errores_form_rec as $err) {
            mensaje_flash('error_form_pago_rec', $err, 'alert-danger');
        }
    }
}

// Comentario: Procesamiento de registro de pago efectuado.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago_efectuado'])) {
    $id_pago_recurrente_hist = filter_input(INPUT_POST, 'id_pago_recurrente_hist', FILTER_VALIDATE_INT); // Comentario: Puede ser null si es pago único.
    $descripcion_pago_efectuado = sanitizar_entrada($_POST['descripcion_pago_efectuado'] ?? '');
    $monto_efectivamente_pagado = filter_var($_POST['monto_efectivamente_pagado'] ?? 0, FILTER_VALIDATE_FLOAT);
    $fecha_efectiva_pago = sanitizar_entrada($_POST['fecha_efectiva_pago'] ?? date('Y-m-d'));
    $metodo_pago = sanitizar_entrada($_POST['metodo_pago'] ?? '');
    $referencia_comprobante = sanitizar_entrada($_POST['referencia_comprobante'] ?? '');
    $observaciones_pago_efectuado = strip_tags($_POST['observaciones_pago_efectuado'] ?? '');

    $errores_form_hist = [];
    if (empty($descripcion_pago_efectuado)) $errores_form_hist[] = "La descripción del pago efectuado es obligatoria.";
    if ($monto_efectivamente_pagado === false || $monto_efectivamente_pagado <= 0) $errores_form_hist[] = "El monto pagado no es válido.";
    if (empty($fecha_efectiva_pago) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $fecha_efectiva_pago)) {
        $errores_form_hist[] = "La fecha de pago no es válida.";
    }
    // Comentario: Más validaciones...

    if (empty($errores_form_hist)) {
        try {
            $sql_insert_hist = "INSERT INTO pagos_historial
                                (id_pago_recurrente, descripcion_pago_efectuado, monto_efectivamente_pagado, fecha_efectiva_pago, metodo_pago, referencia_comprobante, observaciones, id_usuario_gestor_pago)
                                VALUES (:id_rec, :desc_efec, :monto_efec, :fecha_efec, :metodo, :ref, :obs, :id_user)";
            $stmt_insert_hist = $pdo->prepare($sql_insert_hist);
            $stmt_insert_hist->execute([
                ':id_rec' => $id_pago_recurrente_hist ?: null, // Comentario: Permitir NULL.
                ':desc_efec' => $descripcion_pago_efectuado,
                ':monto_efec' => $monto_efectivamente_pagado,
                ':fecha_efec' => $fecha_efectiva_pago,
                ':metodo' => $metodo_pago,
                ':ref' => $referencia_comprobante,
                ':obs' => $observaciones_pago_efectuado,
                ':id_user' => $id_usuario_actual
            ]);

            // Comentario: Si está vinculado a un pago recurrente, actualizar fecha_proximo_pago del recurrente.
            if ($id_pago_recurrente_hist) {
                // Comentario: Lógica para calcular la nueva fecha_proximo_pago basada en la frecuencia.
                // Comentario: Esto es un ejemplo simple, puede ser más complejo.
                $stmt_freq = $pdo->prepare("SELECT frecuencia_pago, fecha_proximo_pago FROM pagos_recurrentes WHERE id_pago_recurrente = :id_rec_freq");
                $stmt_freq->bindParam(':id_rec_freq', $id_pago_recurrente_hist, PDO::PARAM_INT);
                $stmt_freq->execute();
                $pago_rec_info = $stmt_freq->fetch(PDO::FETCH_ASSOC);

                if ($pago_rec_info) {
                    $nueva_fecha_prox_pago = date('Y-m-d', strtotime($pago_rec_info['fecha_proximo_pago'] . ' +1 ' . str_replace(['mensual','bimestral','trimestral','semestral','anual'],['month','2 months','3 months','6 months','year'],$pago_rec_info['frecuencia_pago']) ));
                    if ($pago_rec_info['frecuencia_pago'] === 'unico') { // Comentario: Si es único, se puede marcar como finalizado.
                         $pdo->prepare("UPDATE pagos_recurrentes SET estado = 'finalizado' WHERE id_pago_recurrente = ?")->execute([$id_pago_recurrente_hist]);
                    } else {
                         $pdo->prepare("UPDATE pagos_recurrentes SET fecha_proximo_pago = ? WHERE id_pago_recurrente = ?")->execute([$nueva_fecha_prox_pago, $id_pago_recurrente_hist]);
                    }
                }
            }
            mensaje_flash('exito_gestor_pagos', 'Pago efectuado registrado exitosamente.', 'alert-success');
        } catch (PDOException $e) {
            error_log("Error al registrar pago efectuado: " . $e->getMessage());
            mensaje_flash('error_form_pago_hist', 'Error al registrar el pago efectuado: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=pagos_gestor');
    } else {
         foreach ($errores_form_hist as $err) {
            mensaje_flash('error_form_pago_hist', $err, 'alert-danger');
        }
    }
}


// Comentario: Cargar pagos recurrentes (con filtros).
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
                JOIN usuarios u ON pr.id_usuario_registra = u.id_usuario
                $where_clause_rec
                ORDER BY pr.fecha_proximo_pago ASC, pr.descripcion_pago ASC";
    $stmt_rec = $pdo->prepare($sql_rec);
    $stmt_rec->execute($params_rec);
    $pagos_recurrentes = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
     error_log("Error al cargar pagos recurrentes: " . $e->getMessage());
    mensaje_flash('error_gestor_pagos', 'Error al cargar la lista de pagos recurrentes.', 'alert-danger');
}

// Comentario: Cargar historial de pagos (últimos N o con paginación).
$historial_pagos = [];
try {
    $sql_hist_list = "SELECT ph.*, pr.descripcion_pago as desc_pago_recurrente, u.nombre_usuario as usuario_gestor_nombre
                      FROM pagos_historial ph
                      LEFT JOIN pagos_recurrentes pr ON ph.id_pago_recurrente = pr.id_pago_recurrente
                      JOIN usuarios u ON ph.id_usuario_gestor_pago = u.id_usuario
                      ORDER BY ph.fecha_efectiva_pago DESC, ph.id_historial_pago DESC
                      LIMIT 20"; // Comentario: Mostrar los últimos 20.
    $stmt_hist_list = $pdo->query($sql_hist_list);
    $historial_pagos = $stmt_hist_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar historial de pagos: " . $e->getMessage());
    mensaje_flash('error_gestor_pagos', 'Error al cargar el historial de pagos efectuados.', 'alert-danger');
}

?>
<h2>Gestor de Pagos</h2>

<?php
mensaje_flash('error_gestor_pagos');
mensaje_flash('exito_gestor_pagos');
?>

<div class="gestor-pagos-grid">
    <section id="nuevo-pago-recurrente" class="card-sigi">
        <h3>Registrar Nuevo Pago Recurrente</h3>
        <?php mensaje_flash('error_form_pago_rec'); ?>
        <form action="index.php?vista=pagos_gestor" method="POST">
            <div class="grupo-formulario">
                <label for="descripcion_pago_rec">Descripción del Pago:</label>
                <input type="text" id="descripcion_pago_rec" name="descripcion_pago_rec" required>
            </div>
            <div class="grupo-formulario">
                <label for="proveedor_pago_rec">Proveedor:</label>
                <input type="text" id="proveedor_pago_rec" name="proveedor_pago_rec">
            </div>
            <div class="grupo-formulario">
                <label for="monto_pago_rec">Monto:</label>
                <input type="number" id="monto_pago_rec" name="monto_pago_rec" step="0.01" required>
            </div>
            <div class="grupo-formulario">
                <label for="moneda_pago_rec">Moneda:</label>
                <select id="moneda_pago_rec" name="moneda_pago_rec">
                    <option value="BOB">BOB</option>
                    <option value="USD">USD</option>
                </select>
            </div>
            <div class="grupo-formulario">
                <label for="frecuencia_pago_rec">Frecuencia:</label>
                <select id="frecuencia_pago_rec" name="frecuencia_pago_rec">
                    <option value="mensual">Mensual</option>
                    <option value="bimestral">Bimestral</option>
                    <option value="trimestral">Trimestral</option>
                    <option value="semestral">Semestral</option>
                    <option value="anual">Anual</option>
                    <option value="unico">Único</option>
                </select>
            </div>
            <div class="grupo-formulario">
                <label for="dia_pago_estimado_rec">Día Estimado de Pago (1-31, si aplica):</label>
                <input type="number" id="dia_pago_estimado_rec" name="dia_pago_estimado_rec" min="1" max="31">
            </div>
            <div class="grupo-formulario">
                <label for="fecha_proximo_pago_rec">Fecha Próximo Pago:</label>
                <input type="date" id="fecha_proximo_pago_rec" name="fecha_proximo_pago_rec" required>
            </div>
             <div class="grupo-formulario">
                <label for="estado_pago_rec">Estado:</label>
                <select id="estado_pago_rec" name="estado_pago_rec">
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                    <option value="finalizado">Finalizado</option>
                </select>
            </div>
            <div class="grupo-formulario">
                <label for="observaciones_pago_rec">Observaciones:</label>
                <textarea id="observaciones_pago_rec" name="observaciones_pago_rec" rows="2"></textarea>
            </div>
            <button type="submit" name="guardar_pago_recurrente" class="boton boton-primario">Guardar Pago Recurrente</button>
        </form>
    </section>

    <section id="registrar-pago-efectuado" class="card-sigi">
        <h3>Registrar Pago Efectuado</h3>
        <?php mensaje_flash('error_form_pago_hist'); ?>
        <form action="index.php?vista=pagos_gestor" method="POST">
            <div class="grupo-formulario">
                <label for="id_pago_recurrente_hist">Vinculado a Pago Recurrente (Opcional):</label>
                <select id="id_pago_recurrente_hist" name="id_pago_recurrente_hist">
                    <option value="">-- Ninguno (Pago Único/No Recurrente) --</option>
                    <?php foreach ($pagos_recurrentes as $pr_opt): if($pr_opt['estado'] === 'activo'): ?>
                        <option value="<?php echo $pr_opt['id_pago_recurrente']; ?>">
                            <?php echo htmlspecialchars($pr_opt['descripcion_pago'] . ' (Próx: ' . date('d/m/Y', strtotime($pr_opt['fecha_proximo_pago'])) . ')', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div class="grupo-formulario">
                <label for="descripcion_pago_efectuado">Descripción del Pago Efectuado:</label>
                <input type="text" id="descripcion_pago_efectuado" name="descripcion_pago_efectuado" required>
            </div>
            <div class="grupo-formulario">
                <label for="monto_efectivamente_pagado">Monto Pagado:</label>
                <input type="number" id="monto_efectivamente_pagado" name="monto_efectivamente_pagado" step="0.01" required>
            </div>
            <div class="grupo-formulario">
                <label for="fecha_efectiva_pago">Fecha Efectiva de Pago:</label>
                <input type="date" id="fecha_efectiva_pago" name="fecha_efectiva_pago" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="grupo-formulario">
                <label for="metodo_pago">Método de Pago:</label>
                <input type="text" id="metodo_pago" name="metodo_pago" placeholder="Ej: Transferencia, Cheque Nro X, Efectivo">
            </div>
            <div class="grupo-formulario">
                <label for="referencia_comprobante">Referencia Comprobante:</label>
                <input type="text" id="referencia_comprobante" name="referencia_comprobante" placeholder="Ej: Factura Nro Y, Recibo Z">
            </div>
            <div class="grupo-formulario">
                <label for="observaciones_pago_efectuado">Observaciones:</label>
                <textarea id="observaciones_pago_efectuado" name="observaciones_pago_efectuado" rows="2"></textarea>
            </div>
            <button type="submit" name="registrar_pago_efectuado" class="boton boton-exito">Registrar Pago</button>
        </form>
    </section>
</div>

<section id="lista-pagos-recurrentes" class="mt-3 card-sigi">
    <h3>Lista de Pagos Recurrentes y Alertas</h3>
    <form action="index.php" method="GET" class="form-filtros mb-2">
        <input type="hidden" name="vista" value="pagos_gestor">
        <label for="filtro_estado_recurrente">Estado:</label>
        <select name="filtro_estado_recurrente" id="filtro_estado_recurrente" onchange="this.form.submit()">
            <option value="activo" <?php echo ($filtro_estado_recurrente === 'activo') ? 'selected' : ''; ?>>Activos</option>
            <option value="inactivo" <?php echo ($filtro_estado_recurrente === 'inactivo') ? 'selected' : ''; ?>>Inactivos</option>
            <option value="finalizado" <?php echo ($filtro_estado_recurrente === 'finalizado') ? 'selected' : ''; ?>>Finalizados</option>
            <option value="todos" <?php echo ($filtro_estado_recurrente === 'todos') ? 'selected' : ''; ?>>Todos</option>
        </select>
        <label for="filtro_proveedor">Proveedor:</label>
        <input type="text" name="filtro_proveedor" id="filtro_proveedor" value="<?php echo htmlspecialchars($filtro_proveedor, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="boton boton-secundario btn-sm">Filtrar</button>
    </form>

    <?php if (empty($pagos_recurrentes)): ?>
        <p>No hay pagos recurrentes que coincidan con los filtros.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Proveedor</th>
                    <th>Monto</th>
                    <th>Frecuencia</th>
                    <th>Próximo Pago</th>
                    <th>Estado</th>
                    <th>Obs.</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pagos_recurrentes as $pr):
                    $alerta_proximidad = '';
                    if ($pr['estado'] === 'activo' && $pr['fecha_proximo_pago']) {
                        $fecha_prox = new DateTime($pr['fecha_proximo_pago']);
                        $hoy = new DateTime();
                        $intervalo_prox = $hoy->diff($fecha_prox);
                        if (!$intervalo_prox->invert && $intervalo_prox->days <= 7) { // Comentario: Menos de 7 días o ya pasó.
                            $alerta_proximidad = 'alerta-proximo'; // Comentario: Rojo si está muy cerca o pasado.
                            if ($intervalo_prox->days <= 0 && !$intervalo_prox->invert) $alerta_proximidad = 'alerta-vencido';
                        } elseif (!$intervalo_prox->invert && $intervalo_prox->days <= 15) {
                             $alerta_proximidad = 'alerta-moderada'; // Comentario: Amarillo si está entre 8 y 15 días.
                        }
                    }
                ?>
                <tr class="<?php echo $alerta_proximidad; ?>">
                    <td><?php echo htmlspecialchars($pr['descripcion_pago'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pr['proveedor'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo number_format($pr['monto_pago'], 2) . ' ' . htmlspecialchars($pr['moneda'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo ucfirst($pr['frecuencia_pago']); ?></td>
                    <td><?php echo $pr['fecha_proximo_pago'] ? date('d/m/Y', strtotime($pr['fecha_proximo_pago'])) : 'N/A'; ?></td>
                    <td><span class="estado-pago estado-<?php echo $pr['estado']; ?>"><?php echo ucfirst($pr['estado']); ?></span></td>
                    <td title="<?php echo htmlspecialchars($pr['observaciones'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo mb_substr(strip_tags($pr['observaciones'] ?? ''), 0, 30) . (mb_strlen($pr['observaciones'] ?? '') > 30 ? '...' : ''); ?>
                    </td>
                    <td>
                        <button class="boton-tabla btn-sm" onclick="llenarFormularioPagoEfectuado(<?php echo $pr['id_pago_recurrente']; ?>, '<?php echo htmlspecialchars(addslashes($pr['descripcion_pago']), ENT_QUOTES, 'UTF-8'); ?>', <?php echo $pr['monto_pago']; ?>)">Registrar Pago</button>
                        <!-- Comentario: Botón para editar pago recurrente (requiere otro formulario/modal) -->
                        <!-- <a href="index.php?vista=pagos_gestor_editar_rec&id=<?php echo $pr['id_pago_recurrente']; ?>" class="boton-tabla btn-sm">Editar</a> -->
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section id="historial-pagos-efectuados" class="mt-3 card-sigi">
    <h3>Historial de Pagos Efectuados (Últimos 20)</h3>
     <?php if (empty($historial_pagos)): ?>
        <p>No hay pagos efectuados registrados.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>Fecha Pago</th>
                    <th>Descripción</th>
                    <th>Monto</th>
                    <th>Método</th>
                    <th>Ref. Comprobante</th>
                    <th>Vinculado a</th>
                    <th>Obs.</th>
                    <th>Registrado por</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($historial_pagos as $ph): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($ph['fecha_efectiva_pago'])); ?></td>
                    <td><?php echo htmlspecialchars($ph['descripcion_pago_efectuado'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo number_format($ph['monto_efectivamente_pagado'], 2); ?></td>
                    <td><?php echo htmlspecialchars($ph['metodo_pago'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($ph['referencia_comprobante'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td title="<?php echo htmlspecialchars($ph['desc_pago_recurrente'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo $ph['id_pago_recurrente'] ? 'Sí (ID: '.$ph['id_pago_recurrente'].')' : 'No'; ?>
                    </td>
                    <td title="<?php echo htmlspecialchars($ph['observaciones'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                         <?php echo mb_substr(strip_tags($ph['observaciones'] ?? ''), 0, 30) . (mb_strlen($ph['observaciones'] ?? '') > 30 ? '...' : ''); ?>
                    </td>
                    <td><?php echo htmlspecialchars($ph['usuario_gestor_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<style>
.gestor-pagos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; }
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.form-filtros label, .form-filtros input, .form-filtros select { margin-right: 0.5rem; margin-bottom: 0.5rem; }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875em; }
.estado-pago.estado-activo { background-color: var(--color-exito); color: white; padding: 2px 5px; border-radius: 3px; }
.estado-pago.estado-inactivo { background-color: var(--color-secundario); color: white; padding: 2px 5px; border-radius: 3px; }
.estado-pago.estado-finalizado { background-color: #adb5bd; color: black; padding: 2px 5px; border-radius: 3px; }
.alerta-vencido { background-color: #f8d7da !important; /* Rojo claro */ }
.alerta-proximo { background-color: #ffeeba !important; /* Amarillo claro */ }
.alerta-moderada { background-color: #d1ecf1 !important; /* Azul claro info */ }
</style>

<script>
function llenarFormularioPagoEfectuado(idRecurrente, descripcionRecurrente, montoRecurrente) {
    document.getElementById('id_pago_recurrente_hist').value = idRecurrente;
    document.getElementById('descripcion_pago_efectuado').value = "Pago de: " + descripcionRecurrente;
    document.getElementById('monto_efectivamente_pagado').value = montoRecurrente;
    document.getElementById('registrar-pago-efectuado').scrollIntoView({ behavior: 'smooth' });
    document.getElementById('descripcion_pago_efectuado').focus();
}
</script>

<?php
// Comentario: Fin del archivo vistas/pagos_gestor.php
?>
