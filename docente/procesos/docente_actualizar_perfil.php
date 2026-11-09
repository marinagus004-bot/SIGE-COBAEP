<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../perfil.php");
    exit();
}

$id_maestro = (int)$_SESSION['id_usuario'];
$telefono = trim($_POST['telefono'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$titulo_academico = trim($_POST['titulo_academico'] ?? '');
$biografia = trim($_POST['biografia'] ?? '');

function volver_perfil(string $msg, string $texto = ''): void {
    header("Location: ../perfil.php?msg=$msg" . ($texto ? '&texto=' . urlencode($texto) : ''));
    exit();
}

if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) volver_perfil('error', 'El correo no es válido.');
if ($telefono !== '' && !preg_match('/^\d{10}$/', preg_replace('/\D/', '', $telefono))) volver_perfil('error', 'El teléfono debe tener 10 dígitos.');

try {
    $nueva_foto = null;
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['foto_perfil'];
        if ($f['error'] !== UPLOAD_ERR_OK) volver_perfil('error', 'No se pudo subir la imagen.');
        if ($f['size'] > 3 * 1024 * 1024) volver_perfil('error', 'La imagen no debe pesar más de 3 MB.');
        $info = @getimagesize($f['tmp_name']);
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$info || !isset($tipos[$info['mime']])) volver_perfil('error', 'Solo se permiten imágenes JPG, PNG o WEBP.');

        $dir = __DIR__ . '/../../uploads/perfiles/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $nombre = 'perfil_' . $id_maestro . '_' . time() . '.' . $tipos[$info['mime']];
        if (!move_uploaded_file($f['tmp_name'], $dir . $nombre)) volver_perfil('error', 'No se pudo guardar la imagen.');
        $nueva_foto = 'uploads/perfiles/' . $nombre;

        // Borrar la foto anterior para no acumular archivos
        $st = $pdo->prepare("SELECT foto_perfil FROM MAESTROS WHERE id_maestro = ?");
        $st->execute([$id_maestro]);
        $anterior = preg_replace('#^(\.\./)+#', '', (string)$st->fetchColumn());
        if (strpos($anterior, 'uploads/perfiles/') === 0 && is_file(__DIR__ . '/../../' . $anterior)) unlink(__DIR__ . '/../../' . $anterior);
    }

    if ($nueva_foto) {
        $st = $pdo->prepare("UPDATE MAESTROS SET telefono = ?, correo = ?, titulo_academico = ?, biografia = ?, foto_perfil = ? WHERE id_maestro = ?");
        $st->execute([$telefono, $correo, $titulo_academico, $biografia, $nueva_foto, $id_maestro]);
    } else {
        $st = $pdo->prepare("UPDATE MAESTROS SET telefono = ?, correo = ?, titulo_academico = ?, biografia = ? WHERE id_maestro = ?");
        $st->execute([$telefono, $correo, $titulo_academico, $biografia, $id_maestro]);
    }
    volver_perfil('success');
} catch (PDOException $e) {
    error_log("Error al actualizar perfil: " . $e->getMessage());
    volver_perfil('error', 'No se pudieron guardar los cambios.');
}
