<?php
// Archivo: config.php
// Propósito: Configuración de la conexión a la base de datos y variables globales del sistema SIGI ANCB.
// Comentario en español explicando el propósito de este archivo.

// --- Configuración de la Base de Datos ---
// Comentario: Define las constantes para la conexión a la base de datos MySQL.
// Comentario: Asegúrate de cambiar estos valores por los correctos para tu entorno.
define('DB_HOST', 'localhost'); // Comentario: Host de la base de datos, usualmente 'localhost'.
define('DB_USER', 'tu_usuario_db'); // Comentario: Usuario de la base de datos. Reemplazar con tu usuario.
define('DB_PASS', 'tu_contrasena_db'); // Comentario: Contraseña del usuario de la base de datos. Reemplazar.
define('DB_NAME', 'sigi_ancb_db'); // Comentario: Nombre de la base de datos creada con schema.sql.
define('DB_CHARSET', 'utf8mb4'); // Comentario: Juego de caracteres para la conexión.

// --- Configuración de la Aplicación ---
// Comentario: Define constantes globales para la aplicación.

// Comentario: URL base de la aplicación. Ajustar según sea necesario.
// Comentario: Si está en el directorio raíz de un dominio, sería 'http://tusitio.com/'.
// Comentario: Si está en una subcarpeta, ej. 'http://localhost/sigi_ancb/'.
// Comentario: Es importante que termine con una barra inclinada '/'.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://"; // Comentario: Determina si es HTTP o HTTPS.
$host = $_SERVER['HTTP_HOST']; // Comentario: Obtiene el host del servidor.
$script_name = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']); // Comentario: Obtiene la ruta base del script.
define('BASE_URL', $protocol . $host . $script_name); // Comentario: URL base completa de la aplicación.

// Comentario: Nombre de la institución, puede ser útil para títulos o reportes.
define('NOMBRE_INSTITUCION', 'Academia Nacional de Ciencias de Bolivia'); // Comentario: Nombre completo de la institución.

// Comentario: Ruta al directorio de documentos subidos. Debe tener permisos de escritura.
define('DIR_DOCUMENTOS', __DIR__ . '/docs/'); // Comentario: Ruta absoluta al directorio /docs.

// Comentario: Configuración de la zona horaria para funciones de fecha y hora en PHP.
// Comentario: Usar una zona horaria válida de PHP. Ejemplo: 'America/La_Paz'.
date_default_timezone_set('America/La_Paz'); // Comentario: Establece la zona horaria por defecto.

// Comentario: Modo de depuración. Poner en false en producción.
// Comentario: Si está en true, podría mostrar errores detallados de PHP.
define('DEBUG_MODE', true); // Comentario: Activa o desactiva el modo de depuración.

// Comentario: Configuración de la sesión.
define('SESSION_NAME', 'SIGI_ANCB_SESSION'); // Comentario: Nombre para la cookie de sesión.
define('SESSION_LIFETIME', 0); // Comentario: Duración de la cookie de sesión (0 = hasta cerrar navegador).
define('SESSION_SECURE', false); // Comentario: true si se usa HTTPS, para enviar cookie solo sobre HTTPS. (Cambiar a true en producción con HTTPS).
define('SESSION_HTTPONLY', true); // Comentario: true para que la cookie no sea accesible por JavaScript.

// --- Conexión a la Base de Datos (PDO) ---
// Comentario: Intenta establecer la conexión con la base de datos usando PDO.
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET; // Comentario: Data Source Name para PDO.
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Comentario: Lanza excepciones en errores de PDO.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Comentario: Modo de fetch por defecto (array asociativo).
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Comentario: Desactiva la emulación de sentencias preparadas para mayor seguridad.
]; // Comentario: Opciones para la conexión PDO.

try {
    // Comentario: Crea una nueva instancia de PDO para la conexión.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Comentario: La variable $pdo estará disponible globalmente para ser usada en otros scripts que incluyan config.php.
} catch (PDOException $e) {
    // Comentario: Manejo de error si la conexión falla.
    // Comentario: En un entorno de producción, se debería registrar este error y mostrar un mensaje amigable.
    error_log("Error de conexión a la BD: " . $e->getMessage()); // Comentario: Registra el error en el log del servidor.
    // Comentario: Muestra un mensaje genérico al usuario. No mostrar $e->getMessage() en producción.
    die("Error de conexión con la base de datos. Por favor, contacte al administrador del sistema. <!-- " . $e->getMessage() . " -->");
}

// Comentario: Si se necesita usar MySQLi en lugar de PDO, la conexión sería algo así:
/*
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); // Comentario: Crea una nueva instancia de MySQLi.
if ($mysqli->connect_error) { // Comentario: Verifica si hay error de conexión.
    error_log("Error de conexión a la BD (MySQLi): " . $mysqli->connect_error); // Comentario: Registra el error.
    die("Error de conexión con la base de datos (MySQLi). Por favor, contacte al administrador del sistema. <!-- " . $mysqli->connect_error . " -->"); // Comentario: Mensaje de error.
}
$mysqli->set_charset(DB_CHARSET); // Comentario: Establece el juego de caracteres para MySQLi.
// Comentario: La variable $mysqli estaría disponible globalmente.
*/

// Comentario: Fin del archivo config.php
?>
