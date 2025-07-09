<?php
// Archivo: header.php
// Propósito: Parte superior común de todas las páginas HTML del sistema SIGI ANCB.
// Incluye DOCTYPE, <head> (con metadatos, título, CSS), y el menú de navegación principal.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Asegurarse de que config.php y funciones.php estén disponibles.
// Comentario: Normalmente, index.php se encarga de incluir config.php y funciones.php antes que header.php.
// Comentario: Se asume que la sesión ya ha sido iniciada por funciones.php o index.php.

// Comentario: Obtener el ID del usuario actual para personalizar el menú.
$id_usuario_actual = obtener_id_usuario_actual();
$nombre_usuario_actual = ''; // Comentario: Inicializar nombre de usuario.
$rol_usuario_actual = ''; // Comentario: Inicializar rol de usuario.

if ($id_usuario_actual) {
    // Comentario: Si hay un usuario logueado, obtener su nombre y rol para mostrar en el header.
    global $pdo; // Comentario: Acceder a la conexión PDO.
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT nombres, apellidos FROM usuarios WHERE id_usuario = :id_usuario");
            $stmt->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
            $stmt->execute();
            $usuario_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($usuario_info) {
                $nombre_usuario_actual = htmlspecialchars($usuario_info['nombres'] . ' ' . $usuario_info['apellidos'], ENT_QUOTES, 'UTF-8');
            }
            // Comentario: Obtener el rol del usuario.
            $rol_usuario_actual = obtener_rol_usuario($id_usuario_actual);
            if ($rol_usuario_actual === false) $rol_usuario_actual = "Rol no definido"; // Comentario: Manejo si no se encuentra el rol.

        } catch (PDOException $e) {
            error_log("Error al obtener datos del usuario para el header: " . $e->getMessage());
            $nombre_usuario_actual = "Usuario Desconocido"; // Comentario: Mensaje de error genérico.
            $rol_usuario_actual = "Error Rol"; // Comentario: Mensaje de error genérico.
        }
    }
}

// Comentario: Determinar la vista actual para marcar el enlace activo en el menú.
$vista_actual = $_GET['vista'] ?? 'dashboard'; // Comentario: 'dashboard' es la vista por defecto si no se especifica.

// Comentario: Lógica para notificación de comunicados nuevos/pendientes.
$num_comunicados_nuevos = 0;
if (verificar_sesion() && $id_usuario_actual && isset($_SESSION['id_rol'])) {
    global $pdo; // Comentario: Asegurarse que $pdo esté disponible.
    $id_rol_actual_com = $_SESSION['id_rol'];
    $condiciones_notif_com = "c.estado = 'publicado' AND c.fecha_publicacion <= NOW() AND (c.fecha_expiracion IS NULL OR c.fecha_expiracion > NOW())";
    $condiciones_notif_com .= " AND (c.para_roles IS NULL OR JSON_CONTAINS(c.para_roles, CAST(:id_rol_notif AS JSON), '$'))";

    // Comentario: Opcional: Excluir leídos si se implementa la tabla `comunicados_leidos_usuarios`
    // $condiciones_notif_com .= " AND c.id_comunicado NOT IN (SELECT clu.id_comunicado FROM comunicados_leidos_usuarios clu WHERE clu.id_usuario = :id_usuario_actual_notif)";

    $sql_notif_com = "SELECT COUNT(DISTINCT c.id_comunicado) as total_nuevos
                      FROM comunicados c
                      WHERE $condiciones_notif_com";
    try {
        $stmt_notif_com = $pdo->prepare($sql_notif_com);
        $params_notif_com = [':id_rol_notif' => (string)$id_rol_actual_com];
        // if (strpos($condiciones_notif_com, ':id_usuario_actual_notif') !== false) {
        //     $params_notif_com[':id_usuario_actual_notif'] = $id_usuario_actual;
        // }
        $stmt_notif_com->execute($params_notif_com);
        $num_comunicados_nuevos = (int)$stmt_notif_com->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error al contar comunicados para notificación: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="es"> <!-- Comentario: Establece el idioma de la página a español. -->
<head>
    <meta charset="UTF-8"> <!-- Comentario: Define la codificación de caracteres a UTF-8. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Comentario: Configura el viewport para diseño responsivo. -->
    <title><?php echo defined('NOMBRE_INSTITUCION') ? htmlspecialchars(NOMBRE_INSTITUCION, ENT_QUOTES, 'UTF-8') : 'SIGI'; ?> - <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($vista_actual, ENT_QUOTES, 'UTF-8'))); ?></title> <!-- Comentario: Título dinámico de la página. -->

    <!-- Comentario: Enlace a la hoja de estilos principal. Se usa BASE_URL para la ruta correcta. -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/estilos.css">

    <!-- Comentario: Aquí se podrían añadir más enlaces a CSS o metadatos si fueran necesarios. -->
    <!-- Comentario: Ejemplo de Favicon (colocar archivo favicon.ico en la raíz o en /img/). -->
    <!-- <link rel="icon" href="<?php echo BASE_URL; ?>favicon.ico" type="image/x-icon"> -->
    <!-- <link rel="icon" href="<?php echo BASE_URL; ?>img/favicon.png" type="image/png"> -->

    <!-- Comentario: Si se usa Bootstrap u otra librería CSS localmente, se enlazaría aquí. -->
    <!-- <link rel="stylesheet" href="<?php echo BASE_URL; ?>libs/bootstrap/css/bootstrap.min.css"> -->

</head>
<body> <!-- Comentario: Inicio del cuerpo del documento HTML. -->

    <header class="encabezado-principal"> <!-- Comentario: Contenedor del encabezado. -->
        <div class="logo-institucional"> <!-- Comentario: Sección para el logo. -->
            <a href="<?php echo BASE_URL; ?>index.php?vista=dashboard">
                <img src="<?php echo BASE_URL; ?>img/logo.png" alt="Logo <?php echo defined('NOMBRE_INSTITUCION') ? htmlspecialchars(NOMBRE_INSTITUCION, ENT_QUOTES, 'UTF-8') : 'SIGI'; ?>" id="logo-principal-img">
            </a>
            <h1><?php echo defined('NOMBRE_INSTITUCION') ? htmlspecialchars(NOMBRE_INSTITUCION, ENT_QUOTES, 'UTF-8') : 'Sistema Integrado de Gestión Institucional'; ?></h1>
        </div>

        <?php if (verificar_sesion()): ?> // Comentario: Mostrar información de usuario y menú de navegación solo si hay sesión activa.
        <div class="info-usuario-header"> <!-- Comentario: Sección para información del usuario y logout. -->
            <span>Bienvenido/a, <strong><?php echo $nombre_usuario_actual; ?></strong> (<?php echo htmlspecialchars($rol_usuario_actual, ENT_QUOTES, 'UTF-8'); ?>)</span>
            <a href="<?php echo BASE_URL; ?>index.php?vista=perfil" class="boton-header">Mi Perfil</a>
            <a href="<?php echo BASE_URL; ?>logout.php" class="boton-header boton-logout">Cerrar Sesión</a>
        </div>
        <?php endif; ?>
    </header>

    <?php if (verificar_sesion()): ?> // Comentario: Mostrar el menú de navegación principal solo si hay sesión activa.
    <nav class="navegacion-principal"> <!-- Comentario: Contenedor del menú de navegación. -->
        <ul>
            <!-- Comentario: Enlace al Dashboard (visible para todos los usuarios logueados). -->
            <li><a href="<?php echo BASE_URL; ?>index.php?vista=dashboard" class="<?php echo ($vista_actual == 'dashboard') ? 'activo' : ''; ?>">Dashboard</a></li>

            <!-- Comentario: Módulo de Perfil (visible para todos los usuarios logueados). -->
            <!-- Ya está en info-usuario-header, pero podría estar aquí también si se prefiere -->
            <!-- <li><a href="<?php echo BASE_URL; ?>index.php?vista=perfil" class="<?php echo ($vista_actual == 'perfil') ? 'activo' : ''; ?>">Mi Perfil</a></li> -->

            <!-- Comentario: Módulo de Correspondencia (accesos varían según permisos). -->
            <?php if (tiene_permiso('VER_CORRESPONDENCIA_PROPIA') || tiene_permiso('REDACTAR_CORRESPONDENCIA_INTERNA') || tiene_permiso('REGISTRAR_CORRESPONDENCIA_EXTERNA')): ?>
                <li class="dropdown"> <!-- Comentario: Elemento de menú desplegable. -->
                    <a href="#" class="<?php echo (strpos($vista_actual, 'correspondencia_') === 0 || $vista_actual == 'documento_detalle') ? 'activo' : ''; ?>">Correspondencia</a>
                    <ul class="dropdown-menu"> <!-- Comentario: Submenú. -->
                        <?php if (tiene_permiso('VER_CORRESPONDENCIA_PROPIA')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=correspondencia_bandeja" class="<?php echo ($vista_actual == 'correspondencia_bandeja') ? 'activo-sub' : ''; ?>">Bandeja</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('REDACTAR_CORRESPONDENCIA_INTERNA')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=correspondencia_redactar" class="<?php echo ($vista_actual == 'correspondencia_redactar') ? 'activo-sub' : ''; ?>">Redactar Interna</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('REGISTRAR_CORRESPONDENCIA_EXTERNA')): // Secretaria ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=correspondencia_registrar" class="<?php echo ($vista_actual == 'correspondencia_registrar') ? 'activo-sub' : ''; ?>">Registrar Externa</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Comentario: Módulo de Solicitudes (accesos varían según permisos). -->
            <?php if (tiene_permiso('SOLICITAR_VACACION') || tiene_permiso('SOLICITAR_MATERIAL') || tiene_permiso('SOLICITAR_ACTIVO') || tiene_permiso('VER_HISTORIAL_SOLICITUDES_PROPIAS') || tiene_permiso('CONTROLAR_SOLICITUDES_VACACION_SECRETARIA') || tiene_permiso('APROBAR_VACACIONES_MAE') || tiene_permiso('APROBAR_SOLICITUDES_ADMIN')): ?>
                <li class="dropdown">
                    <a href="#" class="<?php echo (strpos($vista_actual, 'solicitud') === 0 || strpos($vista_actual, 'vacaciones_') === 0) ? 'activo' : ''; ?>">Solicitudes</a>
                    <ul class="dropdown-menu">
                        <?php if (tiene_permiso('SOLICITAR_VACACION')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_vacacion" class="<?php echo ($vista_actual == 'solicitud_vacacion') ? 'activo-sub' : ''; ?>">Pedir Vacación</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('SOLICITAR_MATERIAL')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_material" class="<?php echo ($vista_actual == 'solicitud_material') ? 'activo-sub' : ''; ?>">Pedir Material</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('SOLICITAR_ACTIVO')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_activo" class="<?php echo ($vista_actual == 'solicitud_activo') ? 'activo-sub' : ''; ?>">Pedir Activo</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('VER_HISTORIAL_SOLICITUDES_PROPIAS')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=solicitudes_historial" class="<?php echo ($vista_actual == 'solicitudes_historial') ? 'activo-sub' : ''; ?>">Mis Solicitudes</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('CONTROLAR_SOLICITUDES_VACACION_SECRETARIA')): // Secretaria ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=vacaciones_control_secretaria" class="<?php echo ($vista_actual == 'vacaciones_control_secretaria') ? 'activo-sub' : ''; ?>">Control Vacaciones (Sec.)</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('APROBAR_VACACIONES_MAE')): // MAE ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=vacaciones_aprobacion_mae" class="<?php echo ($vista_actual == 'vacaciones_aprobacion_mae') ? 'activo-sub' : ''; ?>">Aprobar Vacaciones (MAE)</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('APROBAR_SOLICITUDES_ADMIN')): // Dir. Admin ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=solicitudes_aprobacion_admin" class="<?php echo ($vista_actual == 'solicitudes_aprobacion_admin') ? 'activo-sub' : ''; ?>">Aprobar Mat./Act. (Admin)</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Comentario: Módulos de Administración (Dir. Admin). -->
            <?php if (tiene_permiso('GESTIONAR_PAGOS') || tiene_permiso('IMPORTAR_BIOMETRICO') || tiene_permiso('VER_REPORTES_ASISTENCIA')): ?>
                <li class="dropdown">
                    <a href="#" class="<?php echo ($vista_actual == 'pagos_gestor' || $vista_actual == 'biometrico_importar' || $vista_actual == 'asistencia_reportes') ? 'activo' : ''; ?>">Dir. Administrativa</a>
                    <ul class="dropdown-menu">
                        <?php if (tiene_permiso('GESTIONAR_PAGOS')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=pagos_gestor" class="<?php echo ($vista_actual == 'pagos_gestor') ? 'activo-sub' : ''; ?>">Gestión de Pagos</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('IMPORTAR_BIOMETRICO')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=biometrico_importar" class="<?php echo ($vista_actual == 'biometrico_importar') ? 'activo-sub' : ''; ?>">Importar Biométrico</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('VER_REPORTES_ASISTENCIA')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=asistencia_reportes" class="<?php echo ($vista_actual == 'asistencia_reportes') ? 'activo-sub' : ''; ?>">Reportes Asistencia</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('GESTIONAR_STOCK_MATERIALES')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=gestion_materiales_stock" class="<?php echo ($vista_actual == 'gestion_materiales_stock') ? 'activo-sub' : ''; ?>">Catálogo Materiales (Stock)</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('VER_STOCK_MATERIALES')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=reporte_materiales_stock" class="<?php echo ($vista_actual == 'reporte_materiales_stock') ? 'activo-sub' : ''; ?>">Reporte Stock/Movimientos</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('VER_REPORTE_VACACIONES_PERSONAL')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=admin_reporte_vacaciones" class="<?php echo ($vista_actual == 'admin_reporte_vacaciones') ? 'activo-sub' : ''; ?>">Reporte Vacaciones Personal</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Comentario: Módulo de MAE (Director General Ejecutivo). -->
            <?php
            // Comentario: El MAE también puede tener un menú de reportes o accesos directos.
            $menu_mae_visible = tiene_permiso('DELEGAR_AUTORIDAD_SISTEMA') || tiene_permiso('VER_REPORTE_VACACIONES_PERSONAL');
            if ($menu_mae_visible):
            ?>
            <li class="dropdown">
                <a href="#" class="<?php echo ($vista_actual == 'sistema_delegacion' || $vista_actual == 'admin_reporte_vacaciones') ? 'activo' : ''; ?>">Dirección Ejecutiva (MAE)</a>
                <ul class="dropdown-menu">
                    <?php if (tiene_permiso('DELEGAR_AUTORIDAD_SISTEMA')): ?>
                        <li><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_delegacion" class="<?php echo ($vista_actual == 'sistema_delegacion') ? 'activo-sub' : ''; ?>">Delegar Autoridad</a></li>
                    <?php endif; ?>
                    <?php if (tiene_permiso('VER_REPORTE_VACACIONES_PERSONAL')): ?>
                        <li><a href="<?php echo BASE_URL; ?>index.php?vista=admin_reporte_vacaciones" class="<?php echo ($vista_actual == 'admin_reporte_vacaciones') ? 'activo-sub' : ''; ?>">Reporte Vacaciones Personal</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php elseif (tiene_permiso('DELEGAR_AUTORIDAD_SISTEMA')): // Caso donde solo tiene delegar y no otros items de MAE ?>
                 <li><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_delegacion" class="<?php echo ($vista_actual == 'sistema_delegacion') ? 'activo' : ''; ?>">Delegar Autoridad</a></li>
            <?php endif; ?>

            <!-- Comentario: Módulo de Presupuesto. -->
            <?php if (tiene_permiso('CRUD_PERSONAL_FICHA') || tiene_permiso('VER_DETALLE_PERSONAL_FICHA')): ?>
                <li class="dropdown">
                    <a href="#" class="<?php echo (strpos($vista_actual, 'personal_ficha_') === 0) ? 'activo' : ''; ?>">Personal (Presupuesto)</a>
                    <ul class="dropdown-menu">
                        <?php if (tiene_permiso('CRUD_PERSONAL_FICHA')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=personal_ficha_crud" class="<?php echo ($vista_actual == 'personal_ficha_crud') ? 'activo-sub' : ''; ?>">Fichas de Personal</a></li>
                        <?php endif; ?>
                        <!-- El detalle se vería desde el CRUD -->
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Comentario: Módulo de Mensajería. -->
            <?php if (tiene_permiso('VER_HOJA_RUTA_MENSAJERO')): ?>
                 <li><a href="<?php echo BASE_URL; ?>index.php?vista=mensajero_hoja_ruta" class="<?php echo ($vista_actual == 'mensajero_hoja_ruta') ? 'activo' : ''; ?>">Hoja de Ruta (Mensajero)</a></li>
            <?php endif; ?>

            <!-- Comentario: Módulo de Activos Fijos. -->
            <?php
            // Comentario: Combinar permisos para el menú desplegable de Activos Fijos y Materiales de Escritorio
            $puede_ver_menu_activos = tiene_permiso('VER_INVENTARIO_ACTIVOS') ||
                                      tiene_permiso('ASIGNAR_NUEVO_ACTIVO') ||
                                      (tiene_permiso('GESTIONAR_STOCK_MATERIALES') && $rol_usuario_actual === 'Encargado de Activos Fijos'); // Solo mostrar gestión de stock aquí si es Enc. Activos Fijos

            if ($puede_ver_menu_activos):
            ?>
                <li class="dropdown">
                    <a href="#" class="<?php echo (strpos($vista_actual, 'activos_') === 0 || ($vista_actual == 'gestion_materiales_stock' && $rol_usuario_actual === 'Encargado de Activos Fijos') ) ? 'activo' : ''; ?>">Activos y Materiales</a>
                    <ul class="dropdown-menu">
                        <?php if (tiene_permiso('VER_INVENTARIO_ACTIVOS')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=activos_inventario" class="<?php echo ($vista_actual == 'activos_inventario') ? 'activo-sub' : ''; ?>">Inventario</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('ASIGNAR_NUEVO_ACTIVO')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=activos_asignar" class="<?php echo ($vista_actual == 'activos_asignar') ? 'activo-sub' : ''; ?>">Registrar/Asignar Activo Fijo</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('GESTIONAR_STOCK_MATERIALES') && $rol_usuario_actual === 'Encargado de Activos Fijos'): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=gestion_materiales_stock" class="<?php echo ($vista_actual == 'gestion_materiales_stock') ? 'activo-sub' : ''; ?>">Catálogo Materiales (Stock)</a></li>
                        <?php endif; ?>
                         <?php if (tiene_permiso('VER_STOCK_MATERIALES') && $rol_usuario_actual === 'Encargado de Activos Fijos'): // Si el de activos fijos también puede ver el reporte consolidado ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=reporte_materiales_stock" class="<?php echo ($vista_actual == 'reporte_materiales_stock') ? 'activo-sub' : ''; ?>">Reporte Stock/Movimientos</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Comentario: Módulo de Archivo. -->
            <?php if (tiene_permiso('GESTIONAR_PRESTAMOS_ARCHIVO') || tiene_permiso('RECEPCIONAR_EXPEDIENTES_ARCHIVO')): ?>
                <li class="dropdown">
                    <a href="#" class="<?php echo (strpos($vista_actual, 'archivo_') === 0) ? 'activo' : ''; ?>">Archivo</a>
                    <ul class="dropdown-menu">
                        <?php if (tiene_permiso('GESTIONAR_PRESTAMOS_ARCHIVO')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=archivo_prestamos" class="<?php echo ($vista_actual == 'archivo_prestamos') ? 'activo-sub' : ''; ?>">Préstamos Expedientes</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('RECEPCIONAR_EXPEDIENTES_ARCHIVO')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=archivo_recepcion" class="<?php echo ($vista_actual == 'archivo_recepcion') ? 'activo-sub' : ''; ?>">Recepción Expedientes</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Comentario: Módulo de Comunicados (Ver para todos, Crear para roles específicos) -->
            <?php if (tiene_permiso('VER_COMUNICADOS')): ?>
                <li class="dropdown">
                     <a href="#" class="<?php echo (strpos($vista_actual, 'comunicados_') === 0) ? 'activo' : ''; ?>">
                        Comunicados
                        <?php if ($num_comunicados_nuevos > 0): ?>
                            <span class="badge-notificacion"><?php echo $num_comunicados_nuevos; ?></span>
                        <?php endif; ?>
                     </a>
                     <ul class="dropdown-menu">
                        <li><a href="<?php echo BASE_URL; ?>index.php?vista=comunicados_lista" class="<?php echo ($vista_actual == 'comunicados_lista') ? 'activo-sub' : ''; ?>">Ver Comunicados</a></li>
                        <?php if (tiene_permiso('CREAR_COMUNICADOS')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=comunicados_admin" class="<?php echo ($vista_actual == 'comunicados_admin') ? 'activo-sub' : ''; ?>">Gestionar Comunicados</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <!-- Comentario: Módulo de Configuración del Sistema (Técnico de Sistemas). -->
            <?php if (tiene_permiso('CRUD_USUARIOS_SISTEMA') || tiene_permiso('CRUD_ROLES_SISTEMA') || tiene_permiso('CONFIGURAR_SISTEMA')): ?>
                <li class="dropdown">
                    <a href="#" class="<?php echo (strpos($vista_actual, 'sistema_') === 0 && $vista_actual != 'sistema_delegacion') ? 'activo' : ''; ?>">Administración Sistema</a>
                    <ul class="dropdown-menu">
                        <?php if (tiene_permiso('CRUD_USUARIOS_SISTEMA')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_usuarios_crud" class="<?php echo ($vista_actual == 'sistema_usuarios_crud') ? 'activo-sub' : ''; ?>">Gestionar Usuarios</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('CRUD_ROLES_SISTEMA')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_roles_crud" class="<?php echo ($vista_actual == 'sistema_roles_crud') ? 'activo-sub' : ''; ?>">Gestionar Roles</a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('CONFIGURAR_SISTEMA')): ?>
                            <li><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_configuracion" class="<?php echo ($vista_actual == 'sistema_configuracion') ? 'activo-sub' : ''; ?>">Configuración General</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <main class="contenedor-principal"> <!-- Comentario: Contenedor principal para el contenido de cada vista. -->
        <?php
        // Comentario: Mostrar mensajes flash (errores, éxitos) si existen.
        // Comentario: La función mensaje_flash() se encarga de imprimir el HTML y limpiar la sesión.
        mensaje_flash();
        ?>
        <!-- Comentario: Aquí es donde index.php incluirá el contenido específico de la vista (ej. vistas/dashboard.php). -->
