<?php
// Archivo: vistas/solicitud_material.php
// Propósito: Formulario para que los usuarios soliciten material de escritorio.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('SOLICITAR_MATERIAL', $id_usuario_actual)) {
    mensaje_flash('error_sol_mat', 'No tiene permisos para solicitar material de escritorio.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para el formulario.
$items_solicitados_catalogo = $_POST['items_catalogo'] ?? []; // Array [id_material => cantidad]
$descripcion_otros_materiales = $_POST['descripcion_otros_materiales'] ?? '';
$justificacion_material = $_POST['justificacion_material'] ?? '';

// Comentario: Cargar materiales del catálogo para mostrar en el formulario.
$materiales_catalogo_disponibles = [];
try {
    $stmt_cat = $pdo->query("SELECT id_material, nombre_material, unidad_medida, stock_actual FROM materiales_escritorio WHERE stock_actual > 0 ORDER BY nombre_material ASC");
    // Comentario: O mostrar todos y que el usuario vea stock 0, y el sistema lo maneje. Por ahora, solo los con stock > 0.
    // Comentario: Si se quiere mostrar todos: SELECT id_material, nombre_material, unidad_medida, stock_actual FROM materiales_escritorio ORDER BY nombre_material ASC
    $materiales_catalogo_disponibles = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al cargar catálogo de materiales para solicitud: " . $e->getMessage());
    mensaje_flash('error_sol_mat_form', 'Error al cargar la lista de materiales disponibles.', 'alert-warning');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_solicitud_material'])) {
    $items_solicitados_catalogo_post = $_POST['items_catalogo'] ?? [];
    $descripcion_otros_materiales_post = strip_tags($_POST['descripcion_otros_materiales'] ?? '');
    $justificacion_material_post = strip_tags($_POST['justificacion_material'] ?? '');

    $errores_formulario = [];
    $hay_items_seleccionados = false;

    // Comentario: Validar items del catálogo.
    $detalle_items_catalogo_para_guardar = [];
    foreach ($items_solicitados_catalogo_post as $id_mat => $cantidad_sol) {
        $id_material = filter_var($id_mat, FILTER_VALIDATE_INT);
        $cantidad = filter_var($cantidad_sol, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id_material && $cantidad) {
            // Comentario: Verificar si el material existe y obtener su nombre y stock (para validación y descripción).
            $stmt_check_mat = $pdo->prepare("SELECT nombre_material, stock_actual, unidad_medida FROM materiales_escritorio WHERE id_material = :idm");
            $stmt_check_mat->bindParam(':idm', $id_material, PDO::PARAM_INT);
            $stmt_check_mat->execute();
            $info_mat_db = $stmt_check_mat->fetch(PDO::FETCH_ASSOC);

            if ($info_mat_db) {
                // Comentario: Opcional: Validar si la cantidad solicitada excede el stock.
                // Comentario: Por ahora, se permite solicitar más del stock y se gestiona en aprobación.
                // if ($cantidad > $info_mat_db['stock_actual']) {
                //     $errores_formulario[] = "La cantidad solicitada para '" . htmlspecialchars($info_mat_db['nombre_material']) . "' (" . $cantidad . ") excede el stock disponible (" . $info_mat_db['stock_actual'] . ").";
                // }
                $detalle_items_catalogo_para_guardar[] = "- " . $cantidad . " " . htmlspecialchars($info_mat_db['unidad_medida'], ENT_QUOTES, 'UTF-8') . " de " . htmlspecialchars($info_mat_db['nombre_material'], ENT_QUOTES, 'UTF-8') . " (ID Cat: " . $id_material . ")";
                $hay_items_seleccionados = true;
            } else {
                $errores_formulario[] = "Se intentó solicitar un material del catálogo no válido (ID: $id_material).";
            }
        } elseif ($id_material && !$cantidad && !empty($cantidad_sol) ) { // Comentario: Si se seleccionó pero la cantidad es 0 o inválida.
             $errores_formulario[] = "La cantidad para un material seleccionado del catálogo no es válida.";
        }
    }

    if (!$hay_items_seleccionados && empty($descripcion_otros_materiales_post)) {
        $errores_formulario[] = "Debe solicitar al menos un material del catálogo o describir otros materiales.";
    }
    if (empty($justificacion_material_post)) {
        $errores_formulario[] = "La justificación de la solicitud es obligatoria.";
    }

    if (empty($errores_formulario)) {
        try {
            $descripcion_final_solicitud = "Justificación:\n" . $justificacion_material_post . "\n\n";
            if (!empty($detalle_items_catalogo_para_guardar)) {
                $descripcion_final_solicitud .= "Materiales del Catálogo Solicitados:\n" . implode("\n", $detalle_items_catalogo_para_guardar) . "\n\n";
            }
            if (!empty($descripcion_otros_materiales_post)) {
                $descripcion_final_solicitud .= "Otros Materiales Solicitados (no en catálogo o sin stock):\n" . $descripcion_otros_materiales_post . "\n";
            }

            // Comentario: JSON para datos estructurados de la solicitud (opcional, pero recomendado para facilitar el procesamiento).
            $datos_estructurados_solicitud = json_encode([
                'items_catalogo' => $items_solicitados_catalogo_post, // Guardar [id_material => cantidad]
                'otros_materiales' => $descripcion_otros_materiales_post
            ]);


            $sql = "INSERT INTO solicitudes (id_usuario_solicitante, tipo_solicitud, descripcion_solicitud, estado_solicitud, fecha_solicitud, observaciones_gestion)
                    VALUES (:id_usuario, 'material_escritorio', :descripcion, 'pendiente_aprobacion_admin', NOW(), :datos_json)";
            // Comentario: Usamos 'observaciones_gestion' temporalmente para el JSON, idealmente sería un campo 'datos_adjuntos_json' o similar.
            // Comentario: O modificar 'descripcion_solicitud' para que sea TEXT y pueda guardar más info.
            // Comentario: Por ahora, la descripción legible va en descripcion_solicitud, y el JSON en observaciones_gestion.

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $id_usuario_actual,
                ':descripcion' => trim($descripcion_final_solicitud),
                ':datos_json' => $datos_estructurados_solicitud
            ]);

            mensaje_flash('exito_sol_mat', 'Su solicitud de material ha sido enviada exitosamente y está pendiente de aprobación.', 'alert-success');
            redirigir('index.php?vista=solicitudes_historial');

        } catch (PDOException $e) {
            error_log("Error al guardar solicitud de material (mejorada): " . $e->getMessage());
            mensaje_flash('error_sol_mat', 'Ocurrió un error al procesar su solicitud. Intente más tarde.', 'alert-danger');
        }
    } else {
        // Comentario: Repoblar campos para el usuario.
        $items_solicitados_catalogo = $items_solicitados_catalogo_post;
        $descripcion_otros_materiales = $descripcion_otros_materiales_post;
        $justificacion_material = $justificacion_material_post;
        foreach ($errores_formulario as $error) {
            mensaje_flash('error_sol_mat_form', $error, 'alert-danger');
        }
    }
}

?>
<h2>Solicitud de Material de Escritorio</h2>

<?php
mensaje_flash('error_sol_mat');
mensaje_flash('error_sol_mat_form');
mensaje_flash('exito_sol_mat');
?>

<p>Seleccione materiales del catálogo disponible o describa otros materiales que necesite.</p>

<form action="index.php?vista=solicitud_material" method="POST" class="validar-js">
    <fieldset class="mb-3">
        <legend>Materiales del Catálogo (Disponibles en Stock)</legend>
        <?php if (empty($materiales_catalogo_disponibles)): ?>
            <p class="alert alert-info">No hay materiales disponibles en el catálogo con stock actualmente.</p>
        <?php else: ?>
            <div class="catalogo-items-grid">
            <?php foreach ($materiales_catalogo_disponibles as $mat_cat): ?>
                <div class="item-catalogo-solicitud">
                    <label for="item_cat_<?php echo $mat_cat['id_material']; ?>">
                        <strong><?php echo htmlspecialchars($mat_cat['nombre_material'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <small>(Unidad: <?php echo htmlspecialchars($mat_cat['unidad_medida'], ENT_QUOTES, 'UTF-8'); ?>, Stock: <?php echo $mat_cat['stock_actual']; ?>)</small>
                    </label>
                    <input type="number"
                           name="items_catalogo[<?php echo $mat_cat['id_material']; ?>]"
                           id="item_cat_<?php echo $mat_cat['id_material']; ?>"
                           min="0"
                           max="<?php echo $mat_cat['stock_actual']; // Comentario: Opcional: Limitar al stock actual en cliente ?>"
                           placeholder="Cantidad"
                           value="<?php echo htmlspecialchars($items_solicitados_catalogo[$mat_cat['id_material']] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           class="form-control-sm input-cantidad-catalogo">
                </div>
            <?php endforeach; ?>
            </div>
            <small class="form-text text-muted">Deje la cantidad en blanco o en 0 si no desea solicitar un ítem del catálogo.</small>
        <?php endif; ?>
    </fieldset>

    <div class="grupo-formulario">
        <label for="descripcion_otros_materiales">Otros Materiales No Listados o Sin Stock (Incluir cantidades):</label>
        <textarea id="descripcion_otros_materiales" name="descripcion_otros_materiales" rows="4" placeholder="Ej:
- 1 Tóner para impresora modelo X (específico)
- 5 Carpetas Colgantes Azules (si no están en catálogo)"><?php echo htmlspecialchars($descripcion_otros_materiales, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Describa aquí ítems que no encontró en el catálogo o que figuran sin stock.</small>
    </div>

    <div class="grupo-formulario">
        <label for="justificacion_material">Justificación General de la Solicitud:</label>
        <textarea id="justificacion_material" name="justificacion_material" rows="4" required><?php echo htmlspecialchars($justificacion_material, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small>Explique brevemente por qué necesita estos materiales.</small>
    </div>

    <div class="grupo-formulario acciones-formulario">
        <button type="submit" name="enviar_solicitud_material" class="boton boton-primario">Enviar Solicitud</button>
        <a href="index.php?vista=dashboard" class="boton boton-secundario">Cancelar</a>
    </div>
</form>

<style>
.catalogo-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}
.item-catalogo-solicitud {
    border: 1px solid #eee;
    padding: 0.75rem;
    border-radius: var(--borde-radio);
    background-color: #f9f9f9;
}
.item-catalogo-solicitud label {
    display: block;
    margin-bottom: 0.3rem;
}
.item-catalogo-solicitud label small {
    color: var(--color-secundario);
}
.input-cantidad-catalogo {
    width: 80px; /* Comentario: Ancho fijo para cantidad. */
    padding: 0.3rem;
}
.text-muted { color: #6c757d !important; }
</style>

<?php
// Comentario: Fin del archivo vistas/solicitud_material.php
?>
