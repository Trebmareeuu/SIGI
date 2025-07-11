<?php
// Archivo: vistas/solicitud_vacacion.php
// Propósito: Página informativa sobre el proceso manual de solicitud de vacaciones y descarga de formato.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Ya no se necesita la lógica de creación de solicitud aquí.
// Comentario: El permiso 'SOLICITAR_VACACION' se quitará de los roles de funcionario estándar.
// Comentario: Se podría mantener un permiso 'VER_INFO_VACACION' si esta página debe ser restringida.
// Comentario: Por ahora, se asume que si el usuario llega aquí (ej. desde un menú antiguo o enlace directo),
// Comentario: se le muestra la información del proceso manual.

global $pdo; // Comentario: $pdo podría ser necesario si se quiere mostrar saldo aquí.
$id_usuario_actual = obtener_id_usuario_actual();
$dias_disponibles_vacacion_display = "N/A"; // Comentario: Inicializar.

if ($id_usuario_actual) { // Comentario: Solo calcular si hay usuario logueado.
    try {
        $dias_asignados_anual = 20; // Comentario: Default.
        $stmt_ficha = $pdo->prepare("SELECT dias_vacacion_anuales_asignados FROM personal_fichas WHERE id_usuario = :id_user_ficha");
        $stmt_ficha->bindParam(':id_user_ficha', $id_usuario_actual, PDO::PARAM_INT);
        $stmt_ficha->execute();
        $dias_asignados_raw = $stmt_ficha->fetchColumn();
        if ($dias_asignados_raw !== false && !is_null($dias_asignados_raw)) {
            $dias_asignados_anual = (int)$dias_asignados_raw;
        }

        $anio_actual_calculo = date('Y');
        $sql_tomados = "SELECT SUM(dias_solicitados_vacacion) as total_tomados
                        FROM solicitudes
                        WHERE id_usuario_solicitante = :id_user_tomados
                        AND tipo_solicitud = 'vacacion'
                        AND estado_solicitud = 'aprobada'
                        AND YEAR(fecha_inicio_vacacion) = :anio_calc";
        $stmt_tomados = $pdo->prepare($sql_tomados);
        // Comentario: La variable $anio_actual_para_calculo no estaba definida aquí, usando $anio_actual_calculo.
        $stmt_tomados->execute([':id_user_tomados' => $id_usuario_actual, ':anio_calc' => $anio_actual_calculo]);
        $total_dias_tomados_este_anio = (int)$stmt_tomados->fetchColumn();
        $dias_disponibles_vacacion_display = $dias_asignados_anual - $total_dias_tomados_este_anio;
    } catch (PDOException $e) {
        error_log("Error al calcular días de vacación para info: " . $e->getMessage());
        $dias_disponibles_vacacion_display = "Error al calcular";
    }
}
?>
<h2>Proceso de Solicitud de Vacaciones</h2>

<?php
mensaje_flash('error_sol_vac');
mensaje_flash('exito_sol_vac');
?>

<div class="card-sigi">
    <h3>Información Importante sobre Solicitudes de Vacación</h3>
    <p>Estimado/a funcionario/a, el proceso para solicitar vacaciones se realiza de la siguiente manera:</p>
    <ol>
        <li>Descargue el formato oficial de "Solicitud de Vacación" haciendo clic en el enlace de abajo.</li>
        <li>Complete todos los campos del formulario de manera clara y precisa.</li>
        <li>Firme su solicitud.</li>
        <li>Presente la carta de solicitud física en la oficina de Secretaría de Dirección.</li>
        <li>Secretaría de Dirección gestionará la aprobación con la Dirección Ejecutiva (MAE).</li>
        <li>Una vez que su solicitud sea aprobada y procesada internamente, podrá consultar el estado y sus días de vacación restantes a través de la opción "Mis Datos RRHH / Vacaciones" en este sistema.</li>
    </ol>

    <p><strong>Sus días de vacación disponibles para el año <?php echo date('Y'); ?> (referencial):
       <strong><?php echo htmlspecialchars($dias_disponibles_vacacion_display, ENT_QUOTES, 'UTF-8'); ?></strong> días.</strong>
    </p>
    <p>Este saldo es informativo y se actualizará una vez que sus solicitudes aprobadas sean registradas en el sistema por Secretaría.</p>

    <hr>

    <h4>Descarga de Formulario</h4>
    <p>
        <a href="<?php echo BASE_URL; ?>docs/formularios_descarga/formato_solicitud_vacacion.pdf" target="_blank" class="boton boton-primario">
            <span class="icono-pdf">📄</span> Descargar Formato de Solicitud de Vacación (PDF)
        </a>
        <br>
        <small>(Si no tiene un lector de PDF, puede descargarlo <a href="https://get.adobe.com/reader/" target="_blank" rel="noopener noreferrer">aquí</a>)</small>
    </p>
    <p>
         <a href="<?php echo BASE_URL; ?>docs/formularios_descarga/formato_solicitud_vacacion.docx" target="_blank" class="boton boton-secundario">
            <span class="icono-doc">📝</span> Descargar Formato de Solicitud de Vacación (Word)
        </a>
    </p>

    <p class="mt-3">
        Si tiene alguna duda sobre el proceso, por favor, consulte con Secretaría de Dirección o la Unidad de Recursos Humanos.
    </p>
</div>

<div class="acciones-formulario mt-3">
     <a href="index.php?vista=dashboard" class="boton boton-info">Volver al Dashboard</a>
     <?php if(tiene_permiso('VER_MIS_VACACIONES', $id_usuario_actual)): // Asumiendo que este permiso lleva a la nueva vista de consulta ?>
         <a href="index.php?vista=mis_datos_rrhh" class="boton boton-secundario">Consultar Mis Vacaciones</a>
     <?php endif; ?>
</div>

<style>
    .card-sigi ol { padding-left: 20px; margin-bottom: 1rem;}
    .card-sigi ol li { margin-bottom: 0.5rem; }
    .icono-pdf::before, .icono-doc::before {
        /* Comentario: Se podrían usar iconos reales aquí con font awesome o svgs */
        font-family: "Arial", sans-serif;
        margin-right: 5px;
    }
</style>

<?php
// Comentario: Fin del archivo vistas/solicitud_vacacion.php (Modificado para proceso manual)
?>
