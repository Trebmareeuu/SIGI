<?php
// Archivo: vistas/asistencia_reportes.php
// Propósito: (Dir. Admin) Vista para generar y ver reportes de asistencia del personal.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('VER_REPORTES_ASISTENCIA', $id_usuario_actual)) {
    mensaje_flash('error_reporte_asist', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para filtros del reporte.
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Comentario: Primer día del mes actual por defecto.
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-t');  // Comentario: Último día del mes actual por defecto.
$filtro_id_empleado = $_GET['id_empleado'] ?? 'todos';       // Comentario: 'todos' o ID de un empleado.

// Comentario: Cargar lista de empleados para el filtro.
$lista_empleados = [];
try {
    $stmt_emp = $pdo->query("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo, cargo
                             FROM usuarios
                             WHERE estado = 'activo'
                             ORDER BY apellidos, nombres ASC");
    $lista_empleados = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar lista de empleados para reporte asistencia: " . $e->getMessage());
    // Comentario: No es crítico, el filtro puede no mostrarse o estar vacío.
}

// Comentario: Datos del reporte.
$reporte_asistencia_agrupado = [];

if (isset($_GET['generar_reporte'])) { // Comentario: Generar reporte solo si se activa el botón/filtro.
    $condiciones_sql = ["DATE(a.fecha_hora_marcacion) BETWEEN :fecha_desde AND :fecha_hasta"];
    $params_sql = [
        ':fecha_desde' => $filtro_fecha_desde,
        ':fecha_hasta' => $filtro_fecha_hasta
    ];

    if ($filtro_id_empleado !== 'todos' && filter_var($filtro_id_empleado, FILTER_VALIDATE_INT)) {
        // Comentario: Si se filtra por un empleado específico, buscar por id_usuario_sistema o id_empleado_biometrico.
        // Comentario: Para esto, necesitamos saber cómo se relaciona el id_empleado del filtro (que será un id_usuario)
        // Comentario: con los datos en la tabla 'asistencia'.
        // Comentario: Asumimos que 'id_usuario_sistema' en 'asistencia' es el id_usuario.
        // Comentario: O si no está enlazado, se podría buscar por 'id_empleado_biometrico' si el filtro de empleado
        // Comentario: pudiera proporcionar ese ID (más complejo para el UI).
        // Comentario: Por ahora, se asume que el filtro_id_empleado es el 'id_usuario_sistema'.
        $condiciones_sql[] = "a.id_usuario_sistema = :id_empleado_filtro";
        $params_sql[':id_empleado_filtro'] = $filtro_id_empleado;
    }

    $where_clause = "WHERE " . implode(" AND ", $condiciones_sql);

    try {
        // Comentario: Consulta para agrupar marcaciones por empleado y día, mostrando primera y última marca.
        // Comentario: Esta consulta es un ejemplo y puede necesitar ajustes según los requerimientos exactos del reporte
        // Comentario: (ej. manejo de múltiples entradas/salidas en un día, cálculo de horas trabajadas, etc.).
        $sql_reporte = "SELECT
                            COALESCE(u.id_usuario, a.id_empleado_biometrico) as id_ref_empleado, -- ID de referencia
                            COALESCE(CONCAT(u.apellidos, ', ', u.nombres), a.id_empleado_biometrico) as nombre_empleado,
                            u.cargo as cargo_empleado,
                            DATE(a.fecha_hora_marcacion) as fecha_marcacion,
                            MIN(CASE WHEN a.tipo_marcacion = 'entrada' OR a.tipo_marcacion = 'desconocido' THEN TIME(a.fecha_hora_marcacion) ELSE NULL END) as primera_entrada,
                            MAX(CASE WHEN a.tipo_marcacion = 'salida' OR a.tipo_marcacion = 'desconocido' THEN TIME(a.fecha_hora_marcacion) ELSE NULL END) as ultima_salida,
                            GROUP_CONCAT(DISTINCT TIME(a.fecha_hora_marcacion) ORDER BY a.fecha_hora_marcacion SEPARATOR ', ') as todas_las_marcas
                        FROM asistencia a
                        LEFT JOIN usuarios u ON a.id_usuario_sistema = u.id_usuario
                        $where_clause
                        GROUP BY id_ref_empleado, nombre_empleado, cargo_empleado, fecha_marcacion
                        ORDER BY nombre_empleado ASC, fecha_marcacion ASC";

        $stmt_reporte = $pdo->prepare($sql_reporte);
        $stmt_reporte->execute($params_sql);
        $reporte_asistencia_agrupado = $stmt_reporte->fetchAll(PDO::FETCH_ASSOC);

        if (empty($reporte_asistencia_agrupado)) {
            mensaje_flash('info_reporte_asist', 'No se encontraron registros de asistencia para los filtros seleccionados.', 'alert-info');
        }

    } catch (PDOException $e) {
        error_log("Error al generar reporte de asistencia: " . $e->getMessage());
        mensaje_flash('error_reporte_asist', 'Ocurrió un error al generar el reporte. Intente más tarde.', 'alert-danger');
    }
}

?>
<h2>Reportes de Asistencia</h2>

<?php
mensaje_flash('error_reporte_asist');
mensaje_flash('info_reporte_asist');
?>

<div class="card-sigi filtros-reporte mb-3">
    <h3>Filtrar Reporte</h3>
    <form action="index.php" method="GET">
        <input type="hidden" name="vista" value="asistencia_reportes">
        <input type="hidden" name="generar_reporte" value="1">

        <div class="grupo-formulario-inline">
            <label for="fecha_desde">Desde:</label>
            <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($filtro_fecha_desde, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="grupo-formulario-inline">
            <label for="fecha_hasta">Hasta:</label>
            <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($filtro_fecha_hasta, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="grupo-formulario-inline">
            <label for="id_empleado">Empleado:</label>
            <select id="id_empleado" name="id_empleado">
                <option value="todos" <?php echo ($filtro_id_empleado === 'todos') ? 'selected' : ''; ?>>Todos los Empleados</option>
                <?php foreach ($lista_empleados as $emp): ?>
                    <option value="<?php echo $emp['id_usuario']; ?>" <?php echo ($filtro_id_empleado == $emp['id_usuario']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($emp['nombre_completo'] . ($emp['cargo'] ? ' (' . $emp['cargo'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="boton boton-primario">Generar Reporte</button>
        <!-- Comentario: Botón para exportar a CSV/Excel (requiere librería o lógica adicional) -->
        <!-- <button type="submit" name="exportar_reporte" value="csv" class="boton boton-secundario">Exportar a CSV</button> -->
    </form>
</div>

<?php if (isset($_GET['generar_reporte']) && !empty($reporte_asistencia_agrupado)): ?>
    <div class="card-sigi resultado-reporte">
        <h3>Resultados del Reporte de Asistencia</h3>
        <p>Periodo: <?php echo date("d/m/Y", strtotime($filtro_fecha_desde)); ?> al <?php echo date("d/m/Y", strtotime($filtro_fecha_hasta)); ?></p>
        <?php if ($filtro_id_empleado !== 'todos'):
            $nombre_empleado_filtrado = "Todos";
            foreach($lista_empleados as $e){ if($e['id_usuario'] == $filtro_id_empleado) {$nombre_empleado_filtrado = $e['nombre_completo']; break;} }
        ?>
        <p>Empleado: <?php echo htmlspecialchars($nombre_empleado_filtrado, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="tabla-datos tabla-reporte-asistencia">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Cargo</th>
                        <th>Fecha</th>
                        <th>Primera Entrada</th>
                        <th>Última Salida</th>
                        <th>Horas (Aprox.)</th>
                        <th>Todas las Marcas del Día</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $empleado_actual_reporte = null;
                    foreach ($reporte_asistencia_agrupado as $reg):
                        // Comentario: Para agrupar visualmente por empleado si son varios.
                        if ($filtro_id_empleado === 'todos' && $reg['nombre_empleado'] !== $empleado_actual_reporte) {
                            if ($empleado_actual_reporte !== null) {
                                // echo '<tr><td colspan="7" class="separador-empleado"></td></tr>'; // Comentario: Separador.
                            }
                            $empleado_actual_reporte = $reg['nombre_empleado'];
                        }

                        // Comentario: Calcular horas trabajadas (aproximado, no considera almuerzo, etc.).
                        $horas_trabajadas_str = "N/A";
                        if ($reg['primera_entrada'] && $reg['ultima_salida']) {
                            try {
                                $entrada_dt = new DateTime($reg['primera_entrada']);
                                $salida_dt = new DateTime($reg['ultima_salida']);
                                if ($salida_dt > $entrada_dt) {
                                    $intervalo_trabajo = $entrada_dt->diff($salida_dt);
                                    $horas_trabajadas_str = $intervalo_trabajo->format('%H:%I');
                                }
                            } catch (Exception $e_time) { $horas_trabajadas_str = "Error Calc."; }
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reg['nombre_empleado'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($reg['cargo_empleado'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo date('d/m/Y (D)', strtotime($reg['fecha_marcacion'])); // Comentario: (D) para día de la semana. ?></td>
                        <td class="<?php echo !$reg['primera_entrada'] ? 'dato-faltante' : ''; ?>"><?php echo $reg['primera_entrada'] ? htmlspecialchars($reg['primera_entrada'], ENT_QUOTES, 'UTF-8') : 'Sin Entrada'; ?></td>
                        <td class="<?php echo !$reg['ultima_salida'] ? 'dato-faltante' : ''; ?>"><?php echo $reg['ultima_salida'] ? htmlspecialchars($reg['ultima_salida'], ENT_QUOTES, 'UTF-8') : 'Sin Salida'; ?></td>
                        <td><?php echo $horas_trabajadas_str; ?></td>
                        <td class="todas-las-marcas"><?php echo htmlspecialchars($reg['todas_las_marcas'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif (isset($_GET['generar_reporte']) && empty($reporte_asistencia_agrupado)): ?>
    <!-- Comentario: El mensaje flash 'info_reporte_asist' ya se muestra arriba si no hay datos. -->
<?php else: ?>
    <div class="alert alert-info">Seleccione los filtros y haga clic en "Generar Reporte" para ver los datos de asistencia.</div>
<?php endif; ?>


<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.grupo-formulario-inline { display: inline-block; margin-right: 1rem; margin-bottom: 0.5rem; }
.grupo-formulario-inline label { margin-right: 0.3rem; }
.tabla-reporte-asistencia td.dato-faltante { color: #dc3545; font-style: italic; }
.tabla-reporte-asistencia td.todas-las-marcas { font-size: 0.85em; color: #6c757d; }
.separador-empleado { background-color: #e9ecef; height: 5px; } /* Comentario: Estilo para separador. */
</style>

<?php
// Comentario: Fin del archivo vistas/asistencia_reportes.php
?>
