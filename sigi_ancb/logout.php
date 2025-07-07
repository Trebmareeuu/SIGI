<?php
// Archivo: logout.php
// Propósito: Script para destruir la sesión activa del usuario y cerrar sesión.
// Redirige al usuario a la página de login después de cerrar la sesión.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Incluir config.php para acceder a constantes de sesión si es necesario,
// Comentario: y funciones.php para la función redirigir() y el manejo inicial de sesión.
// Comentario: Es importante que session_start() se llame antes de poder destruir la sesión.
// Comentario: funciones.php ya maneja el inicio de sesión si no está activa.
require_once 'config.php'; // Comentario: Para BASE_URL y configuraciones de sesión.
require_once 'php_includes/funciones.php'; // Comentario: Para redirigir() y el session_start() implícito.

// Comentario: El session_start() ya está manejado dentro de funciones.php.
// Comentario: Si no estuviera allí, se necesitaría algo como:
// if (session_status() == PHP_SESSION_NONE) {
//     session_name(SESSION_NAME);
//     session_start();
// }

// Comentario: 1. Vaciar todas las variables de sesión.
$_SESSION = array(); // Comentario: Sobrescribe el array $_SESSION con un array vacío.

// Comentario: 2. Si se desea destruir la sesión completamente, también se borra la cookie de sesión.
// Comentario: Nota: ¡Esto destruirá la sesión, no solo los datos de la sesión!
if (ini_get("session.use_cookies")) { // Comentario: Verifica si las sesiones usan cookies.
    $params = session_get_cookie_params(); // Comentario: Obtiene los parámetros de la cookie de sesión.
    // Comentario: Establece una cookie con el mismo nombre pero con una fecha de expiración en el pasado.
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Comentario: 3. Finalmente, destruir la sesión.
session_destroy(); // Comentario: Destruye toda la información registrada de una sesión.

// Comentario: Registrar acción de logout en historial (si se implementa una tabla de auditoría de accesos).
// if ($id_usuario_que_cerro_sesion) { // Se necesitaría obtener el ID antes de destruir la sesión.
//     registrar_auditoria_acceso($id_usuario_que_cerro_sesion, 'logout');
// }


// Comentario: 4. Redirigir al usuario a la página de login.
// Comentario: Usar la función redirigir() que utiliza BASE_URL.
redirigir('index.php?vista=login&mensaje=logout_exitoso');
// Comentario: Se puede añadir un parámetro para mostrar un mensaje en la página de login (opcional).

// Comentario: Fin del script logout.php
exit; // Comentario: Asegura que no se ejecute más código después de la redirección.
?>
