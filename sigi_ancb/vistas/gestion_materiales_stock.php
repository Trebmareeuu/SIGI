<?php
// Archivo: vistas/gestion_materiales_stock.php
// Propósito: (Enc. Activos Fijos / Dir. Admin) CRUD para catálogo de materiales y registro de entradas de stock.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('GESTIONAR_STOCK_MATERIALES', $id_usuario_actual)) {
    mensaje_flash('error_gestion_stock', 'No tiene permisos para gestionar el stock de materiales.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// --- Lógica para CRUD de Catálogo de Materiales ---
$accion_material_crud = $_GET['accion_mat_crud'] ?? 'listar'; // 'listar', 'crear', 'editar'
$id_material_editar = null;
$material_para_editar = null;

// Comentario: Cargar material para edición.
if ($accion_material_crud === 'editar' && isset($_GET['id_material'])) {
    $id_material_editar = filter_var($_GET['id_material'], FILTER_VALIDATE_INT);
    if ($id_material_editar) {
        try {
            $stmt_edit_mat = $pdo->prepare("SELECT * FROM materiales_escritorio WHERE id_material = :id_mat_ed");
            $stmt_edit_mat->bindParam(':id_mat_ed', $id_material_editar, PDO::PARAM_INT);
            $stmt_edit_mat->execute();
            $material_para_editar = $stmt_edit_mat->fetch(PDO::FETCH_ASSOC);
            if (!$material_para_editar) {
                mensaje_flash('error_gestion_stock', 'Material de escritorio no encontrado para editar.', 'alert-danger');
                $accion_material_crud = 'listar';
            }
        } catch (PDOException $e) {
            error_log("Error al cargar material para editar: " . $e->getMessage());
            mensaje_flash('error_gestion_stock', 'Error al cargar datos del material para edición.', 'alert-danger');
            $accion_material_crud = 'listar';
        }
    } else {
        mensaje_flash('error_gestion_stock', 'ID de material no válido para editar.', 'alert-danger');
        $accion_material_crud = 'listar';
    }
}


// Comentario: Guardar/Actualizar catálogo de material.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['guardar_material_nuevo']) || isset($_POST['actualizar_material_existente']))) {
    $id_material_form = filter_input(INPUT_POST, 'id_material_hidden', FILTER_VALIDATE_INT);
    $nombre_material_form = sanitizar_entrada($_POST['nombre_material'] ?? '');
    $descripcion_material_form = strip_tags($_POST['descripcion_material'] ?? '');
    $unidad_medida_form = sanitizar_entrada($_POST['unidad_medida'] ?? '');
    $punto_reorden_form = filter_var($_POST['punto_reorden'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    // Comentario: El stock_actual no se modifica directamente desde este formulario, sino con entradas/salidas.

    $errores_form_mat = [];
    if (empty($nombre_material_form)) $errores_form_mat[] = "El nombre del material es obligatorio.";
    if (empty($unidad_medida_form)) $errores_form_mat[] = "La unidad de medida es obligatoria.";
    if ($punto_reorden_form === false) $errores_form_mat[] = "El punto de reorden debe ser un número entero no negativo.";

    // Comentario: Verificar unicidad de nombre_material.
    $id_excluir_mat_check = $id_material_form ?: 0;
    try {
        $stmt_check_mat_nombre = $pdo->prepare("SELECT id_material FROM materiales_escritorio WHERE nombre_material = :nombre_m AND id_material != :id_excluir_m");
        $stmt_check_mat_nombre->execute([':nombre_m' => $nombre_material_form, ':id_excluir_m' => $id_excluir_mat_check]);
        if ($stmt_check_mat_nombre->fetch()) $errores_form_mat[] = "El nombre de material '$nombre_material_form' ya existe.";
    } catch (PDOException $e) {
        $errores_form_mat[] = "Error al verificar unicidad del nombre de material: " . $e->getMessage();
    }

    if (empty($errores_form_mat)) {
        try {
            $params_sql_mat = [
                ':nom' => $nombre_material_form, ':desc' => $descripcion_material_form,
                ':unidad' => $unidad_medida_form, ':reorden' => $punto_reorden_form
            ];
            if (isset($_POST['guardar_material_nuevo'])) {
                $sql_op_mat = "INSERT INTO materiales_escritorio (nombre_material, descripcion_material, unidad_medida, punto_reorden, stock_actual)
                               VALUES (:nom, :desc, :unidad, :reorden, 0)"; // Comentario: Stock inicial 0.
                $stmt_op_mat = $pdo->prepare($sql_op_mat);
                $stmt_op_mat->execute($params_sql_mat);
                mensaje_flash('exito_gestion_stock', 'Nuevo material de escritorio creado exitosamente.', 'alert-success');
            } elseif (isset($_POST['actualizar_material_existente']) && $id_material_form) {
                $params_sql_mat[':id_mat_upd'] = $id_material_form;
                $sql_op_mat = "UPDATE materiales_escritorio SET nombre_material = :nom, descripcion_material = :desc, unidad_medida = :unidad, punto_reorden = :reorden
                               WHERE id_material = :id_mat_upd";
                $stmt_op_mat = $pdo->prepare($sql_op_mat);
                $stmt_op_mat->execute($params_sql_mat);
                mensaje_flash('exito_gestion_stock', 'Material de escritorio actualizado exitosamente.', 'alert-success');
            }
            redirigir('index.php?vista=gestion_materiales_stock');
        } catch (PDOException $e) {
            error_log("Error al guardar/actualizar material: " . $e->getMessage());
            mensaje_flash('error_form_material_submit', 'Error al procesar la solicitud del material: ' . $e->getMessage(), 'alert-danger');
        }
    } else {
        foreach ($errores_form_mat as $err_m) {
            mensaje_flash('error_form_material_validation', $err_m, 'alert-danger');
        }
        if (isset($_POST['actualizar_material_existente'])) $accion_material_crud = 'editar';
    }
}


// --- Lógica para Registrar Entrada de Stock ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_entrada_stock'])) {
    $id_material_entrada = filter_input(INPUT_POST, 'id_material_entrada_stock', FILTER_VALIDATE_INT);
    $cantidad_entrada = filter_var($_POST['cantidad_entrada_stock'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $observaciones_entrada = strip_tags($_POST['observaciones_entrada_stock'] ?? '');

    $errores_form_entrada = [];
    if (empty($id_material_entrada)) $errores_form_entrada[] = "Debe seleccionar un material para la entrada de stock.";
    if ($cantidad_entrada === false) $errores_form_entrada[] = "La cantidad de entrada debe ser un número entero positivo.";

    if (empty($errores_form_entrada)) {
        $pdo->beginTransaction();
        try {
            // Comentario: 1. Actualizar stock_actual en materiales_escritorio.
            $sql_upd_stock = "UPDATE materiales_escritorio SET stock_actual = stock_actual + :cant WHERE id_material = :id_mat_stock";
            $stmt_upd_stock = $pdo->prepare($sql_upd_stock);
            $stmt_upd_stock->execute([':cant' => $cantidad_entrada, ':id_mat_stock' => $id_material_entrada]);

            // Comentario: 2. Registrar en movimientos_materiales.
            $sql_mov_stock = "INSERT INTO movimientos_materiales (id_material, tipo_movimiento, cantidad, id_usuario_registra, observaciones)
                              VALUES (:id_mat_mov, 'entrada', :cant_mov, :id_user_mov, :obs_mov)";
            $stmt_mov_stock = $pdo->prepare($sql_mov_stock);
            $stmt_mov_stock->execute([
                ':id_mat_mov' => $id_material_entrada, ':cant_mov' => $cantidad_entrada,
                ':id_user_mov' => $id_usuario_actual, ':obs_mov' => $observaciones_entrada
            ]);
            $pdo->commit();
            mensaje_flash('exito_gestion_stock', "Entrada de $cantidad_entrada unidad(es) registrada exitosamente para el material seleccionado.", 'alert-success');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al registrar entrada de stock: " . $e->getMessage());
            mensaje_flash('error_form_entrada_submit', 'Error al registrar la entrada de stock: ' . $e->getMessage(), 'alert-danger');
        }
        redirigir('index.php?vista=gestion_materiales_stock');
    } else {
        foreach ($errores_form_entrada as $err_e) {
            mensaje_flash('error_form_entrada_validation', $err_e, 'alert-danger');
        }
    }
}


// --- Cargar lista de materiales para mostrar y para selects ---
$lista_materiales_catalogo = [];
try {
    $stmt_list_mat = $pdo->query("SELECT * FROM materiales_escritorio ORDER BY nombre_material ASC");
    $lista_materiales_catalogo = $stmt_list_mat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al listar materiales: " . $e->getMessage());
    mensaje_flash('error_gestion_stock', 'Error al cargar el catálogo de materiales.', 'alert-danger');
}

?>
<h2>Gestión de Stock de Materiales de Escritorio</h2>

<?php
mensaje_flash('error_gestion_stock');
mensaje_flash('exito_gestion_stock');
mensaje_flash('error_form_material_submit');
mensaje_flash('error_form_material_validation');
mensaje_flash('error_form_entrada_submit');
mensaje_flash('error_form_entrada_validation');
?>

<div class="gestion-materiales-grid">
    <section class="card-sigi" id="form-material-catalogo">
        <h3><?php echo ($accion_material_crud === 'editar' && $material_para_editar) ? 'Editar Material del Catálogo' : 'Añadir Nuevo Material al Catálogo'; ?></h3>
        <form action="index.php?vista=gestion_materiales_stock" method="POST" class="validar-js">
            <?php if ($accion_material_crud === 'editar' && $material_para_editar): ?>
                <input type="hidden" name="id_material_hidden" value="<?php echo $material_para_editar['id_material']; ?>">
            <?php endif; ?>
            <div class="grupo-formulario">
                <label for="nombre_material">Nombre del Material:</label>
                <input type="text" id="nombre_material" name="nombre_material" value="<?php echo htmlspecialchars($material_para_editar['nombre_material'] ?? ($_POST['nombre_material'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="200">
            </div>
            <div class="grupo-formulario">
                <label for="descripcion_material">Descripción Adicional:</label>
                <textarea id="descripcion_material" name="descripcion_material" rows="2"><?php echo htmlspecialchars($material_para_editar['descripcion_material'] ?? ($_POST['descripcion_material'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="grupo-formulario">
                <label for="unidad_medida">Unidad de Medida:</label>
                <input type="text" id="unidad_medida" name="unidad_medida" value="<?php echo htmlspecialchars($material_para_editar['unidad_medida'] ?? ($_POST['unidad_medida'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50" placeholder="Ej: Unidad, Caja x10, Resma">
            </div>
            <div class="grupo-formulario">
                <label for="punto_reorden">Punto de Reorden (Stock Mínimo):</label>
                <input type="number" id="punto_reorden" name="punto_reorden" value="<?php echo htmlspecialchars($material_para_editar['punto_reorden'] ?? ($_POST['punto_reorden'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" min="0" required>
            </div>

            <?php if ($accion_material_crud === 'editar' && $material_para_editar): ?>
                <button type="submit" name="actualizar_material_existente" class="boton boton-primario">Actualizar Material</button>
                <a href="index.php?vista=gestion_materiales_stock" class="boton boton-secundario">Cancelar Edición</a>
            <?php else: ?>
                <button type="submit" name="guardar_material_nuevo" class="boton boton-exito">Añadir Material al Catálogo</button>
            <?php endif; ?>
        </form>
    </section>

    <section class="card-sigi" id="form-entrada-stock">
        <h3>Registrar Entrada de Stock</h3>
        <form action="index.php?vista=gestion_materiales_stock" method="POST" class="validar-js">
            <div class="grupo-formulario">
                <label for="id_material_entrada_stock">Material:</label>
                <select id="id_material_entrada_stock" name="id_material_entrada_stock" required>
                    <option value="">-- Seleccione un material --</option>
                    <?php foreach($lista_materiales_catalogo as $mat_opt): ?>
                        <option value="<?php echo $mat_opt['id_material']; ?>">
                            <?php echo htmlspecialchars($mat_opt['nombre_material'] . ' (Stock actual: ' . $mat_opt['stock_actual'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grupo-formulario">
                <label for="cantidad_entrada_stock">Cantidad Recibida (Entrada):</label>
                <input type="number" id="cantidad_entrada_stock" name="cantidad_entrada_stock" min="1" required>
            </div>
            <div class="grupo-formulario">
                <label for="observaciones_entrada_stock">Observaciones (Ej: Nro. Factura Proveedor, Fecha Compra):</label>
                <textarea id="observaciones_entrada_stock" name="observaciones_entrada_stock" rows="2"></textarea>
            </div>
            <button type="submit" name="registrar_entrada_stock" class="boton boton-primario">Registrar Entrada</button>
        </form>
    </section>
</div>

<section class="card-sigi mt-3" id="catalogo-materiales">
    <h3>Catálogo de Materiales de Escritorio y Stock Actual</h3>
    <?php if(empty($lista_materiales_catalogo)): ?>
        <p>No hay materiales de escritorio definidos en el catálogo todavía.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre Material</th>
                    <th>Descripción</th>
                    <th>Unidad</th>
                    <th>Stock Actual</th>
                    <th>Punto Reorden</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lista_materiales_catalogo as $mat_item): ?>
                <tr class="<?php if($mat_item['stock_actual'] <= $mat_item['punto_reorden']) echo 'stock-bajo-alerta'; ?>">
                    <td><?php echo $mat_item['id_material']; ?></td>
                    <td><?php echo htmlspecialchars($mat_item['nombre_material'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td title="<?php echo htmlspecialchars($mat_item['descripcion_material'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars(mb_substr($mat_item['descripcion_material'] ?? '', 0, 50) . (mb_strlen($mat_item['descripcion_material'] ?? '') > 50 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td><?php echo htmlspecialchars($mat_item['unidad_medida'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="text-align:center; font-weight:bold;"><?php echo $mat_item['stock_actual']; ?></td>
                    <td style="text-align:center;"><?php echo $mat_item['punto_reorden']; ?></td>
                    <td>
                        <a href="index.php?vista=gestion_materiales_stock&accion_mat_crud=editar&id_material=<?php echo $mat_item['id_material']; ?>#form-material-catalogo" class="boton-tabla editar btn-sm" title="Editar Material">✏️</a>
                        <!-- Comentario: Eliminar material solo si no tiene movimientos o stock? O marcar como inactivo? -->
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<style>
.gestion-materiales-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem; }
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875em; }
.stock-bajo-alerta { background-color: #fff3cd; /* Comentario: Amarillo claro para alerta de stock bajo. */ }
.stock-bajo-alerta td:nth-child(5) { color: var(--color-error); font-weight: bolder; } /* Comentario: Resaltar el stock. */
</style>

<?php
// Comentario: Fin del archivo vistas/gestion_materiales_stock.php
?>
