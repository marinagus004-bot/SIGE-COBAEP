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
    $stmt = $pdo->prepare("SELECT m.nombre_materia, g.nombre_grupo, g.semestre FROM clases_regulares c INNER JOIN materias m ON c.id_materia = m.id_materia LEFT JOIN grupos g ON c.id_grupo = g.id_grupo WHERE c.id_clase = ?");
    $stmt->execute([$id_clase]);
    $clase = $stmt->fetch(PDO::FETCH_ASSOC);

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
    die("Error al cargar reporte: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Asistencia - SIGE COBAEP</title>
    <style>
        /* Diseño institucional, sobrio y sin aspecto "IA" */
        * { box-sizing: border-box; }
        body { background-color: #f0f2f5; font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; border: 1px solid #ccc; border-top: 4px solid #1b4332; padding: 25px; }
        
        /* Cabecera */
        .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 22px; color: #1b4332; font-weight: normal; }
        .header p { margin: 5px 0 0 0; color: #555; font-size: 14px; font-weight: bold; }
        .btn-volver { background: #e9ecef; color: #333; padding: 8px 15px; text-decoration: none; font-size: 13px; border: 1px solid #ccc; }
        .btn-volver:hover { background: #d3d9df; }

        /* Panel de Filtros para Excel */
        .filter-panel { background: #f8f9fa; border: 1px solid #e2e8f0; padding: 15px; margin-bottom: 20px; }
        .filter-panel h3 { margin: 0 0 10px 0; font-size: 14px; color: #333; }
        .form-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 11px; font-weight: bold; color: #666; margin-bottom: 4px; text-transform: uppercase; }
        .form-control { padding: 6px 10px; border: 1px solid #aaa; border-radius: 2px; font-size: 13px; }
        
        .btn-excel { background: #1b4332; color: white; border: none; padding: 8px 20px; font-size: 13px; cursor: pointer; border-radius: 2px; font-weight: bold; display: flex; align-items: center; gap: 5px; }
        .btn-excel:hover { background: #112d21; }

        /* Tabla Estilo Clásico */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 10px; font-size: 13px; text-align: left; }
        th { background-color: #e9ecef; color: #333; font-size: 12px; font-weight: bold; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        tr:hover { background-color: #f1f3f5; }
        
        .text-center { text-align: center; }
        .val-p { color: #198754; font-weight: bold; }
        .val-r { color: #d97706; font-weight: bold; }
        .val-f { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Reporte General de Asistencia</h1>
                <p><?php echo htmlspecialchars($clase['nombre_materia']); ?> | Grupo: <?php echo htmlspecialchars($clase['semestre']) . '° "' . htmlspecialchars($clase['nombre_grupo']) . '"'; ?></p>
            </div>
            <a href="gestionar_aula.php?id_clase=<?php echo $id_clase; ?>" class="btn-volver">Regresar al Aula</a>
        </div>

        <div class="filter-panel">
            <h3>Filtros y Exportación de Sábana (Excel)</h3>
            <form id="form_filtros" action="procesos/descargar_asistencia_excel.php" method="GET" class="form-row">
                <input type="hidden" name="id_clase" id="id_clase_input" value="<?php echo $id_clase; ?>">
                
                <div class="form-group">
                    <label>Tipo de Corte</label>
                    <select name="tipo_filtro" id="tipo_filtro" class="form-control" onchange="toggleFiltros()">
                        <option value="semestre">Semestre Completo</option>
                        <option value="parcial">Por Parcial (Fechas)</option>
                        <option value="mes">Por Mes Específico</option>
                    </select>
                </div>

                <!-- Filtro por Fechas -->
                <div class="form-group" id="div_fechas" style="display: none;">
                    <label>Desde - Hasta</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control">
                        <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
                    </div>
                </div>

                <!-- Filtro por Mes -->
                <div class="form-group" id="div_mes" style="display: none;">
                    <label>Mes</label>
                    <select name="mes" id="mes" class="form-control">
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
                </div>

                <div class="form-group" style="display: flex; gap: 10px;">
                    <!-- Botón para actualizar la vista previa en pantalla -->
                    <button type="button" class="btn-excel" style="background: #0d6efd;" onclick="actualizarVistaPrevia()">
                        <i class="fas fa-sync-alt"></i> Actualizar Tabla
                    </button>
                    <!-- Botón para descargar el Excel real -->
                    <button type="submit" class="btn-excel">
                        <i class="fas fa-file-excel"></i> Descargar XLS
                    </button>
                </div>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Matrícula</th>
                    <th style="width: 55%;">Nombre del Alumno</th>
                    <th class="text-center">Asist.</th>
                    <th class="text-center">Retardos</th>
                    <th class="text-center">Faltas</th>
                </tr>
            </thead>
            <tbody id="cuerpo_tabla">
                <!-- Los datos se cargarán aquí por defecto o vía AJAX -->
                <?php foreach($alumnos as $alum): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($alum['matricula']); ?></td>
                        <td><?php echo htmlspecialchars($alum['apellido_paterno'] . ' ' . $alum['nombre']); ?></td>
                        <td class="text-center val-p"><?php echo $alum['presentes']; ?></td>
                        <td class="text-center val-r"><?php echo $alum['retardos']; ?></td>
                        <td class="text-center val-f"><?php echo $alum['faltas']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
   <script>
        // Función 1: Muestra u oculta los campos de fecha o mes según lo que elija el maestro
        function toggleFiltros() {
            var tipo = document.getElementById("tipo_filtro").value;
            document.getElementById("div_fechas").style.display = (tipo === 'parcial') ? 'block' : 'none';
            document.getElementById("div_mes").style.display = (tipo === 'mes') ? 'block' : 'none';
        }

        // Función 2: Actualiza la tabla dinámicamente usando AJAX (sin recargar la página)
        function actualizarVistaPrevia() {
            const id_clase = document.getElementById('id_clase_input').value;
            const tipo = document.getElementById('tipo_filtro').value;
            const mes = document.getElementById('mes').value;
            const f_inicio = document.getElementById('fecha_inicio').value;
            const f_fin = document.getElementById('fecha_fin').value;
            
            const tbody = document.getElementById('cuerpo_tabla');
            tbody.innerHTML = "<tr><td colspan='5' class='text-center'><i class='fas fa-spinner fa-spin'></i> Calculando asistencias...</td></tr>";

            // Construir la URL con los parámetros
            const url = `procesos/obtener_tabla_asistencia_ajax.php?id_clase=${id_clase}&tipo_filtro=${tipo}&mes=${mes}&fecha_inicio=${f_inicio}&fecha_fin=${f_fin}`;

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    tbody.innerHTML = html;
                })
                .catch(error => {
                    tbody.innerHTML = "<tr><td colspan='5' class='text-center' style='color:red;'>Error al cargar los datos.</td></tr>";
                });
        }
    </script>
</body>
</html>
</body>
</html>

 
</body>
</html>