<?php
/**
 * docente/procesos/permisos.php
 * Verifica que la clase / actividad / entrega pertenezca al docente con sesión
 * iniciada, para que un docente no pueda modificar las aulas de otro.
 */

function docente_dueno_clase(PDO $pdo, int $id_clase): bool {
    $st = $pdo->prepare("SELECT 1 FROM clases_regulares WHERE id_clase = ? AND id_maestro = ?");
    $st->execute([$id_clase, (int)$_SESSION['id_usuario']]);
    return (bool)$st->fetchColumn();
}

function docente_dueno_actividad(PDO $pdo, int $id_actividad): bool {
    $st = $pdo->prepare("
        SELECT 1 FROM actividades_clase ac
        INNER JOIN clases_regulares c ON c.id_clase = ac.id_clase
        WHERE ac.id_actividad = ? AND c.id_maestro = ?
    ");
    $st->execute([$id_actividad, (int)$_SESSION['id_usuario']]);
    return (bool)$st->fetchColumn();
}

function docente_dueno_entrega(PDO $pdo, int $id_entrega): bool {
    $st = $pdo->prepare("
        SELECT 1 FROM entregas_actividad ea
        INNER JOIN actividades_clase ac ON ac.id_actividad = ea.id_actividad
        INNER JOIN clases_regulares c ON c.id_clase = ac.id_clase
        WHERE ea.id_entrega = ? AND c.id_maestro = ?
    ");
    $st->execute([$id_entrega, (int)$_SESSION['id_usuario']]);
    return (bool)$st->fetchColumn();
}

/** Corta la petición: responde JSON (para peticiones AJAX) o regresa a "Mis grupos". */
function docente_denegar(bool $json = false): void {
    $msg = 'No tienes permiso para modificar esta clase.';
    if ($json) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'status' => 'error', 'message' => $msg]);
    } else {
        header("Location: " . (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'procesos' ? '../' : '') . "mis_grupos.php?error=" . urlencode($msg));
    }
    exit();
}
