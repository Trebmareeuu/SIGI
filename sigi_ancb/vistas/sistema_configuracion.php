<?php
// Archivo: vistas/sistema_configuracion.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: (Sistemas) Panel para configuraciones generales del sistema - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: La variable $configuraciones es definida en logica/sistema_configuracion_logica.php
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
    <div class="alert alert-warning">No hay parámetros de configuración definidos en la base de datos. Contacte al desarrollador si esto es un error.</div>
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
                        // Comentario: Usar el valor de $_POST si existe (para repoblar en caso de error de validación no manejado con redirección)
                        // Comentario: Pero como la lógica ahora redirige, $config['valor_config'] siempre tendrá el valor de la BD.
                        $valor_actual_campo = $config['valor_config'];
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($config['clave_config'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars($config['descripcion_config'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php
                                switch ($config['tipo_dato']):
                                    case 'numero': ?>
                                        <input type="number" name="<?php echo $nombre_campo_form; ?>" value="<?php echo htmlspecialchars($valor_actual_campo, ENT_QUOTES, 'UTF-8'); ?>" class="form-control-sm">
                                        <?php break;
                                    case 'booleano':
                                        $isChecked = ($valor_actual_campo === '1' || strtolower($valor_actual_campo) === 'true');
                                    ?>
                                        <input type="checkbox" name="<?php echo $nombre_campo_form; ?>" value="1" <?php echo $isChecked ? 'checked' : ''; ?> class="form-check-input-sm">
                                        <?php break;
                                    case 'json': ?>
                                        <textarea name="<?php echo $nombre_campo_form; ?>" rows="3" class="form-control-sm" style="font-family: monospace;"><?php echo htmlspecialchars($valor_actual_campo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <?php break;
                                    case 'ruta_archivo': ?>
                                         <input type="text" name="<?php echo $nombre_campo_form; ?>" value="<?php echo htmlspecialchars($valor_actual_campo, ENT_QUOTES, 'UTF-8'); ?>" class="form-control-sm" placeholder="Ej: img/logos/mi_logo.png">
                                        <?php break;
                                    case 'texto':
                                    default:
                                        if (strlen($valor_actual_campo ?? '') > 80 || strpos($valor_actual_campo, "\n") !== false) { ?>
                                            <textarea name="<?php echo $nombre_campo_form; ?>" rows="3" class="form-control-sm"><?php echo htmlspecialchars($valor_actual_campo, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <?php } else { ?>
                                            <input type="text" name="<?php echo $nombre_campo_form; ?>" value="<?php echo htmlspecialchars($valor_actual_campo, ENT_QUOTES, 'UTF-8'); ?>" class="form-control-sm">
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
/* Comentario: Estilos específicos para esta vista (si son necesarios y no están en estilos.css global). */
.tabla-configuracion input[type="text"].form-control-sm,
.tabla-configuracion input[type="number"].form-control-sm,
.tabla-configuracion textarea.form-control-sm {
    width: 100%;
    padding: 0.3rem 0.5rem;
    font-size: 0.9em;
    border: 1px solid #ccc;
    border-radius: var(--borde-radio);
    box-sizing: border-box; /* Comentario: Asegurar que padding no aumente el tamaño total. */
}
.tabla-configuracion textarea.form-control-sm {
    min-height: 60px;
    resize: vertical;
}
.tabla-configuracion .form-check-input-sm {
    transform: scale(1.2);
    margin-left: 5px;
    vertical-align: middle;
}
.text-muted { color: #6c757d !important; }
</style>

<?php
// Comentario: Fin del archivo vistas/sistema_configuracion.php (SOLO PRESENTACIÓN)
?>
