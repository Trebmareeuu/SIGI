<?php
// Archivo: vistas/personal_ficha_detalle.php
// Propósito: (Presupuesto) Vista detallada de la ficha de un funcionario.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
// Comentario: El permiso VER_DETALLE_PERSONAL_FICHA o CRUD_PERSONAL_FICHA podría aplicar.
if (!tiene_permiso('VER_DETALLE_PERSONAL_FICHA', $id_usuario_actual) && !tiene_permiso('CRUD_PERSONAL_FICHA', $id_usuario_actual)) {
    mensaje_flash('error_ficha_detalle', 'No tiene permisos para ver detalles de fichas de personal.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

$id_ficha = filter_input(INPUT_GET, 'id_ficha', FILTER_VALIDATE_INT);

if (!$id_ficha) {
    mensaje_flash('error_ficha_detalle', 'ID de ficha no válido o no proporcionado.', 'alert-danger');
    redirigir('index.php?vista=personal_ficha_crud');
}

$ficha_detalle = null;
try {
    $sql_ficha = "SELECT pf.*,
                         u.nombre_usuario, u.nombres, u.apellidos, u.email as email_usuario, u.cargo as cargo_usuario, u.estado as estado_usuario,
                         r.nombre_rol
                  FROM personal_fichas pf
                  JOIN usuarios u ON pf.id_usuario = u.id_usuario
                  JOIN roles r ON u.id_rol = r.id_rol
                  WHERE pf.id_ficha_personal = :id_ficha_detalle";
    $stmt_ficha = $pdo->prepare($sql_ficha);
    $stmt_ficha->bindParam(':id_ficha_detalle', $id_ficha, PDO::PARAM_INT);
    $stmt_ficha->execute();
    $ficha_detalle = $stmt_ficha->fetch(PDO::FETCH_ASSOC);

    if (!$ficha_detalle) {
        mensaje_flash('error_ficha_detalle', "Ficha de personal con ID $id_ficha no encontrada.", 'alert-danger');
        redirigir('index.php?vista=personal_ficha_crud');
    }
} catch (PDOException $e) {
    error_log("Error al cargar detalle de ficha ID $id_ficha: " . $e->getMessage());
    mensaje_flash('error_ficha_detalle', 'Ocurrió un error al cargar los detalles de la ficha. Intente más tarde.', 'alert-danger');
    // Comentario: $ficha_detalle podría ser null, la vista debe manejar esto.
}

function display_dato_ficha($etiqueta, $valor, $formato_fecha = false) {
    $valor_mostrar = !empty($valor) ? htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') : '<em class="text-muted">No registrado</em>';
    if ($formato_fecha && !empty($valor) && $valor !== '0000-00-00') { // Comentario: Chequear fecha inválida también.
        try {
            $valor_mostrar = date('d/m/Y', strtotime($valor));
        } catch(Exception $_) {
            $valor_mostrar = htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . ' <em class="text-danger">(Fecha Inválida)</em>';
        }
    } elseif ($formato_fecha && (empty($valor) || $valor === '0000-00-00') ){
         $valor_mostrar = '<em class="text-muted">No registrada</em>';
    }
    echo "<tr><th>$etiqueta:</th><td>$valor_mostrar</td></tr>";
}

?>
<h2>Detalle de Ficha de Personal</h2>

<?php mensaje_flash('error_ficha_detalle'); ?>

<?php if ($ficha_detalle): ?>
    <div class="botones-accion-detalle mb-3">
        <a href="index.php?vista=personal_ficha_crud" class="boton boton-secundario">Volver al Listado</a>
        <?php if (tiene_permiso('CRUD_PERSONAL_FICHA', $id_usuario_actual)): ?>
        <a href="index.php?vista=personal_ficha_crud&accion_ficha_crud=editar&id_ficha=<?php echo $id_ficha; ?>" class="boton boton-primario">Editar Ficha</a>
        <?php endif; ?>
        <!-- Comentario: Botón para imprimir/PDF (requiere lógica adicional). -->
        <!-- <button onclick="window.print();" class="boton boton-info">Imprimir Ficha</button> -->
    </div>

    <div class="detalle-ficha-grid">
        <section class="card-sigi">
            <h3>Datos del Funcionario (Sistema)</h3>
            <table class="tabla-info-detalle">
                <?php display_dato_ficha('Nombres Completos', $ficha_detalle['nombres'] . ' ' . $ficha_detalle['apellidos']); ?>
                <?php display_dato_ficha('Nombre de Usuario', $ficha_detalle['nombre_usuario']); ?>
                <?php display_dato_ficha('Correo Electrónico (Sistema)', $ficha_detalle['email_usuario']); ?>
                <?php display_dato_ficha('Cargo (Sistema)', $ficha_detalle['cargo_usuario']); ?>
                <?php display_dato_ficha('Rol en Sistema', $ficha_detalle['nombre_rol']); ?>
                <?php display_dato_ficha('Estado Cuenta Sistema', ucfirst($ficha_detalle['estado_usuario'])); ?>
            </table>
        </section>

        <section class="card-sigi">
            <h3>Información Personal (Ficha)</h3>
            <table class="tabla-info-detalle">
                <?php display_dato_ficha('Código de Empleado', $ficha_detalle['codigo_empleado']); ?>
                <?php display_dato_ficha('Fecha de Nacimiento', $ficha_detalle['fecha_nacimiento'], true); ?>
                <?php display_dato_ficha('Lugar de Nacimiento', $ficha_detalle['lugar_nacimiento']); ?>
                <?php display_dato_ficha('Nacionalidad', $ficha_detalle['nacionalidad']); ?>
                <?php display_dato_ficha('Cédula de Identidad', $ficha_detalle['ci_numero'] . ' ' . $ficha_detalle['ci_expedido_en']); ?>
                <?php display_dato_ficha('Estado Civil', ucfirst(str_replace('_','/',$ficha_detalle['estado_civil'] ?? ''))); ?>
                <?php display_dato_ficha('Domicilio Actual', nl2br(htmlspecialchars($ficha_detalle['domicilio_actual'] ?? '', ENT_QUOTES, 'UTF-8'))); ?>
            </table>
        </section>

        <section class="card-sigi">
            <h3>Contacto de Emergencia</h3>
            <table class="tabla-info-detalle">
                <?php display_dato_ficha('Nombre Contacto', $ficha_detalle['contacto_emergencia_nombre']); ?>
                <?php display_dato_ficha('Relación/Parentesco', $ficha_detalle['relacion_contacto_emergencia']); ?>
                <?php display_dato_ficha('Teléfono Emergencia', $ficha_detalle['telefono_emergencia']); ?>
            </table>
        </section>

        <section class="card-sigi">
            <h3>Formación y Datos Laborales</h3>
             <table class="tabla-info-detalle">
                <?php display_dato_ficha('Nivel Educativo', $ficha_detalle['nivel_educativo']); ?>
                <?php display_dato_ficha('Profesión', $ficha_detalle['profesion']); ?>
                <?php display_dato_ficha('Fecha Ingreso Institución', $ficha_detalle['fecha_ingreso_institucion'], true); ?>
                <?php display_dato_ficha('Tipo de Contrato', $ficha_detalle['tipo_contrato']); ?>
                <?php display_dato_ficha('Salario Base (Bs.)', is_numeric($ficha_detalle['salario_base']) ? number_format((float)$ficha_detalle['salario_base'], 2, '.', ',') : $ficha_detalle['salario_base'] ); ?>
            </table>
        </section>

        <section class="card-sigi">
            <h3>Datos de Seguridad Social y Médicos</h3>
            <table class="tabla-info-detalle">
                <?php display_dato_ficha('AFP Asociada', $ficha_detalle['afp_asociada']); ?>
                <?php display_dato_ficha('NUA/CUA', $ficha_detalle['nua_cua']); ?>
                <?php display_dato_ficha('Grupo Sanguíneo y Factor RH', $ficha_detalle['grupo_sanguineo']); ?>
                <?php display_dato_ficha('Alergias Conocidas', nl2br(htmlspecialchars($ficha_detalle['alergias_conocidas'] ?? '', ENT_QUOTES, 'UTF-8'))); ?>
                <?php display_dato_ficha('Observaciones Médicas', nl2br(htmlspecialchars($ficha_detalle['observaciones_medicas'] ?? '', ENT_QUOTES, 'UTF-8'))); ?>
            </table>
        </section>

        <section class="card-sigi">
            <h3>Fechas de Registro (Ficha)</h3>
            <table class="tabla-info-detalle">
                <?php display_dato_ficha('Fecha Creación Ficha', date('d/m/Y H:i:s', strtotime($ficha_detalle['fecha_creacion_ficha']))); ?>
                <?php display_dato_ficha('Última Modificación Ficha', date('d/m/Y H:i:s', strtotime($ficha_detalle['fecha_modificacion_ficha']))); ?>
            </table>
        </section>
    </div>

<?php else: ?>
    <?php if(empty(mensaje_flash(null,null,null))): ?>
    <p>La ficha de personal solicitada no pudo ser cargada o no existe.</p>
    <?php endif; ?>
<?php endif; ?>


<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); margin-bottom: 1.5rem; }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.detalle-ficha-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); /* Comentario: Ajustar minmax según necesidad. */
    gap: 1.5rem;
}
.tabla-info-detalle { width: 100%; border-collapse: collapse; }
.tabla-info-detalle th, .tabla-info-detalle td {
    padding: 0.6rem 0.3rem; /* Comentario: Un poco más de padding vertical. */
    text-align: left;
    border-bottom: 1px dotted #f0f0f0; /* Comentario: Borde más sutil. */
    vertical-align: top; /* Comentario: Para datos largos. */
}
.tabla-info-detalle th { font-weight: bold; width: 40%; color: var(--color-secundario); }
.tabla-info-detalle td .text-muted { font-size: 0.9em; }
.text-danger { color: var(--color-error); }
.botones-accion-detalle .boton { margin-right: 0.5rem; }
</style>

<?php
// Comentario: Fin del archivo vistas/personal_ficha_detalle.php
?>
