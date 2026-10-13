<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Marcar como "Finalizado" a los alumnos que actualmente están en grupos de 6to semestre
    $sqlEgresarAlumnos = "
        UPDATE alumnos a
        INNER JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno
        INNER JOIN GRUPOS g ON ga.id_grupo = g.id_grupo
        SET a.estatus = 'Finalizado', ga.fecha_baja = CURDATE()
        WHERE g.semestre = 6 AND g.estatus = 'Activo' AND ga.fecha_baja IS NULL
    ";
    $pdo->exec($sqlEgresarAlumnos);

    // 2. Ocultar (Inactivar) los grupos de 6to semestre para que no saturen la pantalla
    $sqlDesactivarGrupos = "UPDATE GRUPOS SET estatus = 'Inactivo' WHERE semestre = 6 AND estatus = 'Activo'";
    $pdo->exec($sqlDesactivarGrupos);

    // 3. Promover los grupos activos restantes sumando 1 a su semestre (1A -> 2A, 2A -> 3A, etc.)
    $sqlPromoverGrupos = "UPDATE GRUPOS SET semestre = semestre + 1 WHERE estatus = 'Activo'";
    $pdo->exec($sqlPromoverGrupos);

    // 4. Promover el semestre individual en la tabla ALUMNOS a los que siguen en Alta
    $sqlPromoverAlumnos = "UPDATE alumnos SET semestre = semestre + 1 WHERE estatus = 'Alta' AND semestre < 6";
    $pdo->exec($sqlPromoverAlumnos);

    $pdo->commit();

    // Redirigir con mensaje de éxito
    header("Location: ../admin/gestion_grupos.php?msg=ciclo_cerrado");
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error al promover semestre: " . $e->getMessage());
    die("Error crítico al promover el semestre. Contacta al soporte técnico. Detalle: " . $e->getMessage());
}
?>