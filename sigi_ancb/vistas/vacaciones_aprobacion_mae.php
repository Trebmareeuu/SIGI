<?php
// Archivo: vistas/vacaciones_aprobacion_mae.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: (MAE) Bandeja para aprobar o rechazar solicitudes de vacación - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $solicitudes_pendientes_mae, $pagina_actual, $total_paginas
// Comentario: son definidas en logica/vacaciones_aprobacion_mae_logica.php
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
                            echo htmlspecialchars($just_corta_mae, ENT_QUOTES, 'UTF-8') . (mb_strlen(strip_tags($sol['descripcion_solicitud'])) > 50 ? '...' : '');
                            ?>
                            <button class="boton-tabla ver-detalle-solicitud-modal-mae"
                                    data-id-solicitud="<?php echo $sol['id_solicitud']; ?>"
                                    data-descripcion="<?php echo htmlspecialchars(nl2br(strip_tags($sol['descripcion_solicitud'])), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-obs-secretaria="<?php echo htmlspecialchars(nl2br(strip_tags($sol['obs_secretaria'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Ver Justificación Completa y Obs. Secretaría">👁️</button>
                        </td>
                        <td class="obs-previas-col" title="<?php echo htmlspecialchars(strip_tags($sol['obs_secretaria'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php
                            $obs_sec_corta = mb_substr(strip_tags($sol['obs_secretaria'] ?? ''), 0, 40);
                            echo htmlspecialchars($obs_sec_corta, ENT_QUOTES, 'UTF-8') . (mb_strlen(strip_tags($sol['obs_secretaria'] ?? '')) > 40 ? '...' : '');
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
                                <button type="submit" name="accion_aprobacion_vacacion" value="rechazar_vacacion" class="boton-tabla error confirmar-accion" data-mensaje-confirmacion="¿Está seguro de RECHAZAR esta solicitud? Se requerirá un motivo en el campo de comentario." title="Rechazar Solicitud">❌ Rechazar</button>
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
        <span class="modal-cerrar-sigi" id="cerrarModalDetalleSolMAE">&times;</span>
        <h4>Detalles de Solicitud (ID: <span id="modalIdSolicitudFullMAE"></span>)</h4>

        <div id="modalSeccionJustificacionMAE">
            <p><strong>Justificación del Solicitante:</strong></p>
            <div id="modalDescripcionCompletaFullMAE" class="modal-texto-scroll"></div>
        </div>

        <div id="modalSeccionObsSecretariaMAE" class="oculto mt-2">
            <p><strong>Observaciones de Secretaría:</strong></p>
            <div id="modalObsSecretariaFullMAE" class="modal-texto-scroll" style="background-color: #e9ecef;"></div>
        </div>
    </div>
</div>

<style>
/* Comentario: Estilos específicos para esta vista (si son necesarios y no están en estilos.css global). */
.form-accion-bandeja .grupo-formulario-sm { margin-bottom: 5px; }
.form-accion-bandeja .grupo-formulario-sm label { font-size: 0.8em; }
.form-accion-bandeja textarea { width: 100%; font-size: 0.9em; padding: 3px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box;}
.form-accion-bandeja .boton-tabla { margin-top: 5px; margin-right: 5px; font-size:0.85em; padding: 4px 8px;}
.boton-tabla.exito { background-color: var(--color-exito); color:white; border:none; }
.boton-tabla.error { background-color: var(--color-error); color:white; border:none; }
.obs-previas-col { font-size: 0.8em; color: #555; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;}
.modal-texto-scroll { white-space: pre-wrap; background-color:#f9f9f9; padding:10px; border: 1px solid #eee; border-radius:4px; max-height:200px; overflow-y:auto; }
.mt-2 { margin-top: 0.5rem !important; }
</style>

<script>
// Comentario: JS para el modal.
document.addEventListener('DOMContentLoaded', function() {
    const botonesDetalleModalMAE = document.querySelectorAll('.ver-detalle-solicitud-modal-mae');
    const modalMAE = document.getElementById('modalDetalleSolicitudMAE');
    const cerrarModalBtnMAE = document.getElementById('cerrarModalDetalleSolMAE');

    const modalIdSpanMAE = document.getElementById('modalIdSolicitudFullMAE');
    const modalDescDivMAE = document.getElementById('modalDescripcionCompletaFullMAE');
    const modalSeccionObsSecDivMAE = document.getElementById('modalSeccionObsSecretariaMAE');
    const modalObsSecDivMAE = document.getElementById('modalObsSecretariaFullMAE');

    if (modalMAE) {
        botonesDetalleModalMAE.forEach(boton => {
            boton.addEventListener('click', function() {
                if(modalIdSpanMAE) modalIdSpanMAE.textContent = this.dataset.idSolicitud;
                if(modalDescDivMAE) modalDescDivMAE.innerHTML = this.dataset.descripcion;

                if (this.dataset.obsSecretaria && this.dataset.obsSecretaria.trim() !== '' && modalObsSecDivMAE && modalSeccionObsSecDivMAE) {
                    modalObsSecDivMAE.innerHTML = this.dataset.obsSecretaria;
                    modalSeccionObsSecDivMAE.classList.remove('oculto');
                } else if(modalSeccionObsSecDivMAE) {
                    modalSeccionObsSecDivMAE.classList.add('oculto');
                    if(modalObsSecDivMAE) modalObsSecDivMAE.innerHTML = '';
                }
                modalMAE.classList.remove('oculto');
            });
        });

        if (cerrarModalBtnMAE) {
            cerrarModalBtnMAE.onclick = function() {
                modalMAE.classList.add('oculto');
            }
        }

        modalMAE.addEventListener('click', function(event) {
            if (event.target === modalMAE) {
                modalMAE.classList.add('oculto');
            }
        });
    }
});
</script>

<?php
// Comentario: Fin del archivo vistas/vacaciones_aprobacion_mae.php (SOLO PRESENTACIÓN)
?>
