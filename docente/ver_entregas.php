<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_actividad = isset($_GET['id_actividad']) ? (int)$_GET['id_actividad'] : 0;
$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0;

if ($id_actividad === 0) {
    header("Location: mis_grupos.php?error=Actividad no especificada.");
    exit();
}

try {
    $stmtAct = $pdo->prepare("SELECT titulo, fecha_limite FROM actividades_clase WHERE id_actividad = ?");
    $stmtAct->execute([$id_actividad]);
    $actividad = $stmtAct->fetch(PDO::FETCH_ASSOC);

    // Consulta actualizada para traer el id_entrega, estatus_entrega y retroalimentacion
    $stmtEntregas = $pdo->prepare("
        SELECT a.nombre, a.apellido_paterno, a.matricula, 
               e.id_entrega, e.archivo_ruta, e.fecha_entrega, e.estatus_entrega, e.retroalimentacion 
        FROM clase_regular_alumno cra 
        INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno 
        LEFT JOIN entregas_actividad e ON a.id_alumno = e.id_alumno AND e.id_actividad = ?
        WHERE cra.id_clase = ?
        ORDER BY a.apellido_paterno ASC
    ");
    $stmtEntregas->execute([$id_actividad, $id_clase]);
    $alumnos_entregas = $stmtEntregas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Entregas de Actividad · Portal Docente</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_docente.css">
    <style>
        .header-entregas { background: white; border: 1px solid var(--line); border-radius: 16px; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; box-shadow: var(--shadow-sm); }
        .header-entregas h2 { margin: 0 0 5px 0; font-size: 1.5rem; font-weight: 800; color: var(--ink); }
        .table-entregas { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; border: 1px solid var(--line); }
        .table-entregas th { background: #f8fafc; text-align: left; padding: 14px; font-size: 0.85rem; color: var(--muted); border-bottom: 1px solid var(--line); }
        .table-entregas td { padding: 14px; border-bottom: 1px solid var(--line); font-size: 0.9rem; color: var(--ink); }
        
        .badge-status { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .badge-pendiente { background: #fef9c3; color: #854d0e; }
        .badge-revisada { background: #dcfce7; color: #166534; }
        .badge-faltante { background: #fee2e2; color: #991b1b; }

        .btn-descargar { background: #eff6ff; color: #2563eb; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-block;}
        .btn-descargar:hover { background: #dbeafe; }
        .btn-revisar { background: white; border: 1px solid #cbd5e1; color: var(--ink); padding: 8px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
        .btn-revisar:hover { background: #f1f5f9; }

        /* Estilos del Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 2rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .modal-overlay.active { display: flex; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--muted); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: 'Inter'; font-size: 0.95rem; outline: none; }
        .btn-group { display: flex; gap: 10px; justify-content: flex-end; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <main class="main-content" style="margin-left:0; width:100%; padding:2rem;">
        <div class="header-entregas">
            <div>
                <h2><?php echo htmlspecialchars($actividad['titulo']); ?></h2>
                <span style="color:var(--muted); font-size:0.9rem;"><i class="fas fa-inbox"></i> Repositorio de evidencias y revisión</span>
            </div>
            <a href="gestionar_aula.php?id_clase=<?php echo $id_clase; ?>" class="btn-revisar" style="text-decoration:none;"><i class="fas fa-arrow-left"></i> Volver a la clase</a>
        </div>

        <?php if(isset($_GET['exito'])): ?>
            <div style="background:#ecfdf5; color:#065f46; padding:12px; border-radius:10px; margin-bottom:1rem; border:1px solid #a7f3d0;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?></div>
        <?php endif; ?>

        <table class="table-entregas">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Estado de Revisión</th>
                    <th>Fecha de Subida</th>
                    <th>Archivo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($alumnos_entregas as $alumno): 
                    $estatus = $alumno['estatus_entrega'] ?? 'Pendiente';
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['nombre']); ?></strong><br>
                        <span style="font-size:0.75rem; color:var(--muted);">Mat: <?php echo htmlspecialchars($alumno['matricula']); ?></span>
                    </td>
                    <td>
                        <?php if(!$alumno['id_entrega']): ?>
                            <span class="badge-status badge-faltante"><i class="fas fa-times"></i> Sin Entregar</span>
                        <?php elseif($estatus == 'Revisada'): ?>
                            <span class="badge-status badge-revisada"><i class="fas fa-check-double"></i> Revisada</span>
                        <?php else: ?>
                            <span class="badge-status badge-pendiente"><i class="fas fa-clock"></i> Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $alumno['fecha_entrega'] ? date('d/m/Y H:i', strtotime($alumno['fecha_entrega'])) : '-'; ?></td>
                    <td>
                        <?php if($alumno['archivo_ruta']): 
                            // Limpiamos el nombre ocultando el prefijo de seguridad (al_ID_TIEMPO_)
                            $nombre_descarga = preg_replace('/^al_\d+_\d+_/', '', $alumno['archivo_ruta']);
                        ?>
                            <!-- Le pasamos el nombre limpio al atributo download="" -->
                            <a href="../uploads/entregas_alumnos/<?php echo htmlspecialchars($alumno['archivo_ruta']); ?>" class="btn-descargar" target="_blank" download="<?php echo htmlspecialchars($nombre_descarga); ?>">
                                <i class="fas fa-download"></i> Descargar Archivo
                            </a>
                        <?php else: ?>
                            <span style="color:var(--muted); font-size:0.8rem;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($alumno['id_entrega']): ?>
                            <button class="btn-revisar" onclick="abrirModalRevisar(<?php echo $alumno['id_entrega']; ?>, '<?php echo $estatus; ?>', '<?php echo htmlspecialchars(addslashes($alumno['retroalimentacion'] ?? '')); ?>')">
                                <i class="fas fa-comment-dots"></i> Evaluar
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <!-- Modal para Revisar y Retroalimentar -->
    <div class="modal-overlay" id="modalRevisar">
        <div class="modal-content">
            <h3 style="margin:0 0 1rem 0; font-weight:800; border-bottom:1px solid var(--line); padding-bottom:10px;">
                <i class="fas fa-clipboard-check"></i> Evaluar Entrega
            </h3>
            <form action="procesos/docente_revisar_entrega.php" method="POST">
                <input type="hidden" name="id_entrega" id="rev_id_entrega">
                <input type="hidden" name="id_actividad" value="<?php echo $id_actividad; ?>">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                
                <div class="form-group">
                    <label>Estatus de la Revisión</label>
                    <select name="estatus_entrega" id="rev_estatus" class="form-control">
                        <option value="Pendiente">Pendiente de Revisión</option>
                        <option value="Revisada">Revisada y Completada</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Retroalimentación para el alumno (Opcional)</label>
                    <textarea name="retroalimentacion" id="rev_retro" class="form-control" style="min-height: 120px;" placeholder="Describe qué le faltó, qué estuvo mal o si todo está correcto..."></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn-revisar" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" style="background:var(--green); color:white; border:none; padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer;">
                        <i class="fas fa-save"></i> Guardar Evaluación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalRevisar(id_entrega, estatus, retroalimentacion) {
            document.getElementById('rev_id_entrega').value = id_entrega;
            document.getElementById('rev_estatus').value = estatus;
            document.getElementById('rev_retro').value = retroalimentacion;
            document.getElementById('modalRevisar').classList.add('active');
        }

        function cerrarModal() {
            document.getElementById('modalRevisar').classList.remove('active');
        }
    </script>
</body>
</html>