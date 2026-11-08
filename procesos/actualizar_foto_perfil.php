<?php
// procesos/actualizar_foto_perfil.php — El estudiante cambia su fotografía (credencial virtual)
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['foto_perfil'])) {
    volver_perfil('error', 'No se recibió ninguna imagen.');
}

$id_alumno = (int)$_SESSION['id_usuario'];
$foto = $_FILES['foto_perfil'];

if ($foto['error'] !== UPLOAD_ERR_OK) {
    volver_perfil('error', 'No se pudo subir la imagen. Intenta de nuevo.');
}
if ($foto['size'] > 3 * 1024 * 1024) {
    volver_perfil('error', 'La imagen no debe pesar más de 3 MB.');
}

// Validar que realmente sea una imagen (no solo por la extensión)
$tipos_permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$info = @getimagesize($foto['tmp_name']);
if (!$info || !isset($tipos_permitidos[$info['mime']])) {
    volver_perfil('error', 'Formato no válido. Usa una imagen JPG, PNG o WEBP.');
}

$carpeta = __DIR__ . '/../uploads/fotos_alumnos/';
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}
$nombre = 'alumno_' . $id_alumno . '_' . time() . '.' . $tipos_permitidos[$info['mime']];

if (!move_uploaded_file($foto['tmp_name'], $carpeta . $nombre)) {
    volver_perfil('error', 'No se pudo guardar la imagen en el servidor.');
}

try {
    // Borrar la foto anterior para no acumular archivos
    $st = $pdo->prepare("SELECT fotografia FROM ALUMNOS WHERE id_alumno = ?");
    $st->execute([$id_alumno]);
    $anterior = $st->fetchColumn();
    if ($anterior && strpos($anterior, 'uploads/fotos_alumnos/') === 0 && is_file(__DIR__ . '/../' . $anterior)) {
        unlink(__DIR__ . '/../' . $anterior);
    }

    $st = $pdo->prepare("UPDATE ALUMNOS SET fotografia = ? WHERE id_alumno = ?");
    $st->execute(['uploads/fotos_alumnos/' . $nombre, $id_alumno]);
    volver_perfil('exito', 'Tu fotografía se actualizó correctamente.');
} catch (PDOException $e) {
    error_log('Foto alumno: ' . $e->getMessage());
    volver_perfil('error', 'Error al guardar la fotografía.');
}
