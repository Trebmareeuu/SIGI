<?php
// Archivo: vistas/biometrico_importar.php
// Propósito: (Dir. Admin) Formulario para subir el archivo CSV del biométrico y procesarlo.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('IMPORTAR_BIOMETRICO', $id_usuario_actual)) {
    mensaje_flash('error_biometrico', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Inicializar variables para el resumen general de todos los archivos.
$total_registros_insertados_global = 0;
$total_registros_fallidos_global = 0;
$total_lineas_ignoradas_global = 0;
$errores_detalle_global = []; // Para errores específicos por archivo/línea.
$resumen_por_archivo_global = []; // Para feedback por cada archivo procesado.


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_csv_biometrico']) && isset($_FILES['archivos_csv'])) {

    $archivos_subidos = $_FILES['archivos_csv'];
    // Comentario: Contar cuántos archivos se intentaron subir.
    // Comentario: $_FILES['archivos_csv']['name'] será un array. Si solo se sube uno, también es un array de un elemento.
    $cantidad_de_slots_archivos = count($archivos_subidos['name']);
    $archivos_realmente_subidos = 0;

    for ($j = 0; $j < $cantidad_de_slots_archivos; $j++) {
        if ($archivos_subidos['error'][$j] === UPLOAD_ERR_NO_FILE) {
            continue; // Comentario: No se subió archivo en este slot.
        }
        $archivos_realmente_subidos++;
    }

    if ($archivos_realmente_subidos === 0) {
        mensaje_flash('error_biometrico_import', 'No se seleccionó ningún archivo CSV para importar.', 'alert-warning');
        redirigir('index.php?vista=biometrico_importar');
    }

    // Comentario: Iterar sobre cada archivo subido.
    for ($i = 0; $i < $cantidad_de_slots_archivos; $i++) {
        // Comentario: Saltar si no hay archivo en este "slot" del array o si hubo un error de subida no manejable aquí.
        if ($archivos_subidos['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($archivos_subidos['error'][$i] !== UPLOAD_ERR_OK) {
            $errores_detalle_global[] = 'Error al subir el archivo: ' . htmlspecialchars($archivos_subidos['name'][$i], ENT_QUOTES, 'UTF-8') . ' (Código de error PHP: ' . $archivos_subidos['error'][$i] . ')';
            $resumen_por_archivo_global[] = "Archivo '" . htmlspecialchars($archivos_subidos['name'][$i], ENT_QUOTES, 'UTF-8') . "': Error de subida (código " . $archivos_subidos['error'][$i] . ").";
            continue;
        }

        $nombre_archivo_temporal = $archivos_subidos['tmp_name'][$i];
        $nombre_archivo_original = $archivos_subidos['name'][$i];
        $extension_archivo = strtolower(pathinfo($nombre_archivo_original, PATHINFO_EXTENSION));

        if ($extension_archivo !== 'csv') {
            $errores_detalle_global[] = "Archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "': No es un archivo CSV válido y fue ignorado.";
            $resumen_por_archivo_global[] = "Archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "': Ignorado (no es CSV).";
            continue;
        }

        // Comentario: Procesar este archivo CSV individualmente.
        $registros_insertados_archivo = 0;
        $registros_fallidos_archivo = 0;
        $lineas_ignoradas_archivo = 0;

        $pdo->beginTransaction();
        try {
            $fila_actual_csv = 1;
            if (($gestor_csv = fopen($nombre_archivo_temporal, "r")) !== FALSE) {
                fgetcsv($gestor_csv); // Comentario: Omitir cabecera.
                $fila_actual_csv++;

                $sql_insert = "INSERT INTO asistencia (id_empleado_biometrico, fecha_hora_marcacion, tipo_marcacion, origen_dato, id_usuario_sistema)
                               VALUES (:id_bio, :fecha_hora, :tipo, 'importacion_csv',
                                       (SELECT u.id_usuario
                                        FROM usuarios u
                                        JOIN personal_fichas pf ON u.id_usuario = pf.id_usuario
                                        WHERE pf.codigo_empleado = :id_bio_codigo_empleado LIMIT 1)
                                      )
                               ON DUPLICATE KEY UPDATE id_asistencia=id_asistencia";
                $stmt_insert = $pdo->prepare($sql_insert);

                $col_num_empleado = 2; $col_fecha_hora = 3; $col_marc_ent_sal = 4;

                while (($datos_linea = fgetcsv($gestor_csv, 1000, ",")) !== FALSE) {
                    if (count($datos_linea) >= max($col_num_empleado, $col_fecha_hora, $col_marc_ent_sal) + 1) {
                        $id_empleado_biometrico = trim($datos_linea[$col_num_empleado]);
                        $fecha_hora_str_csv = trim($datos_linea[$col_fecha_hora]);
                        $marc_ent_sal_csv = trim($datos_linea[$col_marc_ent_sal]);

                        if (empty($id_empleado_biometrico) || empty($fecha_hora_str_csv)) {
                            $lineas_ignoradas_archivo++;
                            $errores_detalle_global[] = "Archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "', Línea $fila_actual_csv: 'No.' de empleado o 'Fecha/Hora' vacíos.";
                            $fila_actual_csv++; continue;
                        }
                        try {
                            $formato_fecha_csv = 'd/m/Y h:i:s a';
                            $obj_fecha_hora_temp = DateTime::createFromFormat($formato_fecha_csv, $fecha_hora_str_csv);
                            if ($obj_fecha_hora_temp === false) {
                                $formato_fecha_csv_sin_seg = 'd/m/Y h:i a';
                                $obj_fecha_hora_temp = DateTime::createFromFormat($formato_fecha_csv_sin_seg, $fecha_hora_str_csv);
                            }
                            if ($obj_fecha_hora_temp === false) {
                                 // Comentario: Si ambos formatos fallan, intentar con el constructor genérico (puede no interpretar bien a.m./p.m. en todos los casos)
                                 // Comentario: O mejor, lanzar excepción si los formatos específicos fallan.
                                throw new Exception("Formato de fecha no reconocido por createFromFormat.");
                            }
                            $fecha_hora_bd = $obj_fecha_hora_temp->format('Y-m-d H:i:s');
                        } catch (Exception $e_date_parse) {
                            $lineas_ignoradas_archivo++;
                            $errores_detalle_global[] = "Archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "', Línea $fila_actual_csv: Formato de 'Fecha/Hora' no válido ('$fecha_hora_str_csv').";
                            $fila_actual_csv++; continue;
                        }
                        $tipo_marcacion_bd = 'desconocido';
                        if (strtoupper($marc_ent_sal_csv) === 'M/ENT') $tipo_marcacion_bd = 'entrada';
                        elseif (strtoupper($marc_ent_sal_csv) === 'M/SAL') $tipo_marcacion_bd = 'salida';

                        if ($stmt_insert->execute([':id_bio' => $id_empleado_biometrico, ':fecha_hora' => $fecha_hora_bd, ':tipo' => $tipo_marcacion_bd, ':id_bio_codigo_empleado' => $id_empleado_biometrico])) {
                            if ($stmt_insert->rowCount() > 0) $registros_insertados_archivo++; else {
                                $lineas_ignoradas_archivo++;
                                // $errores_detalle_global[] = "Archivo '".htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8')."', Línea $fila_actual_csv: Marcación para '$id_empleado_biometrico' posiblemente duplicada o código de empleado no enlazado.";
                            }
                        } else {
                            $registros_fallidos_archivo++;
                            $infoError = $stmt_insert->errorInfo();
                            $errores_detalle_global[] = "Archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "', Línea $fila_actual_csv: Error SQL para '$id_empleado_biometrico'. SQLSTATE: {$infoError[0]}";
                        }
                    } else {
                        $lineas_ignoradas_archivo++;
                        $errores_detalle_global[] = "Archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "', Línea $fila_actual_csv: Número de columnas insuficiente.";
                    }
                    $fila_actual_csv++;
                }
                fclose($gestor_csv);
                $pdo->commit();
                $resumen_por_archivo_global[] = "Archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "': Insertados: $registros_insertados_archivo, Fallidos: $registros_fallidos_archivo, Ignorados/Duplicados: $lineas_ignoradas_archivo.";
            } else {
                $pdo->rollBack(); // Comentario: Rollback si no se puede abrir el archivo.
                throw new Exception("No se pudo abrir el archivo CSV: '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "'.");
            }
        } catch (Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $errores_detalle_global[] = "Error procesando archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "': " . $e->getMessage();
            $resumen_por_archivo_global[] = "Archivo '" . htmlspecialchars($nombre_archivo_original, ENT_QUOTES, 'UTF-8') . "': Error General - " . $e->getMessage();
        }
        // Comentario: Acumular totales globales.
        $total_registros_insertados_global += $registros_insertados_archivo;
        $total_registros_fallidos_global += $registros_fallidos_archivo;
        $total_lineas_ignoradas_global += $lineas_ignoradas_archivo;
    } // Fin del bucle FOR para cada archivo.

    if ($archivos_realmente_subidos > 0) {
        mensaje_flash('exito_biometrico_import', "Proceso de importación de $archivos_realmente_subidos archivo(s) finalizado. Total Global Insertados: $total_registros_insertados_global. Total Global Fallidos: $total_registros_fallidos_global. Total Global Ignorados/Duplicados: $total_lineas_ignoradas_global.", 'alert-success');
        if (!empty($resumen_por_archivo_global)) {
            $_SESSION['resumen_importacion_archivos_bio'] = $resumen_por_archivo_global;
        }
        if (!empty($errores_detalle_global) && ($total_registros_fallidos_global > 0 || $total_lineas_ignoradas_global > 0) ) {
            $_SESSION['errores_detalle_importacion_bio'] = $errores_detalle_global;
             mensaje_flash('info_biometrico_import_errores', "Algunos registros o archivos tuvieron problemas durante la importación. Revise los detalles.", 'alert-info');
        }
    } elseif (empty($errores_detalle_global)) {
        mensaje_flash('error_biometrico_import', 'No se seleccionaron archivos CSV válidos para procesar.', 'alert-warning');
    }

    // Comentario: Si solo hubo errores de subida y no se procesó ningún archivo.
    if ($archivos_realmente_subidos === 0 && !empty($errores_detalle_global)) {
         foreach($errores_detalle_global as $err_det_global) { mensaje_flash('error_biometrico_import', $err_det_global, 'alert-danger');}
    }
    redirigir('index.php?vista=biometrico_importar');
}

// Comentario: Recuperar errores y resumen de la sesión para mostrarlos.
if (isset($_SESSION['errores_detalle_importacion_bio'])) {
    $errores_detalle_global = $_SESSION['errores_detalle_importacion_bio'];
    unset($_SESSION['errores_detalle_importacion_bio']);
}
if (isset($_SESSION['resumen_importacion_archivos_bio'])) {
    $resumen_por_archivo_display_bio = $_SESSION['resumen_importacion_archivos_bio'];
    unset($_SESSION['resumen_importacion_archivos_bio']);
}
?>
<h2>Importar Registros del Biométrico (CSV)</h2>

<?php
mensaje_flash('error_biometrico');
mensaje_flash('error_biometrico_import');
mensaje_flash('exito_biometrico_import');
mensaje_flash('info_biometrico_import_errores');
?>

<div class="card-sigi">
    <h3>Instrucciones para el archivo CSV:</h3>
    <p>Asegúrese de que su archivo CSV cumpla con el siguiente formato y orden de columnas:</p>
    <ol>
        <li><strong>Dpto.:</strong> Departamento (se ignorará en la importación actual).</li>
        <li><strong>Nombre:</strong> Nombre completo del empleado (se ignorará, se usará el 'No.' para identificación).</li>
        <li><strong>No.:</strong> Número o ID del empleado en el biométrico. <strong>Este valor se usará como <code>id_empleado_biometrico</code>.</strong></li>
        <li><strong>Fecha/Hora:</strong> Fecha y hora de la marcación. Formato esperado: <code>DD/MM/YYYY HH:MM:SS a.m./p.m.</code> (ej: <code>07/05/2025 09:59:13 a.m.</code>). También se intenta con formato sin segundos: <code>DD/MM/YYYY HH:MM a.m./p.m.</code>.</li>
        <li><strong>Marc-Ent/Sal:</strong> Tipo de marcación. Se espera <code>M/Ent</code> para entrada y <code>M/Sal</code> para salida.</li>
        <li><strong>Locación ID:</strong> (Se ignorará).</li>
        <li><strong>ID Numero:</strong> (Se ignorará).</li>
        <li><strong>VerificaCod:</strong> (Se ignorará).</li>
        <li><strong>TarjetaNo:</strong> (Se ignorará).</li>
    </ol>
    <ul>
        <li>El archivo debe estar en formato CSV (valores separados por comas).</li>
        <li>La codificación de caracteres recomendada es UTF-8.</li>
        <li>Se recomienda que el archivo CSV **incluya la fila de cabeceras** como se describe arriba, ya que el sistema la omitirá automáticamente. Si no la incluye, la primera línea de datos de cada archivo podría perderse.</li>
        <li>El sistema intentará enlazar el <code>No.</code> del empleado con el campo <code>codigo_empleado</code> en las fichas de personal para asociar la asistencia al usuario correcto del sistema.</li>
    </ul>

    <form action="index.php?vista=biometrico_importar" method="POST" enctype="multipart/form-data" class="mt-3">
        <div class="grupo-formulario">
            <label for="archivos_csv">Seleccionar Archivo(s) CSV del Biométrico (hasta 14):</label>
            <input type="file" id="archivos_csv" name="archivos_csv[]" accept=".csv" required multiple>
            <small>Puede seleccionar múltiples archivos CSV (hasta un límite práctico del servidor, ej. 14) manteniendo presionada la tecla Ctrl (o Cmd en Mac) al seleccionar.</small>
        </div>
        <button type="submit" name="importar_csv_biometrico" class="boton boton-primario">Importar Registros</button>
    </form>
</div>

<?php if (!empty($resumen_por_archivo_display_bio)): ?>
<div class="card-sigi mt-3">
    <h3>Resumen de Importación por Archivo:</h3>
    <ul class="lista-resumen-importacion">
        <?php foreach ($resumen_por_archivo_display_bio as $resumen_item): ?>
            <li><?php echo htmlspecialchars($resumen_item, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>


<?php if (!empty($errores_detalle_global) && ($total_registros_fallidos_global > 0 || $total_lineas_ignoradas_global > 0 || count(array_filter($errores_detalle_global, function($e){ return strpos($e, 'Error de subida') !== false || strpos($e, 'No es un archivo CSV') !== false; })) > 0 )): ?>
<div class="card-sigi mt-3">
    <h3>Detalle de Errores/Advertencias en la Última Importación (Máximo 30 líneas mostradas):</h3>
    <ul class="lista-errores-importacion">
        <?php
        $count_err_mostrados_global = 0;
        foreach ($errores_detalle_global as $detalle_error_global):
            if ($count_err_mostrados_global >= 30) break;
        ?>
            <li><?php echo htmlspecialchars($detalle_error_global, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php
            $count_err_mostrados_global++;
        endforeach; ?>
    </ul>
    <?php if(count($errores_detalle_global) > 30): ?>
        <p><em>... y <?php echo count($errores_detalle_global) - 30; ?> más errores/advertencias no mostrados. Revise los logs del servidor para más detalles si es necesario.</em></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra_caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.lista-errores-importacion, .lista-resumen-importacion { list-style-type: disc; padding-left: 20px; max-height: 300px; overflow-y: auto; background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; border-radius: var(--borde-radio); }
.lista-errores-importacion li, .lista-resumen-importacion li { margin-bottom: 0.5em; font-size: 0.9em; }
</style>

<?php
// Comentario: Fin del archivo vistas/biometrico_importar.php
?>
