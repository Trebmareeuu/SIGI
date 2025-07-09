<?php
// Archivo: vistas/solicitudes_historial.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: Muestra al usuario el historial y estado de todas sus solicitudes - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $solicitudes, $pagina_actual, $total_paginas, $id_usuario_actual
// Comentario: son definidas en logica/solicitudes_historial_logica.php
?>
<h2>Mis Solicitudes</h2>

<?php
mensaje_flash('error_hist_sol');
mensaje_flash('exito_sol_vac');
mensaje_flash('exito_sol_mat');
mensaje_flash('exito_sol_act');
mensaje_flash('exito_cancelar_sol');
mensaje_flash('error_cancelar_sol');
?>

<div class="acciones-bandeja mb-3">
    <?php if (tiene_permiso('SOLICITAR_VACACION', $id_usuario_actual)): ?>
    <a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_vacacion" class="boton boton-exito">Nueva Solicitud de Vacación</a>
    <?php endif; ?>
    <?php if (tiene_permiso('SOLICITAR_MATERIAL', $id_usuario_actual)): ?>
    <a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_material" class="boton boton-exito">Nueva Solicitud de Material</a>
    <?php endif; ?>
    <?php if (tiene_permiso('SOLICITAR_ACTIVO', $id_usuario_actual)): ?>
    <a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_activo" class="boton boton-exito">Nueva Solicitud de Activo</a>
    <?php endif; ?>
</div>


<?php if (empty($solicitudes)): ?>
    <div class="alert alert-info">No ha realizado ninguna solicitud todavía.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID Sol.</th>
                    <th>Fecha Solicitud</th>
                    <th>Tipo de Solicitud</th>
                    <th>Estado</th>
                    <th>Detalle/Justificación (Extracto)</th>
                    <th>Fecha Decisión</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $sol): ?>
                    <tr>
                        <td><?php echo $sol['id_solicitud']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])); ?></td>
                        <td><?php echo htmlspecialchars(formatear_tipo_solicitud_hist($sol['tipo_solicitud']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="estado-solicitud estado-<?php echo htmlspecialchars($sol['estado_solicitud'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(formatear_estado_solicitud_hist($sol['estado_solicitud']), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $descripcion_corta = mb_substr(strip_tags($sol['descripcion_solicitud']), 0, 70);
                            echo htmlspecialchars($descripcion_corta, ENT_QUOTES, 'UTF-8') . (mb_strlen(strip_tags($sol['descripcion_solicitud'])) > 70 ? '...' : '');
                            ?>
                        </td>
                        <td>
                            <?php echo $sol['fecha_aprobacion_rechazo'] ? date('d/m/Y', strtotime($sol['fecha_aprobacion_rechazo'])) : 'N/A'; ?>
                        </td>
                        <td>
                            <button class="boton-tabla ver-detalle-solicitud"
                                    data-id-solicitud="<?php echo $sol['id_solicitud']; ?>"
                                    data-descripcion="<?php echo htmlspecialchars(nl2br(strip_tags($sol['descripcion_solicitud'])), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-motivo-rechazo="<?php echo htmlspecialchars($sol['motivo_rechazo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-obs-gestion="<?php echo htmlspecialchars($sol['observaciones_gestion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Ver Detalle Completo">👁️ Detalle</button>

                            <?php
                            $estados_cancelables_por_usuario = ['pendiente_revision_secretaria', 'pendiente_aprobacion_mae', 'pendiente_aprobacion_admin'];
                            if (in_array($sol['estado_solicitud'], $estados_cancelables_por_usuario)):
                            ?>
                                <form action="index.php?vista=solicitudes_historial&pagina=<?php echo $pagina_actual; ?>" method="POST" style="display:inline;" class="confirmar-accion" data-mensaje-confirmacion="¿Está seguro de que desea cancelar esta solicitud? Esta acción no se puede deshacer.">
                                    <input type="hidden" name="id_solicitud_a_cancelar" value="<?php echo $sol['id_solicitud']; ?>">
                                    <button type="submit" name="accion_cancelar_solicitud" class="boton-tabla cancelar" title="Cancelar Solicitud">❌ Cancelar</button>
                                </form>
                            <?php endif; ?>
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
                    <li class="page-item"><a class="page-link" href="index.php?vista=solicitudes_historial&pagina=<?php echo $pagina_actual - 1; ?>">Anterior</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="index.php?vista=solicitudes_historial&pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <li class="page-item"><a class="page-link" href="index.php?vista=solicitudes_historial&pagina=<?php echo $pagina_actual + 1; ?>">Siguiente</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>


<!-- Modal para ver detalle de la solicitud -->
<div id="modalDetalleSolicitud" class="modal-sigi oculto">
    <div class="modal-contenido-sigi">
        <span class="modal-cerrar-sigi" id="cerrarModalDetalleSol">&times;</span>
        <h4>Detalle de la Solicitud <span id="modalIdSolicitud"></span></h4>
        <p><strong>Descripción Completa:</strong></p>
        <div id="modalDescripcionCompleta" class="modal-texto-scroll"></div>

        <div id="modalInfoRechazo" class="oculto mt-2">
            <p><strong>Motivo del Rechazo:</strong></p>
            <div id="modalMotivoRechazo" class="modal-texto-scroll" style="background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;"></div>
        </div>

        <div id="modalInfoGestion" class="oculto mt-2">
            <p><strong>Observaciones de Gestión/Aprobación:</strong></p>
            <div id="modalObsGestion" class="modal-texto-scroll" style="background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb;"></div>
        </div>
    </div>
</div>


<style>
/* Comentario: Estilos para estados de solicitud y modal (algunos pueden estar ya en estilos.css). */
.estado-solicitud { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-pendiente_revision_secretaria, .estado-pendiente_aprobacion_mae, .estado-pendiente_aprobacion_admin { background-color: var(--color-advertencia); color: #333; }
.estado-aprobada { background-color: var(--color-exito); }
.estado-rechazada { background-color: var(--color-error); }
.estado-atendida { background-color: var(--color-info); color: #333; }
.estado-cancelada { background-color: var(--color-secundario); }

.boton-tabla.ver-detalle-solicitud { background-color: var(--color-info); color: white; border:none; }
.boton-tabla.ver-detalle-solicitud:hover { background-color: #0a9cb9; }
.boton-tabla.cancelar { background-color: var(--color-advertencia); color: black; border:none;}
.boton-tabla.cancelar:hover { background-color: #e7a100; }

/* Estilos para el modal (podrían estar en estilos.css si es un modal genérico) */
.modal-sigi { position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; }
.modal-sigi.oculto { display: none !important; } /* Comentario: !important para asegurar que se oculte. */
.modal-contenido-sigi { background-color: #fefefe; margin: auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: var(--borde-radio); box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19); position: relative; }
.modal-cerrar-sigi { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; top: 10px; right: 20px; }
.modal-cerrar-sigi:hover, .modal-cerrar-sigi:focus { color: black; text-decoration: none; cursor: pointer; }
.modal-texto-scroll { white-space: pre-wrap; background-color:#f9f9f9; padding:10px; border: 1px solid #eee; border-radius:4px; max-height:200px; overflow-y:auto; }
.mt-2 { margin-top: 0.5rem !important; } /* Comentario: Utilidad de margen. */
</style>

<script>
// Comentario: El script para el modal ya está en main.js o se puede añadir aquí si es específico.
// Comentario: Se asume que main.js ya tiene la lógica para .ver-detalle-solicitud y el modal.
// Comentario: Si no, se debe copiar la lógica del modal de la vista de historial de solicitudes aquí.
document.addEventListener('DOMContentLoaded', function() {
    const botonesDetalle = document.querySelectorAll('.ver-detalle-solicitud');
    const modal = document.getElementById('modalDetalleSolicitud');
    const cerrarModalBtn = document.getElementById('cerrarModalDetalleSol');

    const modalIdSolicitud = document.getElementById('modalIdSolicitud');
    const modalDescripcion = document.getElementById('modalDescripcionCompleta');
    const modalInfoRechazo = document.getElementById('modalInfoRechazo');
    const modalMotivoRechazo = document.getElementById('modalMotivoRechazo');
    const modalInfoGestion = document.getElementById('modalInfoGestion');
    const modalObsGestion = document.getElementById('modalObsGestion');

    if (modal) { // Comentario: Verificar que el modal exista.
        botonesDetalle.forEach(boton => {
            boton.addEventListener('click', function() {
                if(modalIdSolicitud) modalIdSolicitud.textContent = '(ID: ' + this.dataset.idSolicitud + ')';
                if(modalDescripcion) modalDescripcion.innerHTML = this.dataset.descripcion;

                if (this.dataset.motivoRechazo && modalMotivoRechazo && modalInfoRechazo) {
                    modalMotivoRechazo.innerHTML = this.dataset.motivoRechazo;
                    modalInfoRechazo.classList.remove('oculto');
                } else if(modalInfoRechazo) {
                    modalInfoRechazo.classList.add('oculto');
                }

                if (this.dataset.obsGestion && modalObsGestion && modalInfoGestion) {
                    modalObsGestion.innerHTML = this.dataset.obsGestion;
                    modalInfoGestion.classList.remove('oculto');
                } else if (modalInfoGestion) {
                    modalInfoGestion.classList.add('oculto');
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
            if (event.target === modal) {
                modal.classList.add('oculto');
            }
        });
    }
});
</script>
<?php
// Comentario: Fin del archivo vistas/solicitudes_historial.php (SOLO PRESENTACIÓN)
?>
