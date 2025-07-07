<?php
// Archivo: vistas/login.php
// Propósito: Muestra el formulario de inicio de sesión para los usuarios.
// Comentario en español explicando el propósito de este archivo.

// Comentario: No se debe incluir header.php ni footer.php aquí directamente,
// Comentario: ya que la página de login usualmente tiene una estructura diferente (más simple).
// Comentario: index.php decidirá si muestra esta vista "standalone" o dentro de una estructura.
// Comentario: Para este proyecto, se asume que el login es una página separada sin el nav principal.

// Comentario: Verificar si ya hay una sesión activa; si es así, redirigir al dashboard.
// Comentario: Esta lógica podría estar también en index.php antes de cargar la vista de login.
if (verificar_sesion()) {
    redirigir('index.php?vista=dashboard'); // Comentario: Redirige si ya está logueado.
}

// Comentario: Se asume que config.php y funciones.php ya han sido incluidos por index.php
// Comentario: o, si se accede directamente (no recomendado), se necesitarían aquí.
// require_once '../config.php';
// require_once '../php_includes/funciones.php';

// Comentario: Procesamiento del formulario de login (esto se manejará en index.php,
// Comentario: pero aquí se pueden mostrar errores devueltos por ese procesamiento).
$error_login = ''; // Comentario: Variable para almacenar mensajes de error de login.
if (isset($_SESSION['error_login'])) {
    $error_login = $_SESSION['error_login']; // Comentario: Obtiene el error de la sesión.
    unset($_SESSION['error_login']); // Comentario: Limpia el error de la sesión después de mostrarlo.
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo defined('NOMBRE_INSTITUCION') ? htmlspecialchars(NOMBRE_INSTITUCION, ENT_QUOTES, 'UTF-8') : 'SIGI'; ?></title>
    <!-- Comentario: Enlace a la hoja de estilos principal. -->
    <link rel="stylesheet" href="<?php echo defined('BASE_URL') ? BASE_URL : '../'; ?>css/estilos.css">
    <!-- Comentario: Estilos específicos para la página de login podrían ir aquí o en estilos.css -->
    <style>
        /* Comentario: Estilos mínimos para centrar el formulario de login si no se usa el .contenedor-login de estilos.css */
        body.pagina-login {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #e9ecef; /* Comentario: Un fondo ligeramente diferente para la página de login. */
        }
        .login-logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-logo-container img {
            max-width: 150px; /* Comentario: Ajustar según el tamaño del logo. */
            margin-bottom: 0.5rem;
        }
        .login-logo-container h1 {
            font-size: 1.5rem;
            color: var(--color-primario);
            margin:0;
        }
    </style>
</head>
<body class="pagina-login"> <!-- Comentario: Clase específica para el body de la página de login. -->

    <div class="contenedor-login"> <!-- Comentario: Contenedor para el formulario de login. -->

        <div class="login-logo-container">
            <img src="<?php echo defined('BASE_URL') ? BASE_URL : '../'; ?>img/logo.png" alt="Logo <?php echo defined('NOMBRE_INSTITUCION') ? htmlspecialchars(NOMBRE_INSTITUCION, ENT_QUOTES, 'UTF-8') : 'SIGI'; ?>">
            <h1><?php echo defined('NOMBRE_INSTITUCION') ? htmlspecialchars(NOMBRE_INSTITUCION, ENT_QUOTES, 'UTF-8') : 'SIGI'; ?></h1>
            <p>Sistema Integrado de Gestión Institucional</p>
        </div>

        <h2>Iniciar Sesión</h2>

        <?php if (!empty($error_login)): ?>
            <!-- Comentario: Muestra el mensaje de error de login si existe. -->
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_login, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- Comentario: El formulario enviará los datos a index.php (o a la URL base que maneje el login). -->
        <!-- Comentario: Se usa BASE_URL para asegurar que la acción del formulario sea correcta. -->
        <form action="<?php echo defined('BASE_URL') ? BASE_URL : './'; ?>index.php?accion=login" method="POST" class="validar-js">
            <!-- Comentario: El parámetro 'accion=login' puede ser usado por index.php para identificar la solicitud. -->

            <div class="grupo-formulario">
                <label for="nombre_usuario">Nombre de Usuario:</label>
                <input type="text" id="nombre_usuario" name="nombre_usuario" required autofocus
                       value="<?php echo isset($_SESSION['login_intent_usuario']) ? htmlspecialchars($_SESSION['login_intent_usuario'], ENT_QUOTES, 'UTF-8') : ''; unset($_SESSION['login_intent_usuario']); ?>">
                       <!-- Comentario: 'autofocus' pone el cursor en este campo al cargar. -->
                       <!-- Comentario: Se recupera el último intento de usuario para conveniencia. -->
            </div>

            <div class="grupo-formulario">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required>
            </div>

            <div class="grupo-formulario">
                <!-- Comentario: Botón de envío del formulario. -->
                <button type="submit" class="boton" style="width: 100%;">Ingresar</button>
            </div>

            <!-- Comentario: Opcional: Enlace para recuperar contraseña (si se implementa). -->
            <!--
            <div class="texto-centrado mt-2">
                <a href="index.php?vista=recuperar_contrasena">¿Olvidó su contraseña?</a>
            </div>
            -->
        </form>
        <div class="texto-centrado mt-3" style="font-size:0.8em;">
            <p>&copy; <?php echo date("Y"); ?> <?php echo defined('NOMBRE_INSTITUCION') ? htmlspecialchars(NOMBRE_INSTITUCION, ENT_QUOTES, 'UTF-8') : ''; ?>.</p>
        </div>
    </div>

    <!-- Comentario: Scripts JS al final del body para mejor rendimiento. -->
    <!-- Comentario: Para la página de login, solo se necesita main.js si las validaciones JS son complejas -->
    <!-- Comentario: o si hay otra interactividad. Las validaciones HTML5 'required' funcionarán sin JS. -->
    <script src="<?php echo defined('BASE_URL') ? BASE_URL : '../'; ?>js/main.js"></script>
</body>
</html>
<?php
// Comentario: Fin del archivo vistas/login.php
?>
