<?php
// Archivo: vistas/vacaciones_aprobacion_mae.php
// Propósito: (MAE) Bandeja para aprobar o rechazar solicitudes de vacación derivadas por Secretaría.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('APROBAR_VACACIONES_MAE', $id_usuario_actual)) {
    mensaje_flash('error_vac_aprob_mae', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$solicitudes_pendientes_mae = [];

// Comentario: Paginación.
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$regs_por_pagina = 10;
$offset = ($pagina_actual - 1) * $regs_por_pagina;
$total_regs = 0;

try {
    // Comentario: Contar total de solicitudes pendientes de aprobación por MAE.
    $sql_count = "SELECT COUNT(*)
                  FROM solicitudes s
                  WHERE s.tipo_solicitud = 'vacacion'
                  AND s.estado_solicitud = 'pendiente_aprobacion_mae'";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute();
    $total_regs = (int)$stmt_count->fetchColumn();

    // Comentario: Obtener solicitudes pendientes de aprobación por MAE.
    $sql = "SELECT s.id_solicitud, s.fecha_solicitud, s.fecha_inicio_vacacion, s.fecha_fin_vacacion, s.dias_solicitados_vacacion,
                   s.descripcion_solicitud, s.observaciones_gestion as obs_secretaria,
                   u.nombres as solicitante_nombres, u.apellidos as solicitante_apellidos, u.cargo as solicitante_cargo
            FROM solicitudes s
            JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
            WHERE s.tipo_solicitud = 'vacacion' AND s.estado_solicitud = 'pendiente_aprobacion_mae'
            ORDER BY s.fecha_solicitud ASC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $regs_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $solicitudes_pendientes_mae = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar solicitudes de vacación para aprobación MAE: " . $e->getMessage());
    mensaje_flash('error_vac_aprob_mae', 'Ocurrió un error al cargar las solicitudes. Intente más tarde.', 'alert-danger');
}

$total_paginas = ceil($total_regs / $regs_por_pagina);

// Comentario: Procesamiento de acciones (aprobar, rechazar).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_aprobacion_vacacion'])) {
    $id_solicitud_accion = filter_input(INPUT_POST, 'id_solicitud', FILTER_VALIDATE_INT);
    $accion = $_POST['accion_aprobacion_vacacion']; // 'aprobar_vacacion' o 'rechazar_vacacion'
    $motivo_decision_mae = strip_tags($_POST['motivo_decision_mae'] ?? '');

    if ($id_solicitud_accion) {
        $pdo->beginTransaction();
        try {
            $nuevo_estado = '';
            $mensaje_historial = '';
            $campo_motivo_rechazo = null;

            if ($accion === 'aprobar_vacacion') {
                $nuevo_estado = 'aprobada';
                $mensaje_historial = "Solicitud de vacación APROBADA por MAE.";
                if (!empty($motivo_decision_mae)) {
                    $mensaje_historial .= " Comentario MAE: " . $motivo_decision_mae;
                }
            } elseif ($accion === 'rechazar_vacacion') {
                if (empty($motivo_decision_mae)) {
                    throw new Exception("El motivo del rechazo es obligatorio.");
                }
                $nuevo_estado = 'rechazada';
                $campo_motivo_rechazo = $motivo_decision_mae;
                $mensaje_historial = "Solicitud de vacación RECHAZADA por MAE. Motivo: " . $motivo_decision_mae;
            } else {
                throw new Exception("Acción no válida.");
            }

            $sql_update = "UPDATE solicitudes
                           SET estado_solicitud = :nuevo_estado,
                               id_usuario_aprobador = :id_mae,
                               fecha_aprobacion_rechazo = NOW(),
                               motivo_rechazo = :motivo_rechazo,
                               observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nDecisión MAE (', NOW(), '): ', :obs_mae)
                           WHERE id_solicitud = :id_solicitud
                           AND estado_solicitud = 'pendiente_aprobacion_mae'"; // Comentario: Doble check de estado.

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':nuevo_estado' => $nuevo_estado,
                ':id_mae' => $id_usuario_actual,
                ':motivo_rechazo' => $campo_motivo_rechazo,
                ':obs_mae' => $motivo_decision_mae, // Comentario: Se guarda como observación general también.
                ':id_solicitud' => $id_solicitud_accion
            ]);

            if ($stmt_update->rowCount() > 0) {
                // registrar_historial_solicitud($id_solicitud_accion, $id_usuario_actual, 'Decisión MAE Vacación', $mensaje_historial);
                mensaje_flash('exito_vac_aprob_mae', 'Decisión sobre la solicitud ID ' . $id_solicitud_accion . ' registrada exitosamente.', 'alert-success');
            } else {
                 throw new Exception("No se pudo actualizar la solicitud. Puede que ya haya sido procesada o no exista.");
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en acción aprobación vacación MAE: " . $e->getMessage());
            mensaje_flash('error_vac_aprob_mae_accion', 'Error al procesar la decisión: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=vacaciones_aprobacion_mae&pagina=' . $pagina_actual); // Comentario: Recargar.
    } else {
        mensaje_flash('error_vac_aprob_mae_accion', 'ID de solicitud no válido para la acción.', 'alert-danger');
        redirigir('index.php?vista=vacaciones_aprobacion_mae');
    }
}

?>
<h2>Aprobación de Solicitudes de Vacación (MAE)</h2>
<p>Revise las siguientes solicitudes de vacación y tome la acción correspondiente (aprobar o rechazar).</p>

<?php
mensaje_flash('error_vac_aprob_mae');
mensaje_flash('error_vac_aprob_mae_accion');
mensaje_flash('exito_vac_aprob_mae');
?>

<?php if (empty($solicitudes_pendientes_mae)): ?>
    <div class="alert alert-info">No hay solicitudes de vacación pendientes de aprobación por MAE en este momento.</div>
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
                    <th>Obs. Secretaría</th>
                    <th>Acciones MAE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes_pendientes_mae as $sol): ?>
                    <tr>
                        <td><?php echo $sol['id_solicitud']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])); ?></td>
                        <td><?php echo htmlspecialchars($sol['solicitante_apellidos'] . ', ' . $sol['solicitante_nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($sol['solicitante_cargo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($sol['fecha_inicio_vacacion'])); ?> al <?php echo date('d/m/Y', strtotime($sol['fecha_fin_vacacion'])); ?></td>
                        <td style="text-align:center;"><?php echo $sol['dias_solicitados_vacacion']; ?></td>
                        <td>
                            <?php
                            $just_corta_mae = mb_substr(strip_tags($sol['descripcion_solicitud']), 0, 50);
                            echo htmlspecialchars($just_corta_mae, ENT_QUOTES, 'UTF-8') . (mb_strlen($sol['descripcion_solicitud']) > 50 ? '...' : '');
                            ?>
                            <button class="boton-tabla ver-detalle-solicitud-mae"
                                    data-id-solicitud="<?php echo $sol['id_solicitud']; ?>"
                                    data-descripcion="<?php echo htmlspecialchars(nl2br(strip_tags($sol['descripcion_solicitud'])), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Ver Justificación Completa">👁️</button>
                        </td>
                        <td>
                            <?php
                            $obs_sec_corta = mb_substr(strip_tags($sol['obs_secretaria'] ?? ''), 0, 40);
                            echo htmlspecialchars($obs_sec_corta, ENT_QUOTES, 'UTF-8') . (mb_strlen($sol['obs_secretaria'] ?? '') > 40 ? '...' : '');
                             if(!empty($sol['obs_secretaria'])) {
                                echo ' <button class="boton-tabla ver-obs-secretaria-mae"
                                        data-obs-secretaria="'.htmlspecialchars(nl2br(strip_tags($sol['obs_secretaria'])), ENT_QUOTES, 'UTF-8').'"
                                        title="Ver Observaciones de Secretaría">📄</button>';
                             }
                            ?>
                        </td>
                        <td>
                            <form action="index.php?vista=vacaciones_aprobacion_mae&pagina=<?php echo $pagina_actual; ?>" method="POST" class="form-accion-bandeja">
                                <input type="hidden" name="id_solicitud" value="<?php echo $sol['id_solicitud']; ?>">
                                <div class="grupo-formulario-sm">
                                    <label for="motivo_mae_<?php echo $sol['id_solicitud']; ?>">Comentario/Motivo Decisión:</label>
                                    <textarea name="motivo_decision_mae" id="motivo_mae_<?php echo $sol['id_solicitud']; ?>" rows="2" placeholder="Opcional para aprobar, obligatorio para rechazar"></textarea>
                                </div>
                                <button type="submit" name="accion_aprobacion_vacacion" value="aprobar_vacacion" class="boton-tabla exito confirmar-accion" data-mensaje-confirmacion="¿Está seguro de APROBAR esta solicitud de vacación?" title="Aprobar Solicitud">✔️ Aprobar</button>
                                <button type="submit" name="accion_aprobacion_vacacion" value="rechazar_vacacion" class="boton-tabla error confirmar-accion" data-mensaje-confirmacion="¿Está seguro de RECHAZAR esta solicitud? Se requerirá un motivo." title="Rechazar Solicitud">❌ Rechazar</button>
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
                    <li class="page-item"><a class="page-link" href="index.php?vista=vacaciones_aprobacion_mae&pagina=<?php echo $pagina_actual - 1; ?>">Anterior</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="index.php?vista=vacaciones_aprobacion_mae&pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($pagina_actual < $total_paginas): ?>
                    <li class="page-item"><a class="page-link" href="index.php?vista=vacaciones_aprobacion_mae&pagina=<?php echo $pagina_actual + 1; ?>">Siguiente</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<!-- Modal para ver detalle de justificación y obs de secretaría -->
<div id="modalDetalleSolicitudMAE" class="modal-sigi oculto">
    <div class="modal-contenido-sigi">
        <span class="modal-cerrar-sigi" onclick="document.getElementById('modalDetalleSolicitudMAE').classList.add('oculto');">&times;</span>
        <h4>Detalles de Solicitud (ID: <span id="modalIdSolicitudFullMAE"></span>)</h4>

        <div id="modalSeccionJustificacionMAE">
            <p><strong>Justificación del Solicitante:</strong></p>
            <div id="modalDescripcionCompletaFullMAE" class="modal-texto-scroll"></div>
        </div>

        <div id="modalSeccionObsSecretariaMAE" class="oculto" style="margin-top:15px;">
            <p><strong>Observaciones de Secretaría:</strong></p>
            <div id="modalObsSecretariaFullMAE" class="modal-texto-scroll"></div>
        </div>
    </div>
</div>

<style>
/* Comentario: Estilos heredados o similares a vacaciones_control_secretaria.php y otros. */
.form-accion-bandeja .grupo-formulario-sm { margin-bottom: 5px; }
.form-accion-bandeja .grupo-formulario-sm label { font-size: 0.8em; }
.form-accion-bandeja textarea { width: 100%; font-size: 0.9em; padding: 3px; border: 1px solid #ccc; border-radius: 3px; }
.form-accion-bandeja .boton-tabla { margin-top: 5px; margin-right: 5px; font-size:0.85em; padding: 4px 8px;}
.boton-tabla.exito { background-color: var(--color-exito); color:white; }
.boton-tabla.error { background-color: var(--color-error); color:white; }
.modal-texto-scroll { white-space: pre-wrap; background-color:#f9f9f9; padding:10px; border-radius:4px; max-height:200px; overflow-y:auto; border: 1px solid #eee;}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalMAE = document.getElementById('modalDetalleSolicitudMAE');
    const modalIdMAE = document.getElementById('modalIdSolicitudFullMAE');
    const modalDescMAE = document.getElementById('modalDescripcionCompletaFullMAE');
    const modalSeccionObsSecMAE = document.getElementById('modalSeccionObsSecretariaMAE');
    const modalObsSecMAE = document.getElementById('modalObsSecretariaFullMAE');

    document.querySelectorAll('.ver-detalle-solicitud-mae').forEach(boton => {
        boton.addEventListener('click', function() {
            if(modalIdMAE) modalIdMAE.textContent = this.dataset.idSolicitud;
            if(modalDescMAE) modalDescMAE.innerHTML = this.dataset.descripcion;

            // Comentario: Limpiar sección de obs. secretaría por si no aplica a este ítem.
            if(modalSeccionObsSecMAE) modalSeccionObsSecMAE.classList.add('oculto');
            if(modalObsSecMAE) modalObsSecMAE.innerHTML = '';

            if(modalMAE) modalMAE.classList.remove('oculto');
        });
    });

    document.querySelectorAll('.ver-obs-secretaria-mae').forEach(boton => {
        boton.addEventListener('click', function(event) {
            event.stopPropagation(); // Comentario: Evitar que se dispare el click del botón de justificación si está anidado o cercano.
            const idSol = this.closest('tr').querySelector('td:first-child').textContent; // Comentario: Obtener ID de la fila.
            if(modalIdMAE) modalIdMAE.textContent = idSol; // Comentario: Actualizar ID en modal.

            // Comentario: Mostrar la descripción principal también, por contexto.
            const descPrincipal = this.closest('tr').querySelector('.ver-detalle-solicitud-mae').dataset.descripcion;
            if(modalDescMAE) modalDescMAE.innerHTML = descPrincipal;

            if(modalObsSecMAE && this.dataset.obsSecretaria) {
                modalObsSecMAE.innerHTML = this.dataset.obsSecretaria;
                if(modalSeccionObsSecMAE) modalSeccionObsSecMAE.classList.remove('oculto');
            }
            if(modalMAE) modalMAE.classList.remove('oculto');
        });
    });

    const modalCerrarBtnMAE = modalMAE ? modalMAE.querySelector('.modal-cerrar-sigi') : null;
    if(modalCerrarBtnMAE) {
        modalCerrarBtnMAE.onclick = function() {
            if(modalMAE) modalMAE.classList.add('oculto');
        }
    }
    if(modalMAE) {
        modalMAE.addEventListener('click', function(event) {
            if (event.target === modalMAE) {
                modalMAE.classList.add('oculto');
            }
        });
    }
});
</script>

<?php
// Comentario: Fin del archivo vistas/vacaciones_aprobacion_mae.php
?>
