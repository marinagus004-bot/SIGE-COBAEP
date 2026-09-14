<?php
session_start();
require_once '../config/database.php';

// Validar que sea un administrativo
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

// Por defecto, mostramos las asistencias del día de hoy
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

try {
    // Unimos las dos tablas para tener los datos completos del alumno y su hora de entrada
    $sql = "SELECT u.matricula, u.nombre_completo, u.grado, u.grupo, a.hora, a.estado 
            FROM asistencias a
            INNER JOIN usuarios u ON a.matricula = u.matricula
            WHERE a.fecha = :fecha
            ORDER BY a.hora DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':fecha' => $fecha_filtro]);
    $registros = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar los reportes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Asistencia - COBAEP 27</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; padding: 40px; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; }
        h1 { color: #112d4e; margin-top: 0; }
        
        /* Controles superiores */
        .top-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .form-filtro { display: flex; gap: 10px; align-items: center; background: #eef2f5; padding: 10px 15px; border-radius: 8px; }
        .form-filtro input[type="date"] { padding: 8px; border: 1px solid #ccc; border-radius: 5px; }
        .btn-filtrar { background-color: #112d4e; color: white; border: none; padding: 9px 15px; border-radius: 5px; cursor: pointer; }
        .btn-volver { color: #666; text-decoration: none; padding: 9px 15px; border: 1px solid #ccc; border-radius: 5px; }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #112d4e; color: white; }
        tr:hover { background-color: #f5f5f5; }
        
        /* Etiquetas de estado (Badges) */
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: bold; color: white; text-transform: uppercase; }
        .bg-asistencia { background-color: #2e7d32; }
        .bg-retardo { background-color: #f57c00; }
        .bg-inasistencia { background-color: #c62828; }
    </style>
</head>
<body>

<div class="container">
    <div class="top-controls">
        <h1>📄 Reporte Diario de Accesos</h1>
        
        <form method="GET" action="reportes.php" class="form-filtro">
            <label for="fecha"><strong>Filtrar por fecha:</strong></label>
            <input type="date" name="fecha" id="fecha" value="<?php echo htmlspecialchars($fecha_filtro); ?>">
            <button type="submit" class="btn-filtrar">Buscar</button>
            <a href="dashboard_admin.php" class="btn-volver">Volver al Panel</a>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Hora</th>
                <th>Matrícula</th>
                <th>Nombre del Alumno</th>
                <th>Semestre y Grupo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($registros) > 0): ?>
                <?php foreach($registros as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['hora']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['matricula']); ?></td>
                    <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                    <td><?php echo htmlspecialchars($row['grado'] . " - " . $row['grupo']); ?></td>
                    <td>
                        <?php 
                            if($row['estado'] == 'asistencia') {
                                echo '<span class="badge bg-asistencia">Asistencia</span>';
                            } elseif ($row['estado'] == 'retardo') {
                                echo '<span class="badge bg-retardo">Retardo</span>';
                            } else {
                                echo '<span class="badge bg-inasistencia">Falta</span>';
                            }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #777; padding: 30px;">
                        No hay registros de asistencia para la fecha seleccionada (<?php echo htmlspecialchars($fecha_filtro); ?>).
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>