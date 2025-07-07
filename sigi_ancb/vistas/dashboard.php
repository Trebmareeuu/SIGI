<?php
// Archivo: vistas/dashboard.php
// Propósito: Panel principal del sistema. Muestra un resumen o widgets relevantes según el rol del usuario.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Asumimos que config.php, funciones.php, header.php ya han sido incluidos por index.php
// Comentario: y que la sesión del usuario está activa y verificada.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Obtiene el ID del usuario actual.
$rol_usuario_actual = obtener_rol_usuario($id_usuario_actual); // Comentario: Obtiene el rol del usuario actual.

// Comentario: Obtener el nombre del usuario para un saludo personalizado.
$nombre_completo_usuario = "Usuario"; // Comentario: Valor por defecto.
if ($id_usuario_actual && $pdo) { // Comentario: Verifica que $pdo (de config.php) esté disponible.
    try {
        $stmt_user = $pdo->prepare("SELECT nombres, apellidos FROM usuarios WHERE id_usuario = :id_usuario");
        $stmt_user->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
        $stmt_user->execute();
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $nombre_completo_usuario = htmlspecialchars($user_data['nombres'] . " " . $user_data['apellidos'], ENT_QUOTES, 'UTF-8');
        }
    } catch (PDOException $e) {
        error_log("Error al obtener nombre de usuario para dashboard: " . $e->getMessage());
        // Comentario: No es crítico para el funcionamiento del dashboard, se usa el valor por defecto.
    }
}

?>

<div class="contenedor-dashboard"> <!-- Comentario: Contenedor principal para el dashboard. -->
    <h2>Panel Principal</h2>
    <p>Bienvenido/a al Sistema Integrado de Gestión Institucional (SIGI), <strong><?php echo $nombre_completo_usuario; ?></strong>.</p>
    <p>Su rol actual es: <strong><?php echo htmlspecialchars($rol_usuario_actual, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>

    <hr class="mb-3"> <!-- Comentario: Línea divisoria. -->

    <div class="dashboard-widgets"> <!-- Comentario: Contenedor para los widgets. -->

        <!-- Widget: Acciones Rápidas (Común para muchos roles) -->
        <div class="widget">
            <h3>Acciones Rápidas</h3>
            <ul>
                <?php if (tiene_permiso('REDACTAR_CORRESPONDENCIA_INTERNA')): ?>
                    <li><a href="<?php echo BASE_URL; ?>index.php?vista=correspondencia_redactar">Redactar Nota/Informe Interno</a></li>
                <?php endif; ?>
                <?php if (tiene_permiso('SOLICITAR_VACACION')): ?>
                    <li><a href="<?php echo BASE_URL; ?>index.php?vista=solicitud_vacacion">Solicitar Vacación</a></li>
                <?php endif; ?>
                <?php if (tiene_permiso('VER_CORRESPONDENCIA_PROPIA')): ?>
                    <li><a href="<?php echo BASE_URL; ?>index.php?vista=correspondencia_bandeja">Ver Bandeja de Correspondencia</a></li>
                <?php endif; ?>
                <li><a href="<?php echo BASE_URL; ?>index.php?vista=perfil">Actualizar Mi Perfil</a></li>
            </ul>
        </div>

        <!-- Widget: Mis Solicitudes Pendientes (Para Funcionarios Estándar y otros) -->
        <?php if (tiene_permiso('VER_HISTORIAL_SOLICITUDES_PROPIAS') && $pdo): ?>
            <?php
            // Comentario: Contar solicitudes pendientes del usuario actual.
            $conteo_solicitudes_pendientes = 0;
            try {
                $sql_sol_pend = "SELECT COUNT(*) as total
                                 FROM solicitudes
                                 WHERE id_usuario_solicitante = :id_usuario
                                 AND estado_solicitud NOT IN ('aprobada', 'rechazada', 'atendida', 'cancelada')";
                $stmt_sol_pend = $pdo->prepare($sql_sol_pend);
                $stmt_sol_pend->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
                $stmt_sol_pend->execute();
                $res_sol_pend = $stmt_sol_pend->fetch(PDO::FETCH_ASSOC);
                if ($res_sol_pend) {
                    $conteo_solicitudes_pendientes = (int)$res_sol_pend['total'];
                }
            } catch (PDOException $e) {
                error_log("Error al contar solicitudes pendientes: " . $e->getMessage());
            }
            ?>
            <div class="widget">
                <h3>Mis Solicitudes</h3>
                <?php if ($conteo_solicitudes_pendientes > 0): ?>
                    <p>Tiene <strong><?php echo $conteo_solicitudes_pendientes; ?></strong> solicitud(es) en proceso o pendientes de revisión.</p>
                <?php else: ?>
                    <p>No tiene solicitudes pendientes de revisión actualmente.</p>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>index.php?vista=solicitudes_historial" class="boton boton-secundario mt-2">Ver Mis Solicitudes</a>
            </div>
        <?php endif; ?>


        <!-- Widgets Específicos por Rol -->

        <?php // Widget para Secretaria de Dirección ?>
        <?php if ($rol_usuario_actual === 'Secretaria de Dirección' && $pdo): ?>
            <?php
            $conteo_vac_sec = 0;
            $conteo_corr_ext_pend = 0; // Ejemplo, si hubiera un estado "pendiente de registro completo"
            try {
                // Contar solicitudes de vacación pendientes de revisión por secretaría
                $stmt_vac_sec = $pdo->prepare("SELECT COUNT(*) as total FROM solicitudes WHERE tipo_solicitud = 'vacacion' AND estado_solicitud = 'pendiente_revision_secretaria'");
                $stmt_vac_sec->execute();
                $res_vac_sec = $stmt_vac_sec->fetch(PDO::FETCH_ASSOC);
                if ($res_vac_sec) $conteo_vac_sec = (int)$res_vac_sec['total'];

            } catch (PDOException $e) {
                error_log("Error en widget Secretaria: " . $e->getMessage());
            }
            ?>
            <div class="widget">
                <h3>Tareas de Secretaría</h3>
                <p>Solicitudes de vacación para revisar/derivar: <strong><?php echo $conteo_vac_sec; ?></strong>.
                    <a href="<?php echo BASE_URL; ?>index.php?vista=vacaciones_control_secretaria">Ir a Control Vacaciones</a>
                </p>
                <p><a href="<?php echo BASE_URL; ?>index.php?vista=correspondencia_registrar">Registrar Nueva Correspondencia Externa</a></p>
            </div>
        <?php endif; ?>

        <?php // Widget para Director Administrativo ?>
        <?php if ($rol_usuario_actual === 'Director Administrativo' && $pdo): ?>
            <?php
            $conteo_sol_admin = 0;
            $conteo_pagos_prox = 0;
            try {
                // Contar solicitudes de material/activo pendientes de aprobación admin
                $stmt_sol_admin = $pdo->prepare("SELECT COUNT(*) as total FROM solicitudes WHERE tipo_solicitud IN ('material_escritorio', 'activo_mueble_equipo') AND estado_solicitud = 'pendiente_aprobacion_admin'");
                $stmt_sol_admin->execute();
                $res_sol_admin = $stmt_sol_admin->fetch(PDO::FETCH_ASSOC);
                if ($res_sol_admin) $conteo_sol_admin = (int)$res_sol_admin['total'];

                // Contar pagos recurrentes próximos a vencer (ej. en los próximos 7 días)
                $stmt_pagos = $pdo->prepare("SELECT COUNT(*) as total FROM pagos_recurrentes WHERE fecha_proximo_pago BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND estado = 'activo'");
                $stmt_pagos->execute();
                $res_pagos = $stmt_pagos->fetch(PDO::FETCH_ASSOC);
                if ($res_pagos) $conteo_pagos_prox = (int)$res_pagos['total'];

            } catch (PDOException $e) {
                error_log("Error en widget Dir. Admin: " . $e->getMessage());
            }
            ?>
            <div class="widget">
                <h3>Tareas Dirección Administrativa</h3>
                <p>Solicitudes de material/activo para aprobar: <strong><?php echo $conteo_sol_admin; ?></strong>.
                    <a href="<?php echo BASE_URL; ?>index.php?vista=solicitudes_aprobacion_admin">Ir a Aprobaciones</a>
                </p>
                <p>Pagos recurrentes próximos a vencer (7 días): <strong><?php echo $conteo_pagos_prox; ?></strong>.
                    <a href="<?php echo BASE_URL; ?>index.php?vista=pagos_gestor">Ir a Gestión de Pagos</a>
                </p>
                <p><a href="<?php echo BASE_URL; ?>index.php?vista=biometrico_importar">Importar CSV Biométrico</a></p>
                <p><a href="<?php echo BASE_URL; ?>index.php?vista=asistencia_reportes">Ver Reportes de Asistencia</a></p>
            </div>
        <?php endif; ?>

        <?php // Widget para MAE ?>
        <?php if ($rol_usuario_actual === 'Director General Ejecutivo (MAE)' && $pdo): ?>
             <?php
            $conteo_vac_mae = 0;
            try {
                // Contar solicitudes de vacación pendientes de aprobación por MAE
                $stmt_vac_mae = $pdo->prepare("SELECT COUNT(*) as total FROM solicitudes WHERE tipo_solicitud = 'vacacion' AND estado_solicitud = 'pendiente_aprobacion_mae'");
                $stmt_vac_mae->execute();
                $res_vac_mae = $stmt_vac_mae->fetch(PDO::FETCH_ASSOC);
                if ($res_vac_mae) $conteo_vac_mae = (int)$res_vac_mae['total'];

            } catch (PDOException $e) {
                error_log("Error en widget MAE: " . $e->getMessage());
            }
            ?>
            <div class="widget">
                <h3>Tareas Dirección Ejecutiva (MAE)</h3>
                 <p>Solicitudes de vacación para aprobar: <strong><?php echo $conteo_vac_mae; ?></strong>.
                    <a href="<?php echo BASE_URL; ?>index.php?vista=vacaciones_aprobacion_mae">Ir a Aprobación de Vacaciones</a>
                </p>
                <p><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_delegacion">Delegar Autoridad</a></p>
            </div>
        <?php endif; ?>

        <?php // Widget para Técnico de Sistemas ?>
        <?php if ($rol_usuario_actual === 'Técnico de Sistemas' && $pdo): ?>
            <?php
            $conteo_usuarios = 0;
            try {
                $stmt_u = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
                $res_u = $stmt_u->fetch(PDO::FETCH_ASSOC);
                if ($res_u) $conteo_usuarios = (int)$res_u['total'];
            } catch (PDOException $e) { error_log("Error widget Sistemas: ".$e->getMessage()); }
            ?>
            <div class="widget">
                <h3>Administración del Sistema</h3>
                <p>Total de usuarios registrados: <strong><?php echo $conteo_usuarios; ?></strong>.</p>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_usuarios_crud">Gestionar Usuarios</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_roles_crud">Gestionar Roles y Permisos</a></li>
                    <li><a href="<?php echo BASE_URL; ?>index.php?vista=sistema_configuracion">Configuración General del Sistema</a></li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Comentario: Añadir más widgets según sea necesario para otros roles -->
        <!-- Comentario: Encargada de Presupuesto, Mensajero, Encargado de Activos Fijos, Encargada de Archivo -->

        <?php if ($rol_usuario_actual === 'Encargada de Presupuesto' && $pdo): ?>
            <div class="widget">
                <h3>Gestión de Personal (Presupuesto)</h3>
                <p><a href="<?php echo BASE_URL; ?>index.php?vista=personal_ficha_crud">Administrar Fichas de Personal</a></p>
                <!-- Podría mostrar un conteo de personal activo, etc. -->
            </div>
        <?php endif; ?>

        <?php if ($rol_usuario_actual === 'Mensajero' && $pdo): ?>
            <div class="widget">
                <h3>Mensajería</h3>
                <p><a href="<?php echo BASE_URL; ?>index.php?vista=mensajero_hoja_ruta">Ver Hoja de Ruta y Registrar Descargos</a></p>
                <!-- Podría mostrar número de entregas pendientes -->
            </div>
        <?php endif; ?>

        <?php if ($rol_usuario_actual === 'Encargado de Activos Fijos' && $pdo): ?>
            <div class="widget">
                <h3>Activos Fijos</h3>
                 <p><a href="<?php echo BASE_URL; ?>index.php?vista=activos_inventario">Ver Inventario de Activos</a></p>
                 <p><a href="<?php echo BASE_URL; ?>index.php?vista=activos_asignar">Codificar y Asignar Nuevo Activo</a></p>
            </div>
        <?php endif; ?>

        <?php if ($rol_usuario_actual === 'Encargada de Archivo' && $pdo): ?>
            <div class="widget">
                <h3>Archivo Central</h3>
                <p><a href="<?php echo BASE_URL; ?>index.php?vista=archivo_prestamos">Gestionar Préstamos de Expedientes</a></p>
                <p><a href="<?php echo BASE_URL; ?>index.php?vista=archivo_recepcion">Recepción de Expedientes Archivados</a></p>
            </div>
        <?php endif; ?>


    </div> <!-- Fin de .dashboard-widgets -->

</div> <!-- Fin de .contenedor-dashboard -->

<?php
// Comentario: Fin del archivo vistas/dashboard.php
?>
