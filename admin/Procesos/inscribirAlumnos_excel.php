<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_grupo = intval($_POST['id_grupo']);
    
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        $handle = fopen($archivo, "r");
        
        if ($handle !== FALSE) {
            try {
                $pdo->beginTransaction();
                
                $stmtBuscar = $pdo->prepare("SELECT id_alumno FROM ALUMNOS WHERE matricula = ?");
                
                // NUEVA CONSULTA: Buscamos si está activo y traemos el nombre del grupo
                $sqlVerificar = "SELECT g.semestre, g.nombre_grupo 
                                 FROM GRUPO_ALUMNO ga 
                                 INNER JOIN GRUPOS g ON ga.id_grupo = g.id_grupo 
                                 WHERE ga.id_alumno = ? AND ga.fecha_baja IS NULL LIMIT 1";
                $stmtVerificarGrupo = $pdo->prepare($sqlVerificar);
                
                $stmtInscribir = $pdo->prepare("INSERT INTO GRUPO_ALUMNO (id_grupo, id_alumno, fecha_asignacion) VALUES (?, ?, CURDATE())");
                
                $inscritos = 0;
                $omitidos = 0;
                $detalles_omitidos = []; // Arreglo detallado
                $fila = 0;
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $fila++;
                    if ($fila <= 7) continue; 
                    
                    if (isset($data[1])) {
                        $matricula = trim($data[1]); 
                        
                        if (!empty($matricula) && (strpos($matricula, '/') !== false || strpos($matricula, '27') === 0)) {
                            
                            $stmtBuscar->execute([$matricula]);
                            $alumno = $stmtBuscar->fetch();
                            
                            if ($alumno) {
                                $id_alumno = $alumno['id_alumno'];
                                $stmtVerificarGrupo->execute([$id_alumno]);
                                $grupoActual = $stmtVerificarGrupo->fetch();
                                
                                if (!$grupoActual) {
                                    try {
                                        $stmtInscribir->execute([$id_grupo, $id_alumno]);
                                        $inscritos++;
                                    } catch (PDOException $e) { }
                                } else {
                                    $omitidos++;
                                    $detalles_omitidos[] = [
                                        'matricula' => $matricula,
                                        'grupo' => $grupoActual['semestre'] . ' "' . $grupoActual['nombre_grupo'] . '"'
                                    ];
                                }
                            }
                        }
                    }
                }
                
                fclose($handle);
                $pdo->commit();
                
                // Guardamos los detalles en la sesión
                if ($omitidos > 0) {
                    $_SESSION['detalles_omitidos'] = $detalles_omitidos;
                }
                
                header("Location: ../ver_grupo.php?id=" . $id_grupo . "&msg=excel_procesado&cantidad=" . $inscritos . "&omitidos=" . $omitidos);
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                header("Location: ../ver_grupo.php?id=" . $id_grupo . "&error=Error en Base de Datos");
                exit();
            }
        }
    }
}
header("Location: ../gestion_grupos.php");
exit();
?>