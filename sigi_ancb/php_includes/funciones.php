<?php
// Archivo: funciones.php
// Propósito: Contiene funciones PHP globales y de utilidad para el sistema SIGI ANCB.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Asegurarse de que config.php se haya incluido, ya que algunas funciones pueden depender de $pdo o BASE_URL.
// Comentario: Normalmente, index.php se encargará de incluir config.php primero.

if (session_status() == PHP_SESSION_NONE) {
    // Comentario: Inicia la sesión si no ha sido iniciada aún.
    // Comentario: Es importante para funciones como verificar_sesion() o obtener_id_usuario_actual().
    session_name(SESSION_NAME); // Comentario: Establece el nombre de la sesión definido en config.php.
    session_set_cookie_params(SESSION_LIFETIME, '/', $_SERVER['HTTP_HOST'], SESSION_SECURE, SESSION_HTTPONLY); // Comentario: Establece parámetros de la cookie de sesión.
    session_start(); // Comentario: Inicia o reanuda la sesión.
}

/**
 * Función para sanitizar datos de entrada.
 * Previene ataques XSS básicos.
 *
 * @param string $dato El dato a sanitizar.
 * @return string El dato sanitizado.
 * Comentario: Esta función limpia una cadena de caracteres para prevenir inyecciones XSS.
 */
function sanitizar_entrada(string $dato): string {
    // Comentario: Elimina etiquetas HTML y PHP de la cadena.
    $dato = strip_tags($dato);
    // Comentario: Convierte caracteres especiales a entidades HTML.
    $dato = htmlspecialchars($dato, ENT_QUOTES, 'UTF-8');
    // Comentario: Elimina espacios en blanco (u otros caracteres) del inicio y final de la cadena.
    $dato = trim($dato);
    // Comentario: Considerar otras limpiezas si es necesario, dependiendo del contexto de uso.
    return $dato; // Comentario: Retorna la cadena sanitizada.
}

/**
 * Función para verificar si hay una sesión de usuario activa.
 *
 * @return bool True si hay una sesión activa y un id_usuario, false en caso contrario.
 * Comentario: Verifica la existencia de una sesión de usuario válida.
 */
function verificar_sesion(): bool {
    // Comentario: Comprueba si la variable de sesión 'id_usuario' está establecida y no está vacía.
    if (isset($_SESSION['id_usuario']) && !empty($_SESSION['id_usuario'])) {
        // Comentario: Podría añadirse una verificación adicional contra la BD para asegurar que el usuario sigue activo/válido.
        return true; // Comentario: Hay una sesión activa.
    }
    return false; // Comentario: No hay sesión activa.
}

/**
 * Función para redirigir al usuario a una URL específica.
 *
 * @param string $url La URL a la que se va a redirigir (relativa a BASE_URL o absoluta).
 * @return void
 * Comentario: Realiza una redirección HTTP a la URL especificada.
 */
function redirigir(string $url): void {
    // Comentario: Comprueba si la URL es relativa o absoluta.
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        // Comentario: Si la URL es absoluta, la usa directamente.
        header("Location: " . $url); // Comentario: Envía la cabecera de redirección.
    } else {
        // Comentario: Si la URL es relativa, la concatena con BASE_URL.
        header("Location: " . BASE_URL . ltrim($url, '/')); // Comentario: Envía la cabecera de redirección.
    }
    exit; // Comentario: Termina la ejecución del script para asegurar la redirección.
}

/**
 * Función para obtener el ID del usuario actualmente logueado.
 *
 * @return int|null El ID del usuario o null si no está logueado.
 * Comentario: Retorna el ID del usuario almacenado en la sesión.
 */
function obtener_id_usuario_actual(): ?int {
    // Comentario: Verifica si 'id_usuario' está en la sesión.
    if (isset($_SESSION['id_usuario'])) {
        // Comentario: Retorna el ID del usuario como entero.
        return (int)$_SESSION['id_usuario'];
    }
    return null; // Comentario: Retorna null si no hay usuario en sesión.
}

/**
 * Función para obtener el rol del usuario a partir de su ID.
 * Requiere la variable global $pdo (conexión a la BD) de config.php.
 *
 * @param int $id_usuario El ID del usuario.
 * @return string|false El nombre del rol del usuario, o false si no se encuentra o hay error.
 * Comentario: Consulta la base de datos para obtener el nombre del rol de un usuario.
 */
function obtener_rol_usuario(int $id_usuario): string|false {
    global $pdo; // Comentario: Accede a la variable global $pdo para la conexión a la BD.

    // Comentario: Verifica si $pdo está disponible.
    if (!$pdo) {
        error_log("PDO no está disponible en obtener_rol_usuario.");
        return false; // Comentario: Retorna false si no hay conexión a la BD.
    }

    try {
        // Comentario: Prepara la consulta SQL para obtener el nombre del rol.
        $sql = "SELECT r.nombre_rol
                FROM usuarios u
                JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.id_usuario = :id_usuario";
        $stmt = $pdo->prepare($sql); // Comentario: Prepara la sentencia.
        $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT); // Comentario: Vincula el parámetro.
        $stmt->execute(); // Comentario: Ejecuta la consulta.

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC); // Comentario: Obtiene el resultado.

        // Comentario: Retorna el nombre del rol si se encontró.
        return $resultado ? $resultado['nombre_rol'] : false;
    } catch (PDOException $e) {
        // Comentario: Registra cualquier error de base de datos.
        error_log("Error al obtener rol del usuario ID $id_usuario: " . $e->getMessage());
        return false; // Comentario: Retorna false en caso de error.
    }
}


/**
 * Función para obtener los permisos de un usuario a partir de su ID.
 * Los permisos se almacenan como JSON en la tabla 'roles'.
 * Requiere la variable global $pdo.
 *
 * @param int $id_usuario El ID del usuario.
 * @return array Arreglo asociativo de permisos (ej. ['VER_PERFIL' => true]) o un array vacío si no hay permisos o error.
 * Comentario: Obtiene y decodifica los permisos JSON asociados al rol del usuario.
 */
function obtener_permisos_usuario(int $id_usuario): array {
    global $pdo; // Comentario: Accede a la variable global $pdo.

    if (!$pdo) {
        error_log("PDO no está disponible en obtener_permisos_usuario.");
        return []; // Comentario: Retorna array vacío si no hay conexión.
    }

    try {
        // Comentario: Consulta para obtener la cadena JSON de permisos del rol del usuario.
        $sql = "SELECT r.permisos
                FROM usuarios u
                JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.id_usuario = :id_usuario";
        $stmt = $pdo->prepare($sql); // Comentario: Prepara la sentencia.
        $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT); // Comentario: Vincula el parámetro.
        $stmt->execute(); // Comentario: Ejecuta la consulta.

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC); // Comentario: Obtiene el resultado.

        if ($resultado && !empty($resultado['permisos'])) {
            // Comentario: Decodifica la cadena JSON de permisos.
            $permisos_array = json_decode($resultado['permisos'], true);
            // Comentario: Verifica si la decodificación fue exitosa.
            if (json_last_error() === JSON_ERROR_NONE) {
                return $permisos_array; // Comentario: Retorna el array de permisos.
            } else {
                error_log("Error al decodificar JSON de permisos para usuario ID $id_usuario: " . json_last_error_msg());
            }
        }
    } catch (PDOException $e) {
        // Comentario: Registra errores de base de datos.
        error_log("Error al obtener permisos del usuario ID $id_usuario: " . $e->getMessage());
    }
    return []; // Comentario: Retorna array vacío en caso de error o si no hay permisos.
}

/**
 * Función para verificar si el usuario actual tiene un permiso específico.
 *
 * @param string $permiso_requerido El nombre del permiso a verificar (ej. "CREAR_USUARIO").
 * @param int|null $id_usuario_a_verificar (Opcional) El ID del usuario para el cual verificar el permiso. Si es null, se usa el usuario actual en sesión.
 * @return bool True si el usuario tiene el permiso, false en caso contrario.
 * Comentario: Comprueba si un usuario posee un permiso determinado.
 */
function tiene_permiso(string $permiso_requerido, ?int $id_usuario_a_verificar = null): bool {
    // Comentario: Si no se provee un ID de usuario, se toma el de la sesión actual.
    $id_usuario = $id_usuario_a_verificar ?? obtener_id_usuario_actual();

    // Comentario: Si no hay ID de usuario (ni provisto ni en sesión), no tiene permisos.
    if (!$id_usuario) {
        return false;
    }

    // Comentario: Obtiene todos los permisos del usuario.
    $permisos_del_usuario = obtener_permisos_usuario($id_usuario);

    // Comentario: Verifica si el permiso requerido existe y está establecido en true.
    // Comentario: También se consideran permisos "CRUD" que engloban acciones.
    if (isset($permisos_del_usuario[$permiso_requerido]) && $permisos_del_usuario[$permiso_requerido] === true) {
        return true; // Comentario: El usuario tiene el permiso explícito.
    }

    // Comentario: Lógica para permisos CRUD genéricos (ej. CRUD_USUARIOS_SISTEMA implica CREAR_USUARIO, LEER_USUARIO, etc.)
    // Comentario: Esto es un ejemplo, la granularidad puede variar.
    // Comentario: Si el permiso es "CREAR_USUARIO" y el usuario tiene "CRUD_USUARIOS_SISTEMA", se considera válido.
    if (str_starts_with($permiso_requerido, "CREAR_") && isset($permisos_del_usuario["CRUD_" . substr($permiso_requerido, 6) . "_SISTEMA"]) && $permisos_del_usuario["CRUD_" . substr($permiso_requerido, 6) . "_SISTEMA"] === true) return true;
    if (str_starts_with($permiso_requerido, "LEER_") && isset($permisos_del_usuario["CRUD_" . substr($permiso_requerido, 5) . "_SISTEMA"]) && $permisos_del_usuario["CRUD_" . substr($permiso_requerido, 5) . "_SISTEMA"] === true) return true;
    if (str_starts_with($permiso_requerido, "ACTUALIZAR_") && isset($permisos_del_usuario["CRUD_" . substr($permiso_requerido, 11) . "_SISTEMA"]) && $permisos_del_usuario["CRUD_" . substr($permiso_requerido, 11) . "_SISTEMA"] === true) return true;
    if (str_starts_with($permiso_requerido, "ELIMINAR_") && isset($permisos_del_usuario["CRUD_" . substr($permiso_requerido, 9) . "_SISTEMA"]) && $permisos_del_usuario["CRUD_" . substr($permiso_requerido, 9) . "_SISTEMA"] === true) return true;


    // Comentario: Lógica de delegación de autoridad (si aplica para el permiso).
    // Comentario: Esta parte es más compleja y depende de cómo se implemente la delegación.
    // Comentario: Se necesitaría verificar si el usuario actual ($id_usuario) tiene una delegación activa
    // Comentario: del MAE para el $permiso_requerido.
    // if (verificar_delegacion_activa($id_usuario, $permiso_requerido)) {
    //     return true;
    // }

    return false; // Comentario: El usuario no tiene el permiso.
}


/**
 * Función para generar un CITE único para documentos.
 * Ejemplo: ANCB-NIN-001/2024 (Institución-TipoDoc-Correlativo/Año)
 * Requiere la variable global $pdo.
 *
 * @param string $tipo_doc_abreviatura Abreviatura del tipo de documento (ej. 'NIN' para Nota Interna).
 * @param string $siglas_institucion Siglas de la institución (ej. 'ANCB').
 * @return string El CITE generado o una cadena vacía en caso de error.
 * Comentario: Genera un código CITE secuencial y único para los documentos.
 */
function generar_cite_documento(string $tipo_doc_abreviatura, string $siglas_institucion = 'ANCB'): string {
    global $pdo; // Comentario: Accede a la variable global $pdo.

    if (!$pdo) {
        error_log("PDO no está disponible en generar_cite_documento.");
        return ""; // Comentario: Retorna cadena vacía si no hay conexión.
    }

    $anio_actual = date('Y'); // Comentario: Obtiene el año actual.
    $prefijo_cite = strtoupper($siglas_institucion) . '-' . strtoupper($tipo_doc_abreviatura) . '-'; // Comentario: Construye el prefijo del CITE.

    try {
        // Comentario: Busca el último correlativo para este tipo de documento y año.
        $sql = "SELECT MAX(SUBSTRING_INDEX(SUBSTRING_INDEX(cite, '/', 1), '-', -1)) AS max_correlativo
                FROM documentos
                WHERE cite LIKE :prefijo_anio ESCAPE '!'"; // Uso ESCAPE para el comodín literal '_' si fuera necesario.

        $stmt = $pdo->prepare($sql); // Comentario: Prepara la sentencia.
        $prefijo_anio_like = $prefijo_cite . "%/" . $anio_actual; // Comentario: Patrón para la búsqueda LIKE.
        $stmt->bindParam(':prefijo_anio', $prefijo_anio_like, PDO::PARAM_STR); // Comentario: Vincula el parámetro.
        $stmt->execute(); // Comentario: Ejecuta la consulta.
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC); // Comentario: Obtiene el resultado.

        $correlativo_actual = 0; // Comentario: Inicializa el correlativo.
        if ($resultado && $resultado['max_correlativo'] !== null) {
            $correlativo_actual = (int)$resultado['max_correlativo']; // Comentario: Asigna el máximo correlativo encontrado.
        }

        $nuevo_correlativo = $correlativo_actual + 1; // Comentario: Incrementa el correlativo.
        $cite_generado = $prefijo_cite . str_pad((string)$nuevo_correlativo, 3, '0', STR_PAD_LEFT) . '/' . $anio_actual; // Comentario: Formatea el CITE.

        return $cite_generado; // Comentario: Retorna el CITE generado.
    } catch (PDOException $e) {
        // Comentario: Registra errores de base de datos.
        error_log("Error al generar CITE: " . $e->getMessage());
        return ""; // Comentario: Retorna cadena vacía en caso de error.
    }
}

/**
 * Función para mostrar mensajes flash (notificaciones temporales al usuario).
 * Utiliza sesiones para almacenar los mensajes entre peticiones.
 *
 * @param string $nombre El nombre clave del mensaje (ej. 'error', 'exito').
 * @param string $mensaje (Opcional) El mensaje a establecer. Si se omite, intenta mostrar un mensaje existente con ese nombre.
 * @param string $tipo_clase_css (Opcional) Clase CSS para el div del mensaje (ej. 'alert-danger', 'alert-success').
 * @return void
 * Comentario: Gestiona mensajes flash para notificar al usuario.
 */
function mensaje_flash(string $nombre = '', string $mensaje = '', string $tipo_clase_css = 'alert-info'): void {
    // Comentario: Si se provee un mensaje, se guarda en la sesión.
    if (!empty($mensaje) && !empty($nombre)) {
        // Comentario: Elimina mensajes anteriores con el mismo nombre para evitar duplicados.
        if (isset($_SESSION['mensajes_flash'][$nombre])) {
            unset($_SESSION['mensajes_flash'][$nombre]);
        }
        // Comentario: Almacena el nuevo mensaje y su clase CSS.
        $_SESSION['mensajes_flash'][$nombre] = ['mensaje' => $mensaje, 'clase' => $tipo_clase_css];
    }
    // Comentario: Si se provee un nombre pero no un mensaje, es para mostrarlo.
    elseif (!empty($nombre) && empty($mensaje) && isset($_SESSION['mensajes_flash'][$nombre])) {
        // Comentario: Muestra el mensaje en un div con la clase CSS especificada.
        echo '<div class="alert ' . htmlspecialchars($_SESSION['mensajes_flash'][$nombre]['clase'], ENT_QUOTES, 'UTF-8') . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($_SESSION['mensajes_flash'][$nombre]['mensaje'], ENT_QUOTES, 'UTF-8');
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        // Comentario: Elimina el mensaje de la sesión después de mostrarlo.
        unset($_SESSION['mensajes_flash'][$nombre]);
    }
    // Comentario: Si no se provee nombre ni mensaje, muestra todos los mensajes flash almacenados.
    elseif (empty($nombre) && empty($mensaje) && isset($_SESSION['mensajes_flash']) && count($_SESSION['mensajes_flash']) > 0) {
        foreach ($_SESSION['mensajes_flash'] as $key => $data) {
            echo '<div class="alert ' . htmlspecialchars($data['clase'], ENT_QUOTES, 'UTF-8') . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($data['mensaje'], ENT_QUOTES, 'UTF-8');
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['mensajes_flash'][$key]); // Comentario: Elimina cada mensaje después de mostrarlo.
        }
    }
}

/**
 * Función para hashear contraseñas de forma segura.
 *
 * @param string $contrasena La contraseña en texto plano.
 * @return string El hash de la contraseña.
 * Comentario: Utiliza el algoritmo BCRYPT por defecto, que es seguro.
 */
function hashear_contrasena(string $contrasena): string {
    // Comentario: Utiliza la función password_hash de PHP, que maneja la salting automáticamente.
    return password_hash($contrasena, PASSWORD_DEFAULT);
}

/**
 * Función para verificar una contraseña contra un hash almacenado.
 *
 * @param string $contrasena_ingresada La contraseña en texto plano ingresada por el usuario.
 * @param string $hash_almacenado El hash de la contraseña almacenado en la base de datos.
 * @return bool True si la contraseña coincide, false en caso contrario.
 * Comentario: Compara una contraseña con su hash de forma segura.
 */
function verificar_contrasena(string $contrasena_ingresada, string $hash_almacenado): bool {
    // Comentario: Utiliza la función password_verify de PHP.
    return password_verify($contrasena_ingresada, $hash_almacenado);
}


/**
 * Función para registrar una acción en el historial de un documento.
 * Requiere la variable global $pdo.
 *
 * @param int $id_documento ID del documento.
 * @param int $id_usuario_accion ID del usuario que realiza la acción.
 * @param string $tipo_accion Tipo de acción (ej. 'Creación', 'Derivación').
 * @param string $descripcion_detalle Detalles de la acción.
 * @param int|null $id_usuario_origen (Opcional) ID del usuario origen (ej. en derivación).
 * @param int|null $id_usuario_destino (Opcional) ID del usuario destino (ej. en derivación).
 * @return bool True si el registro fue exitoso, false en caso contrario.
 * Comentario: Inserta un nuevo registro en la tabla documentos_historial.
 */
function registrar_historial_documento(int $id_documento, int $id_usuario_accion, string $tipo_accion, string $descripcion_detalle, ?int $id_usuario_origen = null, ?int $id_usuario_destino = null): bool {
    global $pdo; // Comentario: Accede a la variable global $pdo.

    if (!$pdo) {
        error_log("PDO no está disponible en registrar_historial_documento.");
        return false; // Comentario: Retorna false si no hay conexión.
    }

    try {
        $sql = "INSERT INTO documentos_historial (id_documento, id_usuario_accion, tipo_accion, descripcion_detalle, id_usuario_origen, id_usuario_destino, fecha_accion)
                VALUES (:id_documento, :id_usuario_accion, :tipo_accion, :descripcion_detalle, :id_usuario_origen, :id_usuario_destino, NOW())";
        $stmt = $pdo->prepare($sql); // Comentario: Prepara la sentencia.

        // Comentario: Vincula los parámetros.
        $stmt->bindParam(':id_documento', $id_documento, PDO::PARAM_INT);
        $stmt->bindParam(':id_usuario_accion', $id_usuario_accion, PDO::PARAM_INT);
        $stmt->bindParam(':tipo_accion', $tipo_accion, PDO::PARAM_STR);
        $stmt->bindParam(':descripcion_detalle', $descripcion_detalle, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario_origen', $id_usuario_origen, PDO::PARAM_INT_OR_NULL);
        $stmt->bindParam(':id_usuario_destino', $id_usuario_destino, PDO::PARAM_INT_OR_NULL);

        return $stmt->execute(); // Comentario: Ejecuta la inserción y retorna true en éxito.
    } catch (PDOException $e) {
        // Comentario: Registra errores de base de datos.
        error_log("Error al registrar historial para documento ID $id_documento: " . $e->getMessage());
        return false; // Comentario: Retorna false en caso de error.
    }
}

// Comentario: Se podrían añadir más funciones según las necesidades del sistema,
// Comentario: por ejemplo, para formatear fechas, calcular días hábiles, etc.
// Comentario: Fin del archivo funciones.php
?>
