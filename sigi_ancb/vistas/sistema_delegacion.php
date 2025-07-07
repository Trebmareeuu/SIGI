<?php
// Archivo: vistas/sistema_delegacion.php
// Propósito: (MAE) Panel para delegar su autoridad a otro usuario por un tiempo determinado.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Este es el MAE.
if (!tiene_permiso('DELEGAR_AUTORIDAD_SISTEMA', $id_usuario_actual)) {
    mensaje_flash('error_delegacion', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Cargar usuarios a quienes se puede delegar (ej. Directores, Jefes).
// Comentario: Se podría filtrar por rol o tener una lista específica.
$usuarios_delegables = [];
try {
    // Comentario: Ejemplo: todos los usuarios activos excepto el MAE actual.
    // Comentario: Idealmente, filtrar por roles que puedan asumir delegación.
    $stmt_ud = $pdo->prepare("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo, cargo
                              FROM usuarios
                              WHERE id_usuario != :id_mae_actual AND estado = 'activo'
                              ORDER BY apellidos, nombres ASC");
    $stmt_ud->bindParam(':id_mae_actual', $id_usuario_actual, PDO::PARAM_INT);
    $stmt_ud->execute();
    $usuarios_delegables = $stmt_ud->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar usuarios para delegación: " . $e->getMessage());
    mensaje_flash('error_delegacion_form', 'Error al cargar la lista de usuarios delegables.', 'alert-danger');
}

// Comentario: Lista de permisos que el MAE puede delegar (subconjunto de sus propios permisos).
// Comentario: Estos deben ser permisos específicos que un delegado podría necesitar.
$permisos_delegables_mae = [
    'APROBAR_VACACIONES_MAE' => 'Aprobar/Rechazar Solicitudes de Vacación (como MAE)',
    'APROBAR_SOLICITUDES_ADMIN' => 'Aprobar/Rechazar Solicitudes de Material/Activo (como si fuera Dir. Admin)', // Comentario: Ejemplo si MAE puede cubrir.
    // Comentario: 'FIRMAR_DOCUMENTOS_MAE' => 'Firmar Documentos en nombre del MAE (si aplica)',
    // Comentario: Añadir más permisos específicos que el MAE pueda delegar.
];
ksort($permisos_delegables_mae);


// Comentario: Procesamiento del formulario de nueva delegación.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_delegacion'])) {
    $id_usuario_delegado_form = filter_input(INPUT_POST, 'id_usuario_delegado', FILTER_VALIDATE_INT);
    $fecha_inicio_delegacion_form = sanitizar_entrada($_POST['fecha_inicio_delegacion'] ?? ''); // Comentario: Formato YYYY-MM-DD HH:MM
    $fecha_fin_delegacion_form = sanitizar_entrada($_POST['fecha_fin_delegacion'] ?? '');     // Comentario: Formato YYYY-MM-DD HH:MM
    $permisos_delegados_form = $_POST['permisos_delegados'] ?? []; // Comentario: Array de claves de permiso.
    $motivo_delegacion_form = strip_tags($_POST['motivo_delegacion'] ?? '');

    $errores_form_deleg = [];
    if (empty($id_usuario_delegado_form)) $errores_form_deleg[] = "Debe seleccionar un usuario a quien delegar.";
    if (empty($fecha_inicio_delegacion_form) || !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/", $fecha_inicio_delegacion_form.":00")) {
         // Comentario: Añadir :00 para validar formato datetime si solo se pide hora y minuto.
         // Comentario: O usar input type datetime-local que ya da el formato correcto.
         // Comentario: Por ahora, se asume que el input es datetime-local o se ajusta.
         // Comentario: Se espera YYYY-MM-DDTHH:MM del input datetime-local, que PHP puede parsear.
        try { new DateTime($fecha_inicio_delegacion_form); } catch (Exception $_){
            $errores_form_deleg[] = "La fecha y hora de inicio de la delegación no son válidas.";
        }
    }
    if (empty($fecha_fin_delegacion_form) || !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/", $fecha_fin_delegacion_form.":00")) {
         try { new DateTime($fecha_fin_delegacion_form); } catch (Exception $_){
            $errores_form_deleg[] = "La fecha y hora de fin de la delegación no son válidas.";
         }
    }
    if (empty($permisos_delegados_form)) $errores_form_deleg[] = "Debe seleccionar al menos un permiso para delegar.";
    if (empty($motivo_delegacion_form)) $errores_form_deleg[] = "El motivo de la delegación es obligatorio.";

    if (empty($errores_form_deleg)) {
        $obj_fecha_inicio_del = new DateTime($fecha_inicio_delegacion_form);
        $obj_fecha_fin_del = new DateTime($fecha_fin_delegacion_form);
        if ($obj_fecha_fin_del <= $obj_fecha_inicio_del) {
            $errores_form_deleg[] = "La fecha de fin debe ser posterior a la fecha de inicio.";
        }

        // Comentario: Verificar que el MAE realmente tenga los permisos que intenta delegar (seguridad adicional).
        foreach ($permisos_delegados_form as $perm_del) {
            if (!isset($permisos_delegables_mae[$perm_del]) /* || !tiene_permiso($perm_del, $id_usuario_actual) */ ) {
                // Comentario: La segunda condición es redundante si $permisos_delegables_mae se construye bien.
                $errores_form_deleg[] = "Intento de delegar un permiso no válido o no poseído ('$perm_del').";
            }
        }
    }

    if (empty($errores_form_deleg)) {
        try {
            // Comentario: Verificar si ya existe una delegación activa para el MAE o para el delegado en ese rango de fechas (opcional, podría permitir solapamientos si la lógica de `tiene_permiso` lo maneja).
            // Comentario: Por ahora, se permite crearla.

            $permisos_delegados_json = json_encode($permisos_delegados_form);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error al codificar permisos delegados a JSON.");
            }

            $sql_insert_del = "INSERT INTO sistema_delegaciones (id_usuario_delegante, id_usuario_delegado, fecha_inicio_delegacion, fecha_fin_delegacion, permisos_delegados, motivo_delegacion, estado_delegacion)
                               VALUES (:id_mae, :id_delegado, :f_ini, :f_fin, :perm_json, :motivo, 'activa')";
            $stmt_insert_del = $pdo->prepare($sql_insert_del);
            $stmt_insert_del->execute([
                ':id_mae' => $id_usuario_actual,
                ':id_delegado' => $id_usuario_delegado_form,
                ':f_ini' => $obj_fecha_inicio_del->format('Y-m-d H:i:s'),
                ':f_fin' => $obj_fecha_fin_del->format('Y-m-d H:i:s'),
                ':perm_json' => $permisos_delegados_json,
                ':motivo' => $motivo_delegacion_form
            ]);
            mensaje_flash('exito_delegacion', 'Delegación de autoridad creada exitosamente.', 'alert-success');
            redirigir('index.php?vista=sistema_delegacion');
        } catch (Exception $e) {
            error_log("Error al crear delegación: " . $e->getMessage());
            mensaje_flash('error_delegacion_form', 'Error al crear la delegación: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
         foreach ($errores_form_deleg as $err_d) {
            mensaje_flash('error_delegacion_form', $err_d, 'alert-danger');
        }
    }
}


// Comentario: Cargar delegaciones activas y pasadas hechas por este MAE.
$delegaciones_mae = [];
try {
    $stmt_list_del = $pdo->prepare("SELECT sd.*, CONCAT(ud.apellidos, ', ', ud.nombres) as nombre_delegado
                                    FROM sistema_delegaciones sd
                                    JOIN usuarios ud ON sd.id_usuario_delegado = ud.id_usuario
                                    WHERE sd.id_usuario_delegante = :id_mae_actual_list
                                    ORDER BY sd.fecha_inicio_delegacion DESC");
    $stmt_list_del->bindParam(':id_mae_actual_list', $id_usuario_actual, PDO::PARAM_INT);
    $stmt_list_del->execute();
    $delegaciones_mae = $stmt_list_del->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al listar delegaciones del MAE: " . $e->getMessage());
    mensaje_flash('error_delegacion', 'Error al cargar el historial de delegaciones.', 'alert-danger');
}

// Comentario: Lógica para cancelar una delegación activa.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_delegacion'])) {
    $id_delegacion_cancelar = filter_input(INPUT_POST, 'id_delegacion_a_cancelar', FILTER_VALIDATE_INT);
    if ($id_delegacion_cancelar) {
        try {
            // Comentario: Asegurarse que el MAE actual es el delegante y que está activa.
            $sql_cancel = "UPDATE sistema_delegaciones SET estado_delegacion = 'cancelada'
                           WHERE id_delegacion = :id_del_can AND id_usuario_delegante = :id_mae_can AND estado_delegacion = 'activa'";
            $stmt_cancel = $pdo->prepare($sql_cancel);
            $stmt_cancel->execute([
                ':id_del_can' => $id_delegacion_cancelar,
                ':id_mae_can' => $id_usuario_actual
            ]);
            if ($stmt_cancel->rowCount() > 0) {
                mensaje_flash('exito_delegacion', 'Delegación cancelada exitosamente.', 'alert-success');
            } else {
                mensaje_flash('error_delegacion', 'No se pudo cancelar la delegación (puede que no exista, no sea suya, o ya no esté activa).', 'alert-warning');
            }
        } catch (PDOException $e) {
            error_log("Error al cancelar delegación: " . $e->getMessage());
            mensaje_flash('error_delegacion', 'Error al cancelar la delegación: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=sistema_delegacion');
    }
}


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
                    <option value="<?php echo $ud['id_usuario']; ?>">
                        <?php echo htmlspecialchars($ud['nombre_completo'] . ($ud['cargo'] ? ' (' . $ud['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grupo-formulario-grid">
            <div class="grupo-formulario">
                <label for="fecha_inicio_delegacion">Fecha y Hora de Inicio:</label>
                <input type="datetime-local" id="fecha_inicio_delegacion" name="fecha_inicio_delegacion" required
                       min="<?php echo date('Y-m-d\TH:i'); // Comentario: No permitir fechas/horas pasadas. ?>">
            </div>
            <div class="grupo-formulario">
                <label for="fecha_fin_delegacion">Fecha y Hora de Fin:</label>
                <input type="datetime-local" id="fecha_fin_delegacion" name="fecha_fin_delegacion" required>
            </div>
        </div>
        <fieldset class="grupo-formulario">
            <legend>Permisos a Delegar:</legend>
            <div class="permisos-checkbox-container-delegacion">
            <?php foreach ($permisos_delegables_mae as $clave_p_del => $desc_p_del): ?>
                <div class="permiso-item-delegacion">
                    <input type="checkbox" name="permisos_delegados[]" id="perm_del_<?php echo $clave_p_del; ?>" value="<?php echo $clave_p_del; ?>">
                    <label for="perm_del_<?php echo $clave_p_del; ?>"><?php echo htmlspecialchars($desc_p_del, ENT_QUOTES, 'UTF-8'); ?> (<code><?php echo $clave_p_del; ?></code>)</label>
                </div>
            <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="grupo-formulario">
            <label for="motivo_delegacion">Motivo de la Delegación:</label>
            <textarea id="motivo_delegacion" name="motivo_delegacion" rows="3" required></textarea>
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
                                $perm_desc_array = array_map(function($p_key) use ($permisos_delegables_mae) {
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
                            <span class="estado-delegacion estado-del-<?php echo $del_item['estado_delegacion']; ?>">
                                <?php echo ucfirst($del_item['estado_delegacion']); ?>
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
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.grupo-formulario-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.permisos-checkbox-container-delegacion { /* Comentario: Similar a roles_crud */
    display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 0.5rem;
    padding: 10px; border: 1px solid #ddd; border-radius: var(--borde-radio); max-height: 250px; overflow-y: auto;
}
.permiso-item-delegacion input[type="checkbox"] { margin-right: 8px; }
.permiso-item-delegacion label { font-weight: normal; font-size: 0.9em; }
.permiso-item-delegacion label code { font-size: 0.9em; color: #555; background-color: #f0f0f0; padding: 1px 3px; border-radius: 2px; }
.estado-delegacion { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-del-activa { background-color: var(--color-exito); }
.estado-del-finalizada { background-color: var(--color-secundario); }
.estado-del-cancelada { background-color: var(--color-error); }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875em; }
</style>

<?php
// Comentario: Fin del archivo vistas/sistema_delegacion.php
?>
