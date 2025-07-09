<?php
// Archivo: vistas/personal_ficha_crud.php (VERSIÓN CORREGIDA - SOLO PRESENTACIÓN)
// Propósito: (Presupuesto) CRUD para gestionar la ficha del personal - Parte Visual HTML.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Las variables como $fichas_personal, $accion_ficha_crud, $ficha_para_editar, $usuarios_sin_ficha, etc.,
// Comentario: son definidas en el archivo logica/personal_ficha_crud_logica.php
?>
<h2>Gestión de Fichas de Personal</h2>

<?php
mensaje_flash('error_ficha_crud');
mensaje_flash('exito_ficha_crud');
mensaje_flash('error_form_ficha_submit');
mensaje_flash('error_form_ficha_validation');
?>

<?php if ($accion_ficha_crud === 'crear' || ($accion_ficha_crud === 'editar' && $ficha_para_editar)): ?>
    <h3><?php echo ($accion_ficha_crud === 'crear') ? 'Crear Nueva Ficha de Personal' : 'Editar Ficha de: ' . htmlspecialchars(($ficha_para_editar['nombres_usr'] ?? '') . ' ' . ($ficha_para_editar['apellidos_usr'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
    <div class="card-sigi">
        <form action="index.php?vista=personal_ficha_crud" method="POST" class="validar-js form-ficha-personal">
            <?php if ($accion_ficha_crud === 'editar' && isset($ficha_para_editar['id_ficha_personal'])): ?>
                <input type="hidden" name="id_ficha_hidden" value="<?php echo $ficha_para_editar['id_ficha_personal']; ?>">
            <?php endif; ?>

            <fieldset>
                <legend>Datos del Funcionario</legend>
                <div class="grupo-formulario">
                    <label for="id_usuario_ficha">Usuario del Sistema Asociado:</label>
                    <?php if ($accion_ficha_crud === 'editar' && isset($id_usuario_asociado_ficha)): ?>
                        <input type="hidden" name="id_usuario_ficha" value="<?php echo $id_usuario_asociado_ficha; ?>">
                        <p><strong><?php echo htmlspecialchars(($ficha_para_editar['apellidos_usr'] ?? '') . ', ' . ($ficha_para_editar['nombres_usr'] ?? '') . ' (' . ($ficha_para_editar['nombre_usuario'] ?? '') . ')', ENT_QUOTES, 'UTF-8'); ?></strong> (No editable desde aquí)</p>
                    <?php else: // Modo Crear ?>
                        <select id="id_usuario_ficha" name="id_usuario_ficha" required>
                            <option value="">-- Seleccione un usuario --</option>
                            <?php foreach ($usuarios_sin_ficha as $usf): ?>
                                <option value="<?php echo $usf['id_usuario']; ?>" <?php if(($_POST['id_usuario_ficha'] ?? '') == $usf['id_usuario']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($usf['nombre_completo'] . ($usf['cargo'] ? ' (' . $usf['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(empty($usuarios_sin_ficha) && $accion_ficha_crud === 'crear'): ?> <small class="text-danger">No hay usuarios activos sin ficha para asignar. Debe crear usuarios primero o asegurarse que no tengan ya una ficha.</small> <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="grupo-formulario">
                    <label for="codigo_empleado">Código de Empleado:</label>
                    <input type="text" id="codigo_empleado" name="codigo_empleado" value="<?php echo htmlspecialchars($ficha_para_editar['codigo_empleado'] ?? ($_POST['codigo_empleado'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="20">
                </div>
            </fieldset>

            <fieldset>
                <legend>Información Personal</legend>
                <div class="grid-col-2">
                    <div class="grupo-formulario">
                        <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($ficha_para_editar['fecha_nacimiento'] ?? ($_POST['fecha_nacimiento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="grupo-formulario">
                        <label for="lugar_nacimiento">Lugar de Nacimiento:</label>
                        <input type="text" id="lugar_nacimiento" name="lugar_nacimiento" value="<?php echo htmlspecialchars($ficha_para_editar['lugar_nacimiento'] ?? ($_POST['lugar_nacimiento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
                    </div>
                    <div class="grupo-formulario">
                        <label for="nacionalidad">Nacionalidad:</label>
                        <input type="text" id="nacionalidad" name="nacionalidad" value="<?php echo htmlspecialchars($ficha_para_editar['nacionalidad'] ?? ($_POST['nacionalidad'] ?? 'Boliviana'), ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                    </div>
                    <div class="grupo-formulario">
                        <label for="ci_numero">Nro. Cédula Identidad:</label>
                        <input type="text" id="ci_numero" name="ci_numero" value="<?php echo htmlspecialchars($ficha_para_editar['ci_numero'] ?? ($_POST['ci_numero'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="20">
                    </div>
                    <div class="grupo-formulario">
                        <label for="ci_expedido_en">CI Expedido en (Dpto.):</label>
                        <select id="ci_expedido_en" name="ci_expedido_en">
                            <option value="">-- Seleccionar --</option>
                            <?php $deptos = ['LP', 'CB', 'SC', 'OR', 'PT', 'CH', 'TJ', 'BE', 'PD'];
                                  $val_ci_exp = $ficha_para_editar['ci_expedido_en'] ?? ($_POST['ci_expedido_en'] ?? ''); ?>
                            <?php foreach($deptos as $d): ?>
                                <option value="<?php echo $d; ?>" <?php if($val_ci_exp == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grupo-formulario">
                        <label for="estado_civil">Estado Civil:</label>
                        <select id="estado_civil" name="estado_civil">
                            <option value="">-- Seleccionar --</option>
                            <?php $estados_c = ['soltero_a', 'casado_a', 'viudo_a', 'divorciado_a', 'conviviente'];
                                  $val_est_c = $ficha_para_editar['estado_civil'] ?? ($_POST['estado_civil'] ?? ''); ?>
                            <?php foreach($estados_c as $ec): ?>
                                <option value="<?php echo $ec; ?>" <?php if($val_est_c == $ec) echo 'selected'; ?>><?php echo ucfirst(str_replace('_','/',$ec)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grupo-formulario">
                    <label for="domicilio_actual">Domicilio Actual:</label>
                    <textarea id="domicilio_actual" name="domicilio_actual" rows="2"><?php echo htmlspecialchars($ficha_para_editar['domicilio_actual'] ?? ($_POST['domicilio_actual'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend>Información de Contacto de Emergencia</legend>
                 <div class="grid-col-3">
                    <div class="grupo-formulario">
                        <label for="contacto_emergencia_nombre">Nombre Contacto Emergencia:</label>
                        <input type="text" id="contacto_emergencia_nombre" name="contacto_emergencia_nombre" value="<?php echo htmlspecialchars($ficha_para_editar['contacto_emergencia_nombre'] ?? ($_POST['contacto_emergencia_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
                    </div>
                     <div class="grupo-formulario">
                        <label for="relacion_contacto_emergencia">Relación/Parentesco:</label>
                        <input type="text" id="relacion_contacto_emergencia" name="relacion_contacto_emergencia" value="<?php echo htmlspecialchars($ficha_para_editar['relacion_contacto_emergencia'] ?? ($_POST['relacion_contacto_emergencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="50">
                    </div>
                    <div class="grupo-formulario">
                        <label for="telefono_emergencia">Teléfono Contacto Emergencia:</label>
                        <input type="text" id="telefono_emergencia" name="telefono_emergencia" value="<?php echo htmlspecialchars($ficha_para_editar['telefono_emergencia'] ?? ($_POST['telefono_emergencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="30">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Formación y Datos Laborales</legend>
                <div class="grid-col-2">
                    <div class="grupo-formulario">
                        <label for="nivel_educativo">Nivel Educativo Alcanzado:</label>
                        <input type="text" id="nivel_educativo" name="nivel_educativo" value="<?php echo htmlspecialchars($ficha_para_editar['nivel_educativo'] ?? ($_POST['nivel_educativo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
                    </div>
                    <div class="grupo-formulario">
                        <label for="profesion">Profesión:</label>
                        <input type="text" id="profesion" name="profesion" value="<?php echo htmlspecialchars($ficha_para_editar['profesion'] ?? ($_POST['profesion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="150">
                    </div>
                    <div class="grupo-formulario">
                        <label for="fecha_ingreso_institucion">Fecha de Ingreso a la Institución:</label>
                        <input type="date" id="fecha_ingreso_institucion" name="fecha_ingreso_institucion" value="<?php echo htmlspecialchars($ficha_para_editar['fecha_ingreso_institucion'] ?? ($_POST['fecha_ingreso_institucion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                     <div class="grupo-formulario">
                        <label for="tipo_contrato">Tipo de Contrato:</label>
                        <input type="text" id="tipo_contrato" name="tipo_contrato" value="<?php echo htmlspecialchars($ficha_para_editar['tipo_contrato'] ?? ($_POST['tipo_contrato'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                    </div>
                    <div class="grupo-formulario">
                        <label for="salario_base">Salario Base (Bs.):</label>
                        <input type="text" id="salario_base" name="salario_base" value="<?php echo htmlspecialchars($ficha_para_editar['salario_base'] ?? ($_POST['salario_base'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej: 5000.50">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Datos de Seguridad Social y Médicos</legend>
                 <div class="grid-col-3">
                    <div class="grupo-formulario">
                        <label for="afp_asociada">AFP Asociada:</label>
                        <input type="text" id="afp_asociada" name="afp_asociada" value="<?php echo htmlspecialchars($ficha_para_editar['afp_asociada'] ?? ($_POST['afp_asociada'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                    </div>
                    <div class="grupo-formulario">
                        <label for="nua_cua">NUA/CUA:</label>
                        <input type="text" id="nua_cua" name="nua_cua" value="<?php echo htmlspecialchars($ficha_para_editar['nua_cua'] ?? ($_POST['nua_cua'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="50">
                    </div>
                    <div class="grupo-formulario">
                        <label for="grupo_sanguineo">Grupo Sanguíneo y Factor RH:</label>
                        <input type="text" id="grupo_sanguineo" name="grupo_sanguineo" value="<?php echo htmlspecialchars($ficha_para_editar['grupo_sanguineo'] ?? ($_POST['grupo_sanguineo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="10" placeholder="Ej: O+">
                    </div>
                </div>
                <div class="grupo-formulario">
                    <label for="alergias_conocidas">Alergias Conocidas:</label>
                    <textarea id="alergias_conocidas" name="alergias_conocidas" rows="2"><?php echo htmlspecialchars($ficha_para_editar['alergias_conocidas'] ?? ($_POST['alergias_conocidas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="grupo-formulario">
                    <label for="observaciones_medicas">Otras Observaciones Médicas Relevantes:</label>
                    <textarea id="observaciones_medicas" name="observaciones_medicas" rows="2"><?php echo htmlspecialchars($ficha_para_editar['observaciones_medicas'] ?? ($_POST['observaciones_medicas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </fieldset>

            <div class="acciones-formulario mt-3">
                <?php if ($accion_ficha_crud === 'crear'): ?>
                    <button type="submit" name="guardar_ficha_nueva" class="boton boton-primario">Crear Ficha</button>
                <?php else: // Modo Edición ?>
                    <button type="submit" name="actualizar_ficha_existente" class="boton boton-primario">Actualizar Ficha</button>
                <?php endif; ?>
                <a href="index.php?vista=personal_ficha_crud" class="boton boton-secundario">Cancelar</a>
            </div>
        </form>
    </div>
<?php else: // Comentario: $accion_ficha_crud es 'listar' o un error llevó aquí. ?>
    <p><a href="index.php?vista=personal_ficha_crud&accion_ficha_crud=crear" class="boton boton-exito">Crear Nueva Ficha de Personal</a></p>

    <?php if (empty($fichas_personal)): // $fichas_personal se define en el archivo de lógica ?>
        <div class="alert alert-info">No hay fichas de personal registradas.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>Cód. Empleado</th>
                        <th>Apellidos</th>
                        <th>Nombres</th>
                        <th>CI</th>
                        <th>Cargo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fichas_personal as $ficha_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ficha_item['codigo_empleado'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ficha_item['apellidos'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ficha_item['nombres'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ficha_item['ci_numero'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ficha_item['cargo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="index.php?vista=personal_ficha_detalle&id_ficha=<?php echo $ficha_item['id_ficha_personal']; ?>" class="boton-tabla ver" title="Ver Detalle Ficha">👁️</a>
                                <a href="index.php?vista=personal_ficha_crud&accion_ficha_crud=editar&id_ficha=<?php echo $ficha_item['id_ficha_personal']; ?>" class="boton-tabla editar" title="Editar Ficha">✏️</a>
                                <!-- Comentario: La eliminación de fichas podría ser compleja. Considerar desactivar. -->
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
.card-sigi h3, .form-ficha-personal fieldset legend { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.form-ficha-personal fieldset { border: 1px solid #ddd; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--borde-radio); }
.form-ficha-personal fieldset legend { font-size: 1.1em; font-weight: bold; padding: 0 0.5em; width: auto; }
.grid-col-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
.grid-col-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
.boton-tabla.ver { background-color: var(--color-info); color:white; }
.boton-tabla.editar { background-color: var(--color-advertencia); color:black; }
.text-danger { color: var(--color-error); }
.text-warning { color: var(--color-advertencia); } /* Comentario: Añadido para mensajes de advertencia. */
</style>

<?php
// Comentario: Fin del archivo vistas/personal_ficha_crud.php (SOLO PRESENTACIÓN)
?>
