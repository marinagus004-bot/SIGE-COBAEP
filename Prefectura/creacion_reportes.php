<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Prefecto';

// 1. Obtener catálogo de faltas
try {
    $stmt_faltas = $pdo->query("SELECT id_tipo_falta, numero_reglamento, descripcion_falta, puntos_descuento FROM tipos_falta ORDER BY numero_reglamento ASC");
    $tipos_falta = $stmt_faltas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tipos_falta = [];
}

// 2. Buscador y obtención de reportes recientes
$busqueda = trim($_GET['buscar'] ?? '');
$params_reportes = [];
$query_reportes = "
    SELECT r.id_reporte, r.fecha_hora, al.matricula, al.nombre, al.apellido_paterno, 
           tf.descripcion_falta, r.puntos_descontados, r.sancion_acumulativa_activada 
    FROM reportes r
    INNER JOIN alumnos al ON r.id_alumno = al.id_alumno
    INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta
";

if (!empty($busqueda)) {
    $query_reportes .= " WHERE al.matricula LIKE :busqueda OR al.nombre LIKE :busqueda OR al.apellido_paterno LIKE :busqueda";
    $params_reportes[':busqueda'] = '%' . $busqueda . '%';
}

$query_reportes .= " ORDER BY r.fecha_hora DESC LIMIT 20";

try {
    $stmt_reportes = $pdo->prepare($query_reportes);
    $stmt_reportes->execute($params_reportes);
    $ultimos_reportes = $stmt_reportes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ultimos_reportes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Conducta · COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../admin/style_dashboard_admin.css">
    <link rel="stylesheet" href="../Prefectura/style_dashboard_prefectura.css">
    <link rel="stylesheet" href="../Prefectura/style_creacion_reportes.css">
    
    <style>
        /* SCROLL PARA LA TABLA DE HISTORIAL */
        .overview-card .table-responsive { max-height: 450px; overflow-y: auto; }
        .overview-card .table-responsive thead th { position: sticky; top: 0; background-color: #ffffff; z-index: 10; box-shadow: 0 1px 0 var(--line); }
        .overview-card .table-responsive::-webkit-scrollbar { width: 6px; }
        .overview-card .table-responsive::-webkit-scrollbar-track { background: transparent; }
        .overview-card .table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .overview-card .table-responsive::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* SEPARADOR DE FECHAS EN TABLA */
        .date-divider td {
            background-color: #f0fdf4 !important; color: var(--green-700) !important;
            font-weight: 800 !important; font-size: 0.85rem; padding: 0.8rem 1.5rem !important;
            border-bottom: 2px solid var(--green-soft) !important; text-transform: uppercase; letter-spacing: 0.05em;
        }

        /* BOTONES DE ACCIÓN */
        .action-buttons { display: flex; gap: 8px; justify-content: center; }
        .btn-action {
            border: none; background: transparent; width: 30px; height: 30px;
            border-radius: var(--radius-sm); display: grid; place-items: center;
            cursor: pointer; transition: 0.2s ease; color: var(--muted);
            text-decoration: none; font-size: 0.9rem;
        }
        .btn-edit:hover { background: #fef08a; color: #ca8a04; }
        .btn-delete:hover { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy">
                    <strong>SIGE<span>-Cobaep</span></strong>
                    <small>Plantel 27</small>
                </div>
            </div>
            
             <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_prefectura.php" class="nav-item">
                    <i class="fas fa-shield-halved"></i>
                    <span>Control de Acceso</span>
                </a>
                <a href="historial_asistencias.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>Historial y Reportes</span>
                </a>
                <a href="creacion_reportes.php" class="nav-item active">
                    <i class="fa-solid fa-file-lines"></i>
                    <span>Creacion de Reporte</span>
                </a>  
                <!-- NUEVO MÓDULO EN EL MENÚ -->
                <a href="expediente_alumnos.php" class="nav-item">
                    <i class="fas fa-folder-open"></i>
                    <span>Expediente de Alumnos</span>
                </a>
            </nav>

             <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon">
                        <i class="fas fa-building-columns"></i>
                    </div>
                    <div>
                        <strong>COBAEP Plantel 27</strong>
                        <span>Zaragoza, Puebla</span>
                    </div>
                </div>

                <a href="../logout.php" class="logout">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <span class="eyebrow">PREFECTURA · DISCIPLINA</span>
                    <h1>Reportes de Conducta</h1>
                    <p>Registra faltas disciplinarias y consulta el historial reciente.</p>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto"><?php echo date('d/m/Y H:i'); ?></span>
                </div>

                <div class="profile">
                    <div class="profile-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong>
                        <span>Prefecto</span>
                    </div>
                </div>
            </div>
        </header>

        <section class="reportes-container">
            
            <!-- FORMULARIO DE CAPTURA -->
            <article class="form-reporte">
                <div class="card-heading" style="margin-bottom: 20px;">
                    <h2><i class="fas fa-file-signature" style="color: var(--green); margin-right: 8px;"></i> Nuevo Reporte</h2>
                </div>

                <form action="procesos/guardar_reporte.php" method="POST" id="formReporte">
                    
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Matrícula del Alumno</label>
                        <div class="input-group">
                            <input type="text" id="matricula" name="matricula" class="form-control" placeholder="Ej. 22014567" required autocomplete="off">
                            <button type="button" id="btnBuscarAlumno" class="btn-icon-search"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <div id="studentProfile" class="student-profile">
                        <div class="sp-avatar"><i class="fas fa-user"></i></div>
                        <div class="sp-info">
                            <h4 id="spNombre">Juan Pérez López</h4>
                            <p id="spGrupo">4to Semestre - Grupo A</p>
                        </div>
                        <div class="sp-points">
                            <span style="display:block; font-size:0.6rem; color:var(--muted); font-weight:700; text-transform:uppercase;">Puntos</span>
                            <span class="pts-badge" id="spPuntos">100</span>
                        </div>
                    </div>
                    <div id="studentError" class="student-profile error" style="display: none;">
                        <i class="fas fa-exclamation-triangle" style="margin-bottom: 5px; font-size:1.2rem;"></i><br>
                        Alumno no encontrado. Verifique la matrícula.
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--line); margin: 20px 0;">

                    <div class="form-group">
                        <label><i class="fas fa-gavel"></i> Motivo / Regla Infringida</label>
                        <div class="custom-select-container">
                            <input type="hidden" name="id_tipo_falta" id="id_tipo_falta" required>
                            
                            <div class="custom-select-trigger" id="customSelectTrigger">
                                <span id="triggerText">Selecciona o busca la falta...</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            
                            <div class="custom-select-options" id="customSelectOptions">
                                <div class="custom-select-search">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchFalta" placeholder="Buscar regla, palabra...">
                                </div>
                                <ul class="options-list" id="faltaList">
                                    <?php foreach ($tipos_falta as $falta): ?>
                                        <li class="option-item" data-value="<?php echo $falta['id_tipo_falta']; ?>">
                                            <strong>Regla <?php echo $falta['numero_reglamento']; ?>:</strong> 
                                            <?php echo htmlspecialchars($falta['descripcion_falta']); ?>
                                            <span class="pts">-<?php echo $falta['puntos_descuento']; ?> pts</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label for="observaciones"><i class="fas fa-comment-dots"></i> Detalles (Opcional)</label>
                        <textarea id="observaciones" name="observaciones" class="form-control" style="resize:vertical; min-height:80px;" placeholder="Describe la situación..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnSubmit" style="width: 100%; margin-top: 10px;" disabled>
                        <i class="fas fa-paper-plane"></i> Emitir Reporte
                    </button>
                </form>
            </article>

            <!-- TABLA RECIENTES -->
            <article class="overview-card" style="padding: 0; overflow: hidden; margin-top: 0;">
                <div class="card-heading" style="padding: 24px 24px 10px;">
                    <div>
                        <span class="section-kicker">HISTORIAL</span>
                        <h2>Últimos Reportes</h2>
                    </div>
                </div>

                <form method="GET" class="search-bar">
                    <input type="text" name="buscar" placeholder="Buscar por matrícula o nombre..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                    <?php if(!empty($busqueda)): ?>
                        <a href="creacion_reportes.php" class="btn-search" style="text-decoration:none;"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <div class="table-responsive" style="margin-bottom: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Alumno</th>
                                <th>Falta Cometida</th>
                                <th>Pts</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimos_reportes)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 3rem;">No se encontraron reportes recientes.</td></tr>
                            <?php else: 
                                $fecha_actual_agrupacion = '';

                                foreach ($ultimos_reportes as $reporte): 
                                    $fecha_fila_pura = date('Y-m-d', strtotime($reporte['fecha_hora']));
                                    $hora_formateada = date('h:i A', strtotime($reporte['fecha_hora']));
                                    
                                    if ($fecha_actual_agrupacion !== $fecha_fila_pura) {
                                        $fecha_actual_agrupacion = $fecha_fila_pura;
                                        $fecha_mostrar = date('d/m/Y', strtotime($fecha_fila_pura));
                                        
                                        echo '<tr class="date-divider">';
                                        echo '<td colspan="5"><i class="far fa-calendar-alt" style="margin-right: 8px;"></i> Reportes del día: ' . $fecha_mostrar . '</td>';
                                        echo '</tr>';
                                    }
                            ?>
                            <tr>
                                <td style="font-size: 0.75rem; color: var(--muted);"><strong><?php echo $hora_formateada; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($reporte['apellido_paterno'] . ' ' . $reporte['nombre']); ?></strong><br>
                                    <span style="font-size: 0.7rem; color: var(--muted);"><?php echo htmlspecialchars($reporte['matricula']); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(mb_strimwidth($reporte['descripcion_falta'], 0, 35, '...')); ?>
                                    <?php if($reporte['sancion_acumulativa_activada'] == 1): ?>
                                        <br><span style="font-size:0.65rem; color:#b91c1c; font-weight:bold;"><i class="fas fa-exclamation-circle"></i> Sanción Activada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($reporte['puntos_descontados'])): ?>
                                        <span class="status-badge-table status-falta">-<?php echo $reporte['puntos_descontados']; ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--muted); font-size: 0.75rem;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Botón Editar -->
                                        <a href="editar_reporte.php?id=<?php echo $reporte['id_reporte']; ?>" class="btn-action btn-edit" title="Editar reporte">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Botón Eliminar (Abre Modal) -->
                                        <button type="button" class="btn-action btn-delete" title="Eliminar reporte" onclick="abrirModalEliminar(<?php echo $reporte['id_reporte']; ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <!-- MODAL DE CONFIRMACIÓN DE ELIMINACIÓN -->
        <div class="modal-overlay" id="modalEliminarReporte" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 43, 30, 0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2000;">
            <div class="modal" style="background: #ffffff; padding: 2.5rem; border-radius: var(--radius-lg); width: 90%; max-width: 450px; box-shadow: var(--shadow-lg);">
                <h3 style="margin-bottom: 0.5rem; font-size: 1.3rem;"><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirmar Eliminación</h3>
                <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 1.5rem;">Para eliminar este reporte y restaurar los puntos, ingresa tu contraseña de acceso.</p>
                
                <form action="procesos/eliminar_reporte.php" method="POST">
                    <input type="hidden" name="id_reporte" id="delete_id_reporte" value="">
                    
                    <div class="form-group">
                        <label for="password_confirm" style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 0.5rem;"><i class="fas fa-lock"></i> Contraseña de Prefecto</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Ingresa tu contraseña..." required autofocus style="width: 100%; padding: 0.85rem; border: 1px solid var(--line); border-radius: var(--radius-sm);">
                    </div>

                    <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                        <button type="button" class="btn btn-cancel" onclick="cerrarModalEliminar()" style="padding: 0.75rem 1.5rem; border: 1px solid var(--line); border-radius: var(--radius-sm); cursor: pointer;">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; background: #ef4444; color: white; border: none; border-radius: var(--radius-sm); cursor: pointer;">Eliminar Reporte</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="../Prefectura/procesos/dashboard_prefecturas.js"></script>

    <!-- SCRIPTS PARA LA LÓGICA DE LA VISTA -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // LÓGICA DE BÚSQUEDA DE ALUMNO
            const btnBuscar = document.getElementById('btnBuscarAlumno');
            const inputMatricula = document.getElementById('matricula');
            const studentProfile = document.getElementById('studentProfile');
            const studentError = document.getElementById('studentError');
            const btnSubmit = document.getElementById('btnSubmit');
            const inputFalta = document.getElementById('id_tipo_falta');
            
            function validarFormulario() {
                if(studentProfile.classList.contains('active') && inputFalta.value !== "") {
                    btnSubmit.disabled = false;
                } else {
                    btnSubmit.disabled = true;
                }
            }

            function buscarAlumno() {
                let matricula = inputMatricula.value.trim();
                if (matricula.length > 0) {
                    btnBuscar.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; 
                    
                    fetch('procesos/buscar_alumno.php?matricula=' + matricula)
                        .then(response => response.json())
                        .then(data => {
                            btnBuscar.innerHTML = '<i class="fas fa-search"></i>';
                            studentError.style.display = 'none';
                            
                            if (data.success) {
                                document.getElementById('spNombre').textContent = data.nombre;
                                document.getElementById('spGrupo').textContent = data.grado_grupo || 'Sin asignar';
                                
                                let ptsBadge = document.getElementById('spPuntos');
                                ptsBadge.textContent = data.puntos_restantes;
                                
                                if(data.puntos_restantes >= 80) {
                                    ptsBadge.className = 'pts-badge good';
                                } else {
                                    ptsBadge.className = 'pts-badge';
                                }

                                studentProfile.classList.add('active');
                            } else {
                                studentProfile.classList.remove('active');
                                studentError.style.display = 'block';
                            }
                            validarFormulario();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            btnBuscar.innerHTML = '<i class="fas fa-search"></i>';
                        });
                }
            }

            btnBuscar.addEventListener('click', buscarAlumno);
            inputMatricula.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarAlumno();
                }
            });

            // LÓGICA DEL CUSTOM SELECT (BUSCADOR DE FALTAS)
            const trigger = document.getElementById('customSelectTrigger');
            const optionsContainer = document.getElementById('customSelectOptions');
            const searchInput = document.getElementById('searchFalta');
            const optionsList = document.querySelectorAll('.option-item');
            const triggerText = document.getElementById('triggerText');

            trigger.addEventListener('click', function() {
                optionsContainer.classList.toggle('open');
                if(optionsContainer.classList.contains('open')) {
                    searchInput.focus();
                }
            });

            searchInput.addEventListener('input', function() {
                let filter = this.value.toLowerCase();
                optionsList.forEach(option => {
                    let text = option.innerText.toLowerCase();
                    if(text.includes(filter)) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });

            optionsList.forEach(option => {
                option.addEventListener('click', function() {
                    let val = this.getAttribute('data-value');
                    let textContent = this.innerText;

                    triggerText.textContent = textContent;
                    trigger.classList.add('selected');
                    inputFalta.value = val;
                    
                    optionsContainer.classList.remove('open');
                    searchInput.value = "";
                    optionsList.forEach(opt => opt.style.display = 'block');
                    
                    validarFormulario();
                });
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.custom-select-container')) {
                    optionsContainer.classList.remove('open');
                }
            });
        });

        // LÓGICA PARA EL MODAL DE ELIMINAR REPORTE
        function abrirModalEliminar(idReporte) {
            document.getElementById('delete_id_reporte').value = idReporte;
            document.getElementById('password_confirm').value = '';
            document.getElementById('modalEliminarReporte').style.display = 'flex';
        }

        function cerrarModalEliminar() {
            document.getElementById('modalEliminarReporte').style.display = 'none';
        }
    </script>
</body>
</html>