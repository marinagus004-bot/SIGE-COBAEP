<?php
session_start();
require_once '../config/database.php';

$carpeta_admin = 'admin';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../$carpeta_admin/gestion_grupos.php");
    exit();
}

// Recibir y limpiar datos
$semestre     = intval($_POST['semestre'] ?? 0);
$nombre_grupo = strtoupper(trim($_POST['nombre_grupo'] ?? ''));
$turno        = trim($_POST['turno'] ?? '');
$id_admin     = $_SESSION['id_usuario'];

// Validación del lado servidor (el HTML se puede saltar)
if ($semestre < 1 || $semestre > 6
    || !preg_match('/^[A-D]$/', $nombre_grupo)
    || !in_array($turno, ['Matutino', 'Intermedio', 'Vespertino'], true)) {
    header("Location: ../$carpeta_admin/agregar_grupo.php?msg=campos_vacios");
    exit();
}

$es_intermedio = (strtolower($turno) === 'intermedio') ? 1 : 0;

try {
    // Regla de negocio:
    // - Solo puede haber UN grupo activo por semestre + letra entre los turnos regulares (Matutino/Vespertino).
    // - Aparte, puede haber UN grupo Intermedio con ese mismo semestre + letra.
    $sql_check = "SELECT COUNT(*) FROM GRUPOS
                  WHERE estatus = 'Activo'
                    AND semestre = ?
                    AND UPPER(TRIM(nombre_grupo)) = ?
                    AND (LOWER(TRIM(turno)) = 'intermedio') = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$semestre, $nombre_grupo, $es_intermedio]);

    if ($stmt_check->fetchColumn() > 0) {
        header("Location: ../$carpeta_admin/gestion_grupos.php?msg=grupo_duplicado");
        exit();
    }

    $sql = "INSERT INTO GRUPOS (semestre, nombre_grupo, turno, id_admin_responsable) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$semestre, $nombre_grupo, $turno, $id_admin]);

    header("Location: ../$carpeta_admin/gestion_grupos.php?msg=grupo_creado");
    exit();

} catch (PDOException $e) {
    // 23000 = violación de índice único (protección de la base de datos)
    if ($e->getCode() == 23000) {
        header("Location: ../$carpeta_admin/gestion_grupos.php?msg=grupo_duplicado");
        exit();
    }
    die("Error al registrar el grupo: " . $e->getMessage());
}