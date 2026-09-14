<?php
// logout.php
session_start();
// Destruir todas las variables de sesión
session_unset();
session_destroy();
// Redirigir de vuelta a la página principal de elección
header("Location: index.html");
exit();
?>