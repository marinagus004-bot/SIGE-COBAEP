<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0;
if ($id_clase === 0) { header("Location: mis_grupos.php"); exit(); }

try {
    // Info de la materia
    $stmt = $pdo->prepare("SELECT m.nombre_materia, g.nombre_grupo, g.semestre FROM clases_regulares c INNER JOIN materias m ON c.id_materia = m.id_materia LEFT JOIN grupos g ON c.id_grupo = g.id_grupo WHERE c.id_clase = ?");
    $stmt->execute([$id_clase]);
    $clase = $stmt->fetch(PDO::FETCH_ASSOC);

    // Consulta general de alumnos con conteo de asistencias (SINTAXIS CORREGIDA)
    $stmtAlumnos = $pdo->prepare("
        SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno,
            SUM(CASE WHEN ac.estatus IN ('Presente', 'Asistencia') THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN ac.estatus = 'Retardo' THEN 1 ELSE 0 END) as retardos,
            SUM(CASE WHEN ac.estatus IN ('Falta', 'Ausente') THEN 1 ELSE 0 END) as faltas
        FROM clase_regular_alumno cra
        INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno
        LEFT JOIN asistencias_clases ac ON a.id_alumno = ac.id_alumno AND ac.id_clase = cra.id_clase
        WHERE cra.id_clase = ?
        GROUP BY a.id_alumno
        ORDER BY a.apellido_paterno ASC
    ");
    $stmtAlumnos->execute([$id_clase]);
    $alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { 
    // Ahora si falla, te dirá el motivo exacto del error
    die("Error al cargar reporte: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Alumnos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; margin: 0; padding: 2rem; color: #0f172a; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { background: #064e3b; padding: 2rem; border-radius: 16px; color: white; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header h1 { margin: 0 0 5px 0; font-size: 1.5rem; font-weight: 800; }
        .header p { margin: 0; color: #a7f3d0; font-size: 0.95rem; }
        .btn-volver { color:white; text-decoration:none; background:rgba(255,255,255,0.2); padding:10px 20px; border-radius:50px; font-weight:700; transition:0.2s; display:inline-flex; align-items:center; gap:8px;}
        .btn-volver:hover { background:rgba(255,255,255,0.3); }
        
        /* Barra de Exportación Excel */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; background: white; padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .select-mes { padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; font-family: 'Inter'; font-weight: 600; color: #475569; }
        .btn-excel { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-excel:hover { background: #059669; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }

        table { width: 100%; background: white; border-radius: 12px; overflow: hidden; border-collapse: collapse; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 800; color: #475569; font-size: 0.85rem; text-transform: uppercase; }
        td { font-size: 0.95rem; font-weight: 500; }
        tr:hover { background: #f8fafc; }
        
        .badge { padding: 6px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; text-align: center; display: inline-block; min-width: 30px;}
        .b-verde { background: #ecfdf5; color: #059669; }
        .b-amarillo { background: #fffbeb; color: #d97706; }
        .b-rojo { background: #fef2f2; color: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Reporte de Asistencia</h1>
                <p><i class="fas fa-book"></i> <?php echo htmlspecialchars($clase['nombre_materia']); ?> - Grupo <?php echo htmlspecialchars($clase['semestre']) . '° "' . htmlspecialchars($clase['nombre_grupo']) . '"'; ?></p>
            </div>
            <a href="gestionar_aula.php?id_clase=<?php echo $id_clase; ?>" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Aula</a>
        </div>

        <!-- BARRA PARA DESCARGAR EXCEL -->
        <div class="toolbar">
            <div style="font-weight: 700; color: #0f172a;">
                <i class="fas fa-border-all" style="color: #10b981;"></i> Sábana de Asistencias (Excel)
            </div>
            <form action="procesos/descargar_asistencia_excel.php" method="GET" style="display: flex; gap: 10px; align-items: center; margin:0;">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <select name="mes" class="select-mes">
                    <option value="todos">Todo el Semestre</option>
                    <option value="01">Enero</option>
                    <option value="02">Febrero</option>
                    <option value="03">Marzo</option>
                    <option value="04">Abril</option>
                    <option value="05">Mayo</option>
                    <option value="06">Junio</option>
                    <option value="07">Julio</option>
                    <option value="08">Agosto</option>
                    <option value="09">Septiembre</option>
                    <option value="10">Octubre</option>
                    <option value="11">Noviembre</option>
                    <option value="12">Diciembre</option>
                </select>
                <button type="submit" class="btn-excel"><i class="fas fa-file-excel"></i> Descargar XLS</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Matrícula</th>
                    <th>Nombre del Alumno</th>
                    <th style="text-align: center;">Presentes</th>
                    <th style="text-align: center;">Retardos</th>
                    <th style="text-align: center;">Faltas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($alumnos as $alum): ?>
                    <tr>
                        <td style="color: #64748b; font-size: 0.85rem;"><?php echo htmlspecialchars($alum['matricula']); ?></td>
                        <td><strong><?php echo htmlspecialchars($alum['apellido_paterno'] . ' ' . $alum['nombre']); ?></strong></td>
                        <td style="text-align: center;"><span class="badge b-verde"><?php echo $alum['presentes']; ?></span></td>
                        <td style="text-align: center;"><span class="badge b-amarillo"><?php echo $alum['retardos']; ?></span></td>
                        <td style="text-align: center;"><span class="badge b-rojo"><?php echo $alum['faltas']; ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>