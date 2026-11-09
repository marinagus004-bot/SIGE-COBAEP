-- ============================================================
-- SIGE-COBAEP · Base de datos Sistema_SIGE_COBAEP
-- Solo estructura (sin datos)
-- Generado: 09/11/2026 12:52  ·  MySQL 8 (MAMP, puerto 8889)
-- Incluye: tablas, llaves, triggers y eventos, con las correcciones
--   de database/actualizar_bd.php (ALUMNOS.fotografia,
--   ASISTENCIAS.id_clase opcional y CLASES_REGULARIZACION.visible).
-- Importar en phpMyAdmin > Importar, o:
--   mysql -uroot -proot -P8889 < este_archivo.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `Sistema_SIGE_COBAEP` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `Sistema_SIGE_COBAEP`;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `ACTIVIDADES_CLASE`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACTIVIDADES_CLASE` (
  `id_actividad` int NOT NULL AUTO_INCREMENT,
  `id_clase` int NOT NULL,
  `titulo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `archivo_ruta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_publicacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_limite` datetime DEFAULT NULL,
  `fecha_apertura` datetime DEFAULT NULL,
  `permite_entregas` tinyint(1) NOT NULL DEFAULT '0',
  `acepta_retardo` tinyint(1) NOT NULL DEFAULT '0',
  `visible` tinyint(1) DEFAULT '1',
  `tamano_maximo` int DEFAULT '10',
  `numero_archivos` int DEFAULT '1',
  `tipo_archivo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '*',
  PRIMARY KEY (`id_actividad`),
  KEY `fk_act_clase` (`id_clase`),
  CONSTRAINT `fk_act_clase` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARES` (`id_clase`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ACTIVIDADES_REGULARIZACION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ACTIVIDADES_REGULARIZACION` (
  `id_actividad` int NOT NULL AUTO_INCREMENT,
  `id_clase` int NOT NULL,
  `id_bloque` int DEFAULT NULL,
  `titulo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `archivo_ruta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_publicacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_actividad`),
  KEY `id_clase` (`id_clase`),
  KEY `fk_actividad_bloque` (`id_bloque`),
  CONSTRAINT `actividades_regularizacion_ibfk_1` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARIZACION` (`id_clase`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_actividad_bloque` FOREIGN KEY (`id_bloque`) REFERENCES `BLOQUES_REGULARIZACION` (`id_bloque`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ADMINISTRATIVOS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ADMINISTRATIVOS` (
  `id_admin` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contrasena_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'administrativo',
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ALUMNOS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ALUMNOS` (
  `id_alumno` int NOT NULL AUTO_INCREMENT,
  `matricula` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_qr` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contrasena_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `turno` enum('Matutino','Intermedio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sexo` enum('F','M') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `edad` tinyint unsigned DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo_personal` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo_institucional` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `periodo_ingreso` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `semestre` tinyint DEFAULT NULL,
  `capacitacion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estatus` enum('Alta','Baja','Suspendido','Finalizado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Alta',
  `puntos_conducta` smallint NOT NULL DEFAULT '100',
  `estado_disciplinario` enum('Activo','Suspendido','Baja') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Activo',
  `fecha_fin_suspension` date DEFAULT NULL,
  `fotografia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_alumno`),
  UNIQUE KEY `matricula` (`matricula`),
  UNIQUE KEY `codigo_qr` (`codigo_qr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_alumnos_calcular_edad_insert` BEFORE INSERT ON `alumnos` FOR EACH ROW BEGIN
  IF NEW.fecha_nacimiento IS NOT NULL THEN
    SET NEW.edad = TIMESTAMPDIFF(YEAR, NEW.fecha_nacimiento, CURDATE());
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_alumnos_calcular_edad_update` BEFORE UPDATE ON `alumnos` FOR EACH ROW BEGIN
  IF NEW.fecha_nacimiento IS NOT NULL THEN
    SET NEW.edad = TIMESTAMPDIFF(YEAR, NEW.fecha_nacimiento, CURDATE());
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `ASISTENCIAS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASISTENCIAS` (
  `id_asistencia` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_clase` int DEFAULT NULL,
  `fecha_hora_escaneo` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_hora_salida` datetime DEFAULT NULL,
  `tipo_registro` enum('Entrada','Salida') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estatus` enum('Puntual','Retardo','Falta','Retardo Justificado','Falta Justificada','Suspendido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grado` tinyint DEFAULT NULL,
  `grupo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turno` enum('Matutino','Intermedio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_admin` int DEFAULT NULL,
  `id_prefecto` int DEFAULT NULL,
  `motivo_justificacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_asistencia`),
  KEY `fk_asist_alumno` (`id_alumno`),
  KEY `fk_asist_admin` (`id_admin`),
  KEY `fk_asist_prefecto` (`id_prefecto`),
  CONSTRAINT `fk_asist_admin` FOREIGN KEY (`id_admin`) REFERENCES `ADMINISTRATIVOS` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_asist_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asist_prefecto` FOREIGN KEY (`id_prefecto`) REFERENCES `PREFECTOS` (`id_prefecto`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ASISTENCIAS_CLASES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ASISTENCIAS_CLASES` (
  `id_asistencia` int NOT NULL AUTO_INCREMENT,
  `id_clase` int NOT NULL,
  `id_alumno` int NOT NULL,
  `fecha` date NOT NULL,
  `estatus` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_asistencia`),
  UNIQUE KEY `idx_asistencia_unica` (`id_clase`,`id_alumno`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `BLOQUES_REGULARIZACION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BLOQUES_REGULARIZACION` (
  `id_bloque` int NOT NULL AUTO_INCREMENT,
  `id_clase` int NOT NULL,
  `titulo_bloque` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_bloque`),
  KEY `id_clase` (`id_clase`),
  CONSTRAINT `bloques_regularizacion_ibfk_1` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARIZACION` (`id_clase`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `CLASE_ALUMNO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CLASE_ALUMNO` (
  `id_clase_alumno` int NOT NULL AUTO_INCREMENT,
  `id_clase` int NOT NULL,
  `id_alumno` int NOT NULL,
  `id_asignador` int NOT NULL,
  `tipo_asignador` enum('admin','maestro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_asignacion` date NOT NULL DEFAULT (curdate()),
  `calificacion_previa` decimal(5,2) DEFAULT NULL,
  `calificacion_final` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_clase_alumno`),
  UNIQUE KEY `uq_clase_alumno` (`id_clase`,`id_alumno`),
  KEY `fk_ca_alumno` (`id_alumno`),
  CONSTRAINT `fk_ca_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ca_clase` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARIZACION` (`id_clase`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `CLASE_REGULAR_ALUMNO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CLASE_REGULAR_ALUMNO` (
  `id_cra` int NOT NULL AUTO_INCREMENT,
  `id_clase` int NOT NULL,
  `id_alumno` int NOT NULL,
  `acceso` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_cra`),
  KEY `fk_cra_clase` (`id_clase`),
  KEY `fk_cra_alumno` (`id_alumno`),
  CONSTRAINT `fk_cra_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE,
  CONSTRAINT `fk_cra_clase` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARES` (`id_clase`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `CLASES_REGULARES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CLASES_REGULARES` (
  `id_clase` int NOT NULL AUTO_INCREMENT,
  `id_materia` int NOT NULL,
  `id_maestro` int DEFAULT NULL,
  `enlace_clase_virtual` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estatus` enum('Activa','Inactiva') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Activa',
  `id_grupo` int DEFAULT NULL,
  `dias_clase` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  PRIMARY KEY (`id_clase`),
  KEY `fk_cr_materia` (`id_materia`),
  KEY `fk_cr_maestro` (`id_maestro`),
  CONSTRAINT `fk_cr_maestro` FOREIGN KEY (`id_maestro`) REFERENCES `MAESTROS` (`id_maestro`) ON DELETE SET NULL,
  CONSTRAINT `fk_cr_materia` FOREIGN KEY (`id_materia`) REFERENCES `MATERIAS` (`id_materia`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `CLASES_REGULARIZACION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `CLASES_REGULARIZACION` (
  `id_clase` int NOT NULL AUTO_INCREMENT,
  `nombre_materia` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `id_maestro` int DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estatus` enum('Activa','Finalizada','Cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Activa',
  `imagen_portada` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permitir_entregas` tinyint(1) NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_clase`),
  KEY `fk_clase_maestro` (`id_maestro`),
  CONSTRAINT `fk_clase_maestro` FOREIGN KEY (`id_maestro`) REFERENCES `MAESTROS` (`id_maestro`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `DOMICILIO_ALUMNO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DOMICILIO_ALUMNO` (
  `id_domicilio` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `calle` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_exterior` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_interior` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colonia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipio` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_postal` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_domicilio`),
  UNIQUE KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `fk_dom_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ENTREGAS_ACTIVIDAD`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ENTREGAS_ACTIVIDAD` (
  `id_entrega` int NOT NULL AUTO_INCREMENT,
  `id_actividad` int NOT NULL,
  `id_alumno` int NOT NULL,
  `archivo_ruta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_entrega` datetime NOT NULL,
  `estatus_entrega` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'A Tiempo',
  `retroalimentacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_entrega`),
  KEY `id_actividad` (`id_actividad`),
  KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `entregas_actividad_ibfk_1` FOREIGN KEY (`id_actividad`) REFERENCES `ACTIVIDADES_CLASE` (`id_actividad`) ON DELETE CASCADE,
  CONSTRAINT `entregas_actividad_ibfk_2` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ENTREGAS_ALUMNO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ENTREGAS_ALUMNO` (
  `id_entrega` int NOT NULL AUTO_INCREMENT,
  `id_actividad` int NOT NULL,
  `id_alumno` int NOT NULL,
  `id_clase` int NOT NULL,
  `archivo_ruta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_entrega` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `calificacion` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_entrega`),
  KEY `fk_ea_actividad` (`id_actividad`),
  KEY `fk_ea_alumno` (`id_alumno`),
  KEY `fk_ea_clase` (`id_clase`),
  CONSTRAINT `fk_ea_actividad` FOREIGN KEY (`id_actividad`) REFERENCES `ACTIVIDADES_CLASE` (`id_actividad`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_clase` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARES` (`id_clase`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ENTREGAS_REGULARIZACION`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ENTREGAS_REGULARIZACION` (
  `id_entrega` int NOT NULL AUTO_INCREMENT,
  `id_clase` int NOT NULL,
  `id_alumno` int NOT NULL,
  `archivo_ruta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_entrega` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `calificacion` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_entrega`),
  KEY `id_clase` (`id_clase`),
  KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `entregas_regularizacion_ibfk_1` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARIZACION` (`id_clase`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `entregas_regularizacion_ibfk_2` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `GRUPO_ALUMNO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GRUPO_ALUMNO` (
  `id_ga` int NOT NULL AUTO_INCREMENT,
  `id_grupo` int NOT NULL,
  `id_alumno` int NOT NULL,
  `fecha_asignacion` date NOT NULL DEFAULT (curdate()),
  `fecha_baja` date DEFAULT NULL,
  PRIMARY KEY (`id_ga`),
  UNIQUE KEY `uq_grupo_alumno` (`id_grupo`,`id_alumno`),
  KEY `fk_ga_alumno` (`id_alumno`),
  CONSTRAINT `fk_ga_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ga_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `GRUPOS` (`id_grupo`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `GRUPO_MAESTRO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GRUPO_MAESTRO` (
  `id_gm` int NOT NULL AUTO_INCREMENT,
  `id_grupo` int NOT NULL,
  `id_maestro` int NOT NULL,
  `fecha_asignacion` date NOT NULL DEFAULT (curdate()),
  PRIMARY KEY (`id_gm`),
  UNIQUE KEY `uq_grupo_maestro` (`id_grupo`,`id_maestro`),
  KEY `fk_gm_maestro` (`id_maestro`),
  CONSTRAINT `fk_gm_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `GRUPOS` (`id_grupo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_gm_maestro` FOREIGN KEY (`id_maestro`) REFERENCES `MAESTROS` (`id_maestro`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `GRUPOS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GRUPOS` (
  `id_grupo` int NOT NULL AUTO_INCREMENT,
  `nombre_grupo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `semestre` tinyint NOT NULL,
  `capacitacion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turno` enum('Matutino','Intermedio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_admin_responsable` int DEFAULT NULL,
  `estatus` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_grupo`),
  KEY `fk_grupo_admin` (`id_admin_responsable`),
  CONSTRAINT `fk_grupo_admin` FOREIGN KEY (`id_admin_responsable`) REFERENCES `ADMINISTRATIVOS` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `HISTORIAL_SANCIONES_ACUM`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HISTORIAL_SANCIONES_ACUM` (
  `id_historial` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_reporte_disparador` int NOT NULL,
  `id_sancion_acum` int NOT NULL,
  `fecha_activacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `carta_firmada` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_firma` date DEFAULT NULL,
  `firmado_por` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_historial`),
  KEY `fk_hist_alumno` (`id_alumno`),
  KEY `fk_hist_reporte` (`id_reporte_disparador`),
  KEY `fk_hist_sancion` (`id_sancion_acum`),
  CONSTRAINT `fk_hist_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hist_reporte` FOREIGN KEY (`id_reporte_disparador`) REFERENCES `REPORTES` (`id_reporte`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hist_sancion` FOREIGN KEY (`id_sancion_acum`) REFERENCES `SANCIONES_ACUMULATIVAS` (`id_sancion_acum`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `HORARIOS_CLASES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HORARIOS_CLASES` (
  `id_horario` int NOT NULL AUTO_INCREMENT,
  `id_clase` int NOT NULL,
  `dia_semana` tinyint NOT NULL COMMENT '1=Lunes, 2=Martes, 3=Miercoles, 4=Jueves, 5=Viernes',
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  PRIMARY KEY (`id_horario`),
  KEY `id_clase` (`id_clase`),
  CONSTRAINT `horarios_clases_ibfk_1` FOREIGN KEY (`id_clase`) REFERENCES `CLASES_REGULARES` (`id_clase`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `HORARIOS_MAESTRO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HORARIOS_MAESTRO` (
  `id_horario` int NOT NULL AUTO_INCREMENT,
  `id_maestro` int NOT NULL,
  `id_grupo` int NOT NULL,
  `nombre_archivo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_pdf` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_subida` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ciclo` enum('Ago-Ene','Feb-Jul') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `anio` year NOT NULL,
  PRIMARY KEY (`id_horario`),
  KEY `fk_hor_maestro` (`id_maestro`),
  KEY `fk_hor_grupo` (`id_grupo`),
  CONSTRAINT `fk_hor_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `GRUPOS` (`id_grupo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hor_maestro` FOREIGN KEY (`id_maestro`) REFERENCES `MAESTROS` (`id_maestro`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `HORARIOS_TURNO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `HORARIOS_TURNO` (
  `turno` enum('Matutino','Intermedio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hora_entrada` time NOT NULL,
  `fin_tolerancia` time NOT NULL,
  `hora_corte` time NOT NULL,
  PRIMARY KEY (`turno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `MAESTROS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MAESTROS` (
  `id_maestro` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_perfil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo_academico` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biografia` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `usuario` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contrasena_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_admin_registro` int DEFAULT NULL,
  `horario_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_maestro`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `fk_maestro_admin` (`id_admin_registro`),
  CONSTRAINT `fk_maestro_admin` FOREIGN KEY (`id_admin_registro`) REFERENCES `ADMINISTRATIVOS` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `MATERIAS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MATERIAS` (
  `id_materia` int NOT NULL AUTO_INCREMENT,
  `nombre_materia` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_portada` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_materia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `NOTIFICACIONES_WHATSAPP`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `NOTIFICACIONES_WHATSAPP` (
  `id_notificacion` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `tipo` enum('ingreso','salida','inasistencia','reporte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono_destino` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estatus` enum('pendiente','enviado','fallido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `intentos` tinyint DEFAULT '0',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_envio` datetime DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `notificaciones_whatsapp_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `PREFECTOS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `PREFECTOS` (
  `id_prefecto` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contrasena_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_admin_registro` int DEFAULT NULL,
  PRIMARY KEY (`id_prefecto`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `fk_prefecto_admin` (`id_admin_registro`),
  CONSTRAINT `fk_prefecto_admin` FOREIGN KEY (`id_admin_registro`) REFERENCES `ADMINISTRATIVOS` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `REPORTES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `REPORTES` (
  `id_reporte` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_tipo_falta` int NOT NULL,
  `id_emisor` int NOT NULL,
  `tipo_emisor` enum('admin','maestro','prefecto') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `puntos_descontados` tinyint unsigned NOT NULL DEFAULT '0',
  `puntos_acumulados_descuento` smallint NOT NULL DEFAULT '0',
  `puntos_restantes` smallint NOT NULL DEFAULT '100',
  `sancion_acumulativa_activada` tinyint(1) NOT NULL DEFAULT '0',
  `id_sancion_acum_activada` int DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_reporte`),
  KEY `fk_rep_alumno` (`id_alumno`),
  KEY `fk_rep_tipo_falta` (`id_tipo_falta`),
  KEY `fk_rep_sancion_acum` (`id_sancion_acum_activada`),
  CONSTRAINT `fk_rep_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rep_sancion_acum` FOREIGN KEY (`id_sancion_acum_activada`) REFERENCES `SANCIONES_ACUMULATIVAS` (`id_sancion_acum`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rep_tipo_falta` FOREIGN KEY (`id_tipo_falta`) REFERENCES `TIPOS_FALTA` (`id_tipo_falta`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_reporte_descuento_puntos` BEFORE INSERT ON `reportes` FOR EACH ROW BEGIN
  DECLARE v_puntos_tipo          TINYINT UNSIGNED DEFAULT 0;
  DECLARE v_acumulado_previo     SMALLINT         DEFAULT 0;
  DECLARE v_nuevo_acumulado      SMALLINT;
  DECLARE v_puntos_restantes     SMALLINT;
  DECLARE v_id_sancion_acum      INT              DEFAULT NULL;
  
  SET v_puntos_tipo = COALESCE((SELECT puntos_descuento FROM TIPOS_FALTA WHERE id_tipo_falta = NEW.id_tipo_falta LIMIT 1), 0);

  SET v_acumulado_previo = COALESCE((SELECT SUM(puntos_descontados) FROM REPORTES WHERE id_alumno = NEW.id_alumno), 0);
  SET v_nuevo_acumulado  = v_acumulado_previo + v_puntos_tipo;
  SET v_puntos_restantes = 100 - v_nuevo_acumulado;
  IF v_puntos_restantes < 0 THEN 
      SET v_puntos_restantes = 0; 
  END IF;

  SET NEW.puntos_descontados           = v_puntos_tipo;
  SET NEW.puntos_acumulados_descuento  = v_nuevo_acumulado;
  SET NEW.puntos_restantes             = v_puntos_restantes;

  UPDATE ALUMNOS SET puntos_conducta = v_puntos_restantes WHERE id_alumno = NEW.id_alumno;

  IF v_acumulado_previo < 40 AND v_nuevo_acumulado >= 40 THEN
    SET v_id_sancion_acum = (SELECT id_sancion_acum FROM SANCIONES_ACUMULATIVAS WHERE umbral_puntos_menos = 40 LIMIT 1);
  ELSEIF v_acumulado_previo < 25 AND v_nuevo_acumulado >= 25 THEN
    SET v_id_sancion_acum = (SELECT id_sancion_acum FROM SANCIONES_ACUMULATIVAS WHERE umbral_puntos_menos = 25 LIMIT 1);
  END IF;

  IF v_id_sancion_acum IS NOT NULL THEN
    SET NEW.sancion_acumulativa_activada = TRUE;
    SET NEW.id_sancion_acum_activada     = v_id_sancion_acum;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_reporte_historial_sancion` AFTER INSERT ON `reportes` FOR EACH ROW BEGIN
  IF NEW.sancion_acumulativa_activada = TRUE
     AND NEW.id_sancion_acum_activada IS NOT NULL THEN

    INSERT INTO HISTORIAL_SANCIONES_ACUM
      (id_alumno, id_reporte_disparador, id_sancion_acum,
       fecha_activacion, carta_firmada)
    VALUES
      (NEW.id_alumno, NEW.id_reporte, NEW.id_sancion_acum_activada,
       NOW(), FALSE);

  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `SANCIONES_ACUMULATIVAS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SANCIONES_ACUMULATIVAS` (
  `id_sancion_acum` int NOT NULL AUTO_INCREMENT,
  `umbral_puntos_menos` tinyint NOT NULL,
  `accion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_sancion_acum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `SESIONES_PREFECTO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SESIONES_PREFECTO` (
  `id_sesion` int NOT NULL AUTO_INCREMENT,
  `id_prefecto` int NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispositivo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_expiracion` datetime NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_sesion`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_sesion_prefecto` (`id_prefecto`),
  CONSTRAINT `fk_sesion_prefecto` FOREIGN KEY (`id_prefecto`) REFERENCES `PREFECTOS` (`id_prefecto`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `SUSPENSIONES_CLASES`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SUSPENSIONES_CLASES` (
  `id_suspension` int NOT NULL AUTO_INCREMENT,
  `motivo` enum('Suspensión oficial','Consejo Técnico','Vacaciones','Fuerza Mayor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  PRIMARY KEY (`id_suspension`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `TIPOS_FALTA`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TIPOS_FALTA` (
  `id_tipo_falta` int NOT NULL AUTO_INCREMENT,
  `numero_reglamento` tinyint NOT NULL,
  `descripcion_falta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_sancion` enum('Puntos','Suspension','Citacion','Reposicion','Pago') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `puntos_descuento` tinyint unsigned DEFAULT NULL,
  `detalle_sancion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requiere_citacion_tutor` tinyint(1) NOT NULL DEFAULT '0',
  `puede_ser_definitiva` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_tipo_falta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `TUTOR_ALUMNO`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `TUTOR_ALUMNO` (
  `id_tutor` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `nombre_tutor` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono_1` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono_2` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_tutor`),
  UNIQUE KEY `id_alumno` (`id_alumno`),
  CONSTRAINT `fk_tutor_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `ALUMNOS` (`id_alumno`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `VISIBILIDAD_ACTIVIDAD`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `VISIBILIDAD_ACTIVIDAD` (
  `id_actividad` int NOT NULL,
  `id_alumno` int NOT NULL,
  `visible` tinyint(1) DEFAULT '1',
  `fecha_limite_excepcion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_actividad`,`id_alumno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50106 SET @save_time_zone= @@TIME_ZONE */ ;
/*!50106 DROP EVENT IF EXISTS `evt_actualizar_edades_diario` */;
DELIMITER ;;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;;
/*!50003 SET character_set_client  = utf8mb4 */ ;;
/*!50003 SET character_set_results = utf8mb4 */ ;;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;;
/*!50003 SET @saved_time_zone      = @@time_zone */ ;;
/*!50003 SET time_zone             = '+00:00' */ ;;
/*!50106 CREATE*/ /*!50117 DEFINER=`root`@`localhost`*/ /*!50106 EVENT `evt_actualizar_edades_diario` ON SCHEDULE EVERY 1 DAY STARTS '2026-06-30 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
  UPDATE ALUMNOS
  SET edad = TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE())
  WHERE fecha_nacimiento IS NOT NULL;
END */ ;;
/*!50003 SET time_zone             = @saved_time_zone */ ;;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;;
/*!50003 SET character_set_client  = @saved_cs_client */ ;;
/*!50003 SET character_set_results = @saved_cs_results */ ;;
/*!50003 SET collation_connection  = @saved_col_connection */ ;;
/*!50106 DROP EVENT IF EXISTS `evt_marcar_faltas_intermedio` */;;
DELIMITER ;;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;;
/*!50003 SET character_set_client  = utf8mb4 */ ;;
/*!50003 SET character_set_results = utf8mb4 */ ;;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;;
/*!50003 SET @saved_time_zone      = @@time_zone */ ;;
/*!50003 SET time_zone             = '+00:00' */ ;;
/*!50106 CREATE*/ /*!50117 DEFINER=`root`@`localhost`*/ /*!50106 EVENT `evt_marcar_faltas_intermedio` ON SCHEDULE EVERY 1 DAY STARTS '2026-09-08 11:00:00' ON COMPLETION PRESERVE ENABLE DO BEGIN
  IF NOT EXISTS (SELECT 1 FROM SUSPENSIONES_CLASES WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin) THEN
      INSERT INTO ASISTENCIAS (id_alumno, fecha_hora_escaneo, tipo_registro, estatus)
      SELECT al.id_alumno, NOW(), 'Entrada', 
             IF(al.estado_disciplinario = 'Suspendido', 'Suspendido', 'Falta')
      FROM ALUMNOS al
      WHERE al.estatus = 'Alta' 
        AND al.estado_disciplinario IN ('Activo', 'Suspendido')
        AND al.turno = 'Intermedio'
        AND al.id_alumno NOT IN (
            SELECT asi.id_alumno FROM ASISTENCIAS asi WHERE DATE(asi.fecha_hora_escaneo) = CURDATE()
        );
  END IF;
END */ ;;
/*!50003 SET time_zone             = @saved_time_zone */ ;;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;;
/*!50003 SET character_set_client  = @saved_cs_client */ ;;
/*!50003 SET character_set_results = @saved_cs_results */ ;;
/*!50003 SET collation_connection  = @saved_col_connection */ ;;
/*!50106 DROP EVENT IF EXISTS `evt_marcar_faltas_matutino` */;;
DELIMITER ;;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;;
/*!50003 SET character_set_client  = utf8mb4 */ ;;
/*!50003 SET character_set_results = utf8mb4 */ ;;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;;
/*!50003 SET @saved_time_zone      = @@time_zone */ ;;
/*!50003 SET time_zone             = '+00:00' */ ;;
/*!50106 CREATE*/ /*!50117 DEFINER=`root`@`localhost`*/ /*!50106 EVENT `evt_marcar_faltas_matutino` ON SCHEDULE EVERY 1 DAY STARTS '2026-09-08 07:30:00' ON COMPLETION PRESERVE ENABLE DO BEGIN
  IF NOT EXISTS (SELECT 1 FROM SUSPENSIONES_CLASES WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin) THEN
      INSERT INTO ASISTENCIAS (id_alumno, fecha_hora_escaneo, tipo_registro, estatus)
      SELECT al.id_alumno, NOW(), 'Entrada', 
             IF(al.estado_disciplinario = 'Suspendido', 'Suspendido', 'Falta')
      FROM ALUMNOS al
      WHERE al.estatus = 'Alta' 
        AND al.estado_disciplinario IN ('Activo', 'Suspendido')
        AND al.turno = 'Matutino'
        AND al.id_alumno NOT IN (
            SELECT asi.id_alumno FROM ASISTENCIAS asi WHERE DATE(asi.fecha_hora_escaneo) = CURDATE()
        );
  END IF;
END */ ;;
/*!50003 SET time_zone             = @saved_time_zone */ ;;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;;
/*!50003 SET character_set_client  = @saved_cs_client */ ;;
/*!50003 SET character_set_results = @saved_cs_results */ ;;
/*!50003 SET collation_connection  = @saved_col_connection */ ;;
DELIMITER ;
/*!50106 SET TIME_ZONE= @save_time_zone */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

