<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario    = intval($_POST['id_usuario'] ?? 0);
    $tipo_usuario  = trim($_POST['tipo_usuario'] ?? '');
    $nombre        = trim($_POST['nombre'] ?? '');
    $apellido_p    = trim($_POST['apellido_p'] ?? '');
    $apellido_m    = trim($_POST['apellido_m'] ?? '');
    $identificador = trim($_POST['identificador'] ?? '');

    $url_editar = "../admin/editar_usuario.php?id=" . $id_usuario . "&tipo=" . urlencode($tipo_usuario);

    if ($id_usuario <= 0 || empty($tipo_usuario) || empty($nombre) || empty($apellido_p) || empty($identificador)) {
        header("Location: " . $url_editar . "&error=" . urlencode("Por favor completa todos los campos obligatorios."));
        exit();
    }

    try {
        $pdo->beginTransaction();

        if ($tipo_usuario === 'alumnos') {
            $codigo_qr = trim($_POST['codigo_qr'] ?? ('QR' . $identificador));
            $id_grupo  = intval($_POST['id_grupo'] ?? 0);
            $turno     = trim($_POST['turno'] ?? 'Matutino');

            if (!in_array($turno, ['Matutino', 'Intermedio'])) {
                $turno = 'Intermedio';
            }

            if ($id_grupo > 0) {
                $stmtG = $pdo->prepare("SELECT semestre, turno FROM GRUPOS WHERE id_grupo = ?");
                $stmtG->execute([$id_grupo]);
                $datosGrupo = $stmtG->fetch(PDO::FETCH_ASSOC);

                if ($datosGrupo) {
                    $turno = $datosGrupo['turno'];
                    $semestre = $datosGrupo['semestre'];

                    $sql = "UPDATE ALUMNOS 
                            SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, 
                                matricula = ?, codigo_qr = ?, turno = ?, semestre = ?
                            WHERE id_alumno = ?";
                    $pdo->prepare($sql)->execute([
                        $nombre, $apellido_p, $apellido_m ?: null, 
                        $identificador, $codigo_qr, $turno, $semestre, $id_usuario
                    ]);

                    // Dar de baja de otros grupos previos y activar el nuevo
                    $pdo->prepare("UPDATE GRUPO_ALUMNO SET fecha_baja = CURDATE() WHERE id_alumno = ? AND id_grupo != ? AND fecha_baja IS NULL")
                        ->execute([$id_usuario, $id_grupo]);

                    $checkGA = $pdo->prepare("SELECT id_ga FROM GRUPO_ALUMNO WHERE id_grupo = ? AND id_alumno = ?");
                    $checkGA->execute([$id_grupo, $id_usuario]);
                    if ($checkGA->rowCount() > 0) {
                        $pdo->prepare("UPDATE GRUPO_ALUMNO SET fecha_baja = NULL, fecha_asignacion = CURDATE() WHERE id_grupo = ? AND id_alumno = ?")
                            ->execute([$id_grupo, $id_usuario]);
                    } else {
                        $pdo->prepare("INSERT INTO GRUPO_ALUMNO (id_grupo, id_alumno, fecha_asignacion) VALUES (?, ?, CURDATE())")
                            ->execute([$id_grupo, $id_usuario]);
                    }
                }
            } else {
                // Si eligió "-- Sin grupo asignado --", damos de baja cualquier grupo activo
                $pdo->prepare("UPDATE GRUPO_ALUMNO SET fecha_baja = CURDATE() WHERE id_alumno = ? AND fecha_baja IS NULL")
                    ->execute([$id_usuario]);

                $sql = "UPDATE ALUMNOS 
                        SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, 
                            matricula = ?, codigo_qr = ?, turno = ?
                        WHERE id_alumno = ?";
                $pdo->prepare($sql)->execute([
                    $nombre, $apellido_p, $apellido_m ?: null, 
                    $identificador, $codigo_qr, $turno, $id_usuario
                ]);
            }

        } elseif ($tipo_usuario === 'maestros') {
            $sql = "UPDATE MAESTROS 
                    SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, usuario = ? 
                    WHERE id_maestro = ?";
            $pdo->prepare($sql)->execute([$nombre, $apellido_p, $apellido_m ?: null, $identificador, $id_usuario]);

        } elseif ($tipo_usuario === 'admin') {
            $sql = "UPDATE ADMINISTRATIVOS 
                    SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, usuario = ? 
                    WHERE id_admin = ?";
            $pdo->prepare($sql)->execute([$nombre, $apellido_p, $apellido_m ?: null, $identificador, $id_usuario]);

        } elseif ($tipo_usuario === 'prefectos') {
            $sql = "UPDATE PREFECTOS 
                    SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, usuario = ? 
                    WHERE id_prefecto = ?";
            $pdo->prepare($sql)->execute([$nombre, $apellido_p, $apellido_m ?: null, $identificador, $id_usuario]);
        } else {
            throw new Exception("Tipo de usuario no reconocido.");
        }

        $pdo->commit();
        header("Location: " . $url_editar . "&exito=1");
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Detectar si fue error de duplicado (Matrícula, QR o Usuario repetido)
        if ($e->getCode() == 23000) {
            $msgError = "La matrícula, código QR o nombre de usuario '$identificador' ya está registrado en otra cuenta.";
        } else {
            $msgError = "Error en la base de datos: " . $e->getMessage();
        }
        header("Location: " . $url_editar . "&error=" . urlencode($msgError));
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: " . $url_editar . "&error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../admin/gestion_usuarios.php");
    exit();
}