<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    echo "Acceso denegado.";
    exit();
}

$id_actividad = isset($_GET['id_actividad']) ? (int)$_GET['id_actividad'] : 0;
$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0; 

if ($id_actividad > 0 && $id_clase > 0) {
    try {
        // COALESCE(va.visible, 0) obliga a que por defecto todos estén deshabilitados
        $stmt = $pdo->prepare("
            SELECT a.id_alumno, a.nombre, a.apellido_paterno, a.matricula, 
                   COALESCE(va.visible, 0) as puede_ver 
            FROM clase_regular_alumno cra
            INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno
            LEFT JOIN visibilidad_actividad va ON a.id_alumno = va.id_alumno AND va.id_actividad = ?
            WHERE cra.id_clase = ?
            ORDER BY a.apellido_paterno ASC
        ");
        $stmt->execute([$id_actividad, $id_clase]);
        $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($alumnos) === 0) {
            echo "<div style='text-align:center; padding:15px; color:#64748b;'>No hay alumnos registrados.</div>";
            exit;
        }

        // Buscador y botones de control masivo
        echo '
        <div style="margin-bottom: 15px;">
            <div style="display:flex; gap: 10px; margin-bottom: 15px;">
                <button type="button" class="btn-outline" style="flex:1; font-size:0.75rem; color:#059669; border-color:#059669; justify-content:center;" onclick="marcarTodosExc('.$id_actividad.', 1)"><i class="fas fa-check-double"></i> Permitir a Todos</button>
                <button type="button" class="btn-outline" style="flex:1; font-size:0.75rem; color:#dc2626; border-color:#dc2626; justify-content:center;" onclick="marcarTodosExc('.$id_actividad.', 0)"><i class="fas fa-ban"></i> Bloquear a Todos</button>
            </div>
            <div class="search-box" style="margin-bottom:0;">
                <i class="fas fa-search" style="color:#94a3b8;"></i>
                <input type="text" id="buscadorExcepciones" placeholder="Buscar por nombre o matrícula..." style="width:100%; border:none; outline:none; font-family:Inter; font-size:0.9rem;">
            </div>
        </div>
        <div id="listaExcScroll" class="lista-scroll" style="max-height: 300px; overflow-y:auto; padding-right:5px;">
        ';

        foreach ($alumnos as $alum) {
            $iniciales = mb_substr($alum['nombre'], 0, 1) . mb_substr($alum['apellido_paterno'], 0, 1);
            $checked = $alum['puede_ver'] == 1 ? 'checked' : '';
            echo '
            <div class="alumno-exc-item" style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="alumno-avatar" style="width:32px; height:32px; background:#f1f5f9; color:#475569; border-radius:50%; display:grid; place-items:center; font-size:0.75rem; font-weight:bold;">'.htmlspecialchars($iniciales).'</div>
                    <div class="alumno-info" style="line-height:1.2;">
                        <strong class="exc-nombre" style="display:block; font-size:0.85rem; color:var(--ink);">'.htmlspecialchars($alum['apellido_paterno'].' '.$alum['nombre']).'</strong>
                        <span class="exc-mat" style="font-size:0.75rem; color:var(--muted);">Mat: '.htmlspecialchars($alum['matricula']).'</span>
                    </div>
                </div>
                <div class="switch" title="Permitir o bloquear entregas">
                    <input type="checkbox" class="rojo exc-toggle" id="vis_alum_'.$alum['id_alumno'].'" '.$checked.' onchange="toggleVisibilidadAlumno('.$alum['id_alumno'].', '.$id_actividad.', this)">
                    <label class="slider" for="vis_alum_'.$alum['id_alumno'].'"></label>
                </div>
            </div>';
        }
        
        echo '</div>'; // Cierra lista
        
        // Lógica de búsqueda y botones masivos
        echo '
        <script>
            document.getElementById("buscadorExcepciones").addEventListener("input", function(e) {
                let term = e.target.value.toLowerCase();
                document.querySelectorAll(".alumno-exc-item").forEach(item => {
                    let nombre = item.querySelector(".exc-nombre").textContent.toLowerCase();
                    let matricula = item.querySelector(".exc-mat").textContent.toLowerCase();
                    item.style.display = (nombre.includes(term) || matricula.includes(term)) ? "flex" : "none";
                });
            });
            
            function marcarTodosExc(id_actividad, estado) {
                document.querySelectorAll(".exc-toggle").forEach(check => {
                    if ((estado === 1 && !check.checked) || (estado === 0 && check.checked)) {
                        check.checked = (estado === 1);
                        // Disparar el guardado automático
                        let event = new Event("change");
                        check.dispatchEvent(event);
                    }
                });
            }
        </script>
        ';
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>