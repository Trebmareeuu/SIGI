<?php
// Archivo: vistas/admin_reporte_vacaciones.php
// Propósito: (Dir. Admin / MAE) Reporte de vacaciones del personal.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('VER_REPORTE_VACACIONES_PERSONAL', $id_usuario_actual)) {
    mensaje_flash('error_rep_vac', 'No tiene permisos para acceder a este reporte.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Filtros para el reporte.
$filtro_anio_vacaciones = $_GET['anio_vac'] ?? date('Y'); // Año actual por defecto.
$filtro_id_usuario_vac = $_GET['id_usuario_vac'] ?? 'todos'; // 'todos' o ID de usuario.

// Comentario: Cargar usuarios para el filtro.
$lista_usuarios_filtro_vac = [];
try {
    $stmt_usr_f = $pdo->query("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM usuarios WHERE estado = 'activo' ORDER BY apellidos, nombres ASC");
    $lista_usuarios_filtro_vac = $stmt_usr_f->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* No crítico para la funcionalidad principal del reporte */ }


$reporte_datos_vacaciones = [];
$anio_actual_para_calculo = $filtro_anio_vacaciones; // Comentario: Usar el año filtrado para los cálculos.

try {
    $sql_base_reporte_vac = "SELECT
                                u.id_usuario,
                                CONCAT(u.apellidos, ', ', u.nombres) as nombre_completo_empleado,
                                u.cargo as cargo_empleado,
                                pf.dias_vacacion_anuales_asignados
                            FROM usuarios u
                            LEFT JOIN personal_fichas pf ON u.id_usuario = pf.id_usuario
                            WHERE u.estado = 'activo'";

    $params_reporte_vac = [];
    if ($filtro_id_usuario_vac !== 'todos' && filter_var($filtro_id_usuario_vac, FILTER_VALIDATE_INT)) {
        $sql_base_reporte_vac .= " AND u.id_usuario = :id_usr_filtro";
        $params_reporte_vac[':id_usr_filtro'] = $filtro_id_usuario_vac;
    }
    $sql_base_reporte_vac .= " ORDER BY u.apellidos, u.nombres ASC";

    $stmt_reporte_vac = $pdo->prepare($sql_base_reporte_vac);
    $stmt_reporte_vac->execute($params_reporte_vac);
    $empleados_para_reporte = $stmt_reporte_vac->fetchAll(PDO::FETCH_ASSOC);

    // Comentario: Para cada empleado, obtener sus vacaciones aprobadas en el año seleccionado.
    foreach ($empleados_para_reporte as $emp) {
        $id_empleado_actual_rep = $emp['id_usuario'];
        $dias_asignados = $emp['dias_vacacion_anuales_asignados'] ?? 20; // Comentario: Default si no está en ficha.

        $sql_vac_aprobadas = "SELECT id_solicitud, fecha_inicio_vacacion, fecha_fin_vacacion, dias_solicitados_vacacion
                              FROM solicitudes
                              WHERE id_usuario_solicitante = :id_emp_vac
                              AND tipo_solicitud = 'vacacion'
                              AND estado_solicitud = 'aprobada'
                              AND YEAR(fecha_inicio_vacacion) = :anio_rep
                              ORDER BY fecha_inicio_vacacion ASC";
                              // Comentario: Se podría filtrar por YEAR(fecha_fin_vacacion) o un rango más complejo si las vacaciones cruzan años.
                              // Comentario: Por simplicidad, se usa el año de inicio de la vacación.

        $stmt_vac_aprobadas = $pdo->prepare($sql_vac_aprobadas);
        $stmt_vac_aprobadas->execute([
            ':id_emp_vac' => $id_empleado_actual_rep,
            ':anio_rep' => $anio_actual_para_calculo
        ]);
        $vacaciones_aprobadas_empleado = $stmt_vac_aprobadas->fetchAll(PDO::FETCH_ASSOC);

        $total_dias_tomados_anio = 0;
        foreach ($vacaciones_aprobadas_empleado as $vac_ap) {
            $total_dias_tomados_anio += (int)$vac_ap['dias_solicitados_vacacion'];
        }

        $saldo_dias_vacacion = $dias_asignados - $total_dias_tomados_anio;

        $reporte_datos_vacaciones[] = [
            'id_usuario' => $id_empleado_actual_rep,
            'nombre_completo' => $emp['nombre_completo_empleado'],
            'cargo' => $emp['cargo_empleado'],
            'dias_asignados_anual' => $dias_asignados,
            'vacaciones_aprobadas_detalle' => $vacaciones_aprobadas_empleado, // Comentario: Array de detalles.
            'total_dias_tomados_anio' => $total_dias_tomados_anio,
            'saldo_dias_vacacion' => $saldo_dias_vacacion
        ];
    }

} catch (PDOException $e) {
    error_log("Error al generar reporte de vacaciones: " . $e->getMessage());
    mensaje_flash('error_rep_vac', 'Ocurrió un error al generar el reporte de vacaciones. Intente más tarde.', 'alert-danger');
}

?>
<h2>Reporte de Control de Vacaciones del Personal</h2>

<?php mensaje_flash('error_rep_vac'); ?>

<div class="card-sigi filtros-reporte mb-3">
    <h3>Filtrar Reporte</h3>
    <form action="index.php" method="GET">
        <input type="hidden" name="vista" value="admin_reporte_vacaciones">
        <div class="grupo-formulario-inline">
            <label for="anio_vac">Año:</label>
            <select id="anio_vac" name="anio_vac">
                <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                    <option value="<?php echo $i; ?>" <?php if ($filtro_anio_vacaciones == $i) echo 'selected'; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="grupo-formulario-inline">
            <label for="id_usuario_vac">Empleado:</label>
            <select id="id_usuario_vac" name="id_usuario_vac">
                <option value="todos" <?php if ($filtro_id_usuario_vac === 'todos') echo 'selected'; ?>>Todos los Empleados</option>
                <?php foreach ($lista_usuarios_filtro_vac as $usr_f_v): ?>
                    <option value="<?php echo $usr_f_v['id_usuario']; ?>" <?php if ($filtro_id_usuario_vac == $usr_f_v['id_usuario']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($usr_f_v['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="boton boton-primario">Generar Reporte</button>
        <a href="index.php?vista=admin_reporte_vacaciones" class="boton boton-secundario">Limpiar Filtros</a>
    </form>
</div>

<?php if (empty($reporte_datos_vacaciones) && isset($_GET['anio_vac'])): ?>
    <div class="alert alert-info">No se encontraron datos de vacaciones para los filtros seleccionados.</div>
<?php elseif (!empty($reporte_datos_vacaciones)): ?>
    <div class="card-sigi resultado-reporte">
        <h3>Resultados del Reporte para el Año: <?php echo htmlspecialchars($filtro_anio_vacaciones, ENT_QUOTES, 'UTF-8'); ?></h3>
        <div class="table-responsive">
            <table class="tabla-datos tabla-reporte-vacaciones">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Cargo</th>
                        <th style="text-align:center;">Días Asignados <?php echo $filtro_anio_vacaciones; ?></th>
                        <th style="text-align:center;">Total Días Tomados <?php echo $filtro_anio_vacaciones; ?></th>
                        <th style="text-align:center;">Saldo Días Pendientes</th>
                        <th>Detalle Vacaciones Aprobadas <?php echo $filtro_anio_vacaciones; ?> (ID Sol. / Fechas / Días)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte_datos_vacaciones as $rep_vac): ?>
                    <tr class="<?php if($rep_vac['saldo_dias_vacacion'] < 0) echo 'saldo-negativo-vac'; elseif($rep_vac['saldo_dias_vacacion'] == 0 && $rep_vac['total_dias_tomados_anio'] > 0) echo 'saldo-cero-vac'; ?>">
                        <td><?php echo htmlspecialchars($rep_vac['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($rep_vac['cargo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="text-align:center;"><?php echo $rep_vac['dias_asignados_anual']; ?></td>
                        <td style="text-align:center;"><?php echo $rep_vac['total_dias_tomados_anio']; ?></td>
                        <td style="text-align:center; font-weight:bold;"><?php echo $rep_vac['saldo_dias_vacacion']; ?></td>
                        <td>
                            <?php if (empty($rep_vac['vacaciones_aprobadas_detalle'])): ?>
                                <em>Sin vacaciones aprobadas en <?php echo $filtro_anio_vacaciones; ?>.</em>
                            <?php else: ?>
                                <ul class="lista-detalle-vacaciones">
                                <?php foreach ($rep_vac['vacaciones_aprobadas_detalle'] as $det_vac): ?>
                                    <li>
                                        ID Sol: <?php echo $det_vac['id_solicitud']; ?> |
                                        <?php echo date('d/m/y', strtotime($det_vac['fecha_inicio_vacacion'])); ?> -
                                        <?php echo date('d/m/y', strtotime($det_vac['fecha_fin_vacacion'])); ?>
                                        (<?php echo $det_vac['dias_solicitados_vacacion']; ?> días)
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">Seleccione los filtros y haga clic en "Generar Reporte" para ver los datos.</div>
<?php endif; ?>

<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra_caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.grupo-formulario-inline { display: inline-block; margin-right: 1rem; margin-bottom: 0.5rem; }
.grupo-formulario-inline label { margin-right: 0.3rem; }
.lista-detalle-vacaciones { list-style-type: none; padding-left: 0; margin: 0; font-size: 0.9em; }
.lista-detalle-vacaciones li { margin-bottom: 0.2rem; padding: 2px; border-bottom: 1px dotted #eee; }
.lista-detalle-vacaciones li:last-child { border-bottom: none; }
.saldo-negativo-vac td:nth-child(5) { color: var(--color-error); font-weight: bolder; background-color: #f8d7da;}
.saldo-cero-vac td:nth-child(5) { color: var(--color-exito); }
</style>

<?php
// Comentario: Fin del archivo vistas/admin_reporte_vacaciones.php
?>
