<?php
// Archivo: vistas/activos_inventario.php
// Propósito: (Activos Fijos) Vista de todo el inventario de activos.
// Comentario en español explicando el propósito de este archivo.

$id_usuario_actual = obtener_id_usuario_actual();
if (!tiene_permiso('VER_INVENTARIO_ACTIVOS', $id_usuario_actual)) {
    mensaje_flash('error_inventario_act', 'No tiene permisos para acceder a esta función.', 'alert-danger');
    redirigir('index.php?vista=dashboard');
}

global $pdo;

// Comentario: Variables para filtros.
$filtro_tipo_activo = $_GET['filtro_tipo_activo'] ?? '';
$filtro_estado_activo = $_GET['filtro_estado_activo'] ?? '';
$filtro_codigo_activo = $_GET['filtro_codigo_activo'] ?? '';
$filtro_nombre_activo = $_GET['filtro_nombre_activo'] ?? '';
$filtro_id_responsable = $_GET['filtro_id_responsable'] ?? '';

// Comentario: Cargar tipos de activo y estados para los filtros (podrían venir de la BD o ser fijos).
$tipos_activo_disponibles = ['Equipo de Computación', 'Mobiliario', 'Vehículo', 'Maquinaria', 'Otro']; // Comentario: Ejemplo.
$estados_activo_disponibles = ['nuevo', 'bueno', 'regular', 'malo', 'en_reparacion', 'dado_de_baja'];

// Comentario: Cargar usuarios para filtro de responsable.
$lista_usuarios_responsables = [];
try {
    $stmt_resp = $pdo->query("SELECT id_usuario, CONCAT(apellidos, ', ', nombres) as nombre_completo FROM usuarios WHERE estado='activo' ORDER BY apellidos, nombres");
    $lista_usuarios_responsables = $stmt_resp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Comentario: Manejar error si es necesario */ }


// Comentario: Construir consulta SQL con filtros.
$sql_inventario = "SELECT af.*, CONCAT(u.apellidos, ', ', u.nombres) as nombre_responsable
                   FROM activos_fijos af
                   LEFT JOIN usuarios u ON af.id_usuario_responsable = u.id_usuario";
$condiciones_inv = [];
$params_inv = [];

if (!empty($filtro_tipo_activo)) {
    $condiciones_inv[] = "af.tipo_activo = :tipo_activo";
    $params_inv[':tipo_activo'] = $filtro_tipo_activo;
}
if (!empty($filtro_estado_activo)) {
    $condiciones_inv[] = "af.estado_activo = :estado_activo";
    $params_inv[':estado_activo'] = $filtro_estado_activo;
}
if (!empty($filtro_codigo_activo)) {
    $condiciones_inv[] = "af.codigo_activo LIKE :codigo_activo";
    $params_inv[':codigo_activo'] = '%' . $filtro_codigo_activo . '%';
}
if (!empty($filtro_nombre_activo)) {
    $condiciones_inv[] = "af.nombre_activo LIKE :nombre_activo";
    $params_inv[':nombre_activo'] = '%' . $filtro_nombre_activo . '%';
}
if (!empty($filtro_id_responsable) && $filtro_id_responsable !== 'todos') {
    if ($filtro_id_responsable === 'sin_asignar') {
        $condiciones_inv[] = "af.id_usuario_responsable IS NULL";
    } elseif (filter_var($filtro_id_responsable, FILTER_VALIDATE_INT)) {
        $condiciones_inv[] = "af.id_usuario_responsable = :id_responsable";
        $params_inv[':id_responsable'] = $filtro_id_responsable;
    }
}

if (!empty($condiciones_inv)) {
    $sql_inventario .= " WHERE " . implode(" AND ", $condiciones_inv);
}
$sql_inventario .= " ORDER BY af.codigo_activo ASC";

// Comentario: Paginación.
$pagina_actual_inv = isset($_GET['pagina_inv']) ? (int)$_GET['pagina_inv'] : 1;
$regs_por_pagina_inv = 15;
$offset_inv = ($pagina_actual_inv - 1) * $regs_por_pagina_inv;
$total_regs_inv = 0;

$inventario_activos = [];
try {
    // Comentario: Contar total para paginación (con los mismos filtros).
    $sql_count_inv = "SELECT COUNT(*) FROM activos_fijos af ";
    if (!empty($condiciones_inv)) {
         // Comentario: Necesario re-evaluar si hay JOIN en la condición de conteo.
         // Comentario: Si el JOIN solo es para mostrar el nombre del responsable, no es necesario para el COUNT(*).
         // Comentario: Pero si se filtra por nombre_responsable, sí. Por ahora, no se filtra por nombre_responsable.
        $sql_count_inv .= " WHERE " . implode(" AND ", $condiciones_inv);
    }
    $stmt_count_inv = $pdo->prepare($sql_count_inv);
    // Comentario: Bindear parámetros sin el '%' para el count si el LIKE no está en la condición de conteo.
    // Comentario: Es más seguro re-crear params_count si difieren.
    $params_count_inv = [];
    if (!empty($filtro_tipo_activo)) $params_count_inv[':tipo_activo'] = $filtro_tipo_activo;
    if (!empty($filtro_estado_activo)) $params_count_inv[':estado_activo'] = $filtro_estado_activo;
    if (!empty($filtro_codigo_activo)) $params_count_inv[':codigo_activo'] = '%' . $filtro_codigo_activo . '%'; // Comentario: LIKE necesita %
    if (!empty($filtro_nombre_activo)) $params_count_inv[':nombre_activo'] = '%' . $filtro_nombre_activo . '%'; // Comentario: LIKE necesita %
    if (!empty($filtro_id_responsable) && $filtro_id_responsable !== 'todos' && $filtro_id_responsable !== 'sin_asignar' && filter_var($filtro_id_responsable, FILTER_VALIDATE_INT)) {
        $params_count_inv[':id_responsable'] = $filtro_id_responsable;
    }
    $stmt_count_inv->execute($params_count_inv);
    $total_regs_inv = (int)$stmt_count_inv->fetchColumn();

    // Comentario: Aplicar paginación a la consulta principal.
    $sql_inventario_paginado = $sql_inventario . " LIMIT :limit OFFSET :offset";
    $stmt_inv = $pdo->prepare($sql_inventario_paginado);
    // Comentario: Unir params_inv con los de paginación.
    $params_sql_final = array_merge($params_inv, [':limit' => $regs_por_pagina_inv, ':offset' => $offset_inv]);
    foreach($params_sql_final as $key => &$val) { // Comentario: Pasar por referencia para bindParam.
        $stmt_inv->bindParam($key, $val, (is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR) );
    }
    unset($val); // Comentario: Romper referencia.

    $stmt_inv->execute();
    $inventario_activos = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al cargar inventario de activos: " . $e->getMessage());
    mensaje_flash('error_inventario_act', 'Ocurrió un error al cargar el inventario. Intente más tarde.', 'alert-danger');
}

$total_paginas_inv = ceil($total_regs_inv / $regs_por_pagina_inv);

?>
<h2>Inventario de Activos Fijos</h2>

<?php mensaje_flash('error_inventario_act'); ?>
<?php mensaje_flash('exito_activo_asignar'); // Comentario: De la página de asignación. ?>

<div class="card-sigi filtros-inventario mb-3">
    <h3>Filtrar Inventario</h3>
    <form action="index.php" method="GET" class="form-filtros-inventario">
        <input type="hidden" name="vista" value="activos_inventario">
        <div class="grid-filtros">
            <div class="grupo-formulario-sm">
                <label for="filtro_codigo_activo">Código Activo:</label>
                <input type="text" id="filtro_codigo_activo" name="filtro_codigo_activo" value="<?php echo htmlspecialchars($filtro_codigo_activo, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="grupo-formulario-sm">
                <label for="filtro_nombre_activo">Nombre Activo:</label>
                <input type="text" id="filtro_nombre_activo" name="filtro_nombre_activo" value="<?php echo htmlspecialchars($filtro_nombre_activo, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="grupo-formulario-sm">
                <label for="filtro_tipo_activo">Tipo de Activo:</label>
                <select id="filtro_tipo_activo" name="filtro_tipo_activo">
                    <option value="">Todos</option>
                    <?php foreach($tipos_activo_disponibles as $tipo_a): ?>
                        <option value="<?php echo htmlspecialchars($tipo_a, ENT_QUOTES, 'UTF-8'); ?>" <?php if($filtro_tipo_activo == $tipo_a) echo 'selected'; ?>><?php echo htmlspecialchars($tipo_a, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grupo-formulario-sm">
                <label for="filtro_estado_activo">Estado del Activo:</label>
                <select id="filtro_estado_activo" name="filtro_estado_activo">
                    <option value="">Todos</option>
                    <?php foreach($estados_activo_disponibles as $estado_a): ?>
                        <option value="<?php echo $estado_a; ?>" <?php if($filtro_estado_activo == $estado_a) echo 'selected'; ?>><?php echo ucfirst(str_replace('_',' ',$estado_a)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="grupo-formulario-sm">
                <label for="filtro_id_responsable">Responsable Asignado:</label>
                <select id="filtro_id_responsable" name="filtro_id_responsable">
                    <option value="todos">Todos</option>
                    <option value="sin_asignar" <?php if($filtro_id_responsable == 'sin_asignar') echo 'selected'; ?>>Sin Asignar</option>
                    <?php foreach($lista_usuarios_responsables as $usr_resp): ?>
                        <option value="<?php echo $usr_resp['id_usuario']; ?>" <?php if($filtro_id_responsable == $usr_resp['id_usuario']) echo 'selected'; ?>><?php echo htmlspecialchars($usr_resp['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="boton boton-primario btn-sm mt-2">Filtrar Inventario</button>
        <a href="index.php?vista=activos_inventario" class="boton boton-secundario btn-sm mt-2">Limpiar Filtros</a>
    </form>
</div>

<?php if (tiene_permiso('ASIGNAR_NUEVO_ACTIVO', $id_usuario_actual)): ?>
<p><a href="index.php?vista=activos_asignar" class="boton boton-exito mb-2">Registrar y Asignar Nuevo Activo</a></p>
<?php endif; ?>


<?php if (empty($inventario_activos) && isset($_GET['filtro_codigo_activo'])): // Comentario: Solo mostrar si se aplicó algún filtro. ?>
    <div class="alert alert-info">No se encontraron activos que coincidan con los filtros aplicados.</div>
<?php elseif (empty($inventario_activos) && !isset($_GET['filtro_codigo_activo'])): ?>
     <div class="alert alert-info">No hay activos registrados en el inventario. <a href="index.php?vista=activos_asignar">Registre el primero</a>.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="tabla-datos tabla-inventario">
            <thead>
                <tr>
                    <th>Código Activo</th>
                    <th>Nombre/Descripción</th>
                    <th>Tipo</th>
                    <th>Fecha Adquisición</th>
                    <th>Valor (Bs.)</th>
                    <th>Estado</th>
                    <th>Responsable</th>
                    <th>Ubicación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventario_activos as $activo): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($activo['codigo_activo'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                        <td title="<?php echo htmlspecialchars($activo['descripcion_detallada'] ?? $activo['nombre_activo'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($activo['nombre_activo'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                        <td><?php echo htmlspecialchars($activo['tipo_activo'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $activo['fecha_adquisicion'] ? date('d/m/Y', strtotime($activo['fecha_adquisicion'])) : 'N/A'; ?></td>
                        <td style="text-align:right;"><?php echo is_numeric($activo['valor_adquisicion']) ? number_format((float)$activo['valor_adquisicion'], 2) : 'N/A'; ?></td>
                        <td><span class="estado-activo estado-af-<?php echo $activo['estado_activo']; ?>"><?php echo ucfirst(str_replace('_',' ',$activo['estado_activo'])); ?></span></td>
                        <td><?php echo htmlspecialchars($activo['nombre_responsable'] ?? '<em>Sin Asignar</em>', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($activo['ubicacion_actual'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <button class="boton-tabla ver-detalle-activo btn-sm"
                                    data-id-activo="<?php echo $activo['id_activo']; ?>"
                                    data-info="<?php echo htmlspecialchars(json_encode($activo), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Ver Detalle Completo">👁️</button>
                            <?php if (tiene_permiso('ASIGNAR_NUEVO_ACTIVO', $id_usuario_actual)): // Comentario: Mismo permiso para editar. ?>
                                <a href="index.php?vista=activos_asignar&accion_activo=editar&id_activo=<?php echo $activo['id_activo']; ?>" class="boton-tabla editar btn-sm" title="Editar Activo">✏️</a>
                            <?php endif; ?>
                            <!-- Comentario: Botón para dar de baja, transferir, etc. -->
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($total_paginas_inv > 1): ?>
        <nav class="paginacion mt-3">
            <ul class="pagination-lista">
                <?php if ($pagina_actual_inv > 1):
                    // Comentario: Mantener filtros en paginación.
                    $params_url_pag = $_GET; unset($params_url_pag['pagina_inv']);
                    $url_anterior = "index.php?" . http_build_query($params_url_pag) . "&pagina_inv=" . ($pagina_actual_inv - 1);
                ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $url_anterior; ?>">Anterior</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_paginas_inv; $i++):
                     $params_url_pag_i = $_GET; unset($params_url_pag_i['pagina_inv']);
                     $url_pagina_i = "index.php?" . http_build_query($params_url_pag_i) . "&pagina_inv=" . $i;
                ?>
                    <li class="page-item <?php echo ($i == $pagina_actual_inv) ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $url_pagina_i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($pagina_actual_inv < $total_paginas_inv):
                    $params_url_pag_s = $_GET; unset($params_url_pag_s['pagina_inv']);
                    $url_siguiente = "index.php?" . http_build_query($params_url_pag_s) . "&pagina_inv=" . ($pagina_actual_inv + 1);
                ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $url_siguiente; ?>">Siguiente</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>


<!-- Modal para ver detalle del activo -->
<div id="modalDetalleActivo" class="modal-sigi oculto">
    <div class="modal-contenido-sigi" style="max-width: 700px;">
        <span class="modal-cerrar-sigi" onclick="document.getElementById('modalDetalleActivo').classList.add('oculto');">&times;</span>
        <h4>Detalle del Activo Fijo (ID: <span id="modalIdActivo"></span>)</h4>
        <table class="tabla-info-detalle" id="tablaModalActivo">
            <!-- Comentario: Contenido se llenará con JS -->
        </table>
    </div>
</div>


<style>
.card-sigi { background-color: #fdfdfd; padding: 1.5rem; border: 1px solid #eee; border-radius: var(--borde-radio); box-shadow: var(--sombra-caja); }
.card-sigi h3 { margin-top: 0; color: var(--color-primario); border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; margin-bottom: 1rem; }
.grid-filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.5rem 1rem; }
.grupo-formulario-sm input, .grupo-formulario-sm select { width: 100%; padding: 0.3rem; font-size:0.9em; }
.estado-activo { padding: 0.2em 0.5em; border-radius: var(--borde-radio); font-size: 0.85em; font-weight: bold; color: var(--color-blanco); display: inline-block; }
.estado-af-nuevo { background-color: var(--color-exito); }
.estado-af-bueno { background-color: #28a745; } /* Comentario: Verde un poco más oscuro */
.estado-af-regular { background-color: var(--color-advertencia); color: #333; }
.estado-af-malo { background-color: #ffc107; color: #333;} /* Comentario: Naranja/Amarillo oscuro */
.estado-af-en_reparacion { background-color: var(--color-info); color: #333; }
.estado-af-dado_de_baja { background-color: var(--color-secundario); }
.boton-tabla.ver-detalle-activo { background-color: var(--color-info); color:white; }
.boton-tabla.editar { background-color: var(--color-advertencia); color:black; }
/* Comentario: Estilos de paginación y modal ya deberían estar en estilos.css o heredados. */
.tabla-info-detalle { width: 100%; } /* Comentario: Asegurar que la tabla del modal ocupe el ancho. */
.tabla-info-detalle th { width: 35%; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalActivo = document.getElementById('modalDetalleActivo');
    const modalIdSpan = document.getElementById('modalIdActivo');
    const tablaModalActivo = document.getElementById('tablaModalActivo');

    document.querySelectorAll('.ver-detalle-activo').forEach(boton => {
        boton.addEventListener('click', function() {
            const activoData = JSON.parse(this.dataset.info);
            if(modalIdSpan) modalIdSpan.textContent = activoData.id_activo;

            if(tablaModalActivo) {
                tablaModalActivo.innerHTML = ''; // Comentario: Limpiar contenido previo.
                const camposMostrables = {
                    'codigo_activo': 'Código del Activo', 'nombre_activo': 'Nombre/Descripción Principal',
                    'descripcion_detallada': 'Descripción Detallada', 'tipo_activo': 'Tipo de Activo',
                    'fecha_adquisicion': 'Fecha de Adquisición', 'valor_adquisicion': 'Valor de Adquisición (Bs.)',
                    'estado_activo': 'Estado Actual', 'nombre_responsable': 'Responsable Asignado',
                    'ubicacion_actual': 'Ubicación Actual', 'fecha_asignacion': 'Fecha de Última Asignación',
                    'observaciones': 'Observaciones Generales',
                    'fecha_registro_sistema': 'Fecha de Registro en Sistema'
                };

                for (const key in camposMostrables) {
                    if (activoData.hasOwnProperty(key) && activoData[key] !== null && activoData[key] !== '') {
                        let valor = activoData[key];
                        if (key === 'fecha_adquisicion' || key === 'fecha_asignacion' || key === 'fecha_registro_sistema') {
                            if (valor && valor !== '0000-00-00' && valor !== '0000-00-00 00:00:00') {
                                try { valor = new Date(valor).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: (key==='fecha_registro_sistema'?'2-digit':undefined), minute:(key==='fecha_registro_sistema'?'2-digit':undefined) }); } catch(e){ /* no cambiar */ }
                            } else { valor = 'N/A'; }
                        }
                        if (key === 'valor_adquisicion' && !isNaN(parseFloat(valor))) {
                            valor = parseFloat(valor).toFixed(2);
                        }
                        if (key === 'estado_activo') {
                            valor = valor.charAt(0).toUpperCase() + valor.slice(1).replace(/_/g, ' ');
                        }

                        const tr = document.createElement('tr');
                        const th = document.createElement('th');
                        th.textContent = camposMostrables[key] + ':';
                        const td = document.createElement('td');
                        td.innerHTML = valor.toString().replace(/\n/g, '<br>'); // Comentario: Para saltos de línea en observaciones.
                        tr.appendChild(th);
                        tr.appendChild(td);
                        tablaModalActivo.appendChild(tr);
                    }
                }
            }
            if(modalActivo) modalActivo.classList.remove('oculto');
        });
    });

    const modalCerrarBtnActivo = modalActivo ? modalActivo.querySelector('.modal-cerrar-sigi') : null;
    if(modalCerrarBtnActivo) {
        modalCerrarBtnActivo.onclick = function() {
            if(modalActivo) modalActivo.classList.add('oculto');
        }
    }
    if(modalActivo) {
        modalActivo.addEventListener('click', function(event) {
            if (event.target === modalActivo) {
                modalActivo.classList.add('oculto');
            }
        });
    }
});
</script>

<?php
// Comentario: Fin del archivo vistas/activos_inventario.php
?>
