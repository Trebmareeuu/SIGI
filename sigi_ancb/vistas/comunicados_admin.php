<?php
// Archivo: vistas/comunicados_admin.php
// Propósito: (Dir. Admin / MAE) CRUD para gestionar comunicados internos.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CREAR_COMUNICADOS', $id_usuario_actual)) {
    mensaje_flash('error_comunicados_admin', 'No tiene permisos para gestionar comunicados.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// --- Variables y Lógica para CRUD de Comunicados ---
$accion_com_crud = $_GET['accion_com_crud'] ?? 'listar'; // 'listar', 'crear', 'editar'
$id_comunicado_editar = null;
$comunicado_para_editar = null;
$roles_seleccionados_edicion = [];

// Comentario: Cargar roles para el selector de destinatarios.
$lista_roles_sistema = [];
try {
    $stmt_lr = $pdo->query("SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol ASC");
    $lista_roles_sistema = $stmt_lr->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* No crítico para listar, pero sí para crear/editar */ }


// Comentario: Cargar comunicado para edición.
if ($accion_com_crud === 'editar' && isset($_GET['id_com'])) {
    $id_comunicado_editar = filter_var($_GET['id_com'], FILTER_VALIDATE_INT);
    if ($id_comunicado_editar) {
        try {
            $stmt_edit_com = $pdo->prepare("SELECT * FROM comunicados WHERE id_comunicado = :id_com_ed");
            $stmt_edit_com->bindParam(':id_com_ed', $id_comunicado_editar, PDO::PARAM_INT);
            $stmt_edit_com->execute();
            $comunicado_para_editar = $stmt_edit_com->fetch(PDO::FETCH_ASSOC);
            if ($comunicado_para_editar) {
                $roles_seleccionados_edicion = json_decode($comunicado_para_editar['para_roles'] ?? '[]', true);
                if (json_last_error() !== JSON_ERROR_NONE) $roles_seleccionados_edicion = [];
            } else {
                mensaje_flash('error_comunicados_admin', 'Comunicado no encontrado para editar.', 'alert-danger');
                $accion_com_crud = 'listar';
            }
        } catch (PDOException $e) {
            error_log("Error al cargar comunicado para editar: " . $e->getMessage());
            mensaje_flash('error_comunicados_admin', 'Error al cargar datos del comunicado para edición.', 'alert-danger');
            $accion_com_crud = 'listar';
        }
    } else {
        mensaje_flash('error_comunicados_admin', 'ID de comunicado no válido para editar.', 'alert-danger');
        $accion_com_crud = 'listar';
    }
}


// Comentario: Guardar/Actualizar comunicado.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['guardar_comunicado_nuevo']) || isset($_POST['actualizar_comunicado_existente']))) {
    $id_comunicado_form = filter_input(INPUT_POST, 'id_comunicado_hidden', FILTER_VALIDATE_INT);
    $titulo_com_form = sanitizar_entrada($_POST['titulo_comunicado'] ?? '');
    $contenido_com_form = strip_tags($_POST['contenido_comunicado'] ?? '', '<p><br><ul><ol><li><strong><em><u><a><img>'); // Comentario: Permitir más HTML.
    $fecha_publicacion_form = sanitizar_entrada($_POST['fecha_publicacion'] ?? date('Y-m-d H:i:s')); // Comentario: Validar formato.
    $fecha_expiracion_form = sanitizar_entrada($_POST['fecha_expiracion'] ?? ''); // Comentario: Puede ser vacío.
    $para_roles_form = $_POST['para_roles_comunicado'] ?? []; // Array de id_rol.
    $estado_com_form = sanitizar_entrada($_POST['estado_comunicado'] ?? 'borrador');

    $errores_form_com = [];
    if (empty($titulo_com_form)) $errores_form_com[] = "El título del comunicado es obligatorio.";
    if (empty($contenido_com_form)) $errores_form_com[] = "El contenido del comunicado es obligatorio.";

    // Comentario: Validar fechas.
    try { $obj_fpub = new DateTime($fecha_publicacion_form); } catch(Exception $_) { $errores_form_com[] = "Formato de Fecha de Publicación inválido."; }
    if (!empty($fecha_expiracion_form)) {
        try {
            $obj_fexp = new DateTime($fecha_expiracion_form);
            if (isset($obj_fpub) && $obj_fexp <= $obj_fpub) $errores_form_com[] = "La fecha de expiración debe ser posterior a la de publicación.";
        } catch(Exception $_) { $errores_form_com[] = "Formato de Fecha de Expiración inválido."; }
    }

    $para_roles_json = null;
    if (!empty($para_roles_form) && is_array($para_roles_form)) {
        $para_roles_int = array_map('intval', $para_roles_form); // Comentario: Asegurar que sean enteros.
        $para_roles_json = json_encode($para_roles_int);
    } elseif (empty($para_roles_form)) {
        $para_roles_json = null; // Comentario: NULL para todos.
    }


    if (empty($errores_form_com)) {
        try {
            $params_sql_com = [
                ':titulo' => $titulo_com_form, ':contenido' => $contenido_com_form,
                ':f_pub' => isset($obj_fpub) ? $obj_fpub->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                ':f_exp' => (isset($obj_fexp) && !empty($fecha_expiracion_form)) ? $obj_fexp->format('Y-m-d H:i:s') : null,
                ':roles' => $para_roles_json, ':estado' => $estado_com_form
            ];

            if (isset($_POST['guardar_comunicado_nuevo'])) {
                $params_sql_com[':id_creator'] = $id_usuario_actual;
                $sql_op_com = "INSERT INTO comunicados (id_usuario_creador, titulo_comunicado, contenido_comunicado, fecha_publicacion, fecha_expiracion, para_roles, estado)
                               VALUES (:id_creator, :titulo, :contenido, :f_pub, :f_exp, :roles, :estado)";
                $stmt_op_com = $pdo->prepare($sql_op_com);
                $stmt_op_com->execute($params_sql_com);
                mensaje_flash('exito_comunicados_admin', 'Comunicado creado exitosamente.', 'alert-success');
            } elseif (isset($_POST['actualizar_comunicado_existente']) && $id_comunicado_form) {
                $params_sql_com[':id_com_upd'] = $id_comunicado_form;
                // Comentario: No se actualiza id_usuario_creador.
                $sql_op_com = "UPDATE comunicados SET titulo_comunicado = :titulo, contenido_comunicado = :contenido,
                               fecha_publicacion = :f_pub, fecha_expiracion = :f_exp, para_roles = :roles, estado = :estado
                               WHERE id_comunicado = :id_com_upd";
                $stmt_op_com = $pdo->prepare($sql_op_com);
                $stmt_op_com->execute($params_sql_com);
                mensaje_flash('exito_comunicados_admin', 'Comunicado actualizado exitosamente.', 'alert-success');
            }
            redirigir('index.php?vista=comunicados_admin');
        } catch (PDOException $e) {
            error_log("Error al guardar/actualizar comunicado: " . $e->getMessage());
            mensaje_flash('error_form_comunicado_submit', 'Error al procesar el comunicado: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_form_com as $err_c) {
            mensaje_flash('error_form_comunicado_validation', $err_c, 'alert-danger');
        }
        if (isset($_POST['actualizar_comunicado_existente'])) $accion_com_crud = 'editar'; // Comentario: Para repintar form.
    }
}

// Comentario: Lógica para ELIMINAR (o archivar definitivamente) comunicado.
if ($accion_com_crud === 'eliminar' && isset($_GET['id_com']) && isset($_GET['confirmar_eliminar_com'])) {
    $id_com_eliminar = filter_var($_GET['id_com'], FILTER_VALIDATE_INT);
    if ($id_com_eliminar) {
        try {
            // Comentario: Se podría cambiar a estado 'eliminado' si se quiere mantener un registro. Por ahora, borrado físico.
            $stmt_del_com = $pdo->prepare("DELETE FROM comunicados WHERE id_comunicado = :id_com_del");
            $stmt_del_com->bindParam(':id_com_del', $id_com_eliminar, PDO::PARAM_INT);
            $stmt_del_com->execute();
            if ($stmt_del_com->rowCount() > 0) {
                mensaje_flash('exito_comunicados_admin', 'Comunicado eliminado exitosamente.', 'alert-success');
            } else {
                mensaje_flash('error_comunicados_admin', 'No se pudo eliminar el comunicado o ya no existía.', 'alert-warning');
            }
        } catch (PDOException $e) {
            error_log("Error al eliminar comunicado: " . $e->getMessage());
            mensaje_flash('error_comunicados_admin', 'Error al eliminar el comunicado: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        mensaje_flash('error_comunicados_admin', 'ID de comunicado no válido para eliminar.', 'alert-danger');
    }
    redirigir('index.php?vista=comunicados_admin');
}


// --- Cargar lista de comunicados para mostrar ---
$lista_comunicados_admin = [];
if ($accion_com_crud === 'listar') {
    try {
        $stmt_list_com = $pdo->query("SELECT c.*, CONCAT(u.apellidos, ', ', u.nombres) as nombre_creador
                                      FROM comunicados c
                                      JOIN usuarios u ON c.id_usuario_creador = u.id_usuario
                                      ORDER BY c.fecha_publicacion DESC, c.id_comunicado DESC");
        $lista_comunicados_admin = $stmt_list_com->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al listar comunicados: " . $e->getMessage());
        mensaje_flash('error_comunicados_admin', 'Error al cargar la lista de comunicados.', 'alert-danger');
    }
}
?>

<h2>Gestión de Comunicados Internos</h2>

<?php
mensaje_flash('error_comunicados_admin');
mensaje_flash('exito_comunicados_admin');
mensaje_flash('error_form_comunicado_submit');
mensaje_flash('error_form_comunicado_validation');
?>

<?php if ($accion_com_crud === 'crear' || ($accion_com_crud === 'editar' && $comunicado_para_editar)): ?>
    <h3><?php echo ($accion_com_crud === 'crear') ? 'Crear Nuevo Comunicado' : 'Editar Comunicado'; ?></h3>
    <div class="card-sigi">
        <form action="index.php?vista=comunicados_admin" method="POST" class="validar-js">
            <?php if ($accion_com_crud === 'editar'): ?>
                <input type="hidden" name="id_comunicado_hidden" value="<?php echo $comunicado_para_editar['id_comunicado']; ?>">
            <?php endif; ?>

            <div class="grupo-formulario">
                <label for="titulo_comunicado">Título:</label>
                <input type="text" id="titulo_comunicado" name="titulo_comunicado" value="<?php echo htmlspecialchars($comunicado_para_editar['titulo_comunicado'] ?? ($_POST['titulo_comunicado'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
            </div>
            <div class="grupo-formulario">
                <label for="contenido_comunicado">Contenido:</label>
                <textarea id="contenido_comunicado" name="contenido_comunicado" rows="10" required class="editor-wysiwyg-simple"><?php echo htmlspecialchars($comunicado_para_editar['contenido_comunicado'] ?? ($_POST['contenido_comunicado'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <small>Puede usar HTML básico. Considere usar un editor WYSIWYG para mejor formato.</small>
            </div>
             <div class="grid-col-2">
                <div class="grupo-formulario">
                    <label for="fecha_publicacion">Fecha y Hora de Publicación:</label>
                    <input type="datetime-local" id="fecha_publicacion" name="fecha_publicacion" value="<?php echo htmlspecialchars(substr($comunicado_para_editar['fecha_publicacion'] ?? ($_POST['fecha_publicacion'] ?? date('Y-m-d\TH:i')), 0, 16), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="grupo-formulario">
                    <label for="fecha_expiracion">Fecha y Hora de Expiración (Opcional):</label>
                    <input type="datetime-local" id="fecha_expiracion" name="fecha_expiracion" value="<?php echo htmlspecialchars(substr($comunicado_para_editar['fecha_expiracion'] ?? ($_POST['fecha_expiracion'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="grupo-formulario">
                <label for="para_roles_comunicado">Dirigido a Roles (Opcional - si no selecciona, es para todos):</label>
                <select id="para_roles_comunicado" name="para_roles_comunicado[]" multiple size="5">
                    <?php foreach($lista_roles_sistema as $rol_s): ?>
                        <option value="<?php echo $rol_s['id_rol']; ?>" <?php if(in_array($rol_s['id_rol'], $roles_seleccionados_edicion)) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($rol_s['nombre_rol'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Mantenga Ctrl (o Cmd) para seleccionar múltiples roles.</small>
            </div>
            <div class="grupo-formulario">
                <label for="estado_comunicado">Estado:</label>
                <select id="estado_comunicado" name="estado_comunicado" required>
                    <option value="borrador" <?php if(($comunicado_para_editar['estado'] ?? ($_POST['estado_comunicado'] ?? 'borrador')) == 'borrador') echo 'selected'; ?>>Borrador</option>
                    <option value="publicado" <?php if(($comunicado_para_editar['estado'] ?? '') == 'publicado') echo 'selected'; ?>>Publicado</option>
                    <option value="archivado" <?php if(($comunicado_para_editar['estado'] ?? '') == 'archivado') echo 'selected'; ?>>Archivado</option>
                </select>
            </div>

            <?php if ($accion_com_crud === 'crear'): ?>
                <button type="submit" name="guardar_comunicado_nuevo" class="boton boton-primario">Guardar Comunicado</button>
            <?php else: ?>
                <button type="submit" name="actualizar_comunicado_existente" class="boton boton-primario">Actualizar Comunicado</button>
            <?php endif; ?>
            <a href="index.php?vista=comunicados_admin" class="boton boton-secundario">Cancelar</a>
        </form>
    </div>
<?php endif; ?>


<?php if ($accion_com_crud === 'listar'): ?>
    <p><a href="index.php?vista=comunicados_admin&accion_com_crud=crear" class="boton boton-exito">Crear Nuevo Comunicado</a></p>

    <?php if (empty($lista_comunicados_admin)): ?>
        <div class="alert alert-info">No hay comunicados registrados.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Creador</th>
                        <th>Fecha Publicación</th>
                        <th>Fecha Expiración</th>
                        <th>Dirigido a</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_comunicados_admin as $com_item):
                        $roles_dirigidos_nombres = "Todos";
                        if ($com_item['para_roles']) {
                            $ids_roles_dir = json_decode($com_item['para_roles'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($ids_roles_dir) && !empty($ids_roles_dir)) {
                                $nombres_temp = [];
                                foreach($ids_roles_dir as $id_r_dir) {
                                    foreach($lista_roles_sistema as $rol_sis_item) {
                                        if ($rol_sis_item['id_rol'] == $id_r_dir) {
                                            $nombres_temp[] = $rol_sis_item['nombre_rol'];
                                            break;
                                        }
                                    }
                                }
                                if(!empty($nombres_temp)) $roles_dirigidos_nombres = implode(', ', $nombres_temp);
                            }
                        }
                    ?>
                        <tr>
                            <td><?php echo $com_item['id_comunicado']; ?></td>
                            <td><?php echo htmlspecialchars($com_item['titulo_comunicado'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($com_item['nombre_creador'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($com_item['fecha_publicacion'])); ?></td>
                            <td><?php echo $com_item['fecha_expiracion'] ? date('d/m/Y H:i', strtotime($com_item['fecha_expiracion'])) : '<em>Permanente</em>'; ?></td>
                            <td title="<?php echo htmlspecialchars($roles_dirigidos_nombres, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo mb_substr($roles_dirigidos_nombres, 0, 30) . (mb_strlen($roles_dirigidos_nombres) > 30 ? '...' : ''); ?>
                            </td>
                            <td><span class="estado-comunicado estado-com-<?php echo $com_item['estado']; ?>"><?php echo ucfirst($com_item['estado']); ?></span></td>
                            <td>
                                <a href="index.php?vista=comunicados_admin&accion_com_crud=editar&id_com=<?php echo $com_item['id_comunicado']; ?>" class="boton-tabla editar" title="Editar">✏️</a>
                                <a href="index.php?vista=comunicados_admin&accion_com_crud=eliminar&id_com=<?php echo $com_item['id_comunicado']; ?>&confirmar_eliminar_com=1"
                                   class="boton-tabla eliminar confirmar-accion"
                                   data-mensaje-confirmacion="¿Está seguro de ELIMINAR el comunicado '<?php echo htmlspecialchars(addslashes($com_item['titulo_comunicado']), ENT_QUOTES, 'UTF-8'); ?>'?"
                                   title="Eliminar">🗑️</a>
                                <!-- Comentario: Podría haber un botón para "Ver Comunicado" que lleve a la vista de usuario. -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<style>
/* Comentario: Estilos heredados y algunos específicos. */
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra_caja); margin-bottom: 1.5rem; }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.grid-col-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
#para_roles_comunicado { min-height: 100px; }
.estado-comunicado { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-com-publicado { background-color: var(--color-exito); }
.estado-com-borrador { background-color: var(--color-advertencia); color: #333; }
.estado-com-archivado { background-color: var(--color-secundario); }
.boton-tabla.editar { background-color: var(--color-advertencia); color:black; }
.boton-tabla.eliminar { background-color: var(--color-error); color:white; }
/* Comentario:textarea.editor-wysiwyg-simple { min-height: 200px; } */
</style>

<?php
// Comentario: Fin del archivo vistas/comunicados_admin.php
?>
