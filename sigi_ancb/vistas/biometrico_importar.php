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
                    // Comentario: Opcional: Omitir la primera línea si es cabecera.
                    // fgetcsv($gestor_csv); $fila++;

                    $sql_insert = "INSERT INTO asistencia (id_empleado_biometrico, fecha_hora_marcacion, tipo_marcacion, origen_dato, id_usuario_sistema)
                                   VALUES (:id_bio, :fecha_hora, :tipo, 'importacion_csv', (SELECT id_usuario FROM usuarios WHERE nombre_usuario = :id_bio OR email = :id_bio_email LIMIT 1))
                                   ON DUPLICATE KEY UPDATE id_asistencia=id_asistencia"; // Comentario: Evita duplicados exactos si hay constraint UNIQUE.
                                   // Comentario: La subconsulta para id_usuario_sistema es un intento de enlazar. Puede ser NULL si no encuentra.
                                   // Comentario: Ajustar la condición de enlace (nombre_usuario, email, o un campo específico 'codigo_biometrico' en usuarios).
                                   // Comentario: 'ON DUPLICATE KEY UPDATE id_asistencia=id_asistencia' es un truco para ignorar la inserción si ya existe una fila idéntica (requiere un índice UNIQUE en las columnas relevantes, ej. id_empleado_biometrico y fecha_hora_marcacion).
                                   // Comentario: Si no hay índice UNIQUE, se insertarán duplicados. Una mejor aproximación es verificar antes de insertar o manejarlo en la BD.

                    $stmt_insert = $pdo->prepare($sql_insert);

                    while (($datos_linea = fgetcsv($gestor_csv, 1000, ",")) !== FALSE) { // Comentario: Asume delimitador coma.
                        if (count($datos_linea) >= 2) { // Comentario: Espera al menos ID_EMPLEADO, FECHA_HORA.
                            $id_empleado_csv = trim($datos_linea[0]);
                            $fecha_hora_csv = trim($datos_linea[1]);
                            // Comentario: El tipo de marcación (entrada/salida) podría venir en una tercera columna o inferirse.
                            $tipo_marcacion_csv = isset($datos_linea[2]) ? strtolower(trim($datos_linea[2])) : 'desconocido';
                            if (!in_array($tipo_marcacion_csv, ['entrada', 'salida'])) {
                                $tipo_marcacion_csv = 'desconocido';
                            }

                            // Comentario: Validar y formatear datos.
                            if (empty($id_empleado_csv) || empty($fecha_hora_csv)) {
                                $lineas_ignoradas++;
                                $errores_detalle[] = "Línea $fila: ID de empleado o fecha/hora vacíos.";
                                $fila++;
                                continue;
                            }

                            try {
                                // Comentario: Intentar convertir la fecha/hora. El formato del CSV es crucial.
                                // Comentario: Ej: '2024-07-20 08:00:00' o '20/07/2024 08:00'. Adaptar según el CSV.
                                $obj_fecha_hora = new DateTime($fecha_hora_csv);
                                $fecha_hora_bd = $obj_fecha_hora->format('Y-m-d H:i:s');
                            } catch (Exception $e_date) {
                                $lineas_ignoradas++;
                                $errores_detalle[] = "Línea $fila: Formato de fecha/hora no válido ('$fecha_hora_csv'). Error: " . $e_date->getMessage();
                                $fila++;
                                continue;
                            }

                            // Comentario: Ejecutar inserción.
                            // Comentario: Para el enlace id_usuario_sistema, se usa el mismo id_empleado_csv para buscar en nombre_usuario o email.
                            // Comentario: Esto asume que el ID del biométrico coincide con el nombre_usuario o email.
                            // Comentario: Si hay un campo dedicado en 'usuarios' para el código biométrico, usar ese.
                            if ($stmt_insert->execute([
                                ':id_bio' => $id_empleado_csv,
                                ':fecha_hora' => $fecha_hora_bd,
                                ':tipo' => $tipo_marcacion_csv,
                                ':id_bio_email' => $id_empleado_csv // Comentario: Asumiendo que puede ser un email también.
                                ])) {
                                if ($stmt_insert->rowCount() > 0) {
                                    $registros_insertados++;
                                } else {
                                    // Comentario: Si rowCount es 0 y se usó ON DUPLICATE KEY, podría ser un duplicado ignorado.
                                    // Comentario: O un fallo silencioso si no hay error PDO.
                                    // Comentario: Para ser más precisos, se necesitaría verificar si el registro ya existía.
                                    $lineas_ignoradas++; // Comentario: Asumir duplicado o no inserción.
                                    $errores_detalle[] = "Línea $fila: Registro para '$id_empleado_csv' a las '$fecha_hora_bd' posiblemente duplicado o no insertado.";
                                }
                            } else {
                                $registros_fallidos++;
                                $infoError = $stmt_insert->errorInfo();
                                $errores_detalle[] = "Línea $fila: Error al insertar para '$id_empleado_csv'. SQLSTATE: {$infoError[0]}, Driver Code: {$infoError[1]}, Message: {$infoError[2]}";
                            }

                        } else {
                            $lineas_ignoradas++;
                            $errores_detalle[] = "Línea $fila: Número de columnas incorrecto.";
                        }
                        $fila++;
                    }
                    fclose($gestor_csv);
                    $pdo->commit();
                    mensaje_flash('exito_biometrico_import', "Importación completada. Registros insertados: $registros_insertados. Fallidos: $registros_fallidos. Líneas ignoradas/duplicadas: $lineas_ignoradas.", 'alert-success');
                    if (!empty($errores_detalle) && $registros_fallidos > 0) {
                         mensaje_flash('info_biometrico_import_errores', "Algunos registros no pudieron ser importados. Revise los detalles.", 'alert-info');
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
    <ul>
        <li>El archivo debe estar en formato CSV (valores separados por comas).</li>
        <li>La codificación de caracteres recomendada es UTF-8.</li>
        <li>Cada línea debe representar una marcación de asistencia.</li>
        <li>Columnas esperadas (en orden):
            <ol>
                <li><strong>ID_EMPLEADO:</strong> Identificador del empleado tal como figura en el dispositivo biométrico. El sistema intentará enlazar este ID con el campo 'nombre_usuario' o 'email' de la tabla de usuarios del sistema.</li>
                <li><strong>FECHA_HORA:</strong> Fecha y hora de la marcación. Formatos comunes aceptados: <code>YYYY-MM-DD HH:MM:SS</code> o <code>DD/MM/YYYY HH:MM</code>. Asegúrese de que el formato sea consistente en todo el archivo.</li>
                <li><strong>TIPO_MARCACION (Opcional):</strong> Puede ser 'entrada' o 'salida'. Si no se provee o es diferente, se registrará como 'desconocido'.</li>
            </ol>
        </li>
        <li>Se recomienda no incluir una fila de cabecera, o si la incluye, el sistema podría intentar procesarla (causando una línea ignorada/fallida).</li>
        <li>El sistema intentará evitar la inserción de registros exactamente duplicados (mismo ID de empleado y misma fecha/hora de marcación) si la base de datos tiene las restricciones adecuadas.</li>
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
