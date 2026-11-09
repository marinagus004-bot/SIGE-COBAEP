<?php
/**
 * database/actualizar_bd.php
 *
 * Actualiza la base de datos Sistema_SIGE_COBAEP con las tablas y columnas
 * que usa el código actual del sistema. Es seguro ejecutarlo varias veces:
 * solo crea o agrega lo que todavía no existe y nunca borra datos.
 *
 * Uso:
 *   - Navegador: http://localhost:8888/SIGE-COBAEP/database/actualizar_bd.php
 *   - Terminal:  php database/actualizar_bd.php
 */

$es_cli = (PHP_SAPI === 'cli');
if (!$es_cli && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Solo se puede ejecutar desde el equipo local.');
}

require_once __DIR__ . '/../config/database.php';

$log = [];
function registrar(string $msg) { global $log; $log[] = $msg; }

function tabla_existe(PDO $pdo, string $tabla): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND LOWER(table_name) = LOWER(?)");
    $st->execute([$tabla]);
    return (int)$st->fetchColumn() > 0;
}

function columna_existe(PDO $pdo, string $tabla, string $columna): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = LOWER(?) AND LOWER(column_name) = LOWER(?)");
    $st->execute([$tabla, $columna]);
    return (int)$st->fetchColumn() > 0;
}

function crear_tabla(PDO $pdo, string $tabla, string $sql) {
    if (tabla_existe($pdo, $tabla)) { registrar("✔ La tabla $tabla ya existe"); return; }
    $pdo->exec($sql);
    registrar("➕ Tabla $tabla creada");
}

function agregar_columna(PDO $pdo, string $tabla, string $columna, string $definicion) {
    if (columna_existe($pdo, $tabla, $columna)) { registrar("✔ $tabla.$columna ya existe"); return; }
    $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `$columna` $definicion");
    registrar("➕ Columna $tabla.$columna agregada");
}

try {
    // ---------------------------------------------------------------
    // 1. Columnas nuevas en tablas existentes
    // ---------------------------------------------------------------
    // Asistencia de entrada/salida (Prefectura) y justificantes
    agregar_columna($pdo, 'ASISTENCIAS', 'fecha_hora_salida', "DATETIME NULL DEFAULT NULL AFTER `fecha_hora_escaneo`");
    agregar_columna($pdo, 'ASISTENCIAS', 'motivo_justificacion', "TEXT NULL");

    // Actividades del aula virtual: mostrar / ocultar
    agregar_columna($pdo, 'ACTIVIDADES_CLASE', 'visible', "TINYINT(1) NOT NULL DEFAULT 1");

    // Datos de la clase al activar el aula virtual
    agregar_columna($pdo, 'CLASES_REGULARES', 'dias_clase', "VARCHAR(60) NULL DEFAULT NULL");
    agregar_columna($pdo, 'CLASES_REGULARES', 'hora_inicio', "TIME NULL DEFAULT NULL");
    agregar_columna($pdo, 'CLASES_REGULARES', 'hora_fin', "TIME NULL DEFAULT NULL");

    // Perfil del docente
    agregar_columna($pdo, 'MAESTROS', 'titulo_academico', "VARCHAR(150) NULL DEFAULT NULL");
    agregar_columna($pdo, 'MAESTROS', 'biografia', "TEXT NULL");
    agregar_columna($pdo, 'MAESTROS', 'foto_perfil', "VARCHAR(255) NULL DEFAULT NULL");

    // Fotografía del alumno (credencial virtual)
    agregar_columna($pdo, 'ALUMNOS', 'fotografia', "VARCHAR(255) NULL DEFAULT NULL");

    // Espacios de regularización: ocultar al terminar el periodo (el alumno deja de verlo)
    agregar_columna($pdo, 'CLASES_REGULARIZACION', 'visible', "TINYINT(1) NOT NULL DEFAULT 1");

    // La asistencia general (QR de Prefectura y eventos automáticos de faltas) no
    // pertenece a una clase: si id_clase es obligatorio, todos esos registros fallan.
    $st = $pdo->prepare("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ASISTENCIAS' AND COLUMN_NAME = 'id_clase'");
    $st->execute();
    if ($st->fetchColumn() === 'NO') {
        $pdo->exec("ALTER TABLE `ASISTENCIAS` MODIFY `id_clase` INT NULL DEFAULT NULL");
        registrar("➕ ASISTENCIAS.id_clase ahora es opcional");
    } else {
        registrar("✔ ASISTENCIAS.id_clase ya es opcional");
    }

    // ---------------------------------------------------------------
    // 2. Tablas nuevas
    // ---------------------------------------------------------------
    // Horario semanal de cada materia (aula virtual del docente)
    crear_tabla($pdo, 'HORARIOS_CLASES', "
        CREATE TABLE `HORARIOS_CLASES` (
          `id_horario`  INT NOT NULL AUTO_INCREMENT,
          `id_clase`    INT NOT NULL,
          `dia_semana`  TINYINT NOT NULL COMMENT '1 = Lunes ... 7 = Domingo',
          `hora_inicio` TIME NOT NULL,
          `hora_fin`    TIME NOT NULL,
          PRIMARY KEY (`id_horario`),
          KEY `fk_hc_clase` (`id_clase`),
          CONSTRAINT `fk_hc_clase` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARES` (`id_clase`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Entregas de tareas de los alumnos en el aula virtual
    crear_tabla($pdo, 'ENTREGAS_ACTIVIDAD', "
        CREATE TABLE `ENTREGAS_ACTIVIDAD` (
          `id_entrega`        INT NOT NULL AUTO_INCREMENT,
          `id_actividad`      INT NOT NULL,
          `id_alumno`         INT NOT NULL,
          `archivo_ruta`      VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `fecha_entrega`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `estatus_entrega`   VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'A Tiempo',
          `retroalimentacion` TEXT COLLATE utf8mb4_unicode_ci NULL,
          `calificacion`      DECIMAL(5,2) NULL DEFAULT NULL,
          PRIMARY KEY (`id_entrega`),
          UNIQUE KEY `uq_entrega_alumno` (`id_actividad`, `id_alumno`),
          KEY `fk_ent_alumno` (`id_alumno`),
          CONSTRAINT `fk_ent_actividad` FOREIGN KEY (`id_actividad`) REFERENCES `ACTIVIDADES_CLASE` (`id_actividad`) ON DELETE CASCADE,
          CONSTRAINT `fk_ent_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Excepciones por alumno: ocultar actividad o darle otra fecha límite
    crear_tabla($pdo, 'VISIBILIDAD_ACTIVIDAD', "
        CREATE TABLE `VISIBILIDAD_ACTIVIDAD` (
          `id_visibilidad`         INT NOT NULL AUTO_INCREMENT,
          `id_actividad`           INT NOT NULL,
          `id_alumno`              INT NOT NULL,
          `visible`                TINYINT(1) NOT NULL DEFAULT 1,
          `fecha_limite_excepcion` DATETIME NULL DEFAULT NULL,
          PRIMARY KEY (`id_visibilidad`),
          UNIQUE KEY `uq_visibilidad` (`id_actividad`, `id_alumno`),
          KEY `fk_vis_alumno` (`id_alumno`),
          CONSTRAINT `fk_vis_actividad` FOREIGN KEY (`id_actividad`) REFERENCES `ACTIVIDADES_CLASE` (`id_actividad`) ON DELETE CASCADE,
          CONSTRAINT `fk_vis_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    registrar("✅ Base de datos actualizada correctamente.");
} catch (PDOException $e) {
    registrar("❌ Error: " . $e->getMessage());
}

if ($es_cli) {
    echo implode(PHP_EOL, $log) . PHP_EOL;
} else {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Actualizar BD · SIGE-COBAEP</title>'
       . '<style>body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;color:#0f172a}li{margin:6px 0}</style></head><body>'
       . '<h2>Actualización de la base de datos</h2><ul>';
    foreach ($log as $l) echo '<li>' . htmlspecialchars($l) . '</li>';
    echo '</ul><p><a href="../index.html">Volver al sistema</a></p></body></html>';
}
