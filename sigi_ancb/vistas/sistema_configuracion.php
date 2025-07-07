<?php
// Archivo: vistas/sistema_configuracion.php
// Propósito: (Sistemas) Panel para configuraciones generales del sistema.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CONFIGURAR_SISTEMA', $id_usuario_actual)) {
    mensaje_flash('error_sys_config', 'No tiene permisos para acceder a la configuración del sistema.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Cargar todas las configuraciones de la tabla sistema_configuracion.
$configuraciones = [];
try {
    $stmt_configs = $pdo->query("SELECT id_config, clave_config, valor_config, descripcion_config, tipo_dato FROM sistema_configuracion ORDER BY clave_config ASC");
    $configuraciones = $stmt_configs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar configuraciones del sistema: " . $e->getMessage());
    mensaje_flash('error_sys_config', 'Error al cargar las configuraciones del sistema.', 'alert-danger');
}

// Comentario: Procesamiento de la actualización de configuraciones.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_configuraciones'])) {
    $errores_config = [];
    $actualizaciones_exitosas = 0;

    $pdo->beginTransaction();
    try {
        foreach ($configuraciones as $conf) {
            $clave_post = 'config_' . $conf['id_config']; // Comentario: Nombre del campo en el formulario.

            if (isset($_POST[$clave_post])) {
                $nuevo_valor = $_POST[$clave_post];

                // Comentario: Sanitizar/Validar según el tipo_dato.
                switch ($conf['tipo_dato']) {
                    case 'texto':
                    case 'ruta_archivo': // Comentario: Rutas podrían necesitar validación específica.
                        $nuevo_valor_validado = strip_tags($nuevo_valor); // Comentario: Simple sanitización.
                        break;
                    case 'numero':
                        if (!is_numeric($nuevo_valor)) {
                            $errores_config[] = "El valor para '" . htmlspecialchars($conf['descripcion_config'], ENT_QUOTES, 'UTF-8') . "' debe ser numérico.";
                            continue 2; // Comentario: Saltar a la siguiente configuración.
                        }
                        $nuevo_valor_validado = $nuevo_valor; // Comentario: Se guarda como string, la app lo castea.
                        break;
                    case 'booleano':
                        // Comentario: Checkboxes no envían valor si no están marcados.
                        // Comentario: Para un input type="checkbox", si está marcado, el valor es 'on' o el value asignado.
                        // Comentario: Aquí se asume que el formulario enviará '1' o '0', o se ajusta.
                        // Comentario: Si se usa un select 'Sí'/'No', es más fácil.
                        // Comentario: Si usamos un checkbox, el valor enviado es el del atributo 'value' si está marcado.
                        // Comentario: Si no se marca, no se envía. Por eso, se verifica si existe en POST.
                        $nuevo_valor_validado = isset($_POST[$clave_post]) ? '1' : '0'; // Comentario: '1' para true, '0' para false.
                        // Comentario: Si el checkbox tiene value="true", entonces sería:
                        // $nuevo_valor_validado = (isset($_POST[$clave_post]) && $_POST[$clave_post] === 'true') ? 'true' : 'false';
                        break;
                    case 'json':
                        // Comentario: Validar si es un JSON válido.
                        json_decode($nuevo_valor);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                             $errores_config[] = "El valor para '" . htmlspecialchars($conf['descripcion_config'], ENT_QUOTES, 'UTF-8') . "' no es un JSON válido.";
                            continue 2;
                        }
                        $nuevo_valor_validado = $nuevo_valor; // Comentario: Guardar como string.
                        break;
                    default:
                        $nuevo_valor_validado = strip_tags($nuevo_valor); // Comentario: Por defecto, tratar como texto.
                }

                // Comentario: Si el valor ha cambiado, actualizarlo.
                if ($nuevo_valor_validado !== $conf['valor_config']) {
                    $sql_update_conf = "UPDATE sistema_configuracion SET valor_config = :valor WHERE id_config = :id_conf";
                    $stmt_update_conf = $pdo->prepare($sql_update_conf);
                    $stmt_update_conf->execute([':valor' => $nuevo_valor_validado, ':id_conf' => $conf['id_config']]);
                    if ($stmt_update_conf->rowCount() > 0) {
                        $actualizaciones_exitosas++;
                    }
                }
            } elseif ($conf['tipo_dato'] === 'booleano') {
                // Comentario: Si es booleano y no está en POST, significa que el checkbox no fue marcado (valor false o '0').
                // Comentario: Actualizar solo si el valor actual en BD era '1' (o true).
                if ($conf['valor_config'] === '1' || strtolower($conf['valor_config']) === 'true') {
                    $sql_update_conf_bool = "UPDATE sistema_configuracion SET valor_config = '0' WHERE id_config = :id_conf_bool";
                    $stmt_update_conf_bool = $pdo->prepare($sql_update_conf_bool);
                    $stmt_update_conf_bool->execute([':id_conf_bool' => $conf['id_config']]);
                     if ($stmt_update_conf_bool->rowCount() > 0) {
                        $actualizaciones_exitosas++;
                    }
                }
            }
        }

        if (!empty($errores_config)) {
            $pdo->rollBack();
            foreach ($errores_config as $err_c) {
                mensaje_flash('error_sys_config_save', $err_c, 'alert-danger');
            }
        } else {
            $pdo->commit();
            if ($actualizaciones_exitosas > 0) {
                mensaje_flash('exito_sys_config_save', "$actualizaciones_exitosas configuración(es) actualizada(s) exitosamente.", 'alert-success');
            } else {
                mensaje_flash('info_sys_config_save', "No se realizaron cambios en la configuración.", 'alert-info');
            }
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al guardar configuraciones del sistema: " . $e->getMessage());
        mensaje_flash('error_sys_config_save', 'Error al guardar las configuraciones: ' . $e->getMessage(), 'alert-danger');
    }
    redirigir('index.php?vista=sistema_configuracion'); // Comentario: Recargar para mostrar cambios y mensajes.
}


?>
<h2>Configuración General del Sistema</h2>
<p>Modifique los parámetros de configuración del sistema. Los cambios pueden afectar el funcionamiento global.</p>

<?php
mensaje_flash('error_sys_config');
mensaje_flash('error_sys_config_save');
mensaje_flash('exito_sys_config_save');
mensaje_flash('info_sys_config_save');
?>

<?php if (empty($configuraciones)): ?>
    <div class="alert alert-warning">No hay parámetros de configuración definidos en la base de datos.</div>
<?php else: ?>
    <form action="index.php?vista=sistema_configuracion" method="POST" class="validar-js">
        <div class="table-responsive">
            <table class="tabla-datos tabla-configuracion">
                <thead>
                    <tr>
                        <th>Parámetro de Configuración (Clave)</th>
                        <th>Descripción</th>
                        <th>Valor Actual</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($configuraciones as $config):
                        $nombre_campo_form = 'config_' . $config['id_config'];
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($config['clave_config'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars($config['descripcion_config'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php
                                // Comentario: Renderizar el input adecuado según el tipo_dato.
                                switch ($config['tipo_dato']):
                                    case 'numero': ?>
                                        <input type="number" name="<?php echo $nombre_campo_form; ?>" value="<?php echo htmlspecialchars($config['valor_config'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control-sm">
                                        <?php break;
                                    case 'booleano':
                                        // Comentario: Usar un checkbox para booleanos. El valor '1' se asume como true.
                                        $isChecked = ($config['valor_config'] === '1' || strtolower($config['valor_config']) === 'true');
                                    ?>
                                        <input type="checkbox" name="<?php echo $nombre_campo_form; ?>" value="1" <?php echo $isChecked ? 'checked' : ''; ?> class="form-check-input-sm">
                                        <?php break;
                                    case 'json': ?>
                                        <textarea name="<?php echo $nombre_campo_form; ?>" rows="3" class="form-control-sm" style="font-family: monospace;"><?php echo htmlspecialchars($config['valor_config'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <?php break;
                                    case 'ruta_archivo': ?>
                                         <input type="text" name="<?php echo $nombre_campo_form; ?>" value="<?php echo htmlspecialchars($config['valor_config'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control-sm" placeholder="Ej: img/logos/mi_logo.png">
                                        <?php break;
                                    case 'texto':
                                    default:
                                        // Comentario: Usar textarea para textos largos, input para cortos.
                                        if (strlen($config['valor_config'] ?? '') > 80) { ?>
                                            <textarea name="<?php echo $nombre_campo_form; ?>" rows="3" class="form-control-sm"><?php echo htmlspecialchars($config['valor_config'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <?php } else { ?>
                                            <input type="text" name="<?php echo $nombre_campo_form; ?>" value="<?php echo htmlspecialchars($config['valor_config'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control-sm">
                                        <?php }
                                        break;
                                endswitch;
                                ?>
                            </td>
                             <td><small class="text-muted"><?php echo ucfirst($config['tipo_dato']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="grupo-formulario acciones-formulario mt-3">
            <button type="submit" name="guardar_configuraciones" class="boton boton-primario">Guardar Cambios en Configuración</button>
            <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
        </div>
    </form>
<?php endif; ?>

<style>
.tabla-configuracion input[type="text"],
.tabla-configuracion input[type="number"],
.tabla-configuracion textarea {
    width: 100%;
    padding: 0.3rem 0.5rem;
    font-size: 0.9em;
    border: 1px solid #ccc;
    border-radius: var(--borde-radio);
}
.tabla-configuracion textarea {
    min-height: 60px;
    resize: vertical;
}
.tabla-configuracion .form-check-input-sm {
    transform: scale(1.2); /* Comentario: Hacer checkbox un poco más grande. */
    margin-left: 5px;
}
.text-muted { color: #6c757d !important; }
</style>

<?php
// Comentario: Fin del archivo vistas/sistema_configuracion.php
?>
