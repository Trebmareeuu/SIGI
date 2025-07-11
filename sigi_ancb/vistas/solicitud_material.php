<?php
// Archivo: vistas/solicitud_material.php
// Propósito: Página informativa sobre el proceso manual de solicitud de material y descarga de formato.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
// Comentario: Se usará el permiso 'DESCARGAR_FORMULARIOS'.
if (!tiene_permiso('DESCARGAR_FORMULARIOS', $id_usuario_actual) && !$id_usuario_actual) { // Permitir acceso si no hay sesión para ver la info.
    // Si hay sesión pero no permiso, redirigir.
    if(verificar_sesion()){
        mensaje_flash('error_sol_mat_info', 'No tiene permisos para acceder a esta sección.', 'alert-danger');
        redirigir('index.php?vista=dashboard');
    }
}
?>
<h2>Proceso de Solicitud de Material de Escritorio</h2>

<?php
mensaje_flash('error_sol_mat_info');
?>

<div class="card-sigi">
    <h3>Información Importante sobre Solicitudes de Material</h3>
    <p>Estimado/a funcionario/a, el proceso para solicitar material de escritorio es el siguiente:</p>
    <ol>
        <li>Descargue el "Formulario de Solicitud de Material de Escritorio" haciendo clic en uno de los enlaces de abajo.</li>
        <li>Complete todos los campos del formulario, detallando los ítems y cantidades requeridas, y la justificación correspondiente.</li>
        <li>Firme su solicitud y obtenga el Visto Bueno (V°B°) de su Jefe Inmediato Superior en el formulario físico.</li>
        <li>Presente el formulario físico debidamente llenado y firmado a la unidad encargada (por ejemplo, Encargado de Activos Fijos o Administración, según corresponda en su institución).</li>
        <li>La unidad encargada registrará su solicitud en el sistema SIGI ANCB y la derivará al Director Administrativo para su revisión y aprobación.</li>
        <li>Una vez procesada, podrá consultar el estado de su solicitud y los materiales que le fueron entregados a través de la opción "Mis Datos RRHH / Materiales" o "Estado de Mis Solicitudes" en este sistema.</li>
    </ol>

    <hr>

    <h4>Descarga de Formulario</h4>
    <p>
        <a href="<?php echo BASE_URL; ?>docs/formularios_descarga/formato_solicitud_material.pdf" target="_blank" class="boton boton-primario">
            <span class="icono-pdf">📄</span> Descargar Formulario de Sol. de Material (PDF)
        </a>
        <br>
        <small>(Si no tiene un lector de PDF, puede descargarlo <a href="https://get.adobe.com/reader/" target="_blank" rel="noopener noreferrer">aquí</a>)</small>
    </p>
    <p>
         <a href="<?php echo BASE_URL; ?>docs/formularios_descarga/formato_solicitud_material.docx" target="_blank" class="boton boton-secundario">
            <span class="icono-doc">📝</span> Descargar Formulario de Sol. de Material (Word)
        </a>
    </p>

    <p class="mt-3">
        Si tiene alguna duda sobre el proceso, por favor, consulte con la unidad administrativa correspondiente.
    </p>
</div>

<div class="acciones-formulario mt-3">
     <a href="index.php?vista=dashboard" class="boton boton-info">Volver al Dashboard</a>
     <?php if($id_usuario_actual && tiene_permiso('VER_HISTORIAL_SOLICITUDES_PROPIAS', $id_usuario_actual)): ?>
         <a href="index.php?vista=solicitudes_historial" class="boton boton-secundario">Ver Estado de Mis Solicitudes</a>
     <?php endif; ?>
</div>

<style>
    .card-sigi ol { padding-left: 20px; margin-bottom: 1rem;}
    .card-sigi ol li { margin-bottom: 0.5rem; }
    .icono-pdf::before, .icono-doc::before {
        font-family: "Arial", sans-serif;
        margin-right: 5px;
    }
</style>

<?php
// Comentario: Fin del archivo vistas/solicitud_material.php (Modificado para proceso manual)
?>
