<?php
// Archivo: logica/sistema_configuracion_logica.php
// Propósito: Lógica para la gestión de Configuración del Sistema (Sistemas).

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('CONFIGURAR_SISTEMA', $id_usuario_actual)) {
    mensaje_flash('error_sys_config', 'No tiene permisos para acceder a la configuración del sistema.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Cargar todas las configuraciones de la tabla sistema_configuracion.
$configuraciones = []; // Comentario: Definir para que esté disponible en la vista.
try {
    $stmt_configs = $pdo->query("SELECT id_config, clave_config, valor_config, descripcion_config, tipo_dato FROM sistema_configuracion ORDER BY clave_config ASC");
    $configuraciones = $stmt_configs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar configuraciones del sistema: " . $e->getMessage());
    mensaje_flash('error_sys_config', 'Error al cargar las configuraciones del sistema.', 'alert-danger');
}

// Comentario: Procesamiento de la actualización de configuraciones.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_configuraciones'])) {
    $errores_config = [];
    $actualizaciones_exitosas = 0;

    // Comentario: Es importante que $configuraciones ya esté cargada antes de este bloque.
    if (empty($configuraciones) && $pdo) { // Comentario: Recargar si está vacío y hay conexión (caso raro).
        try {
            $stmt_configs_reload = $pdo->query("SELECT id_config, clave_config, valor_config, descripcion_config, tipo_dato FROM sistema_configuracion ORDER BY clave_config ASC");
            $configuraciones = $stmt_configs_reload->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { /* Ya se manejó arriba */ }
    }


    $pdo->beginTransaction();
    try {
        foreach ($configuraciones as $conf) {
            $clave_post = 'config_' . $conf['id_config'];

            if (isset($_POST[$clave_post]) || $conf['tipo_dato'] === 'booleano') { // Comentario: Procesar booleanos aunque no vengan en POST (si se desmarcan).
                $nuevo_valor = $_POST[$clave_post] ?? null; // Comentario: Null si no está en POST (ej. checkbox desmarcado).

                $nuevo_valor_validado = $conf['valor_config']; // Comentario: Por defecto, no cambiar.

                switch ($conf['tipo_dato']) {
                    case 'texto':
                    case 'ruta_archivo':
                        $nuevo_valor_validado = strip_tags((string)$nuevo_valor);
                        break;
                    case 'numero':
                        if (!is_numeric($nuevo_valor) && !empty($nuevo_valor)) { // Comentario: Permitir vacío si es nullable.
                            $errores_config[] = "El valor para '" . htmlspecialchars($conf['descripcion_config'], ENT_QUOTES, 'UTF-8') . "' debe ser numérico.";
                            continue 2;
                        }
                        $nuevo_valor_validado = empty($nuevo_valor) ? null : $nuevo_valor;
                        break;
                    case 'booleano':
                        $nuevo_valor_validado = ($nuevo_valor === '1' || $nuevo_valor === 'on') ? '1' : '0';
                        break;
                    case 'json':
                        if (!empty($nuevo_valor)) {
                            json_decode($nuevo_valor);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                 $errores_config[] = "El valor para '" . htmlspecialchars($conf['descripcion_config'], ENT_QUOTES, 'UTF-8') . "' no es un JSON válido.";
                                continue 2;
                            }
                            $nuevo_valor_validado = $nuevo_valor;
                        } else {
                            $nuevo_valor_validado = null; // Comentario: Permitir JSON vacío como nulo.
                        }
                        break;
                    default:
                        $nuevo_valor_validado = strip_tags((string)$nuevo_valor);
                }

                if ($nuevo_valor_validado !== $conf['valor_config']) {
                    $sql_update_conf = "UPDATE sistema_configuracion SET valor_config = :valor WHERE id_config = :id_conf";
                    $stmt_update_conf = $pdo->prepare($sql_update_conf);
                    $stmt_update_conf->execute([':valor' => $nuevo_valor_validado, ':id_conf' => $conf['id_config']]);
                    if ($stmt_update_conf->rowCount() > 0) {
                        $actualizaciones_exitosas++;
                    }
                }
            }
        }

        if (!empty($errores_config)) {
            $pdo->rollBack();
            foreach ($errores_config as $err_c) {
                mensaje_flash('error_sys_config_save', $err_c, 'alert-danger');
            }
        } else {
            $pdo->commit();
            if ($actualizaciones_exitosas > 0) {
                mensaje_flash('exito_sys_config_save', "$actualizaciones_exitosas configuración(es) actualizada(s) exitosamente.", 'alert-success');
            } else {
                mensaje_flash('info_sys_config_save', "No se realizaron cambios en la configuración.", 'alert-info');
            }
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error al guardar configuraciones del sistema: " . $e->getMessage());
        mensaje_flash('error_sys_config_save', 'Error al guardar las configuraciones: ' . $e->getMessage(), 'alert-danger');
    }
    // Comentario: Recargar la página para mostrar los cambios y mensajes actualizados.
    // Comentario: Esto se hace aquí porque es una acción de lógica.
    redirigir('index.php?vista=sistema_configuracion');
}

// Comentario: Fin de logica/sistema_configuracion_logica.php
?>
