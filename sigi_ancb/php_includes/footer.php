<?php
// Archivo: footer.php
// Propósito: Pie de página común de todas las páginas HTML del sistema SIGI ANCB.
// Incluye el cierre de etiquetas HTML principales, scripts JS y cualquier otro contenido del pie de página.
// Comentario en español explicando el propósito de este archivo.
?>

    </main> <!-- Comentario: Cierre del contenedor principal 'contenedor-principal' abierto en header.php. -->

    <footer class="pie-de-pagina"> <!-- Comentario: Contenedor del pie de página. -->
        <p>
            <!-- Comentario: Texto del pie de página, por ejemplo, derechos de autor y año. -->
            &copy; <?php echo date("Y"); // Comentario: Muestra el año actual dinámicamente. ?>
            <?php echo defined('NOMBRE_INSTITUCION') ? htmlspecialchars(NOMBRE_INSTITUCION, ENT_QUOTES, 'UTF-8') : 'SIGI ANCB'; // Comentario: Muestra el nombre de la institución. ?>.
            Todos los derechos reservados.
        </p>
        <p>
            <!-- Comentario: Información adicional o enlaces, si son necesarios. -->
            Sistema Integrado de Gestión Institucional (SIGI) - Versión 1.0
        </p>
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE === true): ?>
            <!-- Comentario: Mostrar información de depuración si DEBUG_MODE está activo. -->
            <!-- Comentario: Esto podría incluir tiempo de carga de página, consultas SQL, etc. Por ahora, un simple mensaje. -->
            <p style="color: #ffc107; font-size: 0.8em;">Modo Depuración Activo.</p>
        <?php endif; ?>
    </footer> <!-- Comentario: Fin del contenedor del pie de página. -->

    <!-- Comentario: Enlace al archivo JavaScript principal. Se coloca al final para mejorar la carga de la página. -->
    <script src="<?php echo BASE_URL; ?>js/main.js"></script>

    <!-- Comentario: Aquí se podrían añadir más enlaces a archivos JS de librerías locales si fueran necesarios. -->
    <!-- Comentario: Ejemplo si se usara Bootstrap JS localmente. -->
    <!-- <script src="<?php echo BASE_URL; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script> -->

    <!-- Comentario: Cualquier script en línea específico de una página debería ir en la propia vista, -->
    <!-- Comentario: o bien, ser manejado por main.js basándose en identificadores en el HTML. -->

</body> <!-- Comentario: Cierre de la etiqueta <body> abierta en header.php. -->
</html> <!-- Comentario: Cierre de la etiqueta <html> abierta en header.php. -->
<?php
// Comentario: Fin del archivo footer.php
?>
