<?php
// docente/procesos/docente_cambiar_password.php — El docente cambia su propia contraseña
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../perfil.php#seguridad");
    exit();
}

function volver_pwd(string $estado, string $texto): void {
    header("Location: ../perfil.php?pwd=$estado&texto=" . urlencode($texto) . "#seguridad");
    exit();
}

$actual    = $_POST['password_actual'] ?? '';
$nueva     = $_POST['password_nueva'] ?? '';
$confirmar = $_POST['password_confirmar'] ?? '';

if ($actual === '' || $nueva === '' || $confirmar === '') volver_pwd('error', 'Completa los tres campos.');
if ($nueva !== $confirmar) volver_pwd('error', 'La nueva contraseña y su confirmación no coinciden.');
if (strlen($nueva) < 8 || !preg_match('/[A-Za-z]/', $nueva) || !preg_match('/\d/', $nueva)) {
    volver_pwd('error', 'La nueva contraseña debe tener al menos 8 caracteres, con letras y números.');
}
if ($nueva === $actual) volver_pwd('error', 'La nueva contraseña debe ser diferente a la actual.');

try {
    $st = $pdo->prepare("SELECT contrasena_hash FROM MAESTROS WHERE id_maestro = ?");
    $st->execute([(int)$_SESSION['id_usuario']]);
    $hash = $st->fetchColumn();
    if (!$hash || !password_verify($actual, $hash)) volver_pwd('error', 'La contraseña actual es incorrecta.');

    $st = $pdo->prepare("UPDATE MAESTROS SET contrasena_hash = ? WHERE id_maestro = ?");
    $st->execute([password_hash($nueva, PASSWORD_DEFAULT), (int)$_SESSION['id_usuario']]);
    session_regenerate_id(true);
    volver_pwd('ok', 'Tu contraseña se cambió correctamente. Úsala la próxima vez que inicies sesión.');
} catch (PDOException $e) {
    error_log('Cambiar contraseña docente: ' . $e->getMessage());
    volver_pwd('error', 'No se pudo cambiar la contraseña. Inténtalo de nuevo.');
}
