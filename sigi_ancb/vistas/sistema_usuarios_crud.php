<?php
// Archivo: vistas/sistema_usuarios_crud.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: (Sistemas) CRUD completo para gestionar usuarios - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables $usuarios, $roles_disponibles, $accion_crud, $usuario_para_editar, etc.,
// Comentario: son definidas en el archivo logica/sistema_usuarios_crud_logica.php
// Comentario: que es incluido por index.php ANTES de este archivo de vista.
?>

<h2>Gestión de Usuarios del Sistema</h2>

<?php
// Comentario: Mostrar mensajes flash que hayan sido establecidos en la lógica.
mensaje_flash('error_usuarios_crud');
mensaje_flash('exito_usuarios_crud');
mensaje_flash('error_form_usuario_submit'); // Comentario: Para errores de BD al guardar/actualizar.
mensaje_flash('error_form_usuario_validation'); // Comentario: Para errores de validación del form.
?>

<?php
// Comentario: Determinar si se muestra el formulario de creación/edición o la lista.
// Comentario: La variable $accion_crud es definida en el archivo de lógica.
if ($accion_crud === 'crear' || ($accion_crud === 'editar' && $usuario_para_editar)):
?>
    <h3><?php echo ($accion_crud === 'crear') ? 'Crear Nuevo Usuario' : 'Editar Usuario: ' . htmlspecialchars($usuario_para_editar['nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
    <div class="card-sigi">
        <form action="index.php?vista=sistema_usuarios_crud" method="POST" class="validar-js">
            <?php if ($accion_crud === 'editar' && isset($usuario_para_editar['id_usuario'])): ?>
                <input type="hidden" name="id_usuario_hidden" value="<?php echo $usuario_para_editar['id_usuario']; ?>">
            <?php endif; ?>

            <div class="grupo-formulario">
                <label for="nombre_usuario">Nombre de Usuario (para login):</label>
                <input type="text" id="nombre_usuario" name="nombre_usuario" value="<?php echo htmlspecialchars($usuario_para_editar['nombre_usuario'] ?? ($_POST['nombre_usuario'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
            </div>
            <div class="grupo-formulario">
                <label for="nombres">Nombres:</label>
                <input type="text" id="nombres" name="nombres" value="<?php echo htmlspecialchars($usuario_para_editar['nombres'] ?? ($_POST['nombres'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            </div>
            <div class="grupo-formulario">
                <label for="apellidos">Apellidos:</label>
                <input type="text" id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($usuario_para_editar['apellidos'] ?? ($_POST['apellidos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            </div>
            <div class="grupo-formulario">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario_para_editar['email'] ?? ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
            </div>
            <div class="grupo-formulario">
                <label for="id_rol">Rol:</label>
                <select id="id_rol" name="id_rol" required>
                    <option value="">-- Seleccione un rol --</option>
                    <?php foreach($roles_disponibles as $rol): // $roles_disponibles se define en el archivo de lógica ?>
                        <option value="<?php echo $rol['id_rol']; ?>" <?php if (($usuario_para_editar['id_rol'] ?? ($_POST['id_rol'] ?? '')) == $rol['id_rol']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($rol['nombre_rol'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grupo-formulario">
                <label for="cargo">Cargo:</label>
                <input type="text" id="cargo" name="cargo" value="<?php echo htmlspecialchars($usuario_para_editar['cargo'] ?? ($_POST['cargo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
            </div>
            <div class="grupo-formulario">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" <?php echo ($accion_crud === 'crear') ? 'required' : ''; ?> minlength="8">
                <?php if ($accion_crud === 'editar'): ?><small>Dejar en blanco para no cambiar la contraseña actual.</small><?php endif; ?>
            </div>
            <div class="grupo-formulario">
                <label for="confirmar_contrasena">Confirmar Contraseña:</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" <?php echo ($accion_crud === 'crear') ? 'required' : ''; ?> minlength="8">
            </div>
             <div class="grupo-formulario">
                <label for="estado">Estado de la Cuenta:</label>
                <select id="estado" name="estado" required>
                    <option value="activo" <?php if (($usuario_para_editar['estado'] ?? ($_POST['estado'] ?? 'activo')) == 'activo') echo 'selected'; ?>>Activo</option>
                    <option value="inactivo" <?php if (($usuario_para_editar['estado'] ?? '') == 'inactivo') echo 'selected'; ?>>Inactivo</option>
                    <option value="bloqueado" <?php if (($usuario_para_editar['estado'] ?? '') == 'bloqueado') echo 'selected'; ?>>Bloqueado</option>
                </select>
            </div>

            <?php if ($accion_crud === 'crear'): ?>
                <button type="submit" name="guardar_usuario_nuevo" class="boton boton-primario">Crear Usuario</button>
            <?php else: // Modo edición ?>
                <button type="submit" name="actualizar_usuario_existente" class="boton boton-primario">Actualizar Usuario</button>
            <?php endif; ?>
            <a href="index.php?vista=sistema_usuarios_crud" class="boton boton-secundario">Cancelar</a>
        </form>
    </div>
<?php else: // Comentario: $accion_crud es 'listar' o un error llevó aquí. ?>
    <p><a href="index.php?vista=sistema_usuarios_crud&accion_crud=crear" class="boton boton-exito">Crear Nuevo Usuario</a></p>

    <?php if (empty($usuarios)): // $usuarios se define en el archivo de lógica ?>
        <div class="alert alert-info">No hay usuarios registrados en el sistema.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombres</th>
                        <th>Apellidos</th>
                        <th>Email</th>
                        <th>Cargo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usr): ?>
                        <tr>
                            <td><?php echo $usr['id_usuario']; ?></td>
                            <td><?php echo htmlspecialchars($usr['nombre_usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['apellidos'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['cargo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($usr['nombre_rol'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="estado-usuario estado-<?php echo $usr['estado']; ?>"><?php echo ucfirst($usr['estado']); ?></span></td>
                            <td>
                                <a href="index.php?vista=sistema_usuarios_crud&accion_crud=editar&id_user=<?php echo $usr['id_usuario']; ?>" class="boton-tabla editar" title="Editar">✏️</a>
                                <?php
                                // Comentario: $id_usuario_actual se define en el archivo de lógica.
                                if ($usr['id_usuario'] != $id_usuario_actual):
                                ?>
                                <a href="index.php?vista=sistema_usuarios_crud&accion_crud=eliminar&id_user=<?php echo $usr['id_usuario']; ?>&confirmar_eliminar=1"
                                   class="boton-tabla eliminar confirmar-accion"
                                   data-mensaje-confirmacion="¿Está seguro de ELIMINAR al usuario '<?php echo htmlspecialchars($usr['nombre_usuario'], ENT_QUOTES, 'UTF-8'); ?>'? Esta acción podría ser irreversible y afectar registros asociados."
                                   title="Eliminar">🗑️</a>
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
/* Comentario: Estilos específicos para esta vista (si son necesarios y no están en estilos.css global). */
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); margin-bottom: 1.5rem; }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.estado-usuario { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-activo { background-color: var(--color-exito); }
.estado-inactivo { background-color: var(--color-secundario); }
.estado-bloqueado { background-color: var(--color-error); }
.boton-tabla.editar { background-color: var(--color-advertencia); color:black; }
.boton-tabla.eliminar { background-color: var(--color-error); color:white; }
</style>

<?php
// Comentario: Fin del archivo vistas/sistema_usuarios_crud.php (SOLO PRESENTACIÓN)
?>
