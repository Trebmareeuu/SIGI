<?php
// Archivo: vistas/comunicados_lista.php
// Propósito: Muestra la lista de comunicados publicados para los usuarios.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
$rol_usuario_actual_id = $_SESSION['id_rol'] ?? null; // Comentario: Asumiendo que id_rol está en sesión.

if (!tiene_permiso('VER_COMUNICADOS', $id_usuario_actual)) {
    mensaje_flash('error_com_lista', 'No tiene permisos para ver comunicados.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;
$comunicados_publicos = [];

// Comentario: Paginación para comunicados.
$pagina_actual_com = isset($_GET['pagina_com']) ? (int)$_GET['pagina_com'] : 1;
$regs_por_pagina_com = 10;
$offset_com = ($pagina_actual_com - 1) * $regs_por_pagina_com;
$total_regs_com = 0;

// Comentario: Construir condiciones para filtrar comunicados visibles para el rol actual o para todos.
$condiciones_com_visibles = "c.estado = 'publicado' AND c.fecha_publicacion <= NOW() AND (c.fecha_expiracion IS NULL OR c.fecha_expiracion > NOW())";
$params_com_visibles = [];

if ($rol_usuario_actual_id) {
    // Comentario: Si para_roles es NULL, es para todos. Si tiene un JSON array, verificar si el rol del usuario está en él.
    // Comentario: JSON_CONTAINS(para_roles, '["id_rol_del_usuario"]') o similar, adaptado a MySQL.
    // Comentario: O más simple en PHP: para_roles IS NULL OR JSON_SEARCH(para_roles, 'one', '$rol_usuario_actual_id') IS NOT NULL
    // Comentario: Para MySQL 5.7+ se puede usar JSON_CONTAINS. Si es anterior, LIKE '%"id_rol"%' es menos preciso pero una opción.
    // Comentario: Asumiendo que para_roles es un array de IDs de rol: e.g., "[1, 3, 5]"
    $condiciones_com_visibles .= " AND (c.para_roles IS NULL OR JSON_CONTAINS(c.para_roles, CAST(:id_rol_actual AS JSON), '$'))";
    $params_com_visibles[':id_rol_actual'] = (string)$rol_usuario_actual_id; // Comentario: JSON_CONTAINS usualmente espera string para el valor a buscar.
} else {
    // Comentario: Si no se puede determinar el rol, solo mostrar los que son para_roles IS NULL (para todos).
    $condiciones_com_visibles .= " AND c.para_roles IS NULL";
}


try {
    $sql_count_com = "SELECT COUNT(*) FROM comunicados c WHERE $condiciones_com_visibles";
    $stmt_count_com = $pdo->prepare($sql_count_com);
    $stmt_count_com->execute($params_com_visibles);
    $total_regs_com = (int)$stmt_count_com->fetchColumn();

    $sql_com_list = "SELECT c.id_comunicado, c.titulo_comunicado, c.contenido_comunicado, c.fecha_publicacion,
                            CONCAT(u.nombres, ' ', u.apellidos) as nombre_creador
                     FROM comunicados c
                     JOIN usuarios u ON c.id_usuario_creador = u.id_usuario
                     WHERE $condiciones_com_visibles
                     ORDER BY c.fecha_publicacion DESC
                     LIMIT :limit OFFSET :offset";

    $stmt_com_list = $pdo->prepare($sql_com_list);
    // Comentario: Unir params_com_visibles con los de paginación.
    $params_sql_com_final = array_merge($params_com_visibles, [':limit' => $regs_por_pagina_com, ':offset' => $offset_com]);
    foreach($params_sql_com_final as $key_pc => &$val_pc) {
        $stmt_com_list->bindParam($key_pc, $val_pc, (is_int($val_pc) ? PDO::PARAM_INT : PDO::PARAM_STR) );
    }
    unset($val_pc);

    $stmt_com_list->execute();
    $comunicados_publicos = $stmt_com_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar lista de comunicados: " . $e->getMessage());
    mensaje_flash('error_com_lista', 'Ocurrió un error al cargar los comunicados. Intente más tarde.', 'alert-danger');
}
$total_paginas_com = ceil($total_regs_com / $regs_por_pagina_com);

// Comentario: Lógica para ver detalle de un comunicado (si se hace en esta misma página con un parámetro GET).
$comunicado_detalle_ver = null;
if (isset($_GET['ver_id_com']) && filter_var($_GET['ver_id_com'], FILTER_VALIDATE_INT)) {
    $id_com_ver = (int)$_GET['ver_id_com'];
    // Comentario: Volver a consultar con las condiciones de visibilidad para seguridad.
    $sql_com_detalle = "SELECT c.id_comunicado, c.titulo_comunicado, c.contenido_comunicado, c.fecha_publicacion,
                               CONCAT(u.nombres, ' ', u.apellidos) as nombre_creador
                        FROM comunicados c
                        JOIN usuarios u ON c.id_usuario_creador = u.id_usuario
                        WHERE c.id_comunicado = :id_com_v AND $condiciones_com_visibles";
    $stmt_com_detalle = $pdo->prepare($sql_com_detalle);
    $params_detalle_final = array_merge([':id_com_v' => $id_com_ver], $params_com_visibles); // Comentario: Reusar params de visibilidad.
    $stmt_com_detalle->execute($params_detalle_final);
    $comunicado_detalle_ver = $stmt_com_detalle->fetch(PDO::FETCH_ASSOC);
    if (!$comunicado_detalle_ver) {
        mensaje_flash('error_com_lista', 'El comunicado solicitado no está disponible o no tiene permiso para verlo.', 'alert-warning');
    }
    // Comentario: Opcional: Marcar como leído (requiere tabla de comunicados_leidos_usuarios).
}


?>
<h2>Comunicados Internos</h2>

<?php mensaje_flash('error_com_lista'); ?>

<?php if ($comunicado_detalle_ver): ?>
    <div class="card-sigi mb-3">
        <h3><?php echo htmlspecialchars($comunicado_detalle_ver['titulo_comunicado'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <p class="meta-comunicado">
            Publicado por: <strong><?php echo htmlspecialchars($comunicado_detalle_ver['nombre_creador'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
            Fecha de Publicación: <?php echo date('d/m/Y H:i', strtotime($comunicado_detalle_ver['fecha_publicacion'])); ?>
        </p>
        <div class="contenido-comunicado-detalle">
            <?php echo nl2br(htmlspecialchars_decode($comunicado_detalle_ver['contenido_comunicado'])); // Comentario: Usar htmlspecialchars_decode si se guardó HTML sanitizado. O un parser Markdown. ?>
            <?php // Si el contenido es HTML puro y ya sanitizado al guardar: echo $comunicado_detalle_ver['contenido_comunicado']; ?>
        </div>
        <a href="index.php?vista=comunicados_lista" class="boton boton-secundario mt-2">Volver al Listado de Comunicados</a>
    </div>
<?php else: ?>
    <?php if (empty($comunicados_publicos)): ?>
        <div class="alert alert-info">No hay comunicados publicados para mostrar en este momento.</div>
    <?php else: ?>
        <div class="lista-comunicados">
            <?php foreach ($comunicados_publicos as $com_pub): ?>
                <div class="comunicado-item card-sigi">
                    <h4>
                        <a href="index.php?vista=comunicados_lista&ver_id_com=<?php echo $com_pub['id_comunicado']; ?>">
                            <?php echo htmlspecialchars($com_pub['titulo_comunicado'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </h4>
                    <p class="meta-comunicado">
                        Publicado por: <strong><?php echo htmlspecialchars($com_pub['nombre_creador'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        Fecha: <?php echo date('d/m/Y H:i', strtotime($com_pub['fecha_publicacion'])); ?>
                    </p>
                    <div class="extracto-comunicado">
                        <?php
                        $extracto = strip_tags(htmlspecialchars_decode($com_pub['contenido_comunicado']));
                        echo htmlspecialchars(mb_substr($extracto, 0, 200), ENT_QUOTES, 'UTF-8') . (mb_strlen($extracto) > 200 ? '...' : '');
                        ?>
                    </div>
                    <a href="index.php?vista=comunicados_lista&ver_id_com=<?php echo $com_pub['id_comunicado']; ?>" class="boton-link">Leer Más &raquo;</a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginación para Comunicados -->
        <?php if ($total_paginas_com > 1):
            $params_url_pag_com = $_GET; unset($params_url_pag_com['pagina_com'], $params_url_pag_com['ver_id_com']);
        ?>
            <nav class="paginacion mt-3">
                <ul class="pagination-lista">
                    <?php if ($pagina_actual_com > 1):
                        $url_anterior_com = "index.php?" . http_build_query($params_url_pag_com) . "&pagina_com=" . ($pagina_actual_com - 1);
                    ?>
                        <li class="page-item"><a class="page-link" href="<?php echo $url_anterior_com; ?>">Anterior</a></li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_paginas_com; $i++):
                         $url_pagina_i_com = "index.php?" . http_build_query($params_url_pag_com) . "&pagina_com=" . $i;
                    ?>
                        <li class="page-item <?php echo ($i == $pagina_actual_com) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $url_pagina_i_com; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($pagina_actual_com < $total_paginas_com):
                        $url_siguiente_com = "index.php?" . http_build_query($params_url_pag_com) . "&pagina_com=" . ($pagina_actual_com + 1);
                    ?>
                        <li class="page-item"><a class="page-link" href="<?php echo $url_siguiente_com; ?>">Siguiente</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>


<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra_caja); margin-bottom: 1.5rem; }
.comunicado-item h4 { margin-top: 0; margin-bottom: 0.5rem; }
.comunicado-item h4 a { text-decoration: none; color: var(--color-primario); }
.comunicado-item h4 a:hover { text-decoration: underline; }
.meta-comunicado { font-size: 0.85em; color: var(--color-secundario); margin-bottom: 0.75rem; }
.extracto-comunicado { margin-bottom: 0.75rem; color: #555; }
.contenido-comunicado-detalle { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; }
.contenido-comunicado-detalle img { max-width: 100%; height: auto; border-radius: var(--borde-radio); margin-top:0.5em; margin-bottom:0.5em;}
.boton-link { display: inline-block; margin-top: 0.5rem; font-weight: bold; }
/* Paginación ya debería estar estilizada. */
</style>

<?php
// Comentario: Fin del archivo vistas/comunicados_lista.php
?>
