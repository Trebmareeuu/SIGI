<?php
// Archivo: index.php (ESTRUCTURA CON LÓGICA SEPARADA Y OB_START)
// Propósito: Único punto de entrada a la aplicación (Front Controller).
// Gestiona las sesiones, la configuración, el enrutamiento de vistas y la lógica de negocio principal.

// --- Inicialización y Configuración ---
require_once 'config.php'; // Contiene define() y conexión $pdo
require_once 'php_includes/funciones.php'; // Contiene funciones globales, inicia sesión si es necesario.

// --- Lógica de Autenticación (Manejo del Login) ---
// Comentario: Esta lógica de login es global y se maneja aquí directamente
// Comentario: porque redirige antes de cualquier otra cosa si la acción es 'login'.
$accion_global_index = $_GET['accion'] ?? $_POST['accion'] ?? null;

if ($accion_global_index === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario_ingresado = $_POST['nombre_usuario'] ?? '';
    $contrasena_ingresada = $_POST['contrasena'] ?? '';
    $nombre_usuario_sanitizado = sanitizar_entrada($nombre_usuario_ingresado);
    $_SESSION['login_intent_usuario'] = $nombre_usuario_sanitizado;

    if (empty($nombre_usuario_sanitizado) || empty($contrasena_ingresada)) {
        $_SESSION['error_login'] = 'El nombre de usuario y la contraseña son obligatorios.';
        redirigir('index.php?vista=login');
    } else {
        try {
            $sql_auth = "SELECT id_usuario, id_rol, contrasena, estado FROM usuarios WHERE nombre_usuario = :nombre_usuario";
            $stmt_auth = $pdo->prepare($sql_auth);
            $stmt_auth->bindParam(':nombre_usuario', $nombre_usuario_sanitizado, PDO::PARAM_STR);
            $stmt_auth->execute();
            $usuario_auth = $stmt_auth->fetch(PDO::FETCH_ASSOC);

            if ($usuario_auth) {
                if ($usuario_auth['estado'] === 'activo' && verificar_contrasena($contrasena_ingresada, $usuario_auth['contrasena'])) {
                    session_regenerate_id(true);
                    $_SESSION['id_usuario'] = $usuario_auth['id_usuario'];
                    $_SESSION['id_rol'] = $usuario_auth['id_rol']; // Guardamos el id_rol
                    unset($_SESSION['error_login']);
                    unset($_SESSION['login_intent_usuario']);
                    redirigir('index.php?vista=dashboard');
                } elseif ($usuario_auth['estado'] !== 'activo') {
                    $_SESSION['error_login'] = 'Su cuenta de usuario está ' . $usuario_auth['estado'] . '. Contacte al administrador.';
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
            error_log("Error de BD en login (index.php): " . $e->getMessage());
            $_SESSION['error_login'] = 'Error del sistema al intentar iniciar sesión. Intente más tarde.';
            redirigir('index.php?vista=login');
        }
    }
}


// --- Determinación de la Vista y Control de Acceso General ---
$vista_solicitada = $_GET['vista'] ?? 'login';

if (verificar_sesion() && $vista_solicitada === 'login') {
    $vista_solicitada = 'dashboard'; // Si ya hay sesión y pide login, va al dashboard
} elseif (!verificar_sesion() && $vista_solicitada !== 'login') {
    // Si no hay sesión y pide algo diferente a login, se fuerza a login.
    // Podríamos guardar la $vista_solicitada en sesión para redirigir después del login.
    // $_SESSION['redirect_despues_login'] = $vista_solicitada;
    $vista_solicitada = 'login';
}

$vista_limpia = preg_replace('/[^a-zA-Z0-9_]/', '', $vista_solicitada);
$ruta_vista_presentacion = __DIR__ . '/vistas/' . $vista_limpia . '.php';
$ruta_vista_logica = __DIR__ . '/logica/' . $vista_limpia . '_logica.php';

$vistas_publicas = ['login']; // Vistas que no requieren sesión.
$puede_acceder = false; // Bandera de acceso.

if (in_array($vista_limpia, $vistas_publicas)) {
    $puede_acceder = true;
} elseif (verificar_sesion()) {
    // Comentario: El mapeo de permisos ahora es más extenso y se mantiene aquí para la decisión de acceso.
    // Comentario: La lógica específica de cada vista (qué datos carga, etc.) irá en su archivo _logica.php
    $permisos_por_vista = [
        'dashboard' => 'VER_DASHBOARD', 'perfil' => 'VER_PERFIL',
        'correspondencia_bandeja' => 'VER_CORRESPONDENCIA_PROPIA', 'correspondencia_redactar' => 'REDACTAR_CORRESPONDENCIA_INTERNA',
        'correspondencia_registrar' => 'REGISTRAR_CORRESPONDENCIA_EXTERNA', 'documento_detalle' => 'VER_DETALLE_DOCUMENTO',
        'solicitud_vacacion' => 'SOLICITAR_VACACION', 'solicitud_material' => 'SOLICITAR_MATERIAL',
        'solicitud_activo' => 'SOLICITAR_ACTIVO', 'solicitudes_historial' => 'VER_HISTORIAL_SOLICITUDES_PROPIAS',
        'vacaciones_control_secretaria' => 'CONTROLAR_SOLICITUDES_VACACION_SECRETARIA', 'vacaciones_aprobacion_mae' => 'APROBAR_VACACIONES_MAE',
        'solicitudes_aprobacion_admin' => 'APROBAR_SOLICITUDES_ADMIN',
        'pagos_gestor' => 'GESTIONAR_PAGOS',
        'biometrico_importar' => 'IMPORTAR_BIOMETRICO', 'asistencia_reportes' => 'VER_REPORTES_ASISTENCIA',
        'sistema_delegacion' => 'DELEGAR_AUTORIDAD_SISTEMA',
        'sistema_usuarios_crud' => 'CRUD_USUARIOS_SISTEMA', 'sistema_roles_crud' => 'CRUD_ROLES_SISTEMA',
        'sistema_configuracion' => 'CONFIGURAR_SISTEMA',
        'personal_ficha_crud' => 'CRUD_PERSONAL_FICHA', 'personal_ficha_detalle' => 'VER_DETALLE_PERSONAL_FICHA',
        'mensajero_hoja_ruta' => 'VER_HOJA_RUTA_MENSAJERO',
        'activos_inventario' => 'VER_INVENTARIO_ACTIVOS', 'activos_asignar' => 'ASIGNAR_NUEVO_ACTIVO',
        'gestion_materiales_stock' => 'GESTIONAR_STOCK_MATERIALES',
        'reporte_materiales_stock' => 'VER_STOCK_MATERIALES',
        'comunicados_admin' => 'CREAR_COMUNICADOS',
        'comunicados_lista' => 'VER_COMUNICADOS',
        'admin_reporte_vacaciones' => 'VER_REPORTE_VACACIONES_PERSONAL',
        'secretaria_registro_vacaciones' => 'REGISTRAR_VACACION_APROBADA',
        'registro_solicitud_escaneada' => 'REGISTRAR_SOLICITUD_ESCANEO',
        'mis_datos_rrhh' => 'VER_INFO_PERSONAL_RRHH', // <-- Nueva vista y permiso para consulta de funcionarios
        'archivo_prestamos' => 'GESTIONAR_PRESTAMOS_ARCHIVO', 'archivo_recepcion' => 'RECEPCIONAR_EXPEDIENTES_ARCHIVO',
    ];

    if ($vista_limpia === 'dashboard' || $vista_limpia === 'perfil') { // Acceso general si está logueado
        $puede_acceder = true;
    } elseif (isset($permisos_por_vista[$vista_limpia])) {
        if (tiene_permiso($permisos_por_vista[$vista_limpia])) {
            $puede_acceder = true;
        }
    } else {
        // Si la vista no está en el mapeo y no es dashboard/perfil, por defecto no se accede.
        $puede_acceder = false;
    }
}


// --- Carga de Lógica y Vista (ESTRUCTURA CON LÓGICA SEPARADA) ---

// Caso especial: La vista de login no usa la plantilla header/footer ni lógica separada compleja.
if ($vista_limpia === 'login' && file_exists($ruta_vista_presentacion) && $puede_acceder) {
    require_once $ruta_vista_presentacion; // Carga vistas/login.php directamente.
} else {
    // Para todas las demás vistas, usamos la plantilla y el búfer de salida.

    // 1. INCLUIR LA LÓGICA PRIMERO (SI EXISTE)
    // Comentario: Este archivo de lógica definirá variables para la vista y puede realizar redirecciones.
    if (file_exists($ruta_vista_logica)) {
        require_once $ruta_vista_logica;
        // Si el archivo de lógica llamó a redirigir(), el script ya habrá terminado.
    }

    // 2. Iniciar el búfer de salida DESPUÉS de la lógica.
    ob_start();

    // 3. Cargar el contenido de la VISTA (presentación) en el búfer.
    if (file_exists($ruta_vista_presentacion) && $puede_acceder) {
        require_once $ruta_vista_presentacion;
    } elseif (file_exists($ruta_vista_presentacion) && !$puede_acceder && verificar_sesion()) { // Logueado pero sin permiso para esta vista
        http_response_code(403);
        require_once __DIR__ . '/vistas/error_403.php';
    } else { // Vista no existe o no logueado y no es pública (ya manejado arriba, pero por si acaso)
        http_response_code(404);
        require_once __DIR__ . '/vistas/error_404.php';
    }

    // 4. Obtenemos todo el contenido que estaba en el búfer
    $contenido_principal = ob_get_clean();

    // 5. Finalmente, renderizamos la plantilla completa
    require_once 'php_includes/header.php';
    echo $contenido_principal;
    require_once 'php_includes/footer.php';
}

// Fin del archivo index.php
?>
