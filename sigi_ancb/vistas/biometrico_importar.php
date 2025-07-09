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

$registros_insertados = 0;
$registros_fallidos = 0;
$lineas_ignoradas = 0;
$errores_detalle = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_csv_biometrico']) && isset($_FILES['archivo_csv'])) {
    if ($_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $nombre_archivo_temporal = $_FILES['archivo_csv']['tmp_name'];
        $nombre_archivo_original = $_FILES['archivo_csv']['name'];
        $extension_archivo = pathinfo($nombre_archivo_original, PATHINFO_EXTENSION);

        if (strtolower($extension_archivo) !== 'csv') {
            mensaje_flash('error_biometrico_import', 'El archivo debe ser de tipo CSV.', 'alert-danger');
        } else {
            $pdo->beginTransaction();
            try {
                $fila = 1;
                if (($gestor_csv = fopen($nombre_archivo_temporal, "r")) !== FALSE) {
                    // Comentario: Omitir la primera línea si es cabecera (recomendado que el CSV la tenga).
                    fgetcsv($gestor_csv);
                    $fila++;

                    // Comentario: Consulta para insertar, intentando enlazar id_empleado_biometrico con personal_fichas.codigo_empleado.
                    $sql_insert = "INSERT INTO asistencia (id_empleado_biometrico, fecha_hora_marcacion, tipo_marcacion, origen_dato, id_usuario_sistema)
                                   VALUES (:id_bio, :fecha_hora, :tipo, 'importacion_csv',
                                           (SELECT pf.id_usuario FROM personal_fichas pf WHERE pf.codigo_empleado = :id_bio_codigo_empleado LIMIT 1)
                                          )
                                   ON DUPLICATE KEY UPDATE id_asistencia=id_asistencia";
                                   // Comentario: ON DUPLICATE KEY UPDATE requiere un índice UNIQUE en (id_empleado_biometrico, fecha_hora_marcacion) para funcionar como se espera.
                                   // Comentario: Si no existe ese índice, podría dar error o insertar duplicados si otras columnas difieren.
                                   // Comentario: Alternativamente, se podría hacer un SELECT previo para verificar duplicidad.

                    $stmt_insert = $pdo->prepare($sql_insert);

                    // Comentario: Índices de las columnas relevantes según el formato CSV proporcionado.
                    $col_num_empleado = 2; // 'No.'
                    $col_fecha_hora = 3;   // 'Fecha/Hora'
                    $col_marc_ent_sal = 4; // 'Marc-Ent/Sal'

                    while (($datos_linea = fgetcsv($gestor_csv, 1000, ",")) !== FALSE) {
                        if (count($datos_linea) >= max($col_num_empleado, $col_fecha_hora, $col_marc_ent_sal) + 1) { // Comentario: Verificar que existan las columnas necesarias.

                            $id_empleado_biometrico = trim($datos_linea[$col_num_empleado]);
                            $fecha_hora_str_csv = trim($datos_linea[$col_fecha_hora]);
                            $marc_ent_sal_csv = trim($datos_linea[$col_marc_ent_sal]);

                            // Comentario: Validar datos básicos.
                            if (empty($id_empleado_biometrico) || empty($fecha_hora_str_csv)) {
                                $lineas_ignoradas++;
                                $errores_detalle[] = "Línea $fila: 'No.' de empleado o 'Fecha/Hora' vacíos.";
                                $fila++;
                                continue;
                            }

                            // Comentario: Parsear Fecha/Hora (ej. 07/05/2025 09:59:13 a.m.).
                            $fecha_hora_bd = null;
                            try {
                                // Comentario: DateTime::createFromFormat es más robusto para formatos específicos.
                                // Comentario: Necesita manejar 'a.m.' y 'p.m.' correctamente.
                                $formato_fecha_csv = 'd/m/Y h:i:s a';
                                $obj_fecha_hora_temp = DateTime::createFromFormat($formato_fecha_csv, $fecha_hora_str_csv);

                                if ($obj_fecha_hora_temp === false) {
                                    // Comentario: Intentar con un formato sin segundos si falla el primero, o si el CSV a veces no los trae.
                                    $formato_fecha_csv_sin_seg = 'd/m/Y h:i a';
                                    $obj_fecha_hora_temp = DateTime::createFromFormat($formato_fecha_csv_sin_seg, $fecha_hora_str_csv);
                                }

                                if ($obj_fecha_hora_temp === false) {
                                     // Comentario: Intentar un formato más genérico como último recurso, aunque menos preciso para am/pm.
                                    $obj_fecha_hora_temp = new DateTime($fecha_hora_str_csv);
                                }
                                $fecha_hora_bd = $obj_fecha_hora_temp->format('Y-m-d H:i:s');
                            } catch (Exception $e_date_parse) {
                                $lineas_ignoradas++;
                                $errores_detalle[] = "Línea $fila: Formato de 'Fecha/Hora' no válido ('$fecha_hora_str_csv'). Error: " . $e_date_parse->getMessage();
                                $fila++;
                                continue;
                            }

                            // Comentario: Mapear Marc-Ent/Sal.
                            $tipo_marcacion_bd = 'desconocido';
                            if (strtoupper($marc_ent_sal_csv) === 'M/ENT') {
                                $tipo_marcacion_bd = 'entrada';
                            } elseif (strtoupper($marc_ent_sal_csv) === 'M/SAL') {
                                $tipo_marcacion_bd = 'salida';
                            }

                            // Comentario: Ejecutar inserción.
                            if ($stmt_insert->execute([
                                ':id_bio' => $id_empleado_biometrico,
                                ':fecha_hora' => $fecha_hora_bd,
                                ':tipo' => $tipo_marcacion_bd,
                                ':id_bio_codigo_empleado' => $id_empleado_biometrico // Comentario: Usar el No. para buscar en personal_fichas.codigo_empleado.
                                ])) {
                                if ($stmt_insert->rowCount() > 0) {
                                    $registros_insertados++;
                                } else {
                                    $lineas_ignoradas++;
                                    $errores_detalle[] = "Línea $fila: Registro para '$id_empleado_biometrico' a las '$fecha_hora_bd' posiblemente duplicado (o código de empleado no enlazado) y no insertado.";
                                }
                            } else {
                                $registros_fallidos++;
                                $infoError = $stmt_insert->errorInfo();
                                $errores_detalle[] = "Línea $fila: Error SQL al insertar para '$id_empleado_biometrico'. SQLSTATE: {$infoError[0]}, Driver Code: {$infoError[1]}, Message: {$infoError[2]}";
                            }

                        } else {
                            $lineas_ignoradas++;
                            $errores_detalle[] = "Línea $fila: Número de columnas insuficiente para procesar.";
                        }
                        $fila++;
                    }
                    fclose($gestor_csv);
                    $pdo->commit();
                    mensaje_flash('exito_biometrico_import', "Importación completada. Registros nuevos insertados: $registros_insertados. Registros fallidos: $registros_fallidos. Líneas ignoradas/duplicadas/no enlazadas: $lineas_ignoradas.", 'alert-success');
                    if (!empty($errores_detalle) && ($registros_fallidos > 0 || $lineas_ignoradas > $registros_insertados)) { // Comentario: Mostrar si hay fallos o muchas ignoradas.
                         mensaje_flash('info_biometrico_import_errores', "Algunos registros tuvieron problemas. Revise los detalles si se muestran.", 'alert-info');
                    }

                } else {
                    throw new Exception("No se pudo abrir el archivo CSV subido.");
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Error masivo en importación de biométrico: " . $e->getMessage());
                mensaje_flash('error_biometrico_import', 'Error durante el proceso de importación: ' . $e->getMessage(), 'alert-danger');
            }
        }
    } elseif ($_FILES['archivo_csv']['error'] !== UPLOAD_ERR_NO_FILE) {
        mensaje_flash('error_biometrico_import', 'Error al subir el archivo: Código ' . $_FILES['archivo_csv']['error'], 'alert-danger');
    } else {
        mensaje_flash('error_biometrico_import', 'No se seleccionó ningún archivo para importar.', 'alert-warning');
    }
     redirigir('index.php?vista=biometrico_importar'); // Comentario: Recargar para mostrar mensajes.
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
        <li><strong>Fecha/Hora:</strong> Fecha y hora de la marcación. Formato esperado: <code>DD/MM/YYYY HH:MM:SS a.m./p.m.</code> (ej: <code>07/05/2025 09:59:13 a.m.</code>).</li>
        <li><strong>Marc-Ent/Sal:</strong> Tipo de marcación. Se espera <code>M/Ent</code> para entrada y <code>M/Sal</code> para salida.</li>
        <li><strong>Locación ID:</strong> (Se ignorará).</li>
        <li><strong>ID Numero:</strong> (Se ignorará).</li>
        <li><strong>VerificaCod:</strong> (Se ignorará).</li>
        <li><strong>TarjetaNo:</strong> (Se ignorará).</li>
    </ol>
    <ul>
        <li>El archivo debe estar en formato CSV (valores separados por comas).</li>
        <li>La codificación de caracteres recomendada es UTF-8.</li>
        <li>Se recomienda que el archivo CSV **incluya la fila de cabeceras** como se describe arriba, ya que el sistema la omitirá automáticamente. Si no la incluye, la primera línea de datos podría perderse.</li>
        <li>El sistema intentará enlazar el <code>No.</code> del empleado con el campo <code>codigo_empleado</code> en las fichas de personal para asociar la asistencia al usuario correcto del sistema.</li>
    </ul>

    <form action="index.php?vista=biometrico_importar" method="POST" enctype="multipart/form-data" class="mt-3">
        <div class="grupo-formulario">
            <label for="archivo_csv">Seleccionar Archivo CSV del Biométrico:</label>
            <input type="file" id="archivo_csv" name="archivo_csv" accept=".csv" required>
        </div>
        <button type="submit" name="importar_csv_biometrico" class="boton boton-primario">Importar Registros</button>
    </form>
</div>

<?php if (!empty($errores_detalle) && ($registros_fallidos > 0 || $lineas_ignoradas > 0 && count($errores_detalle) < 20 )): // Comentario: Mostrar solo algunos errores para no saturar. ?>
<div class="card-sigi mt-3">
    <h3>Detalle de Errores/Advertencias en la Última Importación (Máximo 20 mostrados):</h3>
    <ul class="lista-errores-importacion">
        <?php
        $count_err_mostrados = 0;
        foreach ($errores_detalle as $detalle_error):
            if ($count_err_mostrados >= 20) break;
        ?>
            <li><?php echo htmlspecialchars($detalle_error, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php
            $count_err_mostrados++;
        endforeach; ?>
    </ul>
    <?php if(count($errores_detalle) > 20): ?>
        <p><em>... y <?php echo count($errores_detalle) - 20; ?> más errores/advertencias no mostrados. Revise los logs del servidor para más detalles si es necesario.</em></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.lista-errores-importacion { list-style-type: disc; padding-left: 20px; max-height: 300px; overflow-y: auto; background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; border-radius: var(--borde-radio); }
.lista-errores-importacion li { margin-bottom: 0.5em; font-size: 0.9em; }
</style>

<?php
// Comentario: Fin del archivo vistas/biometrico_importar.php
?>
