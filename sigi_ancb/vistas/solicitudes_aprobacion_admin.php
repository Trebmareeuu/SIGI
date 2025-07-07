<?php
// Archivo: vistas/solicitudes_aprobacion_admin.php
// Propósito: (Dir. Admin) Bandeja para aprobar/rechazar solicitudes de materiales y activos.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('APROBAR_SOLICITUDES_ADMIN', $id_usuario_actual)) {
    mensaje_flash('error_sol_aprob_admin', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$solicitudes_pendientes_admin = [];

// Comentario: Paginación.
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$regs_por_pagina = 10;
$offset = ($pagina_actual - 1) * $regs_por_pagina;
$total_regs = 0;

try {
    // Comentario: Contar total de solicitudes de material/activo pendientes de aprobación admin.
    $sql_count = "SELECT COUNT(*)
                  FROM solicitudes s
                  WHERE s.tipo_solicitud IN ('material_escritorio', 'activo_mueble_equipo')
                  AND s.estado_solicitud = 'pendiente_aprobacion_admin'";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute();
    $total_regs = (int)$stmt_count->fetchColumn();

    // Comentario: Obtener dichas solicitudes.
    $sql = "SELECT s.id_solicitud, s.fecha_solicitud, s.tipo_solicitud, s.descripcion_solicitud,
                   u.nombres as solicitante_nombres, u.apellidos as solicitante_apellidos, u.cargo as solicitante_cargo
            FROM solicitudes s
            JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
            WHERE s.tipo_solicitud IN ('material_escritorio', 'activo_mueble_equipo')
            AND s.estado_solicitud = 'pendiente_aprobacion_admin'
            ORDER BY s.fecha_solicitud ASC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $regs_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $solicitudes_pendientes_admin = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar solicitudes para aprobación Dir. Admin: " . $e->getMessage());
    mensaje_flash('error_sol_aprob_admin', 'Ocurrió un error al cargar las solicitudes. Intente más tarde.', 'alert-danger');
}

$total_paginas = ceil($total_regs / $regs_por_pagina);

// Comentario: Procesamiento de acciones (aprobar, rechazar).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_aprobacion_solicitud_admin'])) {
    $id_solicitud_accion = filter_input(INPUT_POST, 'id_solicitud', FILTER_VALIDATE_INT);
    $accion = $_POST['accion_aprobacion_solicitud_admin']; // 'aprobar_solicitud' o 'rechazar_solicitud'
    $motivo_decision_admin = strip_tags($_POST['motivo_decision_admin'] ?? '');

    if ($id_solicitud_accion) {
        $pdo->beginTransaction();
        try {
            $nuevo_estado = '';
            $mensaje_historial = '';
            $campo_motivo_rechazo = null;

            // Comentario: Obtener tipo de solicitud para mensaje.
            $stmt_tipo = $pdo->prepare("SELECT tipo_solicitud FROM solicitudes WHERE id_solicitud = :id_sol");
            $stmt_tipo->bindParam(':id_sol', $id_solicitud_accion, PDO::PARAM_INT);
            $stmt_tipo->execute();
            $tipo_sol_db = $stmt_tipo->fetchColumn();
            $tipo_sol_texto = formatear_tipo_solicitud($tipo_sol_db); // Reusar función de historial_solicitudes

            if ($accion === 'aprobar_solicitud_admin') {
                // Comentario: Al aprobar, el estado podría cambiar a 'aprobada' (si ya se entrega) o 'aprobada_pendiente_entrega'.
                // Comentario: Por simplicidad, 'aprobada' y luego se podría marcar como 'atendida' al entregar.
                $nuevo_estado = 'aprobada';
                $mensaje_historial = "Solicitud de $tipo_sol_texto APROBADA por Dir. Admin.";
                if (!empty($motivo_decision_admin)) { // Comentario: Aquí sería más una observación de aprobación.
                    $mensaje_historial .= " Comentario Dir. Admin: " . $motivo_decision_admin;
                }
            } elseif ($accion === 'rechazar_solicitud_admin') {
                if (empty($motivo_decision_admin)) {
                    throw new Exception("El motivo del rechazo es obligatorio.");
                }
                $nuevo_estado = 'rechazada';
                $campo_motivo_rechazo = $motivo_decision_admin;
                $mensaje_historial = "Solicitud de $tipo_sol_texto RECHAZADA por Dir. Admin. Motivo: " . $motivo_decision_admin;
            } else {
                throw new Exception("Acción no válida.");
            }

            $sql_update = "UPDATE solicitudes
                           SET estado_solicitud = :nuevo_estado,
                               id_usuario_aprobador = :id_admin,
                               fecha_aprobacion_rechazo = NOW(),
                               motivo_rechazo = :motivo_rechazo,
                               observaciones_gestion = CONCAT(IFNULL(observaciones_gestion,''), '\nDecisión Dir. Admin (', NOW(), '): ', :obs_admin)
                           WHERE id_solicitud = :id_solicitud
                           AND estado_solicitud = 'pendiente_aprobacion_admin'";

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':nuevo_estado' => $nuevo_estado,
                ':id_admin' => $id_usuario_actual,
                ':motivo_rechazo' => $campo_motivo_rechazo,
                ':obs_admin' => $motivo_decision_admin,
                ':id_solicitud' => $id_solicitud_accion
            ]);

            if ($stmt_update->rowCount() > 0) {
                // registrar_historial_solicitud($id_solicitud_accion, $id_usuario_actual, "Decisión Dir. Admin ($tipo_sol_texto)", $mensaje_historial);
                mensaje_flash('exito_sol_aprob_admin', 'Decisión sobre la solicitud ID ' . $id_solicitud_accion . ' registrada exitosamente.', 'alert-success');
            } else {
                 throw new Exception("No se pudo actualizar la solicitud. Puede que ya haya sido procesada o no exista.");
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error en acción aprobación solicitud Dir. Admin: " . $e->getMessage());
            mensaje_flash('error_sol_aprob_admin_accion', 'Error al procesar la decisión: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=solicitudes_aprobacion_admin&pagina=' . $pagina_actual);
    } else {
        mensaje_flash('error_sol_aprob_admin_accion', 'ID de solicitud no válido para la acción.', 'alert-danger');
        redirigir('index.php?vista=solicitudes_aprobacion_admin');
    }
}

// Comentario: Reutilizar función de formato de historial_solicitudes.php si es necesario.
if (!function_exists('formatear_tipo_solicitud')) {
    function formatear_tipo_solicitud($tipo_bd) {
        $mapa = [
            'vacacion' => 'Vacación',
            'material_escritorio' => 'Material de Escritorio',
            'activo_mueble_equipo' => 'Activo (Mueble/Equipo)',
            'otro' => 'Otro Tipo'
        ];
        return $mapa[$tipo_bd] ?? ucfirst(str_replace('_', ' ', $tipo_bd));
    }
}
?>
<h2>Aprobación de Solicitudes de Materiales y Activos (Dir. Admin.)</h2>
<p>Revise las siguientes solicitudes y tome la acción correspondiente (aprobar o rechazar).</p>

<?php
mensaje_flash('error_sol_aprob_admin');
mensaje_flash('error_sol_aprob_admin_accion');
mensaje_flash('exito_sol_aprob_admin');
?>

<?php if (empty($solicitudes_pendientes_admin)): ?>
    <div class="alert alert-info">No hay solicitudes de materiales o activos pendientes de aprobación en este momento.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID Sol.</th>
                    <th>Fecha Solicitud</th>
                    <th>Solicitante</th>
                    <th>Cargo Sol.</th>
                    <th>Tipo Solicitud</th>
                    <th>Descripción (Extracto)</th>
                    <th>Acciones Dir. Admin.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes_pendientes_admin as $sol): ?>
                    <tr>
                        <td><?php echo $sol['id_solicitud']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])); ?></td>
                        <td><?php echo htmlspecialchars($sol['solicitante_apellidos'] . ', ' . $sol['solicitante_nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($sol['solicitante_cargo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(formatear_tipo_solicitud($sol['tipo_solicitud']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php
                            $desc_corta = mb_substr(strip_tags($sol['descripcion_solicitud']), 0, 60);
                            echo htmlspecialchars($desc_corta, ENT_QUOTES, 'UTF-8') . (mb_strlen($sol['descripcion_solicitud']) > 60 ? '...' : '');
                            ?>
                            <button class="boton-tabla ver-detalle-solicitud-admin"
                                    data-id-solicitud="<?php echo $sol['id_solicitud']; ?>"
                                    data-descripcion="<?php echo htmlspecialchars(nl2br(strip_tags($sol['descripcion_solicitud'])), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Ver Descripción Completa">👁️</button>
                        </td>
                        <td>
                            <form action="index.php?vista=solicitudes_aprobacion_admin&pagina=<?php echo $pagina_actual; ?>" method="POST" class="form-accion-bandeja">
                                <input type="hidden" name="id_solicitud" value="<?php echo $sol['id_solicitud']; ?>">
                                <div class="grupo-formulario-sm">
                                    <label for="motivo_admin_<?php echo $sol['id_solicitud']; ?>">Comentario/Motivo Decisión:</label>
                                    <textarea name="motivo_decision_admin" id="motivo_admin_<?php echo $sol['id_solicitud']; ?>" rows="2" placeholder="Opcional para aprobar, obligatorio para rechazar"></textarea>
                                </div>
                                <button type="submit" name="accion_aprobacion_solicitud_admin" value="aprobar_solicitud_admin" class="boton-tabla exito confirmar-accion" data-mensaje-confirmacion="¿Está seguro de APROBAR esta solicitud?" title="Aprobar Solicitud">✔️ Aprobar</button>
                                <button type="submit" name="accion_aprobacion_solicitud_admin" value="rechazar_solicitud_admin" class="boton-tabla error confirmar-accion" data-mensaje-confirmacion="¿Está seguro de RECHAZAR esta solicitud? Se requerirá un motivo." title="Rechazar Solicitud">❌ Rechazar</button>
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
                    <li class="page-item"><a class="page-link" href="index.php?vista=solicitudes_aprobacion_admin&pagina=<?php echo $pagina_actual - 1; ?>">Anterior</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="index.php?vista=solicitudes_aprobacion_admin&pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($pagina_actual < $total_paginas): ?>
                    <li class="page-item"><a class="page-link" href="index.php?vista=solicitudes_aprobacion_admin&pagina=<?php echo $pagina_actual + 1; ?>">Siguiente</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<!-- Modal para ver detalle de descripción (reutilizar el de historial si es similar) -->
<div id="modalDetalleSolicitudAdmin" class="modal-sigi oculto">
    <div class="modal-contenido-sigi">
        <span class="modal-cerrar-sigi" onclick="document.getElementById('modalDetalleSolicitudAdmin').classList.add('oculto');">&times;</span>
        <h4>Descripción Completa de Solicitud (ID: <span id="modalIdSolicitudFullAdmin"></span>)</h4>
        <div id="modalDescripcionCompletaFullAdmin" class="modal-texto-scroll"></div>
    </div>
</div>

<style>
/* Comentario: Estilos heredados o similares a otras bandejas de aprobación. */
.form-accion-bandeja .grupo-formulario-sm { margin-bottom: 5px; }
.form-accion-bandeja .grupo-formulario-sm label { font-size: 0.8em; }
.form-accion-bandeja textarea { width: 100%; font-size: 0.9em; padding: 3px; border: 1px solid #ccc; border-radius: 3px; }
.form-accion-bandeja .boton-tabla { margin-top: 5px; margin-right: 5px; font-size:0.85em; padding: 4px 8px;}
.boton-tabla.exito { background-color: var(--color-exito); color:white; }
.boton-tabla.error { background-color: var(--color-error); color:white; }
.modal-texto-scroll { white-space: pre-wrap; background-color:#f9f9f9; padding:10px; border-radius:4px; max-height:300px; overflow-y:auto; border: 1px solid #eee;}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalAdmin = document.getElementById('modalDetalleSolicitudAdmin');
    const modalIdAdmin = document.getElementById('modalIdSolicitudFullAdmin');
    const modalDescAdmin = document.getElementById('modalDescripcionCompletaFullAdmin');

    document.querySelectorAll('.ver-detalle-solicitud-admin').forEach(boton => {
        boton.addEventListener('click', function() {
            if(modalIdAdmin) modalIdAdmin.textContent = this.dataset.idSolicitud;
            if(modalDescAdmin) modalDescAdmin.innerHTML = this.dataset.descripcion;
            if(modalAdmin) modalAdmin.classList.remove('oculto');
        });
    });

    const modalCerrarBtnAdmin = modalAdmin ? modalAdmin.querySelector('.modal-cerrar-sigi') : null;
    if(modalCerrarBtnAdmin) {
        modalCerrarBtnAdmin.onclick = function() {
            if(modalAdmin) modalAdmin.classList.add('oculto');
        }
    }
    if(modalAdmin) {
        modalAdmin.addEventListener('click', function(event) {
            if (event.target === modalAdmin) {
                modalAdmin.classList.add('oculto');
            }
        });
    }
});
</script>

<?php
// Comentario: Fin del archivo vistas/solicitudes_aprobacion_admin.php
?>
