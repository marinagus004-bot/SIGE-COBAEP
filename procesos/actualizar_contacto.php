<?php
// procesos/actualizar_contacto.php — El estudiante actualiza su correo personal y teléfono
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}

function volver_perfil(string $tipo, string $mensaje) {
    header("Location: ../estudiante/perfil.php?$tipo=" . urlencode($mensaje));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volver_perfil('error', 'Solicitud no válida.');
}

$correo = trim($_POST['correo_personal'] ?? '');
$telefono = preg_replace('/\D/', '', $_POST['telefono'] ?? '');

if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    volver_perfil('error', 'El correo electrónico no es válido.');
}
if ($telefono !== '' && strlen($telefono) !== 10) {
    volver_perfil('error', 'El teléfono debe tener 10 dígitos.');
}

try {
    $st = $pdo->prepare("UPDATE ALUMNOS SET correo_personal = ?, telefono = ? WHERE id_alumno = ?");
    $st->execute([$correo !== '' ? $correo : null, $telefono !== '' ? $telefono : null, (int)$_SESSION['id_usuario']]);
    volver_perfil('exito', 'Tus datos de contacto se actualizaron correctamente.');
} catch (PDOException $e) {
    error_log('Contacto alumno: ' . $e->getMessage());
    volver_perfil('error', 'Error al guardar tus datos de contacto.');
}
