<?php
// Archivo: index.php (CORREGIDO por usuario con ob_start)
// Propósito: Único punto de entrada a la aplicación (Front Controller).
// Gestiona las sesiones, la configuración, el enrutamiento de vistas y la lógica de negocio principal.

// --- Inicialización y Configuración ---
require_once 'config.php';
require_once 'php_includes/funciones.php';

// --- Lógica de Autenticación (Manejo del Login) ---
$accion = $_GET['accion'] ?? $_POST['accion'] ?? null;

if ($accion === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario_ingresado = $_POST['nombre_usuario'] ?? '';
    $contrasena_ingresada = $_POST['contrasena'] ?? '';
    $nombre_usuario_sanitizado = sanitizar_entrada($nombre_usuario_ingresado);
    $_SESSION['login_intent_usuario'] = $nombre_usuario_sanitizado;

    if (empty($nombre_usuario_sanitizado) || empty($contrasena_ingresada)) {
        $_SESSION['error_login'] = 'El nombre de usuario y la contraseña son obligatorios.';
        redirigir('index.php?vista=login');
    } else {
        try {
            $sql = "SELECT id_usuario, id_rol, contrasena, estado FROM usuarios WHERE nombre_usuario = :nombre_usuario";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nombre_usuario', $nombre_usuario_sanitizado, PDO::PARAM_STR);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                if ($usuario['estado'] === 'activo' && verificar_contrasena($contrasena_ingresada, $usuario['contrasena'])) {
                    session_regenerate_id(true);
                    $_SESSION['id_usuario'] = $usuario['id_usuario'];
                    $_SESSION['id_rol'] = $usuario['id_rol'];
                    unset($_SESSION['error_login']);
                    unset($_SESSION['login_intent_usuario']);
                    redirigir('index.php?vista=dashboard');
                } elseif ($usuario['estado'] !== 'activo') {
                    $_SESSION['error_login'] = 'Su cuenta de usuario está ' . $usuario['estado'] . '. Contacte al administrador.';
                    redirigir('index.php?vista=login');
                } else {
                    $_SESSION['error_login'] = 'Nombre de usuario o contraseña incorrectos.';
                    redirigir('index.php?vista=login');
                }
            } else {
                $_SESSION['error_login'] = 'Nombre de usuario o contraseña incorrectos.';
                redirigir('index.php?vista=login');
            }
        } catch (PDOException $e) {
            error_log("Error de BD en login: " . $e->getMessage());
            $_SESSION['error_login'] = 'Error del sistema al intentar iniciar sesión. Intente más tarde.';
            redirigir('index.php?vista=login');
        }
    }
}

// --- Enrutamiento y Control de Acceso a Vistas ---
$vista_solicitada = $_GET['vista'] ?? 'login';
if (verificar_sesion() && $vista_solicitada === 'login') {
    $vista_solicitada = 'dashboard';
} elseif (!verificar_sesion() && $vista_solicitada !== 'login') {
    $vista_solicitada = 'login';
}

$vista_limpia = preg_replace('/[^a-zA-Z0-9_]/', '', $vista_solicitada);
$ruta_vista = __DIR__ . '/vistas/' . $vista_limpia . '.php';
$vistas_publicas = ['login'];
$puede_acceder = false;

if (in_array($vista_limpia, $vistas_publicas)) {
    $puede_acceder = true;
} elseif (verificar_sesion()) {
    $permisos_por_vista = [
        'dashboard' => 'VER_DASHBOARD', 'perfil' => 'VER_PERFIL',
        'correspondencia_bandeja' => 'VER_CORRESPONDENCIA_PROPIA', 'correspondencia_redactar' => 'REDACTAR_CORRESPONDENCIA_INTERNA',
        'correspondencia_registrar' => 'REGISTRAR_CORRESPONDENCIA_EXTERNA', 'documento_detalle' => 'VER_DETALLE_DOCUMENTO',
        'solicitud_vacacion' => 'SOLICITAR_VACACION', 'solicitud_material' => 'SOLICITAR_MATERIAL',
        'solicitud_activo' => 'SOLICITAR_ACTIVO', 'solicitudes_historial' => 'VER_HISTORIAL_SOLICITUDES_PROPIAS',
        'vacaciones_control_secretaria' => 'CONTROLAR_SOLICITUDES_VACACION_SECRETARIA', 'vacaciones_aprobacion_mae' => 'APROBAR_VACACIONES_MAE',
        'solicitudes_aprobacion_admin' => 'APROBAR_SOLICITUDES_ADMIN', 'pagos_gestor' => 'GESTIONAR_PAGOS',
        'biometrico_importar' => 'IMPORTAR_BIOMETRICO', 'asistencia_reportes' => 'VER_REPORTES_ASISTENCIA',
        'sistema_delegacion' => 'DELEGAR_AUTORIDAD_SISTEMA', 'sistema_usuarios_crud' => 'CRUD_USUARIOS_SISTEMA',
        'sistema_roles_crud' => 'CRUD_ROLES_SISTEMA', 'sistema_configuracion' => 'CONFIGURAR_SISTEMA',
        'personal_ficha_crud' => 'CRUD_PERSONAL_FICHA', 'personal_ficha_detalle' => 'VER_DETALLE_PERSONAL_FICHA',
        'activos_inventario' => 'VER_INVENTARIO_ACTIVOS', 'activos_asignar' => 'ASIGNAR_NUEVO_ACTIVO',
        'gestion_materiales_stock' => 'GESTIONAR_STOCK_MATERIALES',
        'reporte_materiales_stock' => 'VER_STOCK_MATERIALES',
        'comunicados_admin' => 'CREAR_COMUNICADOS',
        'comunicados_lista' => 'VER_COMUNICADOS',
        'admin_reporte_vacaciones' => 'VER_REPORTE_VACACIONES_PERSONAL', // <-- Nuevo permiso y vista
        'archivo_prestamos' => 'GESTIONAR_PRESTAMOS_ARCHIVO', 'archivo_recepcion' => 'RECEPCIONAR_EXPEDIENTES_ARCHIVO',
    ];

    if ($vista_limpia === 'dashboard' || $vista_limpia === 'perfil') {
        $puede_acceder = true;
    } elseif (isset($permisos_por_vista[$vista_limpia])) {
        $permiso_requerido = $permisos_por_vista[$vista_limpia];
        if (tiene_permiso($permiso_requerido)) {
            $puede_acceder = true;
        }
    } else {
        // Si la vista no está en el mapeo y no es dashboard/perfil, por defecto no se accede.
        $puede_acceder = false;
    }
}


// --- Carga de la Vista (ESTRUCTURA CORREGIDA por usuario con ob_start) ---

// Caso especial: La vista de login no usa la plantilla header/footer.
if ($vista_limpia === 'login' && file_exists($ruta_vista) && $puede_acceder) {
    require_once $ruta_vista;
} else {
    // Para todas las demás vistas, usamos la plantilla y el búfer de salida.

    // 1. Iniciar el búfer de salida.
    // Todo lo que se imprima desde aquí (con echo, require, etc.) se guardará en memoria.
    ob_start();

    // 2. Cargar el contenido de la vista en el búfer.
    if (file_exists($ruta_vista) && $puede_acceder) {
        // Si la vista (que puede contener lógica POST y llamadas a redirigir())
        // llama a redirigir(), el script se detendrá con exit() dentro de redirigir()
        // y el resto del código de index.php (incluyendo ob_get_clean y los require de header/footer)
        // nunca se ejecutará. ¡Esto resuelve el problema de "headers already sent"!
        require_once $ruta_vista;
    } elseif (file_exists($ruta_vista) && !$puede_acceder) {
        // Cargar vista de error de acceso denegado en el búfer.
        http_response_code(403);
        require_once __DIR__ . '/vistas/error_403.php';
    } else {
        // Cargar vista de error de no encontrado en el búfer.
        http_response_code(404);
        require_once __DIR__ . '/vistas/error_404.php';
    }

    // 3. Si llegamos aquí, es porque no hubo redirección desde la vista.
    // Obtenemos todo el contenido que estaba en el búfer y lo guardamos en una variable.
    $contenido_principal = ob_get_clean();

    // 4. Ahora, y solo ahora, comenzamos a enviar la página completa al navegador.
    require_once 'php_includes/header.php';

    // Imprimimos el contenido de la vista que capturamos.
    echo $contenido_principal;

    require_once 'php_includes/footer.php';
}

// Fin del archivo index.php
?>
