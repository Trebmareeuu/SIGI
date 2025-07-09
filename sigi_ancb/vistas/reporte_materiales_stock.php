<?php
// Archivo: vistas/reporte_materiales_stock.php
// Propósito: (Dir. Admin) Visualización de stock y movimientos de materiales de escritorio.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
// Comentario: El permiso 'VER_STOCK_MATERIALES' ya fue añadido al rol Dir. Admin en schema.sql.
// Comentario: Si se quisiera un permiso más específico solo para el reporte de movimientos, se podría crear.
if (!tiene_permiso('VER_STOCK_MATERIALES', $id_usuario_actual) && !tiene_permiso('GESTIONAR_STOCK_MATERIALES', $id_usuario_actual) ) {
    mensaje_flash('error_reporte_stock', 'No tiene permisos para ver el stock y movimientos de materiales.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// --- Cargar Stock Actual de Materiales ---
$stock_materiales = [];
try {
    $stmt_stock = $pdo->query("SELECT id_material, nombre_material, descripcion_material, unidad_medida, stock_actual, punto_reorden
                               FROM materiales_escritorio
                               ORDER BY nombre_material ASC");
    $stock_materiales = $stmt_stock->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar stock de materiales para reporte: " . $e->getMessage());
    mensaje_flash('error_reporte_stock', 'Error al cargar el stock de materiales.', 'alert-danger');
}

// --- Cargar Últimos Movimientos de Materiales (con paginación) ---
$movimientos_materiales = [];
$pagina_actual_mov = isset($_GET['pagina_mov']) ? (int)$_GET['pagina_mov'] : 1;
$regs_por_pagina_mov = 15;
$offset_mov = ($pagina_actual_mov - 1) * $regs_por_pagina_mov;
$total_regs_mov = 0;

// Comentario: Filtros para movimientos (opcional, se pueden añadir más adelante si es necesario)
$filtro_tipo_movimiento = $_GET['filtro_tipo_mov'] ?? '';
$filtro_id_material_mov = $_GET['filtro_id_mat_mov'] ?? '';
$filtro_fecha_desde_mov = $_GET['filtro_fecha_desde_mov'] ?? '';
$filtro_fecha_hasta_mov = $_GET['filtro_fecha_hasta_mov'] ?? '';

$condiciones_sql_mov = [];
$params_sql_mov = [];

if(!empty($filtro_tipo_movimiento)){
    $condiciones_sql_mov[] = "mm.tipo_movimiento = :tipo_mov";
    $params_sql_mov[':tipo_mov'] = $filtro_tipo_movimiento;
}
if(!empty($filtro_id_material_mov) && filter_var($filtro_id_material_mov, FILTER_VALIDATE_INT)){
    $condiciones_sql_mov[] = "mm.id_material = :id_mat_mov";
    $params_sql_mov[':id_mat_mov'] = $filtro_id_material_mov;
}
if(!empty($filtro_fecha_desde_mov) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $filtro_fecha_desde_mov)){
    $condiciones_sql_mov[] = "DATE(mm.fecha_movimiento) >= :fecha_desde_mov";
    $params_sql_mov[':fecha_desde_mov'] = $filtro_fecha_desde_mov;
}
if(!empty($filtro_fecha_hasta_mov) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $filtro_fecha_hasta_mov)){
    $condiciones_sql_mov[] = "DATE(mm.fecha_movimiento) <= :fecha_hasta_mov";
    $params_sql_mov[':fecha_hasta_mov'] = $filtro_fecha_hasta_mov;
}
$where_clause_mov = empty($condiciones_sql_mov) ? '' : 'WHERE ' . implode(' AND ', $condiciones_sql_mov);


try {
    $sql_count_mov = "SELECT COUNT(*) FROM movimientos_materiales mm $where_clause_mov";
    $stmt_count_mov = $pdo->prepare($sql_count_mov);
    $stmt_count_mov->execute($params_sql_mov);
    $total_regs_mov = (int)$stmt_count_mov->fetchColumn();

    $sql_mov = "SELECT mm.*, m.nombre_material,
                       CONCAT(u_reg.apellidos, ', ', u_reg.nombres) as nombre_usuario_registra,
                       s.id_usuario_solicitante,
                       CONCAT(u_sol.apellidos, ', ', u_sol.nombres) as nombre_usuario_solicitud
                FROM movimientos_materiales mm
                JOIN materiales_escritorio m ON mm.id_material = m.id_material
                JOIN usuarios u_reg ON mm.id_usuario_registra = u_reg.id_usuario
                LEFT JOIN solicitudes s ON mm.id_solicitud_asociada = s.id_solicitud
                LEFT JOIN usuarios u_sol ON s.id_usuario_solicitante = u_sol.id_usuario
                $where_clause_mov
                ORDER BY mm.fecha_movimiento DESC, mm.id_movimiento DESC
                LIMIT :limit OFFSET :offset";
    $stmt_mov = $pdo->prepare($sql_mov);

    $params_sql_mov_pag = array_merge($params_sql_mov, [':limit' => $regs_por_pagina_mov, ':offset' => $offset_mov]);
    foreach($params_sql_mov_pag as $key_pm => &$val_pm) {
        $stmt_mov->bindParam($key_pm, $val_pm, (is_int($val_pm) ? PDO::PARAM_INT : PDO::PARAM_STR) );
    }
    unset($val_pm);

    $stmt_mov->execute();
    $movimientos_materiales = $stmt_mov->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar movimientos de materiales para reporte: " . $e->getMessage());
    mensaje_flash('error_reporte_stock', 'Error al cargar los movimientos de materiales.', 'alert-danger');
}
$total_paginas_mov = ceil($total_regs_mov / $regs_por_pagina_mov);

?>
<h2>Reporte de Stock y Movimientos de Materiales de Escritorio</h2>

<?php
mensaje_flash('error_reporte_stock');
mensaje_flash('exito_reporte_stock'); // Comentario: No hay acciones de éxito directas en esta vista.
?>

<section class="card-sigi mb-3" id="stock-actual-materiales">
    <h3>Stock Actual de Materiales</h3>
    <?php if(empty($stock_materiales)): ?>
        <p class="alert alert-info">No hay materiales de escritorio definidos en el catálogo.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre Material</th>
                    <th>Unidad</th>
                    <th style="text-align:center;">Stock Actual</th>
                    <th style="text-align:center;">Punto Reorden</th>
                    <th>Estado Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($stock_materiales as $stk_item):
                    $estado_stock_txt = "Ok";
                    $clase_stock_alerta = "";
                    if ($stk_item['stock_actual'] == 0) {
                        $estado_stock_txt = "Agotado";
                        $clase_stock_alerta = "stock-agotado";
                    } elseif ($stk_item['stock_actual'] <= $stk_item['punto_reorden'] && $stk_item['punto_reorden'] > 0) {
                        $estado_stock_txt = "Bajo (Reordenar)";
                        $clase_stock_alerta = "stock-bajo-alerta";
                    }
                ?>
                <tr class="<?php echo $clase_stock_alerta; ?>">
                    <td><?php echo $stk_item['id_material']; ?></td>
                    <td><?php echo htmlspecialchars($stk_item['nombre_material'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($stk_item['unidad_medida'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="text-align:center; font-weight:bold;"><?php echo $stk_item['stock_actual']; ?></td>
                    <td style="text-align:center;"><?php echo $stk_item['punto_reorden']; ?></td>
                    <td><?php echo $estado_stock_txt; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="card-sigi" id="movimientos-materiales">
    <h3>Historial de Movimientos de Materiales</h3>
    <form action="index.php" method="GET" class="form-filtros mb-2">
        <input type="hidden" name="vista" value="reporte_materiales_stock">
        <div class="grid-filtros-mov">
            <div class="grupo-formulario-sm">
                <label for="filtro_id_mat_mov">Material:</label>
                <select name="filtro_id_mat_mov" id="filtro_id_mat_mov">
                    <option value="">Todos</option>
                     <?php foreach($stock_materiales as $mat_f_opt): ?>
                        <option value="<?php echo $mat_f_opt['id_material']; ?>" <?php if($filtro_id_material_mov == $mat_f_opt['id_material']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($mat_f_opt['nombre_material'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grupo-formulario-sm">
                <label for="filtro_tipo_mov">Tipo Mov.:</label>
                <select name="filtro_tipo_mov" id="filtro_tipo_mov">
                    <option value="">Todos</option>
                    <option value="entrada" <?php if($filtro_tipo_movimiento == 'entrada') echo 'selected'; ?>>Entrada</option>
                    <option value="salida_solicitud" <?php if($filtro_tipo_movimiento == 'salida_solicitud') echo 'selected'; ?>>Salida por Solicitud</option>
                    <option value="ajuste_positivo" <?php if($filtro_tipo_movimiento == 'ajuste_positivo') echo 'selected'; ?>>Ajuste Positivo</option>
                    <option value="ajuste_negativo" <?php if($filtro_tipo_movimiento == 'ajuste_negativo') echo 'selected'; ?>>Ajuste Negativo</option>
                </select>
            </div>
            <div class="grupo-formulario-sm">
                <label for="filtro_fecha_desde_mov">Desde:</label>
                <input type="date" name="filtro_fecha_desde_mov" id="filtro_fecha_desde_mov" value="<?php echo htmlspecialchars($filtro_fecha_desde_mov, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="grupo-formulario-sm">
                <label for="filtro_fecha_hasta_mov">Hasta:</label>
                <input type="date" name="filtro_fecha_hasta_mov" id="filtro_fecha_hasta_mov" value="<?php echo htmlspecialchars($filtro_fecha_hasta_mov, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <button type="submit" class="boton boton-primario btn-sm mt-2">Filtrar Movimientos</button>
        <a href="index.php?vista=reporte_materiales_stock" class="boton boton-secundario btn-sm mt-2">Limpiar Filtros</a>
    </form>

    <?php if(empty($movimientos_materiales)): ?>
        <p class="alert alert-info">No hay movimientos de materiales que coincidan con los filtros.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>Fecha Mov.</th>
                    <th>Material</th>
                    <th>Tipo Movimiento</th>
                    <th style="text-align:right;">Cantidad</th>
                    <th>Usuario Registra</th>
                    <th>Solicitud Asoc. (ID)</th>
                    <th>Solicitante (Salida)</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($movimientos_materiales as $mov_item): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($mov_item['fecha_movimiento'])); ?></td>
                    <td><?php echo htmlspecialchars($mov_item['nombre_material'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo ucfirst(str_replace('_',' ',$mov_item['tipo_movimiento'])); ?></td>
                    <td style="text-align:right;"><?php echo $mov_item['cantidad']; ?></td>
                    <td><?php echo htmlspecialchars($mov_item['nombre_usuario_registra'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="text-align:center;"><?php echo $mov_item['id_solicitud_asociada'] ?? 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($mov_item['nombre_usuario_solicitud'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td title="<?php echo htmlspecialchars($mov_item['observaciones'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars(mb_substr($mov_item['observaciones'] ?? '', 0, 40) . (mb_strlen($mov_item['observaciones'] ?? '') > 40 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Paginación para Movimientos -->
    <?php if ($total_paginas_mov > 1):
        // Comentario: Construir URL con filtros para paginación de movimientos.
        $params_url_pag_mov = $_GET; unset($params_url_pag_mov['pagina_mov']);
    ?>
        <nav class="paginacion mt-3">
            <ul class="pagination-lista">
                <?php if ($pagina_actual_mov > 1):
                    $url_anterior_mov = "index.php?" . http_build_query($params_url_pag_mov) . "&pagina_mov=" . ($pagina_actual_mov - 1);
                ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $url_anterior_mov; ?>">Anterior</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_paginas_mov; $i++):
                     $url_pagina_i_mov = "index.php?" . http_build_query($params_url_pag_mov) . "&pagina_mov=" . $i;
                ?>
                    <li class="page-item <?php echo ($i == $pagina_actual_mov) ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $url_pagina_i_mov; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($pagina_actual_mov < $total_paginas_mov):
                    $url_siguiente_mov = "index.php?" . http_build_query($params_url_pag_mov) . "&pagina_mov=" . ($pagina_actual_mov + 1);
                ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $url_siguiente_mov; ?>">Siguiente</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <?php endif; ?>
</section>

<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.stock-bajo-alerta { background-color: #fff3cd !important; }
.stock-bajo-alerta td:nth-child(4), .stock-bajo-alerta td:nth-child(6) { color: #856404; font-weight: bold; }
.stock-agotado { background-color: #f8d7da !important; }
.stock-agotado td:nth-child(4), .stock-agotado td:nth-child(6) { color: var(--color-error); font-weight: bolder; }
.grid-filtros-mov { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem 1rem; }
.form-filtros .grupo-formulario-sm input, .form-filtros .grupo-formulario-sm select { width: calc(100% - 10px); } /* Comentario: Ajuste para que quepan bien */
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875em; }
/* Comentario: Paginación ya debería estar estilizada globalmente o en estilos.css */
</style>

<?php
// Comentario: Fin del archivo vistas/reporte_materiales_stock.php
?>
