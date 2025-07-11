<?php
// Archivo: vistas/solicitud_activo.php
// Propósito: Página informativa sobre el proceso manual de solicitud de activos y descarga de formato.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('DESCARGAR_FORMULARIOS', $id_usuario_actual) && !$id_usuario_actual) {
     if(verificar_sesion()){
        mensaje_flash('error_sol_act_info', 'No tiene permisos para acceder a esta sección.', 'alert-danger');
        redirigir('index.php?vista=dashboard');
    }
}
?>
<h2>Proceso de Solicitud de Activos (Muebles/Equipos)</h2>

<?php
mensaje_flash('error_sol_act_info');
?>

<div class="card-sigi">
    <h3>Información Importante sobre Solicitudes de Activos</h3>
    <p>Estimado/a funcionario/a, el proceso para solicitar activos (muebles, equipos, etc.) es el siguiente:</p>
    <ol>
        <li>Descargue el "Formulario de Solicitud de Activos" haciendo clic en uno de los enlaces de abajo.</li>
        <li>Complete todos los campos del formulario, detallando las especificaciones del activo requerido y la justificación exhaustiva de su necesidad.</li>
        <li>Firme su solicitud y obtenga el Visto Bueno (V°B°) de su Jefe Inmediato Superior en el formulario físico.</li>
        <li>Presente el formulario físico debidamente llenado y firmado al Encargado de Activos Fijos de la institución.</li>
        <li>El Encargado de Activos Fijos registrará su solicitud en el sistema SIGI ANCB y la derivará al Director Administrativo para su análisis, consideración presupuestaria y aprobación.</li>
        <li>Podrá consultar el estado de su solicitud a través de la opción "Mis Solicitudes (Estado)" o "Mis Vacaciones y Materiales" en este sistema.</li>
    </ol>

    <hr>

    <h4>Descarga de Formulario</h4>
    <p>
        <a href="<?php echo BASE_URL; ?>docs/formularios_descarga/formato_solicitud_activo.pdf" target="_blank" class="boton boton-primario">
            <span class="icono-pdf">📄</span> Descargar Formulario de Sol. de Activo (PDF)
        </a>
        <br>
        <small>(Si no tiene un lector de PDF, puede descargarlo <a href="https://get.adobe.com/reader/" target="_blank" rel="noopener noreferrer">aquí</a>)</small>
    </p>
    <p>
         <a href="<?php echo BASE_URL; ?>docs/formularios_descarga/formato_solicitud_activo.docx" target="_blank" class="boton boton-secundario">
            <span class="icono-doc">📝</span> Descargar Formulario de Sol. de Activo (Word)
        </a>
    </p>

    <p class="mt-3">
        Para cualquier consulta sobre el proceso o especificaciones técnicas, por favor, contacte al Encargado de Activos Fijos.
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
// Comentario: Fin del archivo vistas/solicitud_activo.php (Modificado para proceso manual)
?>
