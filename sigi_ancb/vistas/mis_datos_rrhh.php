<?php
// Archivo: vistas/mis_datos_rrhh.php
// Propósito: (Funcionario) Consultar saldo de vacaciones, historial de vacaciones aprobadas y materiales/activos entregados.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
// Comentario: Usaremos permisos más granulares si es necesario, por ahora VER_DASHBOARD o uno nuevo como VER_MI_INFO_RRHH.
// Comentario: Los permisos VER_MIS_VACACIONES y VER_MIS_MATERIALES_ENTREGADOS ya fueron añadidos al rol Funcionario Estándar.
if (!tiene_permiso('VER_MIS_VACACIONES', $id_usuario_actual) && !tiene_permiso('VER_MIS_MATERIALES_ENTREGADOS', $id_usuario_actual)) {
    mensaje_flash('error_mis_datos_rrhh', 'No tiene permisos para acceder a esta información.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$anio_actual_consulta = date('Y'); // Comentario: Consultas por defecto para el año actual.

// --- Saldo de Vacaciones ---
$dias_asignados_anual_info = 'N/A';
$total_dias_tomados_anio_info = 0;
$saldo_dias_vacacion_info = 'N/A';
$vacaciones_aprobadas_historial = [];

if (tiene_permiso('VER_MIS_VACACIONES', $id_usuario_actual)) {
    try {
        $stmt_ficha_vac = $pdo->prepare("SELECT dias_vacacion_anuales_asignados FROM personal_fichas WHERE id_usuario = :id_user_fv");
        $stmt_ficha_vac->bindParam(':id_user_fv', $id_usuario_actual, PDO::PARAM_INT);
        $stmt_ficha_vac->execute();
        $dias_asignados_raw_info = $stmt_ficha_vac->fetchColumn();
        $dias_asignados_anual_info = ($dias_asignados_raw_info !== false && !is_null($dias_asignados_raw_info)) ? (int)$dias_asignados_raw_info : 20;

        $sql_tom_vac = "SELECT id_solicitud, fecha_inicio_vacacion, fecha_fin_vacacion, dias_solicitados_vacacion, ruta_adjunto_solicitud, descripcion_solicitud
                        FROM solicitudes
                        WHERE id_usuario_solicitante = :id_user_tv
                        AND tipo_solicitud = 'vacacion'
                        AND estado_solicitud = 'aprobada'
                        AND YEAR(fecha_inicio_vacacion) = :anio_cv
                        ORDER BY fecha_inicio_vacacion DESC";
        $stmt_tom_vac = $pdo->prepare($sql_tom_vac);
        $stmt_tom_vac->execute([':id_user_tv' => $id_usuario_actual, ':anio_cv' => $anio_actual_consulta]);
        $vacaciones_aprobadas_historial = $stmt_tom_vac->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vacaciones_aprobadas_historial as $vac_ap_h) {
            $total_dias_tomados_anio_info += (int)$vac_ap_h['dias_solicitados_vacacion'];
        }
        $saldo_dias_vacacion_info = $dias_asignados_anual_info - $total_dias_tomados_anio_info;

    } catch (PDOException $e) {
        error_log("Error al calcular datos de vacaciones para mis_datos_rrhh (Usuario ID $id_usuario_actual): " . $e->getMessage());
        mensaje_flash('error_mis_datos_rrhh', 'Error al cargar información de vacaciones.', 'alert-danger');
        $dias_disponibles_vacacion_display = "Error"; // Comentario: Para evitar error si la vista lo espera.
    }
}

// --- Historial de Materiales/Activos Entregados ---
$materiales_activos_entregados = [];
if (tiene_permiso('VER_MIS_MATERIALES_ENTREGADOS', $id_usuario_actual)) {
    try {
        // Comentario: Para saber qué se entregó, necesitamos ver las solicitudes aprobadas
        // Comentario: y, para materiales, idealmente los movimientos de salida que confirman la entrega.
        // Comentario: Por ahora, mostraremos las solicitudes de material/activo que fueron 'aprobadas' o 'atendida'.
        $sql_mat_act = "SELECT id_solicitud, tipo_solicitud, fecha_solicitud, estado_solicitud, descripcion_solicitud, fecha_aprobacion_rechazo, ruta_adjunto_solicitud
                        FROM solicitudes
                        WHERE id_usuario_solicitante = :id_user_mat_act
                        AND tipo_solicitud IN ('material_escritorio', 'activo_mueble_equipo')
                        AND estado_solicitud IN ('aprobada', 'atendida') -- Comentario: 'atendida' si hay un paso post-aprobación.
                        ORDER BY fecha_solicitud DESC
                        LIMIT 20"; // Comentario: Limitar para no sobrecargar.
        $stmt_mat_act = $pdo->prepare($sql_mat_act);
        $stmt_mat_act->bindParam(':id_user_mat_act', $id_usuario_actual, PDO::PARAM_INT);
        $stmt_mat_act->execute();
        $materiales_activos_entregados = $stmt_mat_act->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error al cargar historial de materiales/activos para mis_datos_rrhh (Usuario ID $id_usuario_actual): " . $e->getMessage());
        mensaje_flash('error_mis_datos_rrhh', 'Error al cargar información de materiales/activos.', 'alert-danger');
    }
}

?>
<h2>Mi Información de Recursos Humanos</h2>

<?php mensaje_flash('error_mis_datos_rrhh'); ?>

<?php if (tiene_permiso('VER_MIS_VACACIONES', $id_usuario_actual)): ?>
<section class="card-sigi mb-3" id="info-vacaciones-funcionario">
    <h3>Mis Vacaciones (Año <?php echo $anio_actual_consulta; ?>)</h3>
    <p><strong>Días de Vacación Anuales Asignados:</strong> <?php echo htmlspecialchars($dias_asignados_anual_info, ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>Total Días Tomados (Aprobados en <?php echo $anio_actual_consulta; ?>):</strong> <?php echo $total_dias_tomados_anio_info; ?></p>
    <p><strong>Saldo de Días Pendientes para <?php echo $anio_actual_consulta; ?>:</strong>
        <strong class="<?php if(is_numeric($saldo_dias_vacacion_info) && $saldo_dias_vacacion_info < 0) echo 'text-danger'; elseif(is_numeric($saldo_dias_vacacion_info) && $saldo_dias_vacacion_info == 0) echo 'text-warning'; ?>">
            <?php echo htmlspecialchars($saldo_dias_vacacion_info, ENT_QUOTES, 'UTF-8'); ?>
        </strong>
    </p>

    <h4>Historial de Vacaciones Aprobadas en <?php echo $anio_actual_consulta; ?>:</h4>
    <?php if (empty($vacaciones_aprobadas_historial)): ?>
        <p><em>No tiene vacaciones aprobadas registradas para este año.</em></p>
    <?php else: ?>
        <ul class="lista-simple">
            <?php foreach ($vacaciones_aprobadas_historial as $vac_h): ?>
                <li>
                    Del <strong><?php echo date('d/m/Y', strtotime($vac_h['fecha_inicio_vacacion'])); ?></strong>
                    al <strong><?php echo date('d/m/Y', strtotime($vac_h['fecha_fin_vacacion'])); ?></strong>
                    (<?php echo $vac_h['dias_solicitados_vacacion']; ?> días).
                    Ref: <?php echo htmlspecialchars(mb_substr(strip_tags($vac_h['descripcion_solicitud']), 0, 50) . '...', ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($vac_h['ruta_adjunto_solicitud'])): ?>
                        <a href="<?php echo BASE_URL . 'docs/' . htmlspecialchars($vac_h['ruta_adjunto_solicitud'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" title="Ver Carta Aprobada">(Ver Adjunto)</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="mt-2"><small>Nota: Este es un resumen informativo. Para detalles o discrepancias, contacte a RRHH o Secretaría.</small></p>
</section>
<?php endif; ?>


<?php if (tiene_permiso('VER_MIS_MATERIALES_ENTREGADOS', $id_usuario_actual)): ?>
<section class="card-sigi mb-3" id="info-materiales-funcionario">
    <h3>Historial de Solicitudes de Materiales/Activos (Aprobados/Atendidos - Últimos 20)</h3>
    <?php if (empty($materiales_activos_entregados)): ?>
        <p><em>No tiene solicitudes de materiales o activos aprobados/atendidos recientemente.</em></p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID Sol.</th>
                    <th>Fecha Solicitud</th>
                    <th>Tipo</th>
                    <th>Descripción/Referencia (Extracto)</th>
                    <th>Estado</th>
                    <th>Fecha Decisión</th>
                    <th>Adjunto</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($materiales_activos_entregados as $ma_item): ?>
                <tr>
                    <td><?php echo $ma_item['id_solicitud']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($ma_item['fecha_solicitud'])); ?></td>
                    <td><?php echo htmlspecialchars(formatear_tipo_solicitud($ma_item['tipo_solicitud']), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td title="<?php echo htmlspecialchars(strip_tags($ma_item['descripcion_solicitud']), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars(mb_substr(strip_tags($ma_item['descripcion_solicitud']), 0, 70) . '...', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ma_item['estado_solicitud'])), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $ma_item['fecha_aprobacion_rechazo'] ? date('d/m/Y', strtotime($ma_item['fecha_aprobacion_rechazo'])) : 'N/A'; ?></td>
                    <td>
                        <?php if (!empty($ma_item['ruta_adjunto_solicitud'])): ?>
                            <a href="<?php echo BASE_URL . 'docs/' . htmlspecialchars($ma_item['ruta_adjunto_solicitud'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" title="Ver Form. Escaneado">Ver Adj.</a>
                        <?php else: echo 'N/A'; endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
     <p class="mt-2"><small>Para ver el estado de todas sus solicitudes (incluyendo pendientes o rechazadas), diríjase a "Estado de Mis Solicitudes".</small></p>
</section>
<?php endif; ?>


<div class="acciones-formulario mt-3">
     <a href="index.php?vista=dashboard" class="boton boton-info">Volver al Dashboard</a>
</div>

<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra_caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); }
.card-sigi h4 { margin-top: 1.5rem; color: var(--color-secundario); font-size: 1.1em; }
.lista-simple { list-style: disc; padding-left: 20px; }
.lista-simple li { margin-bottom: 0.3rem; }
.text-danger { color: var(--color-error); }
.text-warning { color: var(--color-advertencia); }
.text-muted { color: #6c757d; }
</style>

<?php
// Comentario: Fin del archivo.
?>
