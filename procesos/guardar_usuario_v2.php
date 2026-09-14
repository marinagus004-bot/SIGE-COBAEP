<?php
// procesos/guardar_usuario_v2.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['rol'] === 'administrativo') {
    
    $tipo = $_POST['tipo_usuario'];
    $nombre = trim($_POST['nombre']);
    $apellido_p = trim($_POST['apellido_p']);
    $apellido_m = !empty($_POST['apellido_m']) ? trim($_POST['apellido_m']) : null;
    $telefono = !empty($_POST['telefono']) ? trim($_POST['telefono']) : null;
    $correo = !empty($_POST['correo']) ? trim($_POST['correo']) : null;
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        if ($tipo === 'alumno') {
            $matricula = trim($_POST['matricula']);
            $codigo_qr = trim($_POST['codigo_qr']);
            $turno = $_POST['turno'];
            $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;

            // Consulta ajustada a las columnas reales de la tabla ALUMNOS
            $sql = "INSERT INTO ALUMNOS 
                    (matricula, codigo_qr, turno, nombre, apellido_paterno, apellido_materno,
                     fecha_nacimiento, telefono, correo_personal, contrasena_hash) 
                    VALUES 
                    (:mat, :qr, :turno, :nom, :ap_p, :ap_m,
                     :fecha_nac, :tel, :correo, :pass)";

            $stmt = $pdo->prepare($sql);
            $params = [
                ':mat'      => $matricula,
                ':qr'       => $codigo_qr,
                ':turno'    => $turno,
                ':nom'      => $nombre,
                ':ap_p'     => $apellido_p,
                ':ap_m'     => $apellido_m,
                ':fecha_nac'=> $fecha_nacimiento,
                ':tel'      => $telefono,
                ':correo'   => $correo,   // se mapea a correo_personal
                ':pass'     => $password_hash
            ];

            $stmt->execute($params);

            // Asignar grupo si se seleccionó
            if (!empty($_POST['id_grupo'])) {
                $id_nuevo_alumno = $pdo->lastInsertId();
                $sqlGrupo = "INSERT INTO GRUPO_ALUMNO (id_alumno, id_grupo) VALUES (:alumno, :grupo)";
                $stmtG = $pdo->prepare($sqlGrupo);
                $stmtG->execute([
                    ':alumno' => $id_nuevo_alumno,
                    ':grupo'  => $_POST['id_grupo']
                ]);
            }

        } elseif ($tipo === 'maestro') {
            $usuario = trim($_POST['usuario']);
            $sql = "INSERT INTO MAESTROS (nombre, apellido_paterno, apellido_materno, telefono, correo, usuario, contrasena_hash) 
                    VALUES (:nom, :ap_p, :ap_m, :tel, :correo, :usr, :pass)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom'    => $nombre,
                ':ap_p'   => $apellido_p,
                ':ap_m'   => $apellido_m,
                ':tel'    => $telefono,
                ':correo' => $correo,
                ':usr'    => $usuario,
                ':pass'   => $password_hash
            ]);

        } elseif ($tipo === 'admin') {
            $usuario = trim($_POST['usuario']);
            $sql = "INSERT INTO ADMINISTRATIVOS (nombre, apellido_paterno, apellido_materno, telefono, correo, usuario, contrasena_hash) 
                    VALUES (:nom, :ap_p, :ap_m, :tel, :correo, :usr, :pass)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom'    => $nombre,
                ':ap_p'   => $apellido_p,
                ':ap_m'   => $apellido_m,
                ':tel'    => $telefono,
                ':correo' => $correo,
                ':usr'    => $usuario,
                ':pass'   => $password_hash
            ]);
        }

        header("Location: ../admin/agregar_usuario.php?exito=1");
        exit();

    } catch (PDOException $e) {
        // Mensaje de error más claro
        die("Error al guardar: " . $e->getMessage() . 
            "<br><br>Verifica que la tabla ALUMNOS tenga las columnas: matricula, codigo_qr, turno, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, telefono, correo_personal, contrasena_hash.");
    }
} else {
    header("Location: ../admin/dashboard_admin.php");
    exit();
}
?>