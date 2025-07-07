<?php
// Archivo: vistas/sistema_roles_crud.php
// Propósito: (Sistemas) CRUD para gestionar los roles y sus permisos.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CRUD_ROLES_SISTEMA', $id_usuario_actual)) {
    mensaje_flash('error_roles_crud', 'No tiene permisos para gestionar roles y permisos.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Lista de todos los permisos disponibles en el sistema (definidos manualmente o desde una tabla de permisos).
// Comentario: Estos son los que se mostrarán como checkboxes. Deben coincidir con los usados en tiene_permiso().
$permisos_sistema_disponibles = [
    // Comentario: Perfil y Dashboard (generalmente para todos los logueados)
    'VER_PERFIL' => 'Ver propio perfil',
    'EDITAR_PERFIL' => 'Editar propio perfil',
    'VER_DASHBOARD' => 'Acceder al Dashboard',
    // Comentario: Correspondencia
    'VER_CORRESPONDENCIA_PROPIA' => 'Ver su correspondencia (asignada/creada)',
    'REDACTAR_CORRESPONDENCIA_INTERNA' => 'Redactar correspondencia interna',
    'REGISTRAR_CORRESPONDENCIA_EXTERNA' => 'Registrar correspondencia externa (Secretaría)',
    'VER_DETALLE_DOCUMENTO' => 'Ver detalle de cualquier documento (si tiene acceso a él)',
    // 'DERIVAR_DOCUMENTO' => 'Derivar documentos', // Comentario: Más granularidad podría ser necesaria
    // 'FINALIZAR_DOCUMENTO' => 'Finalizar documentos',
    // 'ARCHIVAR_DOCUMENTO' => 'Archivar documentos',
    // Comentario: Solicitudes (Usuario)
    'SOLICITAR_VACACION' => 'Solicitar vacaciones',
    'SOLICITAR_MATERIAL' => 'Solicitar material de escritorio',
    'SOLICITAR_ACTIVO' => 'Solicitar activos (muebles/equipos)',
    'VER_HISTORIAL_SOLICITUDES_PROPIAS' => 'Ver historial de sus propias solicitudes',
    // Comentario: Solicitudes (Gestión/Aprobación)
    'CONTROLAR_SOLICITUDES_VACACION_SECRETARIA' => 'Controlar/Derivar solicitudes de vacación (Secretaría)',
    'APROBAR_VACACIONES_MAE' => 'Aprobar/Rechazar solicitudes de vacación (MAE)',
    'APROBAR_SOLICITUDES_ADMIN' => 'Aprobar/Rechazar solicitudes de material/activo (Dir. Admin.)',
    // Comentario: Dirección Administrativa
    'GESTIONAR_PAGOS' => 'Gestionar pagos recurrentes y efectuados',
    'IMPORTAR_BIOMETRICO' => 'Importar archivo CSV del biométrico',
    'VER_REPORTES_ASISTENCIA' => 'Ver reportes de asistencia',
    // Comentario: MAE
    'DELEGAR_AUTORIDAD_SISTEMA' => 'Delegar autoridad a otros usuarios (MAE)',
    // Comentario: Técnico de Sistemas (estos son permisos "macro" que ya tiene el rol)
    'CRUD_USUARIOS_SISTEMA' => 'Gestión CRUD de Usuarios (Sistemas)', // Comentario: Ya es un permiso del rol.
    'CRUD_ROLES_SISTEMA' => 'Gestión CRUD de Roles y Permisos (Sistemas)', // Comentario: Ya es un permiso del rol.
    'CONFIGURAR_SISTEMA' => 'Acceder a Configuración General del Sistema (Sistemas)',
    // Comentario: Presupuesto
    'CRUD_PERSONAL_FICHA' => 'Gestión CRUD de Fichas de Personal (Presupuesto)',
    'VER_DETALLE_PERSONAL_FICHA' => 'Ver detalle de Ficha de Personal (Presupuesto)', // Comentario: Generalmente parte del CRUD.
    // Comentario: Mensajero
    'VER_HOJA_RUTA_MENSAJERO' => 'Ver hoja de ruta y registrar descargos (Mensajero)',
    // Comentario: Activos Fijos
    'VER_INVENTARIO_ACTIVOS' => 'Ver inventario de activos fijos',
    'ASIGNAR_NUEVO_ACTIVO' => 'Codificar y asignar nuevo activo fijo',
    // Comentario: Archivo
    'GESTIONAR_PRESTAMOS_ARCHIVO' => 'Gestionar préstamos de expedientes (Archivo)',
    'RECEPCIONAR_EXPEDIENTES_ARCHIVO' => 'Recepcionar expedientes devueltos/archivados (Archivo)',
    // Comentario: Añadir más permisos granulares según sea necesario.
];
ksort($permisos_sistema_disponibles); // Comentario: Ordenar por clave para consistencia.


// Comentario: Variables y Lógica para listar roles.
$roles = [];
try {
    $sql_listar_roles = "SELECT id_rol, nombre_rol, descripcion_rol, permisos FROM roles ORDER BY nombre_rol ASC";
    $stmt_listar_roles = $pdo->query($sql_listar_roles);
    $roles = $stmt_listar_roles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al listar roles: " . $e->getMessage());
    mensaje_flash('error_roles_crud', 'Error al cargar la lista de roles.', 'alert-danger');
}

// Comentario: Acción (crear, editar, eliminar).
$accion_rol_crud = $_GET['accion_rol_crud'] ?? 'listar';
$id_rol_editar = null;
$rol_para_editar = null;
$permisos_rol_actual_edicion = []; // Comentario: Para checkboxes en edición.

if ($accion_rol_crud === 'editar' && isset($_GET['id_rol'])) {
    $id_rol_editar = filter_var($_GET['id_rol'], FILTER_VALIDATE_INT);
    if ($id_rol_editar) {
        try {
            $stmt_edit_rol = $pdo->prepare("SELECT * FROM roles WHERE id_rol = :id_rol_ed");
            $stmt_edit_rol->bindParam(':id_rol_ed', $id_rol_editar, PDO::PARAM_INT);
            $stmt_edit_rol->execute();
            $rol_para_editar = $stmt_edit_rol->fetch(PDO::FETCH_ASSOC);
            if ($rol_para_editar) {
                $permisos_rol_actual_edicion = json_decode($rol_para_editar['permisos'] ?? '{}', true);
                if (json_last_error() !== JSON_ERROR_NONE) $permisos_rol_actual_edicion = [];
            } else {
                mensaje_flash('error_roles_crud', 'Rol no encontrado para editar.', 'alert-danger');
                $accion_rol_crud = 'listar';
            }
        } catch (PDOException $e) {
            error_log("Error al cargar rol para editar: " . $e->getMessage());
            mensaje_flash('error_roles_crud', 'Error al cargar datos del rol para edición.', 'alert-danger');
            $accion_rol_crud = 'listar';
        }
    } else {
        mensaje_flash('error_roles_crud', 'ID de rol no válido para editar.', 'alert-danger');
        $accion_rol_crud = 'listar';
    }
}


// Comentario: Lógica para CREAR o ACTUALIZAR rol.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['guardar_rol_nuevo']) || isset($_POST['actualizar_rol_existente']))) {
    $id_rol_form = filter_input(INPUT_POST, 'id_rol_hidden', FILTER_VALIDATE_INT);
    $nombre_rol_form = sanitizar_entrada($_POST['nombre_rol'] ?? '');
    $descripcion_rol_form = strip_tags($_POST['descripcion_rol'] ?? '');
    $permisos_seleccionados_form = $_POST['permisos'] ?? []; // Comentario: Array de nombres de permisos.

    $errores_form_rol = [];
    if (empty($nombre_rol_form)) $errores_form_rol[] = "El nombre del rol es obligatorio.";

    // Comentario: Validar que el nombre del rol sea único (excepto para el mismo rol al editar).
    $id_excluir_rol_check = $id_rol_form ?: 0;
    try {
        $stmt_check_rol_nombre = $pdo->prepare("SELECT id_rol FROM roles WHERE nombre_rol = :nombre_r AND id_rol != :id_excluir_r");
        $stmt_check_rol_nombre->execute([':nombre_r' => $nombre_rol_form, ':id_excluir_r' => $id_excluir_rol_check]);
        if ($stmt_check_rol_nombre->fetch()) $errores_form_rol[] = "El nombre de rol '$nombre_rol_form' ya está en uso.";
    } catch (PDOException $e) {
        $errores_form_rol[] = "Error al verificar unicidad del nombre de rol: " . $e->getMessage();
    }

    // Comentario: Convertir array de permisos seleccionados a formato JSON para la BD.
    $permisos_json_para_bd = [];
    foreach ($permisos_sistema_disponibles as $clave_permiso => $desc_permiso) {
        $permisos_json_para_bd[$clave_permiso] = in_array($clave_permiso, $permisos_seleccionados_form);
    }
    $permisos_json_final = json_encode($permisos_json_para_bd);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errores_form_rol[] = "Error al procesar los permisos seleccionados (JSON).";
    }


    if (empty($errores_form_rol)) {
        try {
            if (isset($_POST['guardar_rol_nuevo'])) { // Comentario: CREAR.
                $sql_insert_rol = "INSERT INTO roles (nombre_rol, descripcion_rol, permisos) VALUES (:nr, :dr, :pr)";
                $stmt_op_rol = $pdo->prepare($sql_insert_rol);
                $stmt_op_rol->execute([
                    ':nr' => $nombre_rol_form, ':dr' => $descripcion_rol_form, ':pr' => $permisos_json_final
                ]);
                mensaje_flash('exito_roles_crud', 'Rol creado exitosamente.', 'alert-success');

            } elseif (isset($_POST['actualizar_rol_existente']) && $id_rol_form) { // Comentario: ACTUALIZAR.
                 $sql_update_rol = "UPDATE roles SET nombre_rol = :nr, descripcion_rol = :dr, permisos = :pr WHERE id_rol = :id_rol_upd";
                 $stmt_op_rol = $pdo->prepare($sql_update_rol);
                 $stmt_op_rol->execute([
                    ':nr' => $nombre_rol_form, ':dr' => $descripcion_rol_form, ':pr' => $permisos_json_final, ':id_rol_upd' => $id_rol_form
                ]);
                mensaje_flash('exito_roles_crud', 'Rol actualizado exitosamente.', 'alert-success');
            }
            redirigir('index.php?vista=sistema_roles_crud');
        } catch (PDOException $e) {
            error_log("Error al guardar/actualizar rol: " . $e->getMessage());
            mensaje_flash('error_form_rol_submit', 'Error al procesar la solicitud del rol: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_form_rol as $err_r) {
            mensaje_flash('error_form_rol_validation', $err_r, 'alert-danger');
        }
        // Comentario: Si estaba editando, mantener la acción para que el formulario se muestre de nuevo con errores.
        if (isset($_POST['actualizar_rol_existente'])) {
            $accion_rol_crud = 'editar';
            // Comentario: $rol_para_editar ya debería estar cargado. Los permisos seleccionados vendrían de $_POST.
            $permisos_rol_actual_edicion = $permisos_json_para_bd; // Comentario: Para repintar checkboxes.
        }
    }
}


// Comentario: Lógica para ELIMINAR rol.
if ($accion_rol_crud === 'eliminar' && isset($_GET['id_rol']) && isset($_GET['confirmar_eliminar_rol'])) {
    $id_rol_eliminar = filter_var($_GET['id_rol'], FILTER_VALIDATE_INT);
    if ($id_rol_eliminar) {
        // Comentario: Verificar si hay usuarios con este rol.
        $stmt_check_users_rol = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_rol = :id_rol_chk");
        $stmt_check_users_rol->bindParam(':id_rol_chk', $id_rol_eliminar, PDO::PARAM_INT);
        $stmt_check_users_rol->execute();
        $conteo_usuarios_con_rol = $stmt_check_users_rol->fetchColumn();

        if ($conteo_usuarios_con_rol > 0) {
            mensaje_flash('error_roles_crud', "No se puede eliminar el rol porque hay $conteo_usuarios_con_rol usuario(s) asignado(s) a él. Reasigne esos usuarios a otros roles primero.", 'alert-danger');
        } else {
            try {
                $stmt_del_rol = $pdo->prepare("DELETE FROM roles WHERE id_rol = :id_rol_del");
                $stmt_del_rol->bindParam(':id_rol_del', $id_rol_eliminar, PDO::PARAM_INT);
                $stmt_del_rol->execute();
                if ($stmt_del_rol->rowCount() > 0) {
                    mensaje_flash('exito_roles_crud', 'Rol eliminado exitosamente.', 'alert-success');
                } else {
                    mensaje_flash('error_roles_crud', 'No se pudo eliminar el rol o ya no existía.', 'alert-warning');
                }
            } catch (PDOException $e) {
                error_log("Error al eliminar rol: " . $e->getMessage());
                mensaje_flash('error_roles_crud', 'Error al eliminar el rol: ' . $e->getMessage(), 'alert-danger');
            }
        }
    } else {
        mensaje_flash('error_roles_crud', 'ID de rol no válido para eliminar.', 'alert-danger');
    }
    redirigir('index.php?vista=sistema_roles_crud');
}

?>
<h2>Gestión de Roles y Permisos</h2>

<?php
mensaje_flash('error_roles_crud');
mensaje_flash('exito_roles_crud');
mensaje_flash('error_form_rol_submit');
mensaje_flash('error_form_rol_validation');
?>

<?php if ($accion_rol_crud === 'crear' || ($accion_rol_crud === 'editar' && $rol_para_editar)): ?>
    <h3><?php echo ($accion_rol_crud === 'crear') ? 'Crear Nuevo Rol' : 'Editar Rol: ' . htmlspecialchars($rol_para_editar['nombre_rol'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
    <div class="card-sigi">
        <form action="index.php?vista=sistema_roles_crud" method="POST" class="validar-js">
            <?php if ($accion_rol_crud === 'editar'): ?>
                <input type="hidden" name="id_rol_hidden" value="<?php echo $rol_para_editar['id_rol']; ?>">
            <?php endif; ?>

            <div class="grupo-formulario">
                <label for="nombre_rol">Nombre del Rol:</label>
                <input type="text" id="nombre_rol" name="nombre_rol" value="<?php echo htmlspecialchars($rol_para_editar['nombre_rol'] ?? ($_POST['nombre_rol'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            </div>
            <div class="grupo-formulario">
                <label for="descripcion_rol">Descripción del Rol:</label>
                <textarea id="descripcion_rol" name="descripcion_rol" rows="3"><?php echo htmlspecialchars($rol_para_editar['descripcion_rol'] ?? ($_POST['descripcion_rol'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <fieldset class="grupo-formulario">
                <legend>Permisos Asignados al Rol:</legend>
                <div class="permisos-checkbox-container">
                <?php foreach ($permisos_sistema_disponibles as $clave_permiso => $descripcion_permiso): ?>
                    <div class="permiso-item">
                        <input type="checkbox" name="permisos[]" id="perm_<?php echo $clave_permiso; ?>" value="<?php echo $clave_permiso; ?>"
                               <?php
                               // Comentario: Si estamos editando y el permiso está en el rol, marcarlo.
                               // Comentario: O si es un re-POST con errores, usar los valores de $_POST['permisos'].
                               $permisos_a_chequear = isset($_POST['permisos']) ? $_POST['permisos'] : array_keys(array_filter($permisos_rol_actual_edicion));
                               if (in_array($clave_permiso, $permisos_a_chequear)) echo 'checked';
                               ?>>
                        <label for="perm_<?php echo $clave_permiso; ?>"><?php echo htmlspecialchars($descripcion_permiso, ENT_QUOTES, 'UTF-8'); ?> (<code><?php echo $clave_permiso; ?></code>)</label>
                    </div>
                <?php endforeach; ?>
                </div>
            </fieldset>

            <?php if ($accion_rol_crud === 'crear'): ?>
                <button type="submit" name="guardar_rol_nuevo" class="boton boton-primario">Crear Rol</button>
            <?php else: ?>
                <button type="submit" name="actualizar_rol_existente" class="boton boton-primario">Actualizar Rol</button>
            <?php endif; ?>
            <a href="index.php?vista=sistema_roles_crud" class="boton boton-secundario">Cancelar</a>
        </form>
    </div>
<?php endif; ?>


<?php if ($accion_rol_crud === 'listar'): ?>
    <p><a href="index.php?vista=sistema_roles_crud&accion_rol_crud=crear" class="boton boton-exito">Crear Nuevo Rol</a></p>

    <?php if (empty($roles)): ?>
        <div class="alert alert-info">No hay roles definidos en el sistema.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Rol</th>
                        <th>Descripción</th>
                        <th>Permisos Asignados (Conteo)</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $rol_item):
                        $permisos_del_rol_item = json_decode($rol_item['permisos'] ?? '{}', true);
                        $conteo_permisos_activos = 0;
                        if (is_array($permisos_del_rol_item)) {
                            foreach($permisos_del_rol_item as $estado_permiso) {
                                if ($estado_permiso === true) $conteo_permisos_activos++;
                            }
                        }
                    ?>
                        <tr>
                            <td><?php echo $rol_item['id_rol']; ?></td>
                            <td><?php echo htmlspecialchars($rol_item['nombre_rol'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($rol_item['descripcion_rol'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="text-align:center;"><?php echo $conteo_permisos_activos; ?> / <?php echo count($permisos_sistema_disponibles); ?></td>
                            <td>
                                <a href="index.php?vista=sistema_roles_crud&accion_rol_crud=editar&id_rol=<?php echo $rol_item['id_rol']; ?>" class="boton-tabla editar" title="Editar Rol y Permisos">✏️</a>
                                <!-- Comentario: No permitir eliminar roles fundamentales (ej. Administrador de Sistemas si es el único o si tiene ID fijo) -->
                                <?php if ($rol_item['nombre_rol'] !== 'Técnico de Sistemas'): // Comentario: Ejemplo de protección. ?>
                                <a href="index.php?vista=sistema_roles_crud&accion_rol_crud=eliminar&id_rol=<?php echo $rol_item['id_rol']; ?>&confirmar_eliminar_rol=1"
                                   class="boton-tabla eliminar confirmar-accion"
                                   data-mensaje-confirmacion="¿Está seguro de ELIMINAR el rol '<?php echo htmlspecialchars($rol_item['nombre_rol'], ENT_QUOTES, 'UTF-8'); ?>'? Esta acción no se puede deshacer y podría afectar a usuarios si no se reasignan."
                                   title="Eliminar Rol">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); margin-bottom: 1.5rem; }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.permisos-checkbox-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Comentario: Columnas responsivas para los checkboxes. */
    gap: 0.5rem 1rem; /* Comentario: Espacio entre items. */
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: var(--borde-radio);
    max-height: 400px; /* Comentario: Para evitar listas muy largas. */
    overflow-y: auto;
}
.permiso-item {
    display: flex;
    align-items: center;
}
.permiso-item input[type="checkbox"] {
    margin-right: 8px;
}
.permiso-item label {
    font-weight: normal; /* Comentario: Labels de checkbox no en negrita por defecto. */
    font-size: 0.9em;
    color: #333;
}
.permiso-item label code {
    font-size: 0.9em;
    color: #555;
    background-color: #f0f0f0;
    padding: 1px 3px;
    border-radius: 2px;
}
.boton-tabla.editar { background-color: var(--color-advertencia); color:black; }
.boton-tabla.eliminar { background-color: var(--color-error); color:white; }
</style>

<?php
// Comentario: Fin del archivo vistas/sistema_roles_crud.php
?>
