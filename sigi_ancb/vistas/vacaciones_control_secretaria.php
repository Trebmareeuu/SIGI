<?php
// Archivo: vistas/vacaciones_control_secretaria.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: (Secretaria) Bandeja para revisar y derivar solicitudes de vacación a MAE - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $solicitudes_pendientes_secretaria, $pagina_actual, $total_paginas
// Comentario: son definidas en logica/vacaciones_control_secretaria_logica.php
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
                    <th>Obs. Previas</th>
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
                            echo htmlspecialchars($just_corta, ENT_QUOTES, 'UTF-8') . (mb_strlen(strip_tags($sol['descripcion_solicitud'])) > 50 ? '...' : '');
                            ?>
                            <button class="boton-tabla ver-detalle-solicitud-modal"
                                    data-id-solicitud="<?php echo $sol['id_solicitud']; ?>"
                                    data-descripcion="<?php echo htmlspecialchars(nl2br(strip_tags($sol['descripcion_solicitud'])), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-obs-gestion="<?php echo htmlspecialchars(nl2br(strip_tags($sol['observaciones_gestion'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Ver Justificación Completa y Obs. Previas">👁️</button>
                        </td>
                        <td class="obs-previas-col" title="<?php echo htmlspecialchars(strip_tags($sol['observaciones_gestion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                             <?php
                            $obs_prev_corta = mb_substr(strip_tags($sol['observaciones_gestion'] ?? ''), 0, 40);
                            echo htmlspecialchars($obs_prev_corta, ENT_QUOTES, 'UTF-8') . (mb_strlen(strip_tags($sol['observaciones_gestion'] ?? '')) > 40 ? '...' : '');
                            ?>
                        </td>
                        <td>
                            <form action="index.php?vista=vacaciones_control_secretaria&pagina=<?php echo $pagina_actual; ?>" method="POST" class="form-accion-bandeja">
                                <input type="hidden" name="id_solicitud" value="<?php echo $sol['id_solicitud']; ?>">
                                <div class="grupo-formulario-sm">
                                    <label for="obs_sec_<?php echo $sol['id_solicitud']; ?>">Observaciones/Motivo:</label>
                                    <textarea name="observaciones_secretaria" id="obs_sec_<?php echo $sol['id_solicitud']; ?>" rows="2" placeholder="Opcional para derivar, obligatorio para observar/rechazar"></textarea>
                                </div>
                                <button type="submit" name="accion_control_vacacion" value="derivar_a_mae" class="boton-tabla exito" title="Derivar a MAE">✔️ Derivar a MAE</button>
                                <button type="submit" name="accion_control_vacacion" value="observar_solicitud" class="boton-tabla advertencia" title="Añadir Observaciones (requiere texto en campo de arriba)">⚠️ Observar</button>
                                <button type="submit" name="accion_control_vacacion" value="rechazar_preliminar" class="boton-tabla error confirmar-accion" data-mensaje-confirmacion="¿Está seguro de RECHAZAR esta solicitud? Se requerirá un motivo en el campo de observaciones." title="Rechazar Solicitud (requiere texto en campo de arriba)">❌ Rechazar</button>
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

<!-- Modal para ver detalle (reutilizar el de historial_solicitudes) -->
<div id="modalDetalleSolicitud" class="modal-sigi oculto">
    <div class="modal-contenido-sigi">
        <span class="modal-cerrar-sigi" id="cerrarModalDetalleSolSec">&times;</span>
        <h4>Detalle de Solicitud <span id="modalIdSolicitudSec"></span></h4>
        <p><strong>Justificación / Descripción Completa:</strong></p>
        <div id="modalDescripcionCompletaSec" class="modal-texto-scroll"></div>
        <div id="modalInfoGestionSec" class="oculto mt-2">
            <p><strong>Observaciones Previas de Gestión:</strong></p>
            <div id="modalObsGestionSec" class="modal-texto-scroll" style="background-color: #e9ecef;"></div>
        </div>
    </div>
</div>

<style>
/* Comentario: Estilos específicos para esta vista (si son necesarios y no están en estilos.css global). */
.form-accion-bandeja .grupo-formulario-sm { margin-bottom: 5px; }
.form-accion-bandeja .grupo-formulario-sm label { font-size: 0.8em; }
.form-accion-bandeja textarea { width: 100%; font-size: 0.9em; padding: 3px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
.form-accion-bandeja .boton-tabla { margin-top: 5px; margin-right: 5px; font-size:0.85em; padding: 4px 8px;}
.boton-tabla.exito { background-color: var(--color-exito); color:white; border:none; }
.boton-tabla.advertencia { background-color: var(--color-advertencia); color:black; border:none; }
.boton-tabla.error { background-color: var(--color-error); color:white; border:none; }
.obs-previas-col { font-size: 0.8em; color: #555; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;}
/* Comentario: Estilos de modal y paginación deben estar en estilos.css o heredados. */
.modal-texto-scroll { white-space: pre-wrap; background-color:#f9f9f9; padding:10px; border: 1px solid #eee; border-radius:4px; max-height:200px; overflow-y:auto; }
.mt-2 { margin-top: 0.5rem !important; }
</style>

<script>
// Comentario: JS para el modal (similar al de historial_solicitudes).
document.addEventListener('DOMContentLoaded', function() {
    const botonesDetalleModal = document.querySelectorAll('.ver-detalle-solicitud-modal');
    const modal = document.getElementById('modalDetalleSolicitud'); // Comentario: Usar el mismo ID de modal que en historial.
    const cerrarModalBtn = document.getElementById('cerrarModalDetalleSolSec'); // Comentario: ID específico para el botón de cierre.

    const modalIdSpan = document.getElementById('modalIdSolicitudSec');
    const modalDescDiv = document.getElementById('modalDescripcionCompletaSec');
    const modalInfoGestionDiv = document.getElementById('modalInfoGestionSec');
    const modalObsGestionDiv = document.getElementById('modalObsGestionSec');

    if (modal) {
        botonesDetalleModal.forEach(boton => {
            boton.addEventListener('click', function() {
                if(modalIdSpan) modalIdSpan.textContent = '(ID: ' + this.dataset.idSolicitud + ')';
                if(modalDescDiv) modalDescDiv.innerHTML = this.dataset.descripcion;

                if (this.dataset.obsGestion && this.dataset.obsGestion.trim() !== '' && modalObsGestionDiv && modalInfoGestionDiv) {
                    modalObsGestionDiv.innerHTML = this.dataset.obsGestion;
                    modalInfoGestionDiv.classList.remove('oculto');
                } else if(modalInfoGestionDiv) {
                    modalInfoGestionDiv.classList.add('oculto');
                    if(modalObsGestionDiv) modalObsGestionDiv.innerHTML = ''; // Limpiar
                }
                modal.classList.remove('oculto');
            });
        });

        if (cerrarModalBtn) {
            cerrarModalBtn.onclick = function() {
                modal.classList.add('oculto');
            }
        }

        modal.addEventListener('click', function(event) {
            if (event.target === modal) { // Comentario: Si se hace clic en el fondo del modal.
                modal.classList.add('oculto');
            }
        });
    }
});
</script>

<?php
// Comentario: Fin del archivo vistas/vacaciones_control_secretaria.php (SOLO PRESENTACIÓN)
?>
