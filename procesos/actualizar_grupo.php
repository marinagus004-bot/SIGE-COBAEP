<?php
session_start();
require_once '../config/database.php';

// Nombre de la carpeta donde viven gestion_grupos.php, editar_grupo.php, etc.
// Cámbialo aquí si tu carpeta se llama distinto (ej. 'administrador').
$carpeta_admin = 'admin';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../$carpeta_admin/gestion_grupos.php");
    exit();
}

$id_grupo     = intval($_POST['id_grupo'] ?? 0);
$semestre     = intval($_POST['semestre'] ?? 0);
$nombre_grupo = strtoupper(trim($_POST['nombre_grupo'] ?? ''));
$turno        = trim($_POST['turno'] ?? '');

// Validación del lado servidor
if ($id_grupo < 1 || $semestre < 1 || $semestre > 6
    || !preg_match('/^[A-D]$/', $nombre_grupo)
    || !in_array($turno, ['Matutino', 'Intermedio', 'Vespertino'], true)) {
    header("Location: ../$carpeta_admin/gestion_grupos.php");
    exit();
}

$es_intermedio = (strtolower($turno) === 'intermedio') ? 1 : 0;

try {
    // Misma regla que al crear, pero excluyendo al grupo que se está editando
    $sql_check = "SELECT COUNT(*) FROM GRUPOS
                  WHERE estatus = 'Activo'
                    AND id_grupo <> ?
                    AND semestre = ?
                    AND UPPER(TRIM(nombre_grupo)) = ?
                    AND (LOWER(TRIM(turno)) = 'intermedio') = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id_grupo, $semestre, $nombre_grupo, $es_intermedio]);

    if ($stmt_check->fetchColumn() > 0) {
        header("Location: ../$carpeta_admin/editar_grupo.php?id=$id_grupo&msg=grupo_duplicado");
        exit();
    }

    $sql = "UPDATE GRUPOS SET semestre = ?, nombre_grupo = ?, turno = ? WHERE id_grupo = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$semestre, $nombre_grupo, $turno, $id_grupo]);

    header("Location: ../$carpeta_admin/gestion_grupos.php?msg=actualizado");
    exit();

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        header("Location: ../$carpeta_admin/editar_grupo.php?id=$id_grupo&msg=grupo_duplicado");
        exit();
    }
    die("Error al actualizar el grupo: " . $e->getMessage());
}