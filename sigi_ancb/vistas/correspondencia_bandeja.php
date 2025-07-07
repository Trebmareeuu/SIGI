<?php
// Archivo: vistas/correspondencia_bandeja.php
// Propósito: Muestra las bandejas de entrada, enviados y archivados de correspondencia del usuario.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Asumimos que config.php, funciones.php, header.php ya han sido incluidos por index.php
// Comentario: y que la sesión del usuario está activa y verificada.

$id_usuario_actual = obtener_id_usuario_actual(); // Comentario: Obtiene el ID del usuario actual.
if (!$id_usuario_actual) {
    mensaje_flash('error_bandeja', 'Acceso no autorizado.', 'alert-danger');
    redirigir('index.php?vista=login'); // Comentario: Redirige si no hay usuario.
}

global $pdo; // Comentario: Acceder a la conexión PDO.

// Comentario: Determinar la bandeja activa (entrada, enviados, archivados).
$bandeja_activa = $_GET['sub_vista'] ?? 'entrada'; // Comentario: Por defecto, la bandeja de entrada.
$titulo_bandeja = ''; // Comentario: Título para la bandeja.
$documentos = []; // Comentario: Array para almacenar los documentos.

// Comentario: Paginación (ejemplo básico).
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1; // Comentario: Página actual.
$documentos_por_pagina = 15; // Comentario: Número de documentos por página.
$offset = ($pagina_actual - 1) * $documentos_por_pagina; // Comentario: Cálculo del offset.
$total_documentos = 0; // Comentario: Total de documentos para la paginación.


try {
    $sql_base_count = ""; // Comentario: Para contar el total de documentos.
    $sql_base_select = ""; // Comentario: Para seleccionar los documentos.
    $params = ['id_usuario_actual' => $id_usuario_actual]; // Comentario: Parámetros para la consulta.

    switch ($bandeja_activa) {
        case 'enviados':
            $titulo_bandeja = "Documentos Enviados/Creados";
            // Comentario: Documentos donde el usuario actual es el creador.
            $sql_base_select = "SELECT d.id_documento, d.cite, d.referencia, d.fecha_documento, d.estado_documento, d.tipo_documento,
                                    GROUP_CONCAT(DISTINCT u_asig.nombres, ' ', u_asig.apellidos SEPARATOR ', ') as asignado_a,
                                    ee_dest.nombre_entidad as entidad_destino
                                FROM documentos d
                                LEFT JOIN usuarios u_asig ON d.id_usuario_asignado = u_asig.id_usuario
                                LEFT JOIN entidades_externas ee_dest ON d.id_entidad_externa_destino = ee_dest.id_entidad
                                WHERE d.id_usuario_creador = :id_usuario_actual";
            $sql_base_count = "SELECT COUNT(*) FROM documentos d WHERE d.id_usuario_creador = :id_usuario_actual";
            $orderBy = " ORDER BY d.fecha_recepcion_registro DESC, d.id_documento DESC";
            break;

        case 'archivados':
            $titulo_bandeja = "Documentos Archivados";
            // Comentario: Documentos que están en estado 'archivado' y fueron creados o asignados al usuario.
            // Comentario: O, si hay una tabla de archivo_expedientes vinculada a documentos, se consultaría esa.
            // Comentario: Por ahora, se asume que 'archivado' es un estado en la tabla 'documentos'.
             $sql_base_select = "SELECT d.id_documento, d.cite, d.referencia, d.fecha_documento, d.estado_documento, d.tipo_documento,
                                    uc.nombres as creador_nombres, uc.apellidos as creador_apellidos,
                                    GROUP_CONCAT(DISTINCT u_asig.nombres, ' ', u_asig.apellidos SEPARATOR ', ') as asignado_a
                                FROM documentos d
                                JOIN usuarios uc ON d.id_usuario_creador = uc.id_usuario
                                LEFT JOIN usuarios u_asig ON d.id_usuario_asignado = u_asig.id_usuario
                                WHERE d.estado_documento = 'archivado'
                                AND (d.id_usuario_creador = :id_usuario_actual OR d.id_usuario_asignado = :id_usuario_actual
                                     OR EXISTS (SELECT 1 FROM documentos_historial dh WHERE dh.id_documento = d.id_documento AND dh.id_usuario_destino = :id_usuario_actual))";
            // Comentario: La subconsulta EXISTS verifica si el documento alguna vez fue derivado al usuario.
            $sql_base_count = "SELECT COUNT(*) FROM documentos d WHERE d.estado_documento = 'archivado' AND (d.id_usuario_creador = :id_usuario_actual OR d.id_usuario_asignado = :id_usuario_actual OR EXISTS (SELECT 1 FROM documentos_historial dh WHERE dh.id_documento = d.id_documento AND dh.id_usuario_destino = :id_usuario_actual))";
            $orderBy = " ORDER BY d.fecha_documento DESC, d.id_documento DESC";
            break;

        case 'entrada':
        default:
            $bandeja_activa = 'entrada'; // Comentario: Asegurar que sea 'entrada' por defecto.
            $titulo_bandeja = "Bandeja de Entrada (Documentos Asignados/Recibidos)";
            // Comentario: Documentos donde el usuario actual es el asignado y no están finalizados/archivados.
            // Comentario: O documentos que han sido derivados al usuario en el historial (última derivación).
            $sql_base_select = "SELECT d.id_documento, d.cite, d.referencia, d.fecha_documento, d.estado_documento, d.tipo_documento,
                                    uc.nombres as creador_nombres, uc.apellidos as creador_apellidos,
                                    ee_orig.nombre_entidad as entidad_origen,
                                    (SELECT dh.fecha_accion FROM documentos_historial dh WHERE dh.id_documento = d.id_documento AND dh.id_usuario_destino = :id_usuario_actual ORDER BY dh.fecha_accion DESC LIMIT 1) as fecha_derivacion_a_mi
                                FROM documentos d
                                JOIN usuarios uc ON d.id_usuario_creador = uc.id_usuario
                                LEFT JOIN entidades_externas ee_orig ON d.id_entidad_externa_origen = ee_orig.id_entidad
                                WHERE d.id_usuario_asignado = :id_usuario_actual
                                AND d.estado_documento NOT IN ('archivado', 'anulado', 'finalizado') "; // Comentario: Excluir ciertos estados.
            // Comentario: Se podría añadir una lógica más compleja para "no leídos" o "pendientes de acción".
            $sql_base_count = "SELECT COUNT(*) FROM documentos d WHERE d.id_usuario_asignado = :id_usuario_actual AND d.estado_documento NOT IN ('archivado', 'anulado', 'finalizado')";
            $orderBy = " ORDER BY fecha_derivacion_a_mi DESC, d.fecha_recepcion_registro DESC, d.id_documento DESC";
            break;
    }

    // Comentario: Ejecutar consulta para contar total de documentos.
    $stmt_count = $pdo->prepare($sql_base_count);
    $stmt_count->execute($params);
    $total_documentos = (int)$stmt_count->fetchColumn();

    // Comentario: Ejecutar consulta para obtener los documentos de la página actual.
    $sql_select_paginado = $sql_base_select . $orderBy . " LIMIT :limit OFFSET :offset";
    $stmt_docs = $pdo->prepare($sql_select_paginado);
    // Comentario: Vincular parámetros, incluyendo los de paginación.
    foreach ($params as $key => $value) {
        $stmt_docs->bindValue(':' . $key, $value); // Comentario: Usar bindValue para poder reutilizar params.
    }
    $stmt_docs->bindParam(':limit', $documentos_por_pagina, PDO::PARAM_INT);
    $stmt_docs->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_docs->execute();
    $documentos = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar documentos para bandeja '$bandeja_activa', usuario ID $id_usuario_actual: " . $e->getMessage());
    mensaje_flash('error_bandeja', 'Ocurrió un error al cargar los documentos. Intente más tarde.', 'alert-danger');
    // Comentario: $documentos permanecerá vacío, la vista mostrará que no hay documentos.
}

$total_paginas = ceil($total_documentos / $documentos_por_pagina); // Comentario: Calcula el total de páginas.

?>

<h2>Bandeja de Correspondencia: <?php echo htmlspecialchars($titulo_bandeja, ENT_QUOTES, 'UTF-8'); ?></h2>

<?php mensaje_flash('error_bandeja'); ?>
<?php mensaje_flash('exito_accion_documento'); // Comentario: Para mensajes de éxito de otras acciones. ?>

<div class="bandeja-navegacion mb-3">
    <!-- Comentario: Enlaces para cambiar entre bandejas. -->
    <a href="index.php?vista=correspondencia_bandeja&sub_vista=entrada"
       class="boton <?php echo ($bandeja_activa === 'entrada') ? 'boton-primario' : 'boton-secundario'; ?>">
       Entrada
    </a>
    <a href="index.php?vista=correspondencia_bandeja&sub_vista=enviados"
       class="boton <?php echo ($bandeja_activa === 'enviados') ? 'boton-primario' : 'boton-secundario'; ?>">
       Enviados
    </a>
    <a href="index.php?vista=correspondencia_bandeja&sub_vista=archivados"
       class="boton <?php echo ($bandeja_activa === 'archivados') ? 'boton-primario' : 'boton-secundario'; ?>">
       Archivados
    </a>
    <?php if (tiene_permiso('REDACTAR_CORRESPONDENCIA_INTERNA')): ?>
        <a href="<?php echo BASE_URL; ?>index.php?vista=correspondencia_redactar" class="boton boton-exito" style="float: right;">Redactar Nuevo</a>
    <?php endif; ?>
</div>

<?php if (empty($documentos)): ?>
    <div class="alert alert-info">No hay documentos en esta bandeja.</div>
<?php else: ?>
    <div class="table-responsive"> <!-- Comentario: Para tablas anchas en móviles. -->
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th>CITE</th>
                    <th>Referencia</th>
                    <th>Fecha Doc.</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <?php if ($bandeja_activa === 'entrada'): ?>
                        <th>Origen / Creador</th>
                        <th>Fecha Derivación/Recep.</th>
                    <?php elseif ($bandeja_activa === 'enviados'): ?>
                        <th>Destino / Asignado A</th>
                    <?php elseif ($bandeja_activa === 'archivados'): ?>
                        <th>Creador</th>
                        <th>Asignado Originalmente</th>
                    <?php endif; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documentos as $doc): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($doc['cite'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($doc['referencia'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars( ($doc['fecha_documento'] ? date('d/m/Y', strtotime($doc['fecha_documento'])) : ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $doc['tipo_documento'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="estado-doc estado-<?php echo htmlspecialchars($doc['estado_documento'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $doc['estado_documento'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></td>

                        <?php if ($bandeja_activa === 'entrada'): ?>
                            <td>
                                <?php
                                echo htmlspecialchars($doc['entidad_origen'] ?? ($doc['creador_nombres'].' '.$doc['creador_apellidos']), ENT_QUOTES, 'UTF-8');
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(($doc['fecha_derivacion_a_mi'] ? date('d/m/Y H:i', strtotime($doc['fecha_derivacion_a_mi'])) : 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php elseif ($bandeja_activa === 'enviados'): ?>
                            <td>
                                <?php
                                echo htmlspecialchars($doc['entidad_destino'] ?? ($doc['asignado_a'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                ?>
                            </td>
                        <?php elseif ($bandeja_activa === 'archivados'): ?>
                             <td><?php echo htmlspecialchars(($doc['creador_nombres'] ?? '').' '.($doc['creador_apellidos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                             <td><?php echo htmlspecialchars($doc['asignado_a'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endif; ?>

                        <td>
                            <a href="index.php?vista=documento_detalle&id=<?php echo $doc['id_documento']; ?>" class="boton-tabla ver" title="Ver Detalle">👁️</a>
                            <!-- Comentario: Acciones adicionales según estado y permisos -->
                            <?php if ($bandeja_activa === 'entrada' && $doc['estado_documento'] !== 'finalizado' && $doc['estado_documento'] !== 'archivado'): ?>
                                <?php if (tiene_permiso('DERIVAR_DOCUMENTO')): // Permiso hipotético ?>
                                    <!-- <a href="index.php?vista=documento_derivar&id=<?php echo $doc['id_documento']; ?>" class="boton-tabla derivar" title="Derivar">↪️</a> -->
                                <?php endif; ?>
                                <?php if (tiene_permiso('FINALIZAR_DOCUMENTO')): // Permiso hipotético ?>
                                   <!-- <a href="index.php?accion=finalizar_documento&id=<?php echo $doc['id_documento']; ?>" class="boton-tabla finalizar confirmar-accion" data-mensaje-confirmacion="¿Está seguro de finalizar este documento?" title="Finalizar">✔️</a> -->
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Comentario: Paginación -->
    <?php if ($total_paginas > 1): ?>
        <nav class="paginacion mt-3">
            <ul class="pagination-lista">
                <?php if ($pagina_actual > 1): ?>
                    <li class="page-item"><a class="page-link" href="index.php?vista=correspondencia_bandeja&sub_vista=<?php echo $bandeja_activa; ?>&pagina=<?php echo $pagina_actual - 1; ?>">Anterior</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="index.php?vista=correspondencia_bandeja&sub_vista=<?php echo $bandeja_activa; ?>&pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <li class="page-item"><a class="page-link" href="index.php?vista=correspondencia_bandeja&sub_vista=<?php echo $bandeja_activa; ?>&pagina=<?php echo $pagina_actual + 1; ?>">Siguiente</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<style>
/* Comentario: Estilos específicos para la bandeja de correspondencia y estados de documento. */
.bandeja-navegacion .boton {
    margin-right: 0.5rem;
}
.estado-doc {
    padding: 0.2em 0.5em;
    border-radius: var(--borde-radio);
    font-size: 0.85em;
    font-weight: bold;
    color: var(--color-blanco);
}
.estado-en_redaccion { background-color: #6c757d; /* Gris */ }
.estado-pendiente_revision { background-color: #ffc107; color: #333; /* Amarillo */ }
.estado-derivado { background-color: #0dcaf0; color: #333; /* Celeste info */ }
.estado-en_proceso { background-color: #007bff; /* Azul primario */ }
.estado-finalizado { background-color: #198754; /* Verde éxito */ }
.estado-archivado { background-color: #495057; /* Gris oscuro */ }
.estado-anulado { background-color: #dc3545; /* Rojo error */ }

.boton-tabla {
    display: inline-block;
    padding: 0.3rem 0.6rem;
    margin-right: 0.3rem;
    border-radius: var(--borde-radio);
    text-decoration: none;
    font-size: 0.9em;
    border: 1px solid transparent;
}
.boton-tabla.ver { background-color: var(--color-info); color: white; }
.boton-tabla.ver:hover { background-color: #0a9cb9; }
/* Otros estilos para botones de acción en tabla */

/* Paginación */
.paginacion { text-align: center; }
.pagination-lista { list-style: none; padding: 0; display: inline-block; }
.pagination-lista .page-item { display: inline; margin: 0 2px; }
.pagination-lista .page-link {
    padding: 0.5rem 0.75rem;
    border: 1px solid #dee2e6;
    color: var(--color-primario);
    text-decoration: none;
    border-radius: var(--borde-radio);
}
.pagination-lista .page-item.active .page-link {
    background-color: var(--color-primario);
    color: var(--color-blanco);
    border-color: var(--color-primario);
}
.pagination-lista .page-link:hover {
    background-color: #e9ecef;
}
.table-responsive {
    overflow-x: auto; /* Comentario: Permite scroll horizontal en tablas anchas. */
}
</style>

<?php
// Comentario: Fin del archivo vistas/correspondencia_bandeja.php
?>
