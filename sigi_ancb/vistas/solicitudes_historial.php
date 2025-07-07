<?php
// Archivo: vistas/solicitudes_historial.php
// Propósito: Muestra al usuario el historial y estado de todas sus solicitudes.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('VER_HISTORIAL_SOLICITUDES_PROPIAS', $id_usuario_actual)) {
    mensaje_flash('error_hist_sol', 'No tiene permisos para ver el historial de solicitudes.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$solicitudes = [];

// Comentario: Paginación (ejemplo básico).
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$solicitudes_por_pagina = 10;
$offset = ($pagina_actual - 1) * $solicitudes_por_pagina;
$total_solicitudes = 0;

try {
    // Comentario: Contar total de solicitudes del usuario.
    $sql_count = "SELECT COUNT(*) FROM solicitudes WHERE id_usuario_solicitante = :id_usuario";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
    $stmt_count->execute();
    $total_solicitudes = (int)$stmt_count->fetchColumn();

    // Comentario: Obtener solicitudes del usuario con paginación.
    $sql = "SELECT id_solicitud, tipo_solicitud, fecha_solicitud, estado_solicitud, descripcion_solicitud,
                   fecha_aprobacion_rechazo, motivo_rechazo, observaciones_gestion
            FROM solicitudes
            WHERE id_usuario_solicitante = :id_usuario
            ORDER BY fecha_solicitud DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $solicitudes_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar historial de solicitudes para usuario ID $id_usuario_actual: " . $e->getMessage());
    mensaje_flash('error_hist_sol', 'Ocurrió un error al cargar su historial de solicitudes. Intente más tarde.', 'alert-danger');
}

$total_paginas = ceil($total_solicitudes / $solicitudes_por_pagina);

// Comentario: Función para formatear el tipo de solicitud para mostrar.
function formatear_tipo_solicitud($tipo_bd) {
    $mapa = [
        'vacacion' => 'Vacación',
        'material_escritorio' => 'Material de Escritorio',
        'activo_mueble_equipo' => 'Activo (Mueble/Equipo)',
        'otro' => 'Otro Tipo'
    ];
    return $mapa[$tipo_bd] ?? ucfirst(str_replace('_', ' ', $tipo_bd));
}

// Comentario: Función para formatear el estado de la solicitud para mostrar.
function formatear_estado_solicitud($estado_bd) {
    $mapa_estados = [
        'pendiente_revision_secretaria' => 'Pendiente Revisión (Secretaría)',
        'pendiente_aprobacion_mae' => 'Pendiente Aprobación (MAE)',
        'pendiente_aprobacion_admin' => 'Pendiente Aprobación (Dir. Admin.)',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'atendida' => 'Atendida/Entregada',
        'cancelada' => 'Cancelada por Usuario'
    ];
    return $mapa_estados[$estado_bd] ?? ucfirst(str_replace('_', ' ', $estado_bd));
}

?>
<h2>Mis Solicitudes</h2>

<?php
mensaje_flash('error_hist_sol');
mensaje_flash('exito_sol_vac'); // Comentario: Mensajes de éxito de las páginas de solicitud.
mensaje_flash('exito_sol_mat');
mensaje_flash('exito_sol_act');
mensaje_flash('exito_cancelar_sol');
?>

<div class="acciones-bandeja mb-3">
    <a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_vacacion" class="boton boton-exito">Nueva Solicitud de Vacación</a>
    <a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_material" class="boton boton-exito">Nueva Solicitud de Material</a>
    <a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_activo" class="boton boton-exito">Nueva Solicitud de Activo</a>
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
                        <td><?php echo htmlspecialchars(formatear_tipo_solicitud($sol['tipo_solicitud']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="estado-solicitud estado-<?php echo htmlspecialchars($sol['estado_solicitud'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(formatear_estado_solicitud($sol['estado_solicitud']), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            // Comentario: Mostrar un extracto de la descripción.
                            $descripcion_corta = mb_substr(strip_tags($sol['descripcion_solicitud']), 0, 70);
                            echo htmlspecialchars($descripcion_corta, ENT_QUOTES, 'UTF-8') . (mb_strlen($sol['descripcion_solicitud']) > 70 ? '...' : '');
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
                            // Comentario: Permitir cancelar si está en ciertos estados pendientes.
                            $estados_cancelables = ['pendiente_revision_secretaria', 'pendiente_aprobacion_mae', 'pendiente_aprobacion_admin'];
                            if (in_array($sol['estado_solicitud'], $estados_cancelables)):
                            ?>
                                <form action="index.php?accion=cancelar_solicitud" method="POST" style="display:inline;" class="confirmar-accion" data-mensaje-confirmacion="¿Está seguro de que desea cancelar esta solicitud? Esta acción no se puede deshacer.">
                                    <input type="hidden" name="id_solicitud_cancelar" value="<?php echo $sol['id_solicitud']; ?>">
                                    <button type="submit" class="boton-tabla cancelar" title="Cancelar Solicitud">❌ Cancelar</button>
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


<!-- Modal para ver detalle de la solicitud (ejemplo simple) -->
<div id="modalDetalleSolicitud" class="modal-sigi oculto">
    <div class="modal-contenido-sigi">
        <span class="modal-cerrar-sigi" onclick="document.getElementById('modalDetalleSolicitud').classList.add('oculto');">&times;</span>
        <h4>Detalle de la Solicitud <span id="modalIdSolicitud"></span></h4>
        <p><strong>Descripción Completa:</strong></p>
        <div id="modalDescripcionCompleta" style="white-space: pre-wrap; background-color:#f9f9f9; padding:10px; border-radius:4px; max-height:200px; overflow-y:auto;"></div>

        <div id="modalInfoRechazo" class="oculto">
            <p><strong>Motivo del Rechazo:</strong></p>
            <div id="modalMotivoRechazo" style="white-space: pre-wrap;"></div>
        </div>

        <div id="modalInfoGestion" class="oculto">
            <p><strong>Observaciones de Gestión/Aprobación:</strong></p>
            <div id="modalObsGestion" style="white-space: pre-wrap;"></div>
        </div>
    </div>
</div>


<style>
/* Comentario: Estilos para estados de solicitud y modal. */
.estado-solicitud { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-pendiente_revision_secretaria, .estado-pendiente_aprobacion_mae, .estado-pendiente_aprobacion_admin { background-color: #ffc107; color: #333; /* Amarillo */ }
.estado-aprobada { background-color: #198754; /* Verde éxito */ }
.estado-rechazada { background-color: #dc3545; /* Rojo error */ }
.estado-atendida { background-color: #0dcaf0; color: #333; /* Celeste info */ }
.estado-cancelada { background-color: #6c757d; /* Gris */ }

.boton-tabla.ver-detalle-solicitud { background-color: var(--color-info); color: white; }
.boton-tabla.ver-detalle-solicitud:hover { background-color: #0a9cb9; }
.boton-tabla.cancelar { background-color: var(--color-advertencia); color: black; }
.boton-tabla.cancelar:hover { background-color: #e7a100; }

/* Estilos para el modal simple */
.modal-sigi { position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; }
.modal-sigi.oculto { display: none; }
.modal-contenido-sigi { background-color: #fefefe; margin: auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: var(--borde-radio); box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19); position: relative; }
.modal-cerrar-sigi { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; top: 10px; right: 20px; }
.modal-cerrar-sigi:hover, .modal-cerrar-sigi:focus { color: black; text-decoration: none; cursor: pointer; }
#modalDescripcionCompleta p { margin-bottom: 0.5em; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const botonesDetalle = document.querySelectorAll('.ver-detalle-solicitud');
    const modal = document.getElementById('modalDetalleSolicitud');
    const modalIdSolicitud = document.getElementById('modalIdSolicitud');
    const modalDescripcion = document.getElementById('modalDescripcionCompleta');
    const modalInfoRechazo = document.getElementById('modalInfoRechazo');
    const modalMotivoRechazo = document.getElementById('modalMotivoRechazo');
    const modalInfoGestion = document.getElementById('modalInfoGestion');
    const modalObsGestion = document.getElementById('modalObsGestion');

    botonesDetalle.forEach(boton => {
        boton.addEventListener('click', function() {
            modalIdSolicitud.textContent = '(ID: ' + this.dataset.idSolicitud + ')';
            modalDescripcion.innerHTML = this.dataset.descripcion; // Comentario: innerHTML porque la data puede tener <br>

            if (this.dataset.motivoRechazo) {
                modalMotivoRechazo.innerHTML = this.dataset.motivoRechazo;
                modalInfoRechazo.classList.remove('oculto');
            } else {
                modalInfoRechazo.classList.add('oculto');
            }

            if (this.dataset.obsGestion) {
                modalObsGestion.innerHTML = this.dataset.obsGestion;
                modalInfoGestion.classList.remove('oculto');
            } else {
                modalInfoGestion.classList.add('oculto');
            }

            modal.classList.remove('oculto');
        });
    });

    // Comentario: Cerrar modal al hacer clic fuera del contenido (opcional).
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.classList.add('oculto');
        }
    });
});
</script>
<?php
// Comentario: Fin del archivo vistas/solicitudes_historial.php
?>
