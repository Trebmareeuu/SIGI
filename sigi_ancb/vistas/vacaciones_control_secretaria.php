<?php
// Archivo: vistas/vacaciones_control_secretaria.php
// Propósito: (Secretaria) Bandeja para revisar y derivar solicitudes de vacación a MAE.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CONTROLAR_SOLICITUDES_VACACION_SECRETARIA', $id_usuario_actual)) {
    mensaje_flash('error_vac_ctrl_sec', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$solicitudes_pendientes_secretaria = [];

// Comentario: Paginación.
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$regs_por_pagina = 10;
$offset = ($pagina_actual - 1) * $regs_por_pagina;
$total_regs = 0;

try {
    // Comentario: Contar total de solicitudes pendientes de revisión por secretaría.
    $sql_count = "SELECT COUNT(*)
                  FROM solicitudes s
                  WHERE s.tipo_solicitud = 'vacacion'
                  AND s.estado_solicitud = 'pendiente_revision_secretaria'";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute();
    $total_regs = (int)$stmt_count->fetchColumn();

    // Comentario: Obtener solicitudes pendientes de revisión por secretaría.
    $sql = "SELECT s.id_solicitud, s.fecha_solicitud, s.fecha_inicio_vacacion, s.fecha_fin_vacacion, s.dias_solicitados_vacacion, s.descripcion_solicitud,
                   u.nombres as solicitante_nombres, u.apellidos as solicitante_apellidos, u.cargo as solicitante_cargo
            FROM solicitudes s
            JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
            WHERE s.tipo_solicitud = 'vacacion' AND s.estado_solicitud = 'pendiente_revision_secretaria'
            ORDER BY s.fecha_solicitud ASC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $regs_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $solicitudes_pendientes_secretaria = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar solicitudes de vacación para control secretaría: " . $e->getMessage());
    mensaje_flash('error_vac_ctrl_sec', 'Ocurrió un error al cargar las solicitudes. Intente más tarde.', 'alert-danger');
}

$total_paginas = ceil($total_regs / $regs_por_pagina);

// Comentario: Procesamiento de acciones (derivar, observar, rechazar preliminarmente).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_control_vacacion'])) {
    $id_solicitud_accion = filter_input(INPUT_POST, 'id_solicitud', FILTER_VALIDATE_INT);
    $accion = $_POST['accion_control_vacacion'];
    $observaciones_secretaria = strip_tags($_POST['observaciones_secretaria'] ?? '');

    if ($id_solicitud_accion) {
        $pdo->beginTransaction();
        try {
            $nuevo_estado = '';
            $mensaje_historial = '';

            if ($accion === 'derivar_a_mae') {
                $nuevo_estado = 'pendiente_aprobacion_mae';
                $mensaje_historial = "Solicitud revisada por Secretaría y derivada a MAE para aprobación.";
                if (!empty($observaciones_secretaria)) {
                    $mensaje_historial .= " Observaciones de Secretaría: " . $observaciones_secretaria;
                }
            } elseif ($accion === 'observar_solicitud') {
                // Comentario: "Observar" podría significar devolver al usuario o simplemente añadir notas y mantener pendiente.
                // Comentario: Por ahora, se asume que añade una observación y podría cambiar a un estado como 'observada_secretaria'
                // Comentario: o simplemente se añade la observación al campo 'observaciones_gestion' y se mantiene 'pendiente_revision_secretaria'
                // Comentario: para que la secretaria pueda luego derivarla o rechazarla.
                // Comentario: Opcion: Cambiar estado a 'devuelta_con_observacion' y asignar de nuevo al solicitante.
                // Comentario: Por simplicidad, vamos a añadir la observación y mantenerla para que la secretaria decida si la deriva o no.
                // Comentario: Si se quisiera devolver al usuario, se necesitaría un estado y lógica para que el usuario la vea y reenvíe.
                if (empty($observaciones_secretaria)) {
                     throw new Exception("Debe ingresar las observaciones para esta acción.");
                }
                $sql_obs = "UPDATE solicitudes SET observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nObservación Secretaría (', NOW(), '): ', :obs_sec)
                            WHERE id_solicitud = :id_sol";
                $stmt_obs = $pdo->prepare($sql_obs);
                $stmt_obs->execute([':obs_sec' => $observaciones_secretaria, ':id_sol' => $id_solicitud_accion]);

                $mensaje_historial = "Secretaría añadió observaciones a la solicitud.";
                 // registrar_historial_solicitud($id_solicitud_accion, $id_usuario_actual, 'Observación Secretaría', $observaciones_secretaria);
                mensaje_flash('exito_vac_ctrl_sec', 'Observaciones añadidas a la solicitud ID ' . $id_solicitud_accion . '. La solicitud sigue pendiente de su acción final (derivar o rechazar).', 'alert-info');

            } elseif ($accion === 'rechazar_preliminar') {
                // Comentario: Rechazo por parte de secretaría (si tiene esa atribución).
                if (empty($observaciones_secretaria)) {
                     throw new Exception("Debe ingresar el motivo del rechazo.");
                }
                $nuevo_estado = 'rechazada'; // Comentario: O un estado 'rechazada_secretaria'.
                $mensaje_historial = "Solicitud rechazada por Secretaría.";
                $sql_upd = "UPDATE solicitudes SET estado_solicitud = :estado, motivo_rechazo = :motivo, fecha_aprobacion_rechazo = NOW(), observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nRechazo Secretaría (', NOW(), '): ', :obs_sec)
                            WHERE id_solicitud = :id_sol";
                $stmt_upd = $pdo->prepare($sql_upd);
                $stmt_upd->execute([
                    ':estado' => $nuevo_estado,
                    ':motivo' => $observaciones_secretaria, // Comentario: El motivo del rechazo es la observación.
                    ':obs_sec' => $observaciones_secretaria,
                    ':id_sol' => $id_solicitud_accion
                ]);
                 // registrar_historial_solicitud($id_solicitud_accion, $id_usuario_actual, 'Rechazo Secretaría', $observaciones_secretaria);
                 mensaje_flash('exito_vac_ctrl_sec', 'Solicitud ID ' . $id_solicitud_accion . ' ha sido rechazada.', 'alert-success');
            }

            if (!empty($nuevo_estado) && $accion === 'derivar_a_mae') { // Comentario: Solo para derivar por ahora.
                $sql_update = "UPDATE solicitudes SET estado_solicitud = :nuevo_estado,
                               observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nRevisión Secretaría (', NOW(), '): ', :obs_sec)
                               WHERE id_solicitud = :id_solicitud";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([
                    ':nuevo_estado' => $nuevo_estado,
                    ':obs_sec' => $observaciones_secretaria,
                    ':id_solicitud' => $id_solicitud_accion
                ]);
                 // registrar_historial_solicitud($id_solicitud_accion, $id_usuario_actual, 'Control Secretaría', $mensaje_historial);
                 mensaje_flash('exito_vac_ctrl_sec', 'Acción sobre la solicitud ID ' . $id_solicitud_accion . ' realizada exitosamente.', 'alert-success');
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en acción control vacación secretaria: " . $e->getMessage());
            mensaje_flash('error_vac_ctrl_sec_accion', 'Error al procesar la acción: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=vacaciones_control_secretaria&pagina=' . $pagina_actual); // Comentario: Recargar.
    } else {
        mensaje_flash('error_vac_ctrl_sec_accion', 'ID de solicitud no válido para la acción.', 'alert-danger');
        redirigir('index.php?vista=vacaciones_control_secretaria');
    }
}

?>
<h2>Control de Solicitudes de Vacación (Secretaría)</h2>
<p>Revise las siguientes solicitudes de vacación y tome la acción correspondiente (derivar a MAE, observar o rechazar).</p>

<?php
mensaje_flash('error_vac_ctrl_sec');
mensaje_flash('error_vac_ctrl_sec_accion');
mensaje_flash('exito_vac_ctrl_sec');
?>

<?php if (empty($solicitudes_pendientes_secretaria)): ?>
    <div class="alert alert-info">No hay solicitudes de vacación pendientes de revisión por secretaría en este momento.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID Sol.</th>
                    <th>Fecha Solicitud</th>
                    <th>Solicitante</th>
                    <th>Cargo Sol.</th>
                    <th>Fechas Vacación</th>
                    <th>Días Solic.</th>
                    <th>Justificación (Extracto)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes_pendientes_secretaria as $sol): ?>
                    <tr>
                        <td><?php echo $sol['id_solicitud']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])); ?></td>
                        <td><?php echo htmlspecialchars($sol['solicitante_apellidos'] . ', ' . $sol['solicitante_nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($sol['solicitante_cargo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($sol['fecha_inicio_vacacion'])); ?> al <?php echo date('d/m/Y', strtotime($sol['fecha_fin_vacacion'])); ?></td>
                        <td style="text-align:center;"><?php echo $sol['dias_solicitados_vacacion']; ?></td>
                        <td>
                            <?php
                            $just_corta = mb_substr(strip_tags($sol['descripcion_solicitud']), 0, 50);
                            echo htmlspecialchars($just_corta, ENT_QUOTES, 'UTF-8') . (mb_strlen($sol['descripcion_solicitud']) > 50 ? '...' : '');
                            ?>
                            <button class="boton-tabla ver-detalle-solicitud"
                                    data-id-solicitud="<?php echo $sol['id_solicitud']; ?>"
                                    data-descripcion="<?php echo htmlspecialchars(nl2br(strip_tags($sol['descripcion_solicitud'])), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Ver Justificación Completa">👁️</button>
                        </td>
                        <td>
                            <form action="index.php?vista=vacaciones_control_secretaria&pagina=<?php echo $pagina_actual; ?>" method="POST" class="form-accion-bandeja">
                                <input type="hidden" name="id_solicitud" value="<?php echo $sol['id_solicitud']; ?>">
                                <div class="grupo-formulario-sm">
                                    <label for="obs_sec_<?php echo $sol['id_solicitud']; ?>">Observaciones/Motivo:</label>
                                    <textarea name="observaciones_secretaria" id="obs_sec_<?php echo $sol['id_solicitud']; ?>" rows="2" placeholder="Opcional para derivar, obligatorio para observar/rechazar"></textarea>
                                </div>
                                <button type="submit" name="accion_control_vacacion" value="derivar_a_mae" class="boton-tabla exito" title="Derivar a MAE">✔️ Derivar a MAE</button>
                                <button type="submit" name="accion_control_vacacion" value="observar_solicitud" class="boton-tabla advertencia" title="Añadir Observaciones (requiere texto)">⚠️ Observar</button>
                                <button type="submit" name="accion_control_vacacion" value="rechazar_preliminar" class="boton-tabla error confirmar-accion" data-mensaje-confirmacion="¿Está seguro de RECHAZAR esta solicitud? Se requerirá un motivo." title="Rechazar Solicitud (requiere texto)">❌ Rechazar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($total_paginas > 1): ?>
        <nav class="paginacion mt-3">
            <ul class="pagination-lista">
                <?php if ($pagina_actual > 1): ?>
                    <li class="page-item"><a class="page-link" href="index.php?vista=vacaciones_control_secretaria&pagina=<?php echo $pagina_actual - 1; ?>">Anterior</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="index.php?vista=vacaciones_control_secretaria&pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($pagina_actual < $total_paginas): ?>
                    <li class="page-item"><a class="page-link" href="index.php?vista=vacaciones_control_secretaria&pagina=<?php echo $pagina_actual + 1; ?>">Siguiente</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<!-- Modal para ver detalle de justificación (reutilizar el de historial si es similar) -->
<div id="modalDetalleSolicitud" class="modal-sigi oculto">
    <div class="modal-contenido-sigi">
        <span class="modal-cerrar-sigi" onclick="document.getElementById('modalDetalleSolicitud').classList.add('oculto');">&times;</span>
        <h4>Justificación Completa (ID Solicitud: <span id="modalIdSolicitudFull"></span>)</h4>
        <div id="modalDescripcionCompletaFull" style="white-space: pre-wrap; background-color:#f9f9f9; padding:10px; border-radius:4px; max-height:300px; overflow-y:auto;"></div>
    </div>
</div>

<style>
.form-accion-bandeja .grupo-formulario-sm { margin-bottom: 5px; }
.form-accion-bandeja .grupo-formulario-sm label { font-size: 0.8em; }
.form-accion-bandeja textarea { width: 100%; font-size: 0.9em; padding: 3px; border: 1px solid #ccc; border-radius: 3px; }
.form-accion-bandeja .boton-tabla { margin-top: 5px; margin-right: 5px; font-size:0.85em; padding: 4px 8px;}
.boton-tabla.exito { background-color: var(--color-exito); color:white; }
.boton-tabla.advertencia { background-color: var(--color-advertencia); color:black; }
.boton-tabla.error { background-color: var(--color-error); color:white; }
/* Estilos de modal y paginación ya deberían estar en estilos.css o heredados de otras vistas. */
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const botonesDetalleFull = document.querySelectorAll('.ver-detalle-solicitud'); // Asegurarse que este selector es único o ajustar
    const modalFull = document.getElementById('modalDetalleSolicitud'); // Reutilizando el ID del modal
    const modalIdSolicitudFull = document.getElementById('modalIdSolicitudFull');
    const modalDescripcionFull = document.getElementById('modalDescripcionCompletaFull');

    botonesDetalleFull.forEach(boton => {
        boton.addEventListener('click', function() {
            if(modalIdSolicitudFull) modalIdSolicitudFull.textContent = this.dataset.idSolicitud;
            if(modalDescripcionFull) modalDescripcionFull.innerHTML = this.dataset.descripcion;
            if(modalFull) modalFull.classList.remove('oculto');
        });
    });
     // Cerrar modal (el JS para esto ya podría estar en main.js o en la vista de historial)
    const modalCerrarBtn = modalFull ? modalFull.querySelector('.modal-cerrar-sigi') : null;
    if(modalCerrarBtn) {
        modalCerrarBtn.onclick = function() {
            if(modalFull) modalFull.classList.add('oculto');
        }
    }
    if(modalFull) {
        modalFull.addEventListener('click', function(event) {
            if (event.target === modalFull) {
                modalFull.classList.add('oculto');
            }
        });
    }
});
</script>

<?php
// Comentario: Fin del archivo vistas/vacaciones_control_secretaria.php
?>
