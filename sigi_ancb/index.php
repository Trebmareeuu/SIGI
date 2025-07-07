<?php
// Archivo: index.php
// Propósito: Único punto de entrada a la aplicación (Front Controller).
// Gestiona las sesiones, la configuración, el enrutamiento de vistas y la lógica de negocio principal.
// Comentario en español explicando el propósito de este archivo.

// --- Inicialización y Configuración ---
// Comentario: Incluir archivos de configuración y funciones globales.
// Comentario: config.php establece la conexión a la BD ($pdo) y constantes como BASE_URL.
require_once 'config.php';
// Comentario: funciones.php contiene funciones de utilidad, incluyendo session_start() si no está activa.
require_once 'php_includes/funciones.php';

// Comentario: El session_start() ya está manejado dentro de funciones.php al inicio del archivo.
// Comentario: session_name(SESSION_NAME);
// Comentario: session_set_cookie_params(SESSION_LIFETIME, '/', $_SERVER['HTTP_HOST'], SESSION_SECURE, SESSION_HTTPONLY);
// Comentario: if (session_status() == PHP_SESSION_NONE) { session_start(); }


// --- Lógica de Autenticación (Manejo del Login) ---
// Comentario: Verifica si se está intentando iniciar sesión.
// Comentario: Se espera que el formulario de login envíe 'accion=login'.
$accion = $_GET['accion'] ?? $_POST['accion'] ?? null; // Comentario: Obtiene la acción de GET o POST.

if ($accion === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Comentario: Procesa el intento de login.
    $nombre_usuario_ingresado = $_POST['nombre_usuario'] ?? ''; // Comentario: Obtiene el nombre de usuario del POST.
    $contrasena_ingresada = $_POST['contrasena'] ?? ''; // Comentario: Obtiene la contraseña del POST.

    // Comentario: Sanitizar entradas (aunque para el nombre de usuario podría no ser estrictamente necesario si se usa en una consulta preparada).
    $nombre_usuario_sanitizado = sanitizar_entrada($nombre_usuario_ingresado);
    // Comentario: La contraseña no se sanitiza de la misma forma, se usa tal cual para password_verify.

    // Comentario: Guardar el intento de nombre de usuario en sesión para repoblar el campo en caso de error.
    $_SESSION['login_intent_usuario'] = $nombre_usuario_sanitizado;

    if (empty($nombre_usuario_sanitizado) || empty($contrasena_ingresada)) {
        // Comentario: Error si los campos están vacíos.
        $_SESSION['error_login'] = 'El nombre de usuario y la contraseña son obligatorios.';
        redirigir('index.php?vista=login'); // Comentario: Redirige de vuelta al login.
    } else {
        try {
            // Comentario: Consulta a la base de datos para verificar el usuario.
            $sql = "SELECT id_usuario, id_rol, contrasena, estado FROM usuarios WHERE nombre_usuario = :nombre_usuario";
            $stmt = $pdo->prepare($sql); // Comentario: Prepara la consulta.
            $stmt->bindParam(':nombre_usuario', $nombre_usuario_sanitizado, PDO::PARAM_STR); // Comentario: Vincula el parámetro.
            $stmt->execute(); // Comentario: Ejecuta la consulta.
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC); // Comentario: Obtiene el usuario.

            if ($usuario) {
                // Comentario: Usuario encontrado, verificar contraseña y estado.
                if ($usuario['estado'] === 'activo' && verificar_contrasena($contrasena_ingresada, $usuario['contrasena'])) {
                    // Comentario: Login exitoso.
                    session_regenerate_id(true); // Comentario: Regenera el ID de sesión para prevenir fijación de sesión.
                    $_SESSION['id_usuario'] = $usuario['id_usuario']; // Comentario: Almacena el ID del usuario en la sesión.
                    $_SESSION['id_rol'] = $usuario['id_rol']; // Comentario: Almacena el ID del rol en la sesión.
                    // Comentario: El nombre del rol se puede obtener con obtener_rol_usuario() cuando sea necesario.

                    unset($_SESSION['error_login']); // Comentario: Limpia cualquier error de login previo.
                    unset($_SESSION['login_intent_usuario']); // Comentario: Limpia el nombre de usuario intentado.

                    // Comentario: Registrar acción de login en historial (si se implementa una tabla de auditoría de accesos).
                    // registrar_auditoria_acceso($usuario['id_usuario'], 'login_exitoso');

                    redirigir('index.php?vista=dashboard'); // Comentario: Redirige al dashboard.
                } elseif ($usuario['estado'] !== 'activo') {
                    // Comentario: Cuenta de usuario no activa.
                    $_SESSION['error_login'] = 'Su cuenta de usuario está ' . $usuario['estado'] . '. Contacte al administrador.';
                    // registrar_auditoria_acceso($usuario['id_usuario'], 'login_fallido_cuenta_inactiva');
                    redirigir('index.php?vista=login');
                } else {
                    // Comentario: Contraseña incorrecta.
                    $_SESSION['error_login'] = 'Nombre de usuario o contraseña incorrectos.';
                    // registrar_auditoria_acceso($usuario['id_usuario'], 'login_fallido_contrasena_incorrecta');
                    redirigir('index.php?vista=login');
                }
            } else {
                // Comentario: Usuario no encontrado.
                $_SESSION['error_login'] = 'Nombre de usuario o contraseña incorrectos.';
                // registrar_auditoria_acceso(null, 'login_fallido_usuario_no_encontrado', $nombre_usuario_sanitizado);
                redirigir('index.php?vista=login');
            }
        } catch (PDOException $e) {
            // Comentario: Error de base de datos durante el login.
            error_log("Error de BD en login: " . $e->getMessage()); // Comentario: Registra el error.
            $_SESSION['error_login'] = 'Error del sistema al intentar iniciar sesión. Intente más tarde.';
            redirigir('index.php?vista=login'); // Comentario: Redirige al login.
        }
    }
}

// --- Enrutamiento y Control de Acceso a Vistas ---

// Comentario: Determinar la vista solicitada.
// Comentario: Si .htaccess está configurado, 'vista' vendrá de la URL reescrita.
// Comentario: Si no, se espera un parámetro ?vista=nombre_vista.
$vista_solicitada = $_GET['vista'] ?? 'login'; // Comentario: Vista por defecto es 'login' si no hay sesión.
if (verificar_sesion() && $vista_solicitada === 'login') {
    // Comentario: Si hay sesión y se pide 'login', redirigir al dashboard.
    $vista_solicitada = 'dashboard';
} elseif (!verificar_sesion() && $vista_solicitada !== 'login') {
    // Comentario: Si no hay sesión y se pide algo diferente a 'login', forzar login.
    // Comentario: Guardar la vista solicitada para redirigir después del login (opcional).
    // $_SESSION['vista_redirect_post_login'] = $vista_solicitada;
    $vista_solicitada = 'login';
}

// Comentario: Limpiar el nombre de la vista para seguridad (evitar LFI - Local File Inclusion).
// Comentario: Permitir solo caracteres alfanuméricos y guiones bajos.
$vista_limpia = preg_replace('/[^a-zA-Z0-9_]/', '', $vista_solicitada);
$ruta_vista = __DIR__ . '/vistas/' . $vista_limpia . '.php'; // Comentario: Construye la ruta al archivo de la vista.

// Comentario: Definir un array de vistas públicas (que no requieren login ni permisos especiales más allá de no tener sesión).
$vistas_publicas = ['login']; // Comentario: 'recuperar_contrasena', 'resetear_contrasena' si se implementan.

// Comentario: Verificar si la vista solicitada es pública o si el usuario tiene permisos.
$puede_acceder = false; // Comentario: Bandera de acceso.

if (in_array($vista_limpia, $vistas_publicas)) {
    // Comentario: Si la vista es pública, se permite el acceso.
    $puede_acceder = true;
} elseif (verificar_sesion()) {
    // Comentario: Si hay sesión, verificar permisos para vistas no públicas.
    // Comentario: Mapeo de vistas a permisos requeridos.
    // Comentario: Este mapeo es crucial y debe ser exhaustivo.
    $permisos_por_vista = [
        'dashboard' => 'VER_DASHBOARD', // Comentario: Permiso genérico, todos los logueados deberían tenerlo.
        'perfil' => 'VER_PERFIL',
        // Correspondencia
        'correspondencia_bandeja' => 'VER_CORRESPONDENCIA_PROPIA',
        'correspondencia_redactar' => 'REDACTAR_CORRESPONDENCIA_INTERNA',
        'correspondencia_registrar' => 'REGISTRAR_CORRESPONDENCIA_EXTERNA', // Secretaria
        'documento_detalle' => 'VER_DETALLE_DOCUMENTO',
        // Solicitudes
        'solicitud_vacacion' => 'SOLICITAR_VACACION',
        'solicitud_material' => 'SOLICITAR_MATERIAL',
        'solicitud_activo' => 'SOLICITAR_ACTIVO',
        'solicitudes_historial' => 'VER_HISTORIAL_SOLICITUDES_PROPIAS',
        // Aprobaciones
        'vacaciones_control_secretaria' => 'CONTROLAR_SOLICITUDES_VACACION_SECRETARIA', // Secretaria
        'vacaciones_aprobacion_mae' => 'APROBAR_VACACIONES_MAE', // MAE
        'solicitudes_aprobacion_admin' => 'APROBAR_SOLICITUDES_ADMIN', // Dir. Admin
        // Dir. Admin
        'pagos_gestor' => 'GESTIONAR_PAGOS',
        'biometrico_importar' => 'IMPORTAR_BIOMETRICO',
        'asistencia_reportes' => 'VER_REPORTES_ASISTENCIA',
        // MAE
        'sistema_delegacion' => 'DELEGAR_AUTORIDAD_SISTEMA',
        // Sistemas
        'sistema_usuarios_crud' => 'CRUD_USUARIOS_SISTEMA',
        'sistema_roles_crud' => 'CRUD_ROLES_SISTEMA',
        'sistema_configuracion' => 'CONFIGURAR_SISTEMA',
        // Presupuesto
        'personal_ficha_crud' => 'CRUD_PERSONAL_FICHA',
        'personal_ficha_detalle' => 'VER_DETALLE_PERSONAL_FICHA', // Usualmente se accede desde el CRUD
        // Mensajero
        'mensajero_hoja_ruta' => 'VER_HOJA_RUTA_MENSAJERO',
        // Activos Fijos
        'activos_inventario' => 'VER_INVENTARIO_ACTIVOS',
        'activos_asignar' => 'ASIGNAR_NUEVO_ACTIVO',
        // Archivo
        'archivo_prestamos' => 'GESTIONAR_PRESTAMOS_ARCHIVO',
        'archivo_recepcion' => 'RECEPCIONAR_EXPEDIENTES_ARCHIVO',
        // Vistas de error no necesitan permiso directo aquí, se cargan por condición.
    ];

    // Comentario: Permisos base que todos los usuarios logueados tienen.
    // Comentario: El permiso VER_DASHBOARD se puede añadir al rol "Funcionario Estándar" o manejarlo aquí.
    if ($vista_limpia === 'dashboard' || $vista_limpia === 'perfil') {
        $puede_acceder = true; // Comentario: Dashboard y Perfil son accesibles para todos los logueados.
    } elseif (isset($permisos_por_vista[$vista_limpia])) {
        $permiso_requerido = $permisos_por_vista[$vista_limpia]; // Comentario: Obtiene el permiso necesario.
        if (tiene_permiso($permiso_requerido)) { // Comentario: Verifica si el usuario tiene el permiso.
            $puede_acceder = true;
        }
    } else {
        // Comentario: Si la vista no está en el mapeo de permisos y no es dashboard/perfil,
        // Comentario: por defecto no se permite el acceso para evitar exposiciones.
        // Comentario: O podría ser una vista que no existe.
        $puede_acceder = false;
    }
}


// --- Carga de la Vista ---
if (file_exists($ruta_vista) && $puede_acceder) {
    // Comentario: Si el archivo de la vista existe y el usuario tiene permiso.
    if ($vista_limpia === 'login') {
        // Comentario: La vista de login se carga sin el header/footer principal.
        require_once $ruta_vista;
    } else {
        // Comentario: Carga el header, la vista específica y el footer para páginas autenticadas.
        require_once 'php_includes/header.php';
        require_once $ruta_vista;
        require_once 'php_includes/footer.php';
    }
} elseif (file_exists($ruta_vista) && !$puede_acceder) {
    // Comentario: El archivo existe pero el usuario no tiene permisos.
    // Comentario: Mostrar una página de error 403 (Acceso Denegado).
    http_response_code(403); // Comentario: Establece el código de respuesta HTTP.
    if ($vista_limpia === 'login') { // Si intentaba acceder a login sin ser público y falla (caso raro)
        require_once __DIR__ . '/vistas/login.php'; // Evitar bucle si el error es en el propio login
    } else {
        require_once 'php_includes/header.php';
        require_once __DIR__ . '/vistas/error_403.php'; // Comentario: Carga la vista de error 403.
        require_once 'php_includes/footer.php';
    }
} else {
    // Comentario: El archivo de la vista no existe.
    // Comentario: Mostrar una página de error 404 (No Encontrado).
    http_response_code(404); // Comentario: Establece el código de respuesta HTTP.
     if ($vista_limpia === 'login') { // Si intentaba acceder a login y no existe (caso raro)
        // Podría ser un problema grave, quizás mostrar un error genérico o redirigir a una página estática de error.
        die("Error crítico: Vista de login no encontrada.");
    } else {
        require_once 'php_includes/header.php';
        require_once __DIR__ . '/vistas/error_404.php'; // Comentario: Carga la vista de error 404.
        require_once 'php_includes/footer.php';
    }
}

// Comentario: Fin del archivo index.php
?>
