<?php
// Archivo: vistas/sistema_delegacion.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: (MAE) Panel para delegar su autoridad - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $usuarios_delegables, $permisos_delegables_mae, $delegaciones_mae
// Comentario: son definidas en logica/sistema_delegacion_logica.php
?>
<h2>Delegación de Autoridad (MAE)</h2>
<p>Desde este panel, puede delegar temporalmente algunos de sus permisos a otro usuario.</p>

<?php
mensaje_flash('error_delegacion');
mensaje_flash('error_delegacion_form');
mensaje_flash('exito_delegacion');
?>

<div class="card-sigi mb-3">
    <h3>Crear Nueva Delegación</h3>
    <form action="index.php?vista=sistema_delegacion" method="POST" class="validar-js">
        <div class="grupo-formulario">
            <label for="id_usuario_delegado">Delegar a Usuario:</label>
            <select id="id_usuario_delegado" name="id_usuario_delegado" required>
                <option value="">-- Seleccione un usuario --</option>
                <?php foreach ($usuarios_delegables as $ud): ?>
                    <option value="<?php echo $ud['id_usuario']; ?>" <?php if(($_POST['id_usuario_delegado'] ?? '') == $ud['id_usuario']) echo 'selected'; ?> >
                        <?php echo htmlspecialchars($ud['nombre_completo'] . ($ud['cargo'] ? ' (' . $ud['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grupo-formulario-grid">
            <div class="grupo-formulario">
                <label for="fecha_inicio_delegacion">Fecha y Hora de Inicio:</label>
                <input type="datetime-local" id="fecha_inicio_delegacion" name="fecha_inicio_delegacion"
                       value="<?php echo htmlspecialchars($_POST['fecha_inicio_delegacion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required
                       min="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
            <div class="grupo-formulario">
                <label for="fecha_fin_delegacion">Fecha y Hora de Fin:</label>
                <input type="datetime-local" id="fecha_fin_delegacion" name="fecha_fin_delegacion"
                       value="<?php echo htmlspecialchars($_POST['fecha_fin_delegacion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
        </div>
        <fieldset class="grupo-formulario">
            <legend>Permisos a Delegar:</legend>
            <div class="permisos-checkbox-container-delegacion">
            <?php
            $permisos_delegados_post = $_POST['permisos_delegados'] ?? [];
            foreach ($permisos_delegables_mae as $clave_p_del => $desc_p_del): ?>
                <div class="permiso-item-delegacion">
                    <input type="checkbox" name="permisos_delegados[]" id="perm_del_<?php echo $clave_p_del; ?>" value="<?php echo $clave_p_del; ?>"
                           <?php if (in_array($clave_p_del, $permisos_delegados_post)) echo 'checked'; ?> >
                    <label for="perm_del_<?php echo $clave_p_del; ?>"><?php echo htmlspecialchars($desc_p_del, ENT_QUOTES, 'UTF-8'); ?> (<code><?php echo $clave_p_del; ?></code>)</label>
                </div>
            <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="grupo-formulario">
            <label for="motivo_delegacion">Motivo de la Delegación:</label>
            <textarea id="motivo_delegacion" name="motivo_delegacion" rows="3" required><?php echo htmlspecialchars($_POST['motivo_delegacion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <button type="submit" name="crear_delegacion" class="boton boton-primario">Crear Delegación</button>
    </form>
</div>


<div class="card-sigi">
    <h3>Historial de Delegaciones Realizadas</h3>
    <?php if (empty($delegaciones_mae)): ?>
        <p>No ha realizado ninguna delegación todavía.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>ID Del.</th>
                        <th>Delegado A</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Permisos Delegados</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($delegaciones_mae as $del_item):
                        $permisos_lista_str = "";
                        if ($del_item['permisos_delegados']) {
                            $perm_array = json_decode($del_item['permisos_delegados'], true);
                            if (is_array($perm_array)) {
                                $perm_desc_array = array_map(function($p_key) use ($permisos_delegables_mae) { // Comentario: Usar la lista correcta de permisos.
                                    return $permisos_delegables_mae[$p_key] ?? $p_key;
                                }, $perm_array);
                                $permisos_lista_str = implode('; ', $perm_desc_array);
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo $del_item['id_delegacion']; ?></td>
                        <td><?php echo htmlspecialchars($del_item['nombre_delegado'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($del_item['fecha_inicio_delegacion'])); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($del_item['fecha_fin_delegacion'])); ?></td>
                        <td title="<?php echo htmlspecialchars($permisos_lista_str, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo count(json_decode($del_item['permisos_delegados'], true) ?: []); ?> permiso(s)
                        </td>
                        <td title="<?php echo htmlspecialchars($del_item['motivo_delegacion'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo mb_substr(strip_tags($del_item['motivo_delegacion']), 0, 30) . (mb_strlen($del_item['motivo_delegacion']) > 30 ? '...' : ''); ?>
                        </td>
                        <td>
                            <span class="estado-delegacion estado-del-<?php echo htmlspecialchars($del_item['estado_delegacion'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo ucfirst(htmlspecialchars($del_item['estado_delegacion'], ENT_QUOTES, 'UTF-8')); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($del_item['estado_delegacion'] === 'activa' && new DateTime($del_item['fecha_fin_delegacion']) > new DateTime() ): ?>
                                <form action="index.php?vista=sistema_delegacion" method="POST" class="confirmar-accion" data-mensaje-confirmacion="¿Está seguro de CANCELAR esta delegación activa?">
                                    <input type="hidden" name="id_delegacion_a_cancelar" value="<?php echo $del_item['id_delegacion']; ?>">
                                    <button type="submit" name="cancelar_delegacion" class="boton-tabla error btn-sm" title="Cancelar Delegación">❌ Cancelar</button>
                                </form>
                            <?php else: echo 'N/A'; endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
/* Comentario: Estilos específicos para esta vista (si son necesarios y no están en estilos.css global). */
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.grupo-formulario-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.permisos-checkbox-container-delegacion {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 0.5rem;
    padding: 10px; border: 1px solid #ddd; border-radius: var(--borde-radio); max-height: 250px; overflow-y: auto;
}
.permiso-item-delegacion input[type="checkbox"] { margin-right: 8px; vertical-align: middle; }
.permiso-item-delegacion label { font-weight: normal; font-size: 0.9em; display: inline; } /* Comentario: Para que el label esté al lado. */
.permiso-item-delegacion label code { font-size: 0.9em; color: #555; background-color: #f0f0f0; padding: 1px 3px; border-radius: 2px; }
.estado-delegacion { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-del-activa { background-color: var(--color-exito); }
.estado-del-finalizada { background-color: var(--color-secundario); }
.estado-del-cancelada { background-color: var(--color-error); }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875em; }
.boton-tabla.error { background-color: var(--color-error); color:white; } /* Comentario: Asegurar que se vea. */
</style>

<?php
// Comentario: Fin del archivo vistas/sistema_delegacion.php (SOLO PRESENTACIÓN)
?>
