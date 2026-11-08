<?php
session_start();
require_once '../config/database.php';
date_default_timezone_set('America/Mexico_City');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') { die("Acceso denegado."); }
$id_actividad = isset($_GET['id_actividad']) ? (int)$_GET['id_actividad'] : 0;
require_once __DIR__ . '/procesos/permisos.php';
if (!docente_dueno_actividad($pdo, $id_actividad)) docente_denegar();
if ($id_actividad === 0) { die("Actividad no encontrada."); }

try {
    $stmt = $pdo->prepare("
        SELECT ac.*, m.nombre_materia, g.nombre_grupo, g.semestre 
        FROM actividades_clase ac
        INNER JOIN clases_regulares c ON ac.id_clase = c.id_clase
        INNER JOIN materias m ON c.id_materia = m.id_materia
        INNER JOIN grupos g ON c.id_grupo = g.id_grupo
        WHERE ac.id_actividad = ?
    ");
    $stmt->execute([$id_actividad]);
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actividad) { die("La actividad no existe o fue eliminada."); }
} catch (PDOException $e) { die("Error de conexión a la base de datos."); }

$fecha_actual = time();
$fecha_cierre = !empty($actividad['fecha_limite']) ? strtotime($actividad['fecha_limite']) : null;
$esta_vencida = ($fecha_cierre && $fecha_actual > $fecha_cierre);
$requiere_entrega = isset($actividad['permite_entregas']) ? ($actividad['permite_entregas'] == 1) : true;
$acepta_retardo = isset($actividad['acepta_retardo']) ? ($actividad['acepta_retardo'] == 1) : false;

$bloqueado = false; $motivo_bloqueo = ""; $clase_estado = ""; $texto_estado = "";

if (!$requiere_entrega) {
    $bloqueado = true; $motivo_bloqueo = "El docente desactivó el envío de archivos."; $texto_estado = "Solo lectura"; $clase_estado = "color: #64748b;";
} else if ($esta_vencida && !$acepta_retardo) {
    $bloqueado = true; $motivo_bloqueo = "La fecha límite ha expirado."; $texto_estado = "Cerrada (Vencida)"; $clase_estado = "color: #ef4444;";
} else if ($esta_vencida && $acepta_retardo) {
    $bloqueado = false; $texto_estado = "Abierta (Retardo)"; $clase_estado = "color: #f59e0b;";
} else {
    $bloqueado = false; $texto_estado = "Pendiente de entrega"; $clase_estado = "color: #10b981;";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --bg-alumno: #f1f5f9; --ink: #0f172a; --muted: #64748b; --card-bg: #ffffff; --line: #e2e8f0; --primary: #3b82f6; }
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background: var(--bg-alumno); color: var(--ink); }
        .preview-banner { background: #f97316; color: white; text-align: center; padding: 10px; font-weight: 800; font-size: 0.85rem; position: sticky; top: 0; z-index: 100; }
        .container { padding: 20px; }
        .header-alumno { margin-bottom: 20px; }
        .card-actividad { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--line); padding: 1.5rem; }
        .titulo-actividad { font-size: 1.5rem; font-weight: 800; margin: 0 0 10px 0; color: var(--ink); }
        .meta-datos { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--line); }
        .meta-item { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 700; }
        .instrucciones { font-size: 0.95rem; line-height: 1.6; color: #334155; margin-bottom: 25px; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .seccion-entrega { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px; text-align: center; background: #f8fafc; position: relative; }
        .overlay-bloqueo { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.7); backdrop-filter: blur(3px); display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 12px; z-index: 10; }
        .btn-fake { background: #cbd5e1; color: #64748b; padding: 10px 20px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: not-allowed; }
        .btn-real { background: var(--primary); color: white; padding: 10px 20px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }
        
        /* ESTILOS DE LA NUEVA ALERTA PERSONALIZADA */
        .custom-alert-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
            display: none; align-items: center; justify-content: center; z-index: 1000;
            opacity: 0; transition: opacity 0.2s ease;
        }
        .custom-alert-overlay.show { display: flex; opacity: 1; }
        .custom-alert-box {
            background: white; border-radius: 16px; padding: 2rem; width: 90%; max-width: 380px;
            text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            transform: translateY(20px); transition: transform 0.2s ease;
        }
        .custom-alert-overlay.show .custom-alert-box { transform: translateY(0); }
        .custom-alert-icon {
            width: 60px; height: 60px; background: #fff7ed; color: #f97316;
            border-radius: 50%; display: grid; place-items: center; font-size: 1.8rem;
            margin: 0 auto 15px auto;
        }
        .custom-alert-title { font-size: 1.25rem; font-weight: 800; color: var(--ink); margin: 0 0 10px 0; }
        .custom-alert-text { font-size: 0.95rem; color: var(--muted); margin: 0 0 20px 0; line-height: 1.5; }
        .btn-alert {
            background: var(--primary); color: white; border: none; padding: 12px 25px;
            border-radius: 10px; font-weight: 700; width: 100%; cursor: pointer; transition: 0.2s;
        }
        .btn-alert:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="preview-banner"><i class="fas fa-eye"></i> MODO VISTA PREVIA DEL ALUMNO</div>
    <div class="container">
        <div class="header-alumno">
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary);"><i class="fas fa-book"></i> <?php echo htmlspecialchars($actividad['nombre_materia']); ?></span>
        </div>
        <div class="card-actividad">
            <h1 class="titulo-actividad"><?php echo htmlspecialchars($actividad['titulo']); ?></h1>
            <div class="meta-datos">
                <div class="meta-item" style="<?php echo $clase_estado; ?>"><i class="fas fa-clipboard-check"></i> <?php echo $texto_estado; ?></div>
                <?php if (!empty($actividad['fecha_limite'])): ?>
                    <div class="meta-item" style="color: var(--ink);"><i class="far fa-clock" style="color: #ef4444;"></i> Cierre: <?php echo date('d/m/Y h:i A', strtotime($actividad['fecha_limite'])); ?></div>
                <?php endif; ?>
            </div>
            <div style="font-weight: 800; font-size: 0.95rem; margin-bottom: 10px;"><i class="fas fa-align-left"></i> Instrucciones del Docente:</div>
            <div class="instrucciones"><?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?></div>
            
            <?php if (isset($actividad['archivo_adjunto']) && !empty($actividad['archivo_adjunto'])): ?>
                <div style="margin-bottom: 20px;">
                    <a href="../<?php echo htmlspecialchars($actividad['archivo_adjunto']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 10px; background: #eff6ff; border: 1px solid #bfdbfe; color: #2563eb; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                        <i class="fas fa-file-download"></i> Material Adjunto
                    </a>
                </div>
            <?php endif; ?>

            <div style="font-weight: 800; font-size: 1.05rem; margin-bottom: 15px; border-top: 1px solid var(--line); padding-top: 20px;">Tu Trabajo</div>
            <div class="seccion-entrega" style="<?php echo $bloqueado ? '' : 'border-color: var(--primary); background: #eff6ff;'; ?>">
                <?php if ($bloqueado): ?>
                    <div class="overlay-bloqueo">
                        <i class="fas fa-lock" style="font-size: 2rem; color: #ef4444; margin-bottom: 10px;"></i>
                        <span style="font-weight: 800; color: #1e293b; font-size: 1rem;"><?php echo $motivo_bloqueo; ?></span>
                    </div>
                <?php endif; ?>
                <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem; color: <?php echo $bloqueado ? '#cbd5e1' : 'var(--primary)'; ?>; margin-bottom: 10px;"></i>
                <p style="margin: 0 0 5px 0; font-size: 0.8rem; font-weight: 700; color: var(--primary);">
                  Límites: Hasta <?php echo $actividad['numero_archivos'] ?? 1; ?> archivo(s) de <?php echo $actividad['tamano_maximo'] ?? 10; ?>MB máx.
                  </p>
                 <input type="file" 
                 accept="<?php echo htmlspecialchars($actividad['tipo_archivo'] ?? '*'); ?>" 
                   disabled title="Bloqueado en Vista Previa" 
                       style="display: block; margin: 0 auto 15px auto; border: 1px solid #cbd5e1; padding: 8px; border-radius: 8px; background: white; cursor: not-allowed; opacity: 0.8; font-size: 0.85rem;"> 
                <!-- BOTÓN ACTUALIZADO PARA LLAMAR AL NUEVO MODAL EN LUGAR DE alert() -->
                <button type="button" class="<?php echo $bloqueado ? 'btn-fake' : 'btn-real'; ?>" onclick="abrirAlertaPreview()">
                    <i class="fas fa-paper-plane"></i> <?php echo $bloqueado ? 'Entrega Bloqueada' : 'Entregar Tarea'; ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ESTRUCTURA DEL NUEVO AVISO PERSONALIZADO -->
    <div class="custom-alert-overlay" id="alertaVistaPrevia">
        <div class="custom-alert-box">
            <div class="custom-alert-icon">
                <i class="fas fa-eye"></i>
            </div>
            <h4 class="custom-alert-title">¡Modo Vista Previa!</h4>
            <p class="custom-alert-text">El sistema aparenta funcionar para que veas el diseño, pero al ser una simulación no se enviará ni guardará nada en la base de datos.</p>
            <button class="btn-alert" onclick="cerrarAlertaPreview()">Entendido</button>
        </div>
    </div>

    <script>
        function abrirAlertaPreview() {
            const alerta = document.getElementById('alertaVistaPrevia');
            alerta.style.display = 'flex';
            // Pequeño retardo para permitir la transición fluida (fade in)
            setTimeout(() => { alerta.classList.add('show'); }, 10);
        }

        function cerrarAlertaPreview() {
            const alerta = document.getElementById('alertaVistaPrevia');
            alerta.classList.remove('show');
            // Espera a que termine la animación antes de ocultarlo completamente
            setTimeout(() => { alerta.style.display = 'none'; }, 200);
        }
    </script>
</body>
</html>