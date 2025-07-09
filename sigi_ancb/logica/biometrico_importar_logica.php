<?php
// Archivo: logica/biometrico_importar_logica.php
// Propósito: Lógica para la importación de CSV del biométrico.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('IMPORTAR_BIOMETRICO', $id_usuario_actual)) {
    mensaje_flash('error_biometrico', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para mostrar resultados de la importación.
// Comentario: Estas se guardarán en sesión para mostrarlas después de la redirección.
// $resultados_importacion = $_SESSION['resultados_importacion_biometrico'] ?? null;
// if ($resultados_importacion) {
//     unset($_SESSION['resultados_importacion_biometrico']);
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_csv_biometrico']) && isset($_FILES['archivo_csv'])) {
    $registros_insertados_count = 0;
    $registros_fallidos_count = 0;
    $lineas_ignoradas_count = 0;
    $errores_detalle_import = []; // Comentario: Para errores específicos.

    if ($_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $nombre_archivo_temporal = $_FILES['archivo_csv']['tmp_name'];
        $nombre_archivo_original = $_FILES['archivo_csv']['name'];
        $extension_archivo = strtolower(pathinfo($nombre_archivo_original, PATHINFO_EXTENSION));

        if ($extension_archivo !== 'csv') {
            mensaje_flash('error_biometrico_import', 'El archivo debe ser de tipo CSV.', 'alert-danger');
        } else {
            $pdo->beginTransaction();
            try {
                $fila_num = 1;
                if (($gestor_csv = fopen($nombre_archivo_temporal, "r")) !== FALSE) {
                    // Comentario: Omitir cabecera si existe (ajustar si el CSV no tiene cabecera o tiene varias).
                    // fgetcsv($gestor_csv); $fila_num++;

                    $sql_insert_asist = "INSERT INTO asistencia (id_empleado_biometrico, fecha_hora_marcacion, tipo_marcacion, origen_dato, id_usuario_sistema)
                                         VALUES (:id_bio, :fecha_hora, :tipo_m, 'importacion_csv',
                                                 (SELECT id_usuario FROM usuarios WHERE nombre_usuario = :id_bio_en_usuarios OR codigo_empleado = :id_bio_en_usuarios LIMIT 1)
                                         )";
                    // Comentario: Se añade ON DUPLICATE KEY UPDATE para evitar errores si ya existe la misma marcación exacta.
                    // Comentario: Requiere un índice UNIQUE en (id_empleado_biometrico, fecha_hora_marcacion).
                    // $sql_insert_asist .= " ON DUPLICATE KEY UPDATE id_asistencia=id_asistencia";
                    // Comentario: Alternativamente, verificar existencia antes de insertar o manejar el error de duplicado.
                    // Comentario: Por ahora, se asume que no hay índice UNIQUE y se insertará, o se controlará por lógica de no re-procesar archivos.

                    $stmt_insert_asist = $pdo->prepare($sql_insert_asist);

                    while (($datos_linea = fgetcsv($gestor_csv, 1000, ",")) !== FALSE) {
                        if (count($datos_linea) >= 2) {
                            $id_empleado_csv = trim($datos_linea[0]);
                            $fecha_hora_csv_raw = trim($datos_linea[1]);
                            $tipo_marcacion_csv = isset($datos_linea[2]) ? strtolower(trim($datos_linea[2])) : 'desconocido';
                            if (!in_array($tipo_marcacion_csv, ['entrada', 'salida'])) $tipo_marcacion_csv = 'desconocido';

                            if (empty($id_empleado_csv) || empty($fecha_hora_csv_raw)) {
                                $lineas_ignoradas_count++;
                                $errores_detalle_import[] = "Línea $fila_num: ID de empleado o fecha/hora vacíos.";
                                $fila_num++; continue;
                            }

                            $fecha_hora_bd_asist = null;
                            try {
                                // Comentario: Intentar varios formatos comunes si es necesario.
                                $obj_fecha_hora_asist = new DateTime($fecha_hora_csv_raw);
                                $fecha_hora_bd_asist = $obj_fecha_hora_asist->format('Y-m-d H:i:s');
                            } catch (Exception $e_date_asist) {
                                $lineas_ignoradas_count++;
                                $errores_detalle_import[] = "Línea $fila_num: Formato de fecha/hora no válido ('$fecha_hora_csv_raw').";
                                $fila_num++; continue;
                            }

                            // Comentario: Verificar si esta marcación exacta ya existe para evitar duplicados.
                            $stmt_check_dup = $pdo->prepare("SELECT id_asistencia FROM asistencia WHERE id_empleado_biometrico = :id_bio_chk AND fecha_hora_marcacion = :fh_chk");
                            $stmt_check_dup->execute([':id_bio_chk' => $id_empleado_csv, ':fh_chk' => $fecha_hora_bd_asist]);
                            if ($stmt_check_dup->fetch()) {
                                $lineas_ignoradas_count++;
                                $errores_detalle_import[] = "Línea $fila_num: Marcación duplicada para '$id_empleado_csv' a las '$fecha_hora_bd_asist'.";
                                $fila_num++; continue;
                            }


                            if ($stmt_insert_asist->execute([
                                ':id_bio' => $id_empleado_csv,
                                ':fecha_hora' => $fecha_hora_bd_asist,
                                ':tipo_m' => $tipo_marcacion_csv,
                                ':id_bio_en_usuarios' => $id_empleado_csv // Comentario: Usar el ID del biométrico para buscar en usuarios.
                                ])) {
                                if ($stmt_insert_asist->rowCount() > 0) $registros_insertados_count++;
                                else $registros_fallidos_count++; // Comentario: Si no hay error PDO pero no insertó.
                            } else {
                                $registros_fallidos_count++;
                                $infoErrorAsist = $stmt_insert_asist->errorInfo();
                                $errores_detalle_import[] = "Línea $fila_num: Error SQL al insertar para '$id_empleado_csv'. {$infoErrorAsist[2]}";
                            }
                        } else {
                            $lineas_ignoradas_count++;
                            $errores_detalle_import[] = "Línea $fila_num: Número de columnas incorrecto.";
                        }
                        $fila_num++;
                    }
                    fclose($gestor_csv);
                    $pdo->commit();

                    $_SESSION['resultados_importacion_biometrico'] = [
                        'insertados' => $registros_insertados_count,
                        'fallidos' => $registros_fallidos_count,
                        'ignorados' => $lineas_ignoradas_count,
                        'detalles' => array_slice($errores_detalle_import, 0, 20) // Comentario: Limitar detalles.
                    ];
                    mensaje_flash('exito_biometrico_import', "Importación de CSV completada.", 'alert-success');

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
    redirigir('index.php?vista=biometrico_importar');
}

// Comentario: Recuperar resultados de la sesión para mostrarlos en la vista.
$resultados_importacion_actual = $_SESSION['resultados_importacion_biometrico'] ?? null;
if ($resultados_importacion_actual) {
    unset($_SESSION['resultados_importacion_biometrico']); // Comentario: Limpiar después de usar.
}


// Comentario: Fin de logica/biometrico_importar_logica.php
?>
