<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}
$id_alumno = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_alumno < 1) {
    header("Location: gestion_usuarios.php");
    exit();
}

try {
    $st = $pdo->prepare("
        SELECT a.*, g.id_grupo, g.semestre AS grupo_semestre, g.nombre_grupo, g.turno AS grupo_turno
        FROM ALUMNOS a
        LEFT JOIN GRUPO_ALUMNO ga ON ga.id_alumno = a.id_alumno AND ga.fecha_baja IS NULL
        LEFT JOIN GRUPOS g ON g.id_grupo = ga.id_grupo
        WHERE a.id_alumno = ?
        LIMIT 1
    ");
    $st->execute([$id_alumno]);
    $al = $st->fetch();
    if (!$al) {
        header("Location: gestion_usuarios.php?tipo=alumnos&error=" . urlencode("El alumno no existe."));
        exit();
    }

    $st = $pdo->prepare("SELECT * FROM TUTOR_ALUMNO WHERE id_alumno = ?");
    $st->execute([$id_alumno]);
    $tutor = $st->fetch();

    $st = $pdo->prepare("SELECT * FROM DOMICILIO_ALUMNO WHERE id_alumno = ?");
    $st->execute([$id_alumno]);
    $dom = $st->fetch();

    $st = $pdo->prepare("
        SELECT SUM(estatus = 'Puntual') AS puntuales,
               SUM(estatus IN ('Retardo', 'Retardo Justificado')) AS retardos,
               SUM(estatus IN ('Falta', 'Falta Justificada')) AS faltas
        FROM ASISTENCIAS WHERE id_alumno = ?
    ");
    $st->execute([$id_alumno]);
    $asis = $st->fetch();

    $st = $pdo->prepare("SELECT fecha_hora_escaneo, fecha_hora_salida, estatus FROM ASISTENCIAS WHERE id_alumno = ? ORDER BY fecha_hora_escaneo DESC LIMIT 10");
    $st->execute([$id_alumno]);
    $ultimas = $st->fetchAll();

    $st = $pdo->prepare("
        SELECT r.fecha_hora, r.puntos_descontados, r.observaciones, tf.numero_reglamento, tf.descripcion_falta
        FROM REPORTES r INNER JOIN TIPOS_FALTA tf ON tf.id_tipo_falta = r.id_tipo_falta
        WHERE r.id_alumno = ? ORDER BY r.fecha_hora DESC
    ");
    $st->execute([$id_alumno]);
    $reportes = $st->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar el alumno: " . $e->getMessage());
}

function v($valor) { return ($valor === null || $valor === '') ? '<span style="color:#94a3b8">Sin registro</span>' : htmlspecialchars($valor); }
$nombre_completo = trim($al['nombre'] . ' ' . $al['apellido_paterno'] . ' ' . $al['apellido_materno']);
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
$volver = $al['id_grupo'] ? 'ver_grupo.php?id=' . (int)$al['id_grupo'] : 'gestion_usuarios.php?tipo=alumnos';
$pts = (int)$al['puntos_conducta'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title><?php echo htmlspecialchars($nombre_completo); ?> · COBAEP Plantel 27</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_admin.css">
    <link rel="stylesheet" href="style_sidebar.css">
    <style>
        .btn-back{display:inline-flex;align-items:center;gap:8px;color:#6d7f77;text-decoration:none;font-weight:600;font-size:.9rem;margin-bottom:10px;}
        .btn-back i{background:#f1f5f9;width:32px;height:32px;display:grid;place-items:center;border-radius:50%;}
        .pa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;}
        .pa-card{background:#fff;border:1px solid #e5ece8;border-radius:16px;padding:22px;box-shadow:0 4px 14px rgba(6,63,43,.04);}
        .pa-card h2{font-size:1rem;margin:0 0 14px;color:#10231a;display:flex;align-items:center;gap:10px;}
        .pa-card h2 i{color:#16a05d;}
        .pa-head{display:flex;align-items:center;gap:18px;flex-wrap:wrap;}
        .pa-avatar{width:72px;height:72px;border-radius:50%;background:#e9f8f0;color:#0f7a45;display:grid;place-items:center;font-size:1.6rem;font-weight:700;overflow:hidden;}
        .pa-avatar img{width:100%;height:100%;object-fit:cover;}
        .pa-head h3{margin:0;font-size:1.25rem;color:#10231a;}
        .pa-head p{margin:4px 0 0;color:#6d7f77;font-size:.88rem;}
        .pa-dl{display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:.88rem;margin:0;}
        .pa-dl dt{color:#6d7f77;font-weight:500;}
        .pa-dl dd{margin:0;color:#1e293b;font-weight:600;}
        .pa-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;}
        .pa-stats div{background:#f6faf8;border-radius:12px;padding:12px 6px;}
        .pa-stats strong{display:block;font-size:1.35rem;color:#10231a;}
        .pa-stats span{font-size:.72rem;color:#6d7f77;}
        .pa-table{width:100%;border-collapse:collapse;font-size:.85rem;}
        .pa-table th{text-align:left;color:#475569;font-weight:600;padding:8px;border-bottom:1px solid #e5ece8;}
        .pa-table td{padding:8px;border-bottom:1px solid #f0f4f2;}
        .pa-empty{color:#6d7f77;font-size:.88rem;margin:0;}
        .pa-actions{display:flex;gap:10px;flex-wrap:wrap;margin-left:auto;}
        .pa-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;font-weight:600;font-size:.85rem;text-decoration:none;}
        .pa-btn.edit{background:#fff6e6;color:#b45309;} .pa-btn.list{background:#e9f8f0;color:#0f7a45;}
        .pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600;}
        .pill.ok{background:#e9f8f0;color:#0f7a45;} .pill.warn{background:#fff6e6;color:#b45309;} .pill.bad{background:#fef2f2;color:#b91c1c;}
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú"><i class="fas fa-bars"></i></button>
                <div>
                    <a href="<?php echo $volver; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Volver</a>
                    <h1>Expediente del alumno</h1>
                </div>
            </div>
            <div class="topbar-right">
                <div class="profile">
                    <div class="profile-avatar"><i class="fas fa-user"></i></div>
                    <div class="profile-info"><strong><?php echo $nombre_usuario; ?></strong><span>Administrador</span></div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="pa-card" style="margin-bottom:20px;">
                <div class="pa-head">
                    <div class="pa-avatar">
                        <?php if (!empty($al['fotografia']) && file_exists('../' . $al['fotografia'])): ?>
                            <img src="../<?php echo htmlspecialchars($al['fotografia']); ?>" alt="Foto">
                        <?php else: echo htmlspecialchars(mb_substr($al['nombre'], 0, 1) . mb_substr($al['apellido_paterno'], 0, 1)); endif; ?>
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($nombre_completo); ?></h3>
                        <p><?php echo htmlspecialchars($al['matricula']); ?> ·
                           <?php echo $al['id_grupo'] ? htmlspecialchars($al['grupo_semestre'] . '° "' . $al['nombre_grupo'] . '" · ' . $al['grupo_turno']) : 'Sin grupo asignado'; ?></p>
                    </div>
                    <div class="pa-actions">
                        <?php if ($al['id_grupo']): ?><a class="pa-btn list" href="reportes.php?id_grupo=<?php echo (int)$al['id_grupo']; ?>"><i class="fas fa-list-ol"></i> Lista del grupo</a><?php endif; ?>
                        <a class="pa-btn edit" href="editar_usuario.php?id=<?php echo $id_alumno; ?>&tipo=alumnos"><i class="fas fa-pen"></i> Editar</a>
                    </div>
                </div>
            </div>

            <div class="pa-grid">
                <div class="pa-card">
                    <h2><i class="fas fa-id-card"></i> Datos escolares</h2>
                    <dl class="pa-dl">
                        <dt>Estatus</dt><dd><?php echo v($al['estatus']); ?></dd>
                        <dt>Turno</dt><dd><?php echo v($al['turno']); ?></dd>
                        <dt>Semestre</dt><dd><?php echo v($al['semestre'] ?? $al['grupo_semestre']); ?></dd>
                        <dt>Capacitación</dt><dd><?php echo v($al['capacitacion']); ?></dd>
                        <dt>Periodo de ingreso</dt><dd><?php echo v($al['periodo_ingreso']); ?></dd>
                        <dt>Código QR</dt><dd><?php echo v($al['codigo_qr']); ?></dd>
                    </dl>
                </div>
                <div class="pa-card">
                    <h2><i class="fas fa-user"></i> Datos personales</h2>
                    <dl class="pa-dl">
                        <dt>Sexo</dt><dd><?php echo v($al['sexo'] === 'F' ? 'Femenino' : ($al['sexo'] === 'M' ? 'Masculino' : null)); ?></dd>
                        <dt>Nacimiento</dt><dd><?php echo v($al['fecha_nacimiento'] ? date('d/m/Y', strtotime($al['fecha_nacimiento'])) . ($al['edad'] ? ' (' . (int)$al['edad'] . ' años)' : '') : null); ?></dd>
                        <dt>Teléfono</dt><dd><?php echo v($al['telefono']); ?></dd>
                        <dt>Correo personal</dt><dd><?php echo v($al['correo_personal']); ?></dd>
                        <dt>Correo institucional</dt><dd><?php echo v($al['correo_institucional']); ?></dd>
                        <dt>Domicilio</dt><dd><?php echo v($dom ? trim(($dom['calle'] ?? '') . ' ' . ($dom['num_exterior'] ?? '') . ', ' . ($dom['colonia'] ?? '') . ', ' . ($dom['municipio'] ?? ''), ' ,') : null); ?></dd>
                    </dl>
                </div>
                <div class="pa-card">
                    <h2><i class="fas fa-people-roof"></i> Tutor</h2>
                    <dl class="pa-dl">
                        <dt>Nombre</dt><dd><?php echo v($tutor['nombre_tutor'] ?? null); ?></dd>
                        <dt>Teléfono 1</dt><dd><?php echo v($tutor['telefono_1'] ?? null); ?></dd>
                        <dt>Teléfono 2</dt><dd><?php echo v($tutor['telefono_2'] ?? null); ?></dd>
                    </dl>
                </div>
                <div class="pa-card">
                    <h2><i class="fas fa-chart-pie"></i> Asistencia y conducta</h2>
                    <div class="pa-stats">
                        <div><strong><?php echo (int)$asis['puntuales']; ?></strong><span>Puntuales</span></div>
                        <div><strong><?php echo (int)$asis['retardos']; ?></strong><span>Retardos</span></div>
                        <div><strong><?php echo (int)$asis['faltas']; ?></strong><span>Faltas</span></div>
                        <div><strong><span class="pill <?php echo $pts >= 75 ? 'ok' : ($pts >= 60 ? 'warn' : 'bad'); ?>"><?php echo $pts; ?></span></strong><span>Puntos conducta</span></div>
                    </div>
                </div>
            </div>

            <div class="pa-grid" style="margin-top:20px;">
                <div class="pa-card">
                    <h2><i class="fas fa-clock-rotate-left"></i> Últimas asistencias</h2>
                    <?php if (empty($ultimas)): ?>
                        <p class="pa-empty">Sin registros de asistencia.</p>
                    <?php else: ?>
                        <table class="pa-table">
                            <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Estatus</th></tr></thead>
                            <tbody>
                            <?php foreach ($ultimas as $u): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($u['fecha_hora_escaneo'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($u['fecha_hora_escaneo'])); ?></td>
                                    <td><?php echo $u['fecha_hora_salida'] ? date('H:i', strtotime($u['fecha_hora_salida'])) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($u['estatus']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="pa-card">
                    <h2><i class="fas fa-file-shield"></i> Reportes de conducta</h2>
                    <?php if (empty($reportes)): ?>
                        <p class="pa-empty">El alumno no tiene reportes.</p>
                    <?php else: ?>
                        <table class="pa-table">
                            <thead><tr><th>Fecha</th><th>Falta</th><th>Puntos</th></tr></thead>
                            <tbody>
                            <?php foreach ($reportes as $r): ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?php echo date('d/m/Y', strtotime($r['fecha_hora'])); ?></td>
                                    <td><strong>Art. <?php echo (int)$r['numero_reglamento']; ?></strong> · <?php echo htmlspecialchars(mb_strimwidth($r['descripcion_falta'], 0, 80, '…')); ?></td>
                                    <td>-<?php echo (int)$r['puntos_descontados']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar'), overlay = document.getElementById('sidebarOverlay'), mobileMenu = document.getElementById('mobileMenu'), collapseToggle = document.getElementById('collapseToggle'), body = document.body;
            if (localStorage.getItem('sidebarCollapsed') === 'true') body.classList.add('sidebar-collapsed');
            if (collapseToggle) collapseToggle.addEventListener('click', () => { body.classList.toggle('sidebar-collapsed'); localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed')); });
            if (mobileMenu) mobileMenu.addEventListener('click', () => { sidebar.classList.add('mobile-open'); overlay.classList.add('visible'); body.classList.add('menu-open'); });
            if (overlay) overlay.addEventListener('click', () => { sidebar.classList.remove('mobile-open'); overlay.classList.remove('visible'); body.classList.remove('menu-open'); });
        });
    </script>
</body>
</html>
