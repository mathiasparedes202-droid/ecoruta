-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ecoruta_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `id_comercio` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lng` decimal(9,6) NOT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cliente`),
  KEY `idx_cliente_comercio` (`id_comercio`,`nombre`),
  CONSTRAINT `fk_cliente_comercio` FOREIGN KEY (`id_comercio`) REFERENCES `comercios` (`id_comercio`) ON DELETE CASCADE,
  CONSTRAINT `chk_cliente_nombre` CHECK (octet_length(trim(`nombre`)) >= 2),
  CONSTRAINT `chk_cliente_telefono` CHECK (`telefono` regexp '^[+]?[0-9\\s-]{7,20}$' or `telefono` is null),
  CONSTRAINT `chk_cliente_coords` CHECK (`lat` between -27.6000 and -19.2000 and `lng` between -62.7000 and -54.2000)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,1,'Maria Rodriguez','+595 981 552244','Av. Mariscal Lopez 2233, Asuncion',-25.263700,-57.575900,'Casa blanca, frente a plaza','2026-09-04 22:55:55');
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comercios`
--

DROP TABLE IF EXISTS `comercios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comercios` (
  `id_comercio` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `ruc` varchar(20) NOT NULL,
  `direccion_origen` text NOT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `ciudad` varchar(80) NOT NULL,
  `tarifa_base` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_comercio`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  UNIQUE KEY `ruc` (`ruc`),
  KEY `idx_comercios_usuario` (`id_usuario`),
  CONSTRAINT `fk_comercio_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `chk_razon_social` CHECK (octet_length(trim(`razon_social`)) >= 3),
  CONSTRAINT `chk_tarifa_base_positiva` CHECK (`tarifa_base` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comercios`
--

LOCK TABLES `comercios` WRITE;
/*!40000 ALTER TABLE `comercios` DISABLE KEYS */;
INSERT INTO `comercios` VALUES (1,11,'Gustavo Benitez','80012345-1','UNC - FCEA',-23.407868,-57.412793,'Concepcion',12000.00,'2026-08-29 20:04:16');
/*!40000 ALTER TABLE `comercios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estados_pedido`
--

DROP TABLE IF EXISTS `estados_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados_pedido` (
  `id_estado` smallint(6) NOT NULL AUTO_INCREMENT,
  `nombre_estado` varchar(30) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_estado`),
  UNIQUE KEY `nombre_estado` (`nombre_estado`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estados_pedido`
--

LOCK TABLES `estados_pedido` WRITE;
/*!40000 ALTER TABLE `estados_pedido` DISABLE KEYS */;
INSERT INTO `estados_pedido` VALUES (1,'Pendiente','Pedido creado sin asignar'),(2,'Asignado','Asignado a un repartidor'),(3,'En Camino','En proceso de entrega'),(4,'Entregado','Entrega finalizada con confirmación'),(5,'Cancelado','Pedido cancelado o que no pudo completarse');
/*!40000 ALTER TABLE `estados_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_estados`
--

DROP TABLE IF EXISTS `historial_estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_estados` (
  `id_historial` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_pedido` bigint(20) NOT NULL,
  `id_estado_anterior` smallint(6) DEFAULT NULL,
  `id_estado_nuevo` smallint(6) NOT NULL,
  `id_usuario_cambio` int(11) NOT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historial`),
  KEY `fk_historial_estado_anterior` (`id_estado_anterior`),
  KEY `fk_historial_estado_nuevo` (`id_estado_nuevo`),
  KEY `fk_historial_usuario` (`id_usuario_cambio`),
  KEY `idx_historial_pedido` (`id_pedido`,`fecha_cambio`),
  CONSTRAINT `fk_historial_estado_anterior` FOREIGN KEY (`id_estado_anterior`) REFERENCES `estados_pedido` (`id_estado`),
  CONSTRAINT `fk_historial_estado_nuevo` FOREIGN KEY (`id_estado_nuevo`) REFERENCES `estados_pedido` (`id_estado`),
  CONSTRAINT `fk_historial_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`),
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`id_usuario_cambio`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_estados`
--

LOCK TABLES `historial_estados` WRITE;
/*!40000 ALTER TABLE `historial_estados` DISABLE KEYS */;
INSERT INTO `historial_estados` VALUES (1,9,1,1,1,'Pedido y ruta registrados en base de datos ecoruta_db','2026-08-29 20:06:13'),(2,9,1,1,7,'Entrega confirmada con QR por Carlos Mendoza','2026-08-29 20:06:24'),(3,7,1,2,7,'Estado actualizado a Asignado','2026-08-29 23:18:09'),(4,7,2,3,7,'Estado actualizado a En Camino','2026-08-29 23:18:11'),(5,6,2,3,7,'Estado actualizado a En Camino','2026-08-29 23:27:38'),(6,5,3,4,1,'Firma digital registrada a nombre de: EcoComercio Central Concepción','2026-08-31 23:04:01'),(7,6,3,4,1,'Firma digital registrada a nombre de: EcoComercio Central Concepción','2026-08-31 23:04:10'),(8,7,3,4,1,'Confirmación QR: EcoComercio Central Concepción','2026-08-31 23:04:16'),(9,5,1,2,7,'Estado actualizado por el repartidor.','2026-08-31 23:23:35'),(10,5,2,3,7,'El repartidor inició el recorrido.','2026-08-31 23:23:39'),(11,13,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-01 11:16:36'),(12,13,1,2,1,'Estado actualizado por el repartidor.','2026-09-01 11:29:41'),(13,13,2,3,1,'El repartidor inició el recorrido.','2026-09-01 11:29:43'),(14,13,3,2,1,'Estado actualizado por el repartidor.','2026-09-01 11:31:17'),(15,13,2,3,1,'El repartidor inició el recorrido.','2026-09-01 11:31:22'),(16,13,3,4,1,'Confirmación QR: ecoruta:pedido:13?pedido=13&comercio=1&app=ecoruta','2026-09-01 11:31:39'),(17,5,3,4,1,'Confirmación QR: ecoruta:pedido:13?pedido=13&comercio=1&app=ecoruta','2026-09-01 11:51:37'),(18,14,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-01 12:09:49'),(19,14,1,2,1,'Estado actualizado por el repartidor.','2026-09-01 12:10:33'),(20,14,2,3,1,'El repartidor inició el recorrido.','2026-09-01 12:10:34'),(21,14,3,4,1,'Confirmación QR: ecoruta:pedido:14?pedido=14&comercio=1&repartidor=0&app=ecoruta','2026-09-01 12:11:34'),(22,15,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-01 12:25:47'),(23,15,1,2,1,'Estado actualizado por el repartidor.','2026-09-01 12:26:08'),(24,15,2,3,1,'El repartidor inició el recorrido.','2026-09-01 12:26:19'),(25,15,3,4,1,'Confirmación QR: ecoruta:pedido:15?pedido=15&comercio=1&repartidor=0&app=ecoruta','2026-09-01 12:27:16'),(26,16,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-02 15:54:15'),(28,18,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-03 01:16:13'),(29,18,1,2,1,'Estado actualizado por el repartidor.','2026-09-03 01:16:29'),(30,18,2,3,1,'El repartidor inició el recorrido.','2026-09-03 01:16:30'),(31,19,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-03 01:28:34'),(32,18,3,4,1,'Confirmación QR: ecoruta:pedido:18?pedido=18&comercio=1&repartidor=0&app=ecoruta','2026-09-03 15:45:22'),(33,16,1,2,1,'Estado actualizado por el repartidor.','2026-09-03 15:47:48'),(34,16,2,3,1,'El repartidor inició el recorrido.','2026-09-03 15:47:49'),(35,16,3,4,1,'Confirmación QR: ecoruta:pedido:16?pedido=16&comercio=1&repartidor=0&app=ecoruta','2026-09-03 16:01:07'),(36,19,1,2,1,'Estado actualizado por el repartidor.','2026-09-03 16:05:31'),(37,19,2,3,1,'El repartidor inició el recorrido.','2026-09-03 16:05:35'),(38,19,3,4,1,'Confirmación QR: ecoruta:pedido:19?pedido=19&comercio=1&repartidor=0&app=ecoruta','2026-09-03 16:10:02'),(39,20,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-03 16:36:26'),(40,20,1,2,1,'Estado actualizado por el repartidor.','2026-09-03 16:37:07'),(41,20,2,3,1,'El repartidor inició el recorrido.','2026-09-03 16:37:08'),(42,20,3,4,1,'Confirmación QR: ecoruta:pedido:20?pedido=20&comercio=1&repartidor=0&app=ecoruta','2026-09-03 16:37:47'),(43,21,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-03 19:23:05'),(44,21,1,2,1,'Estado actualizado por el repartidor.','2026-09-03 19:24:16'),(45,21,2,3,1,'El repartidor inició el recorrido.','2026-09-03 19:24:17'),(46,21,3,4,1,'Confirmación QR: ecoruta:pedido:21?pedido=21&comercio=1&repartidor=0&app=ecoruta','2026-09-03 19:27:01'),(47,22,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-03 19:57:32'),(48,23,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-03 19:58:28'),(49,23,1,2,1,'Estado actualizado por el repartidor.','2026-09-03 20:00:08'),(50,22,1,2,1,'Estado actualizado por el repartidor.','2026-09-03 20:00:11'),(51,23,2,3,1,'El repartidor inició el recorrido.','2026-09-03 20:00:25'),(52,22,2,3,1,'El repartidor inició el recorrido.','2026-09-03 20:00:51'),(53,23,3,4,1,'Confirmación QR: ecoruta:pedido:23?pedido=23&comercio=1&repartidor=0&app=ecoruta','2026-09-03 20:01:49'),(58,27,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-04 23:05:34'),(59,27,1,2,10,'Pedido asignado a un repartidor','2026-09-04 23:05:59'),(60,27,2,2,10,'Pedido asignado a un repartidor','2026-09-04 23:06:05'),(61,27,2,3,7,'El repartidor inició el recorrido.','2026-09-04 23:21:12'),(62,27,3,4,1,'Confirmación QR: ecoruta:pedido:27?pedido=27&comercio=1&repartidor=0&app=ecoruta','2026-09-04 23:30:45'),(63,22,3,4,1,'Confirmación QR: ecoruta:pedido:22?pedido=22&comercio=1&repartidor=0&app=ecoruta','2026-09-04 23:31:47'),(74,34,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-04 23:49:38'),(75,34,1,2,7,'Estado actualizado por el repartidor.','2026-09-04 23:49:57'),(76,34,2,3,7,'El repartidor inició el recorrido.','2026-09-04 23:50:02'),(77,35,NULL,1,11,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-04 23:50:58'),(78,35,1,2,10,'Pedido asignado a un repartidor','2026-09-04 23:51:21'),(79,35,2,2,10,'Pedido asignado a un repartidor','2026-09-04 23:51:29'),(80,35,2,5,1,'Pedido cancelado: Comercio no entregó el paquete','2026-09-04 23:52:09'),(81,35,5,2,10,'Pedido asignado a un repartidor','2026-09-04 23:53:03'),(82,35,2,3,1,'El repartidor inició el recorrido.','2026-09-04 23:53:08'),(83,35,3,5,1,'Pedido cancelado: Destino incorrecto o no válido','2026-09-04 23:53:19'),(84,36,NULL,1,1,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-05 00:05:37'),(85,37,NULL,1,1,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-05 00:05:37'),(86,38,NULL,1,1,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-05 00:05:46'),(87,39,NULL,1,1,'Pedido y ruta registrados en base de datos ecoruta_db','2026-09-05 00:05:46'),(88,38,1,2,9,'Pedido asignado a un repartidor','2026-09-05 00:05:46'),(89,39,1,2,9,'Pedido asignado a un repartidor','2026-09-05 00:05:46'),(95,38,2,3,1,'El repartidor inició el recorrido.','2026-09-05 00:07:36'),(96,39,2,3,1,'El repartidor inició el recorrido.','2026-09-05 00:07:59'),(97,38,3,4,1,'Confirmación QR: ecoruta:pedido:38?pedido=38&comercio=1&repartidor=0&app=ecoruta','2026-09-05 00:11:54'),(98,39,3,4,1,'Confirmación QR: ecoruta:pedido:39?pedido=39&comercio=1&repartidor=0&app=ecoruta','2026-09-05 00:12:15'),(99,36,1,2,10,'Se asignó el pedido a un repartidor','2026-09-05 00:52:43'),(100,36,2,3,1,'El repartidor inició el recorrido.','2026-09-05 00:53:12'),(101,37,1,2,10,'Se asignó el pedido a un repartidor','2026-09-05 00:53:33'),(102,36,3,4,1,'Confirmación QR: ecoruta:pedido:36?pedido=36&comercio=1&repartidor=0&app=ecoruta','2026-09-05 00:54:25'),(103,37,2,3,1,'El repartidor inició el recorrido.','2026-09-05 00:54:29'),(104,37,3,4,1,'Confirmación QR: ecoruta:pedido:37?pedido=37&comercio=1&repartidor=0&app=ecoruta','2026-09-05 00:54:53'),(105,34,3,4,1,'Confirmación QR: ecoruta:pedido:34?pedido=34&comercio=1&repartidor=0&app=ecoruta','2026-09-05 00:57:50'),(106,42,NULL,1,11,'El comercio registró la entrega y espera asignación','2026-09-05 01:13:28'),(107,42,1,2,10,'Se asignó el pedido a un repartidor','2026-09-05 01:14:01'),(108,42,2,3,17,'El repartidor inició el recorrido.','2026-09-05 01:15:01'),(109,42,3,4,17,'Confirmación QR: ecoruta:pedido:42?pedido=42&comercio=1&repartidor=0&app=ecoruta','2026-09-05 01:15:45'),(110,43,NULL,1,11,'El comercio registró la entrega y espera asignación','2026-09-05 01:17:26'),(111,43,1,2,10,'Se asignó el pedido a un repartidor','2026-09-05 01:18:01');
/*!40000 ALTER TABLE `historial_estados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metricas_diarias`
--

DROP TABLE IF EXISTS `metricas_diarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metricas_diarias` (
  `fecha_reporte` date NOT NULL,
  `total_pedidos` int(11) NOT NULL DEFAULT 0,
  `entregados` int(11) NOT NULL DEFAULT 0,
  `co2_total_ahorrado_kg` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `km_recorridos_sin_emision` decimal(12,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`fecha_reporte`),
  CONSTRAINT `chk_total_pedidos_positivo` CHECK (`total_pedidos` >= 0),
  CONSTRAINT `chk_entregados_positivo` CHECK (`entregados` >= 0),
  CONSTRAINT `chk_co2_total_positivo` CHECK (`co2_total_ahorrado_kg` >= 0),
  CONSTRAINT `chk_km_positivos` CHECK (`km_recorridos_sin_emision` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metricas_diarias`
--

LOCK TABLES `metricas_diarias` WRITE;
/*!40000 ALTER TABLE `metricas_diarias` DISABLE KEYS */;
/*!40000 ALTER TABLE `metricas_diarias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id_notificacion` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario_destino` int(11) NOT NULL,
  `tipo` varchar(40) NOT NULL COMMENT 'nuevo_pedido | pedido_asignado | pedido_cancelado | pedido_entregado',
  `titulo` varchar(255) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `id_pedido` bigint(20) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_notificaciones_usuario` (`id_usuario_destino`,`leida`,`fecha`),
  CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
INSERT INTO `notificaciones` VALUES (27,6,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #34 (pan). Te toca asignar al repartidor.',34,0,'2026-09-04 23:49:38'),(28,9,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #34 (pan). Te toca asignar al repartidor.',34,0,'2026-09-04 23:49:38'),(29,10,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #34 (pan). Te toca asignar al repartidor.',34,1,'2026-09-04 23:49:38'),(30,6,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #35 (pan). Te toca asignar al repartidor.',35,0,'2026-09-04 23:50:58'),(31,9,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #35 (pan). Te toca asignar al repartidor.',35,0,'2026-09-04 23:50:58'),(32,10,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #35 (pan). Te toca asignar al repartidor.',35,1,'2026-09-04 23:50:58'),(33,1,'pedido_asignado','Te asignaron un pedido','El pedido #35 quedó asignado a tu recorrido. Entrá a la app para verlo.',35,1,'2026-09-04 23:51:21'),(34,1,'pedido_asignado','Te asignaron un pedido','El pedido #35 quedó asignado a tu recorrido. Entrá a la app para verlo.',35,1,'2026-09-04 23:51:29'),(35,6,'pedido_cancelado','Pedido cancelado','El pedido #35 de «Gustavo Benitez» fue cancelado. Motivo: Comercio no entregó el paquete',35,0,'2026-09-04 23:52:09'),(36,9,'pedido_cancelado','Pedido cancelado','El pedido #35 de «Gustavo Benitez» fue cancelado. Motivo: Comercio no entregó el paquete',35,0,'2026-09-04 23:52:09'),(37,10,'pedido_cancelado','Pedido cancelado','El pedido #35 de «Gustavo Benitez» fue cancelado. Motivo: Comercio no entregó el paquete',35,1,'2026-09-04 23:52:09'),(38,1,'pedido_asignado','Te asignaron un pedido','El pedido #35 quedó asignado a tu recorrido. Entrá a la app para verlo.',35,1,'2026-09-04 23:53:03'),(39,6,'pedido_cancelado','Pedido cancelado','El pedido #35 de «Gustavo Benitez» fue cancelado. Motivo: Destino incorrecto o no válido',35,0,'2026-09-04 23:53:19'),(40,9,'pedido_cancelado','Pedido cancelado','El pedido #35 de «Gustavo Benitez» fue cancelado. Motivo: Destino incorrecto o no válido',35,0,'2026-09-04 23:53:19'),(41,10,'pedido_cancelado','Pedido cancelado','El pedido #35 de «Gustavo Benitez» fue cancelado. Motivo: Destino incorrecto o no válido',35,1,'2026-09-04 23:53:19'),(42,6,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #36 (Pago test efectivo). Te toca asignar al repartidor.',36,0,'2026-09-05 00:05:37'),(43,9,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #36 (Pago test efectivo). Te toca asignar al repartidor.',36,0,'2026-09-05 00:05:37'),(44,10,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #36 (Pago test efectivo). Te toca asignar al repartidor.',36,1,'2026-09-05 00:05:37'),(45,6,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #37 (Pago test transferencia). Te toca asignar al repartidor.',37,0,'2026-09-05 00:05:37'),(46,9,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #37 (Pago test transferencia). Te toca asignar al repartidor.',37,0,'2026-09-05 00:05:37'),(47,10,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #37 (Pago test transferencia). Te toca asignar al repartidor.',37,1,'2026-09-05 00:05:37'),(48,6,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #38 (Pago test efectivo). Te toca asignar al repartidor.',38,0,'2026-09-05 00:05:46'),(49,9,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #38 (Pago test efectivo). Te toca asignar al repartidor.',38,0,'2026-09-05 00:05:46'),(50,10,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #38 (Pago test efectivo). Te toca asignar al repartidor.',38,1,'2026-09-05 00:05:46'),(51,6,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #39 (Pago test transferencia). Te toca asignar al repartidor.',39,0,'2026-09-05 00:05:46'),(52,9,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #39 (Pago test transferencia). Te toca asignar al repartidor.',39,0,'2026-09-05 00:05:46'),(53,10,'nuevo_pedido','Nuevo pedido por organizar','El comercio «Gustavo Benitez» creó el pedido #39 (Pago test transferencia). Te toca asignar al repartidor.',39,1,'2026-09-05 00:05:46'),(54,1,'pedido_asignado','Te asignaron un pedido','El pedido #38 quedó asignado a tu recorrido. Entrá a la app para verlo.',38,1,'2026-09-05 00:05:46'),(55,1,'pedido_asignado','Te asignaron un pedido','El pedido #39 quedó asignado a tu recorrido. Entrá a la app para verlo.',39,1,'2026-09-05 00:05:46'),(56,6,'pedido_pagado','Cobro registrado por el repartidor','El repartidor registró el cobro en efectivo del pedido #38.',38,0,'2026-09-05 00:05:46'),(57,9,'pedido_pagado','Cobro registrado por el repartidor','El repartidor registró el cobro en efectivo del pedido #38.',38,0,'2026-09-05 00:05:46'),(58,10,'pedido_pagado','Cobro registrado por el repartidor','El repartidor registró el cobro en efectivo del pedido #38.',38,1,'2026-09-05 00:05:46'),(59,1,'pedido_pagado','Pago confirmado','El pedido #39 ya fue pagado por transferencia. No tenés que cobrarlo al entregar.',39,1,'2026-09-05 00:05:46'),(75,1,'pedido_asignado','Te asignaron un pedido','El pedido #36 quedó asignado a tu recorrido. Entrá a la app para verlo.',36,1,'2026-09-05 00:52:43'),(76,1,'pedido_asignado','Te asignaron un pedido','El pedido #37 quedó asignado a tu recorrido. Entrá a la app para verlo.',37,1,'2026-09-05 00:53:33'),(77,6,'pedido_pagado','Cobro registrado por el repartidor','El repartidor registró el cobro en efectivo del pedido #36.',36,0,'2026-09-05 00:54:25'),(78,9,'pedido_pagado','Cobro registrado por el repartidor','El repartidor registró el cobro en efectivo del pedido #36.',36,0,'2026-09-05 00:54:25'),(79,10,'pedido_pagado','Cobro registrado por el repartidor','El repartidor registró el cobro en efectivo del pedido #36.',36,1,'2026-09-05 00:54:25'),(80,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #42 (g). Está esperando que le asignes un repartidor.',42,0,'2026-09-05 01:13:28'),(81,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #42 (g). Está esperando que le asignes un repartidor.',42,0,'2026-09-05 01:13:28'),(82,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #42 (g). Está esperando que le asignes un repartidor.',42,1,'2026-09-05 01:13:28'),(83,17,'pedido_asignado','Te asignaron un pedido','El pedido #42 quedó asignado a tu recorrido. Entrá a la app para verlo.',42,1,'2026-09-05 01:14:01'),(84,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #43 (f). Está esperando que le asignes un repartidor.',43,0,'2026-09-05 01:17:26'),(85,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #43 (f). Está esperando que le asignes un repartidor.',43,0,'2026-09-05 01:17:26'),(86,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #43 (f). Está esperando que le asignes un repartidor.',43,1,'2026-09-05 01:17:26'),(87,7,'pedido_asignado','Te asignaron un pedido','El pedido #43 quedó asignado a tu recorrido. Entrá a la app para verlo.',43,0,'2026-09-05 01:18:01'),(88,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #44 (E2E mixto valido). Está esperando que le asignes un repartidor.',44,0,'2026-09-05 01:41:23'),(89,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #44 (E2E mixto valido). Está esperando que le asignes un repartidor.',44,0,'2026-09-05 01:41:23'),(90,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #44 (E2E mixto valido). Está esperando que le asignes un repartidor.',44,1,'2026-09-05 01:41:23'),(91,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #45 (E2E mixto valido). Está esperando que le asignes un repartidor.',45,0,'2026-09-05 01:41:23'),(92,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #45 (E2E mixto valido). Está esperando que le asignes un repartidor.',45,0,'2026-09-05 01:41:23'),(93,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #45 (E2E mixto valido). Está esperando que le asignes un repartidor.',45,1,'2026-09-05 01:41:23'),(94,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #46 (E2E pago efectivo). Está esperando que le asignes un repartidor.',46,0,'2026-09-05 01:41:49'),(95,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #46 (E2E pago efectivo). Está esperando que le asignes un repartidor.',46,0,'2026-09-05 01:41:49'),(96,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #46 (E2E pago efectivo). Está esperando que le asignes un repartidor.',46,1,'2026-09-05 01:41:49'),(97,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #47 (E2E transferencia con comprobante). Está esperando que le asignes un repartidor.',47,0,'2026-09-05 01:41:49'),(98,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #47 (E2E transferencia con comprobante). Está esperando que le asignes un repartidor.',47,0,'2026-09-05 01:41:49'),(99,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #47 (E2E transferencia con comprobante). Está esperando que le asignes un repartidor.',47,1,'2026-09-05 01:41:50'),(100,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #48 (E2E calcular tarifa). Está esperando que le asignes un repartidor.',48,0,'2026-09-05 01:41:50'),(101,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #48 (E2E calcular tarifa). Está esperando que le asignes un repartidor.',48,0,'2026-09-05 01:41:50'),(102,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #48 (E2E calcular tarifa). Está esperando que le asignes un repartidor.',48,1,'2026-09-05 01:41:50'),(103,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #49 (E2E mixto valido). Está esperando que le asignes un repartidor.',49,0,'2026-09-05 01:41:50'),(104,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #49 (E2E mixto valido). Está esperando que le asignes un repartidor.',49,0,'2026-09-05 01:41:50'),(105,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #49 (E2E mixto valido). Está esperando que le asignes un repartidor.',49,1,'2026-09-05 01:41:50'),(106,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #50 (E2E cancel). Está esperando que le asignes un repartidor.',50,0,'2026-09-05 01:41:50'),(107,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #50 (E2E cancel). Está esperando que le asignes un repartidor.',50,0,'2026-09-05 01:41:50'),(108,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #50 (E2E cancel). Está esperando que le asignes un repartidor.',50,1,'2026-09-05 01:41:50'),(109,1,'pedido_asignado','Te asignaron un pedido','El pedido #50 quedó asignado a tu recorrido. Entrá a la app para verlo.',50,0,'2026-09-05 01:41:50'),(110,6,'pedido_cancelado','Pedido cancelado','El pedido #50 de «Gustavo Benitez» fue cancelado. Motivo: Cliente pidió posponer',50,0,'2026-09-05 01:41:50'),(111,9,'pedido_cancelado','Pedido cancelado','El pedido #50 de «Gustavo Benitez» fue cancelado. Motivo: Cliente pidió posponer',50,0,'2026-09-05 01:41:50'),(112,10,'pedido_cancelado','Pedido cancelado','El pedido #50 de «Gustavo Benitez» fue cancelado. Motivo: Cliente pidió posponer',50,1,'2026-09-05 01:41:50'),(113,11,'pedido_cancelado','Tu pedido fue cancelado','El pedido #50 (Cliente pidió posponer) fue cancelado. Contactá a tu cliente para avisarle y, si querés, editalo y volvé a lanzarlo desde Mis Pedidos.',50,0,'2026-09-05 01:41:50'),(114,6,'pedido_relanzado','Pedido relanzado','El pedido #50 volvió a lanzarse y quedó pendiente de asignación.',50,0,'2026-09-05 01:41:50'),(115,9,'pedido_relanzado','Pedido relanzado','El pedido #50 volvió a lanzarse y quedó pendiente de asignación.',50,0,'2026-09-05 01:41:50'),(116,10,'pedido_relanzado','Pedido relanzado','El pedido #50 volvió a lanzarse y quedó pendiente de asignación.',50,1,'2026-09-05 01:41:50'),(117,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #51 (E2E pago efectivo). Está esperando que le asignes un repartidor.',51,0,'2026-09-05 01:42:04'),(118,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #51 (E2E pago efectivo). Está esperando que le asignes un repartidor.',51,0,'2026-09-05 01:42:04'),(119,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #51 (E2E pago efectivo). Está esperando que le asignes un repartidor.',51,1,'2026-09-05 01:42:04'),(120,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #52 (E2E transferencia con comprobante). Está esperando que le asignes un repartidor.',52,0,'2026-09-05 01:42:04'),(121,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #52 (E2E transferencia con comprobante). Está esperando que le asignes un repartidor.',52,0,'2026-09-05 01:42:04'),(122,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #52 (E2E transferencia con comprobante). Está esperando que le asignes un repartidor.',52,1,'2026-09-05 01:42:04'),(123,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #53 (E2E calcular tarifa). Está esperando que le asignes un repartidor.',53,0,'2026-09-05 01:42:04'),(124,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #53 (E2E calcular tarifa). Está esperando que le asignes un repartidor.',53,0,'2026-09-05 01:42:04'),(125,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #53 (E2E calcular tarifa). Está esperando que le asignes un repartidor.',53,1,'2026-09-05 01:42:04'),(126,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #54 (E2E mixto valido). Está esperando que le asignes un repartidor.',54,0,'2026-09-05 01:42:04'),(127,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #54 (E2E mixto valido). Está esperando que le asignes un repartidor.',54,0,'2026-09-05 01:42:04'),(128,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #54 (E2E mixto valido). Está esperando que le asignes un repartidor.',54,1,'2026-09-05 01:42:04'),(129,6,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #55 (E2E cancel). Está esperando que le asignes un repartidor.',55,0,'2026-09-05 01:42:05'),(130,9,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #55 (E2E cancel). Está esperando que le asignes un repartidor.',55,0,'2026-09-05 01:42:05'),(131,10,'nuevo_pedido','Llegó un pedido nuevo','El comercio «Gustavo Benitez» creó el pedido #55 (E2E cancel). Está esperando que le asignes un repartidor.',55,1,'2026-09-05 01:42:05'),(132,1,'pedido_asignado','Te asignaron un pedido','El pedido #55 quedó asignado a tu recorrido. Entrá a la app para verlo.',55,0,'2026-09-05 01:42:05'),(133,6,'pedido_cancelado','Pedido cancelado','El pedido #55 de «Gustavo Benitez» fue cancelado. Motivo: Cliente pidió posponer',55,0,'2026-09-05 01:42:05'),(134,9,'pedido_cancelado','Pedido cancelado','El pedido #55 de «Gustavo Benitez» fue cancelado. Motivo: Cliente pidió posponer',55,0,'2026-09-05 01:42:05'),(135,10,'pedido_cancelado','Pedido cancelado','El pedido #55 de «Gustavo Benitez» fue cancelado. Motivo: Cliente pidió posponer',55,1,'2026-09-05 01:42:05'),(136,11,'pedido_cancelado','Tu pedido fue cancelado','El pedido #55 (Cliente pidió posponer) fue cancelado. Contactá a tu cliente para avisarle y, si querés, editalo y volvé a lanzarlo desde Mis Pedidos.',55,0,'2026-09-05 01:42:05'),(137,6,'pedido_relanzado','Pedido relanzado','El pedido #55 volvió a lanzarse y quedó pendiente de asignación.',55,0,'2026-09-05 01:42:05'),(138,9,'pedido_relanzado','Pedido relanzado','El pedido #55 volvió a lanzarse y quedó pendiente de asignación.',55,0,'2026-09-05 01:42:05'),(139,10,'pedido_relanzado','Pedido relanzado','El pedido #55 volvió a lanzarse y quedó pendiente de asignación.',55,1,'2026-09-05 01:42:05');
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedidos` (
  `id_pedido` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_comercio` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_repartidor` int(11) DEFAULT NULL,
  `id_estado` smallint(6) NOT NULL DEFAULT 1,
  `direccion_origen` text NOT NULL,
  `direccion_destino` text NOT NULL,
  `detalle_paquete` varchar(255) NOT NULL,
  `peso_kg` decimal(8,3) DEFAULT NULL,
  `alto_cm` decimal(6,2) DEFAULT NULL,
  `ancho_cm` decimal(6,2) DEFAULT NULL,
  `largo_cm` decimal(6,2) DEFAULT NULL,
  `distancia_km` decimal(8,3) DEFAULT NULL,
  `tarifa_ecologica` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` varchar(15) NOT NULL DEFAULT 'efectivo',
  `pagado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_pago` datetime DEFAULT NULL,
  `comprobante_transferencia` varchar(80) DEFAULT NULL,
  `monto_efectivo` decimal(10,2) DEFAULT NULL,
  `monto_transferencia` decimal(10,2) DEFAULT NULL,
  `monto_recibido` decimal(10,2) DEFAULT NULL,
  `vuelto` decimal(10,2) DEFAULT NULL,
  `co2_ahorrado_kg` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `observaciones` text DEFAULT NULL,
  `motivo_cancelacion` varchar(255) DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `fecha_solicitud` datetime DEFAULT current_timestamp(),
  `fecha_asignacion` datetime DEFAULT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  `confirmacion_tipo` varchar(30) DEFAULT NULL,
  `confirmacion_datos` text DEFAULT NULL,
  `destinatario_nombre` varchar(150) DEFAULT NULL,
  `destinatario_telefono` varchar(20) DEFAULT NULL,
  `dest_lat` decimal(9,6) DEFAULT NULL,
  `dest_lng` decimal(9,6) DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `fk_pedido_estado` (`id_estado`),
  KEY `idx_pedidos_comercio` (`id_comercio`,`fecha_solicitud`),
  KEY `idx_pedidos_repartidor` (`id_repartidor`,`id_estado`),
  KEY `idx_pedido_cliente` (`id_cliente`),
  CONSTRAINT `fk_pedido_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE SET NULL,
  CONSTRAINT `fk_pedido_comercio` FOREIGN KEY (`id_comercio`) REFERENCES `comercios` (`id_comercio`),
  CONSTRAINT `fk_pedido_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_pedido` (`id_estado`),
  CONSTRAINT `fk_pedido_repartidor` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`),
  CONSTRAINT `chk_peso_valido` CHECK (`peso_kg` > 0 and `peso_kg` <= 50 or `peso_kg` is null),
  CONSTRAINT `chk_distancia_valida` CHECK (`distancia_km` >= 0 or `distancia_km` is null),
  CONSTRAINT `chk_tarifa_positiva` CHECK (`tarifa_ecologica` >= 0),
  CONSTRAINT `chk_co2_positivo` CHECK (`co2_ahorrado_kg` >= 0),
  CONSTRAINT `chk_confirmacion_valida` CHECK (`confirmacion_tipo` in ('Firma Digital','Código QR','Prueba Fotográfica') or `confirmacion_tipo` is null),
  CONSTRAINT `chk_fecha_entrega_solo_cuando_entregado` CHECK (`id_estado` = 4 and `fecha_entrega` is not null or `id_estado` <> 4 and `fecha_entrega` is null)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
INSERT INTO `pedidos` VALUES (5,1,NULL,1,4,'Av. Pinedo 1420, Concepción','Barrio Itacurubí, Calle 4ta','Canasta de Productos Orgánicos',3.500,NULL,NULL,NULL,2.800,18000.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.5040,'Entregar en portería residencial',NULL,NULL,NULL,NULL,'2026-09-01 11:51:37',NULL,NULL,NULL,NULL,NULL,NULL),(6,1,NULL,1,4,'Presidente Franco esq. 14 de Mayo','Barrio Villa Alta, Casa 12','Insumos Médicos y Cuidado Personal',1.200,NULL,NULL,NULL,3.400,19500.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.6120,'Timbre 2B - Cliente abonó online',NULL,NULL,'2026-08-29 20:04:37',NULL,'2026-08-31 23:04:10',NULL,NULL,NULL,NULL,NULL,NULL),(7,1,NULL,NULL,4,'Mariscal López 880','Universidad Nacional de Concepción','Documentos y Cuadernos Reciclados',2.000,NULL,NULL,NULL,4.100,22000.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.7380,'Dejar en Mesa de Entrada',NULL,NULL,'2026-08-29 20:04:37',NULL,'2026-08-31 23:04:16',NULL,NULL,NULL,NULL,NULL,NULL),(9,1,NULL,1,4,'Plaza de la Libertad, Concepcion','Puerto Antiguo','Prueba de Ruta Guardada en DB',1.500,NULL,NULL,NULL,2.300,17500.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.4140,NULL,NULL,NULL,'2026-08-29 20:06:13',NULL,'2026-08-29 20:06:24',NULL,NULL,NULL,NULL,NULL,NULL),(13,1,NULL,2,4,'San Blas','Energon','Delivery',2.000,NULL,NULL,NULL,3.400,4.20,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.7100,'',NULL,NULL,'2026-09-01 11:16:35',NULL,'2026-09-01 11:31:39',NULL,NULL,NULL,NULL,NULL,NULL),(14,1,NULL,NULL,4,'b','a','a10',10.000,NULL,NULL,NULL,3.400,4.20,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.7100,'',NULL,NULL,'2026-09-01 12:09:49',NULL,'2026-09-01 12:11:34',NULL,NULL,NULL,NULL,NULL,NULL),(15,1,NULL,NULL,4,'c','d','da',12.000,NULL,NULL,NULL,3.400,4.20,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.7100,'',NULL,NULL,'2026-09-01 12:25:47',NULL,'2026-09-01 12:27:16',NULL,NULL,NULL,NULL,NULL,NULL),(16,1,NULL,NULL,4,'a','b','a',14.900,NULL,NULL,NULL,3.400,4.20,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.7100,'',NULL,NULL,'2026-09-02 15:54:15',NULL,'2026-09-03 16:01:07',NULL,NULL,NULL,NULL,NULL,NULL),(18,1,NULL,NULL,4,'UNC- FCEA','Greater London, England, United Kingdom (51.50745, -0.12777)','ss',15.000,NULL,NULL,NULL,2315.331,5800328.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,416.7596,'',NULL,NULL,'2026-09-03 01:16:13',NULL,'2026-09-03 15:45:22',NULL,NULL,NULL,NULL,NULL,NULL),(19,1,NULL,NULL,4,'UNC- FCEA','Energon, Inmaculada, Concepción, Región Oriental, Paraguay (-23.41070, -57.44168)','213',14.700,NULL,NULL,NULL,3.296,20240.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.5933,'',NULL,NULL,'2026-09-03 01:28:34',NULL,'2026-09-03 16:10:02',NULL,NULL,NULL,NULL,NULL,NULL),(20,1,NULL,NULL,4,'UNC- FCEA','Greater London, England, United Kingdom (51.50745, -0.12777)','Pedido de alvaro',50.000,NULL,NULL,NULL,2315.331,5800328.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,416.7596,'',NULL,NULL,'2026-09-03 16:36:26',NULL,'2026-09-03 16:37:47',NULL,NULL,NULL,NULL,NULL,NULL),(21,1,NULL,NULL,4,'UNC- FCEA','Energon, Inmaculada, Concepción, Región Oriental, Paraguay (-23.41070, -57.44168)','Pan',15.000,NULL,NULL,NULL,3.296,20240.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.5933,'',NULL,NULL,'2026-09-03 19:23:05',NULL,'2026-09-03 19:27:01',NULL,NULL,NULL,NULL,NULL,NULL),(22,1,NULL,NULL,4,'UNC - FCEA','Energon, Inmaculada, Concepción, Región Oriental, Paraguay (-23.41070, -57.44168)','Pedido A',1.000,NULL,NULL,NULL,3.296,20240.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.5933,'',NULL,NULL,'2026-09-03 19:57:32',NULL,'2026-09-04 23:31:47',NULL,NULL,NULL,NULL,NULL,NULL),(23,1,NULL,NULL,4,'UNC - FCEA','Energon, Inmaculada, Concepción, Región Oriental, Paraguay (-23.41070, -57.44168)','Pan',10.000,NULL,NULL,NULL,3.296,20240.00,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,0.5933,'',NULL,NULL,'2026-09-03 19:58:28',NULL,'2026-09-03 20:01:49',NULL,NULL,NULL,NULL,NULL,NULL),(27,1,1,2,4,'UNC - FCEA','Av. Mariscal Lopez 2233, Asuncion (-25.26370, -57.57590)','pan',5.000,4.00,4.00,4.00,407.582,1038470.25,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,73.3647,'',NULL,NULL,'2026-09-04 23:05:34','2026-09-04 23:06:05','2026-09-04 23:30:45',NULL,NULL,'Maria Rodriguez','+595 981 552244',-25.263700,-57.575900),(34,1,1,1,4,'UNC - FCEA','Av. Mariscal Lopez 2233, Asuncion (-25.26370, -57.57590)','pan',15.000,4.00,4.00,4.00,407.582,1053470.25,'efectivo',1,'2026-09-05 00:55:16',NULL,NULL,NULL,NULL,NULL,73.3647,'',NULL,NULL,'2026-09-04 23:49:38',NULL,'2026-09-05 00:57:50',NULL,NULL,'Maria Rodriguez','+595 981 552244',-25.263700,-57.575900),(35,1,1,1,5,'UNC - FCEA','Av. Mariscal Lopez 2233, Asuncion (-25.26370, -57.57590)','pan',20.000,4.00,4.00,4.00,407.582,1060970.25,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,73.3647,'','Destino incorrecto o no válido','2026-09-04 23:53:19','2026-09-04 23:50:58','2026-09-04 23:53:03',NULL,NULL,NULL,'Maria Rodriguez','+595 981 552244',-25.263700,-57.575900),(36,1,NULL,1,4,'Av. Pinedo 1420, Concepcion','Barrio Centro (-23.4025,-57.4443)','Pago test efectivo',1.500,NULL,NULL,NULL,2.100,19500.00,'efectivo',1,'2026-09-05 00:54:25',NULL,NULL,NULL,NULL,NULL,0.3780,NULL,NULL,NULL,'2026-09-05 00:05:37','2026-09-05 00:52:43','2026-09-05 00:54:25',NULL,NULL,NULL,NULL,-23.402500,-57.444300),(37,1,NULL,1,4,'Av. Pinedo 1420, Concepcion','Barrio Gral. Diaz (-23.4100,-57.4500)','Pago test transferencia',2.000,NULL,NULL,NULL,3.000,22500.00,'transferencia',1,'2026-09-05 05:05:37',NULL,NULL,NULL,NULL,NULL,0.5400,NULL,NULL,NULL,'2026-09-05 00:05:37','2026-09-05 00:53:33','2026-09-05 00:54:53',NULL,NULL,NULL,NULL,-23.410000,-57.450000),(38,1,NULL,1,4,'Av. Pinedo 1420, Concepcion','Barrio Centro (-23.4025,-57.4443)','Pago test efectivo',1.500,NULL,NULL,NULL,2.100,19500.00,'efectivo',1,'2026-09-05 00:05:46',NULL,NULL,NULL,NULL,NULL,0.3780,NULL,NULL,NULL,'2026-09-05 00:05:46','2026-09-05 00:05:46','2026-09-05 00:11:54',NULL,NULL,NULL,NULL,-23.402500,-57.444300),(39,1,NULL,1,4,'Av. Pinedo 1420, Concepcion','Barrio Gral. Diaz (-23.4100,-57.4500)','Pago test transferencia',2.000,NULL,NULL,NULL,3.000,22500.00,'transferencia',1,'2026-09-05 05:05:46',NULL,NULL,NULL,NULL,NULL,0.5400,NULL,NULL,NULL,'2026-09-05 00:05:46','2026-09-05 00:05:46','2026-09-05 00:12:15',NULL,NULL,NULL,NULL,-23.410000,-57.450000),(42,1,NULL,4,4,'UNC - FCEA','Energon, Inmaculada, Concepción, Región Oriental, Paraguay (-23.41070, -57.44168)','g',15.000,15.00,40.00,20.00,3.296,45740.25,'efectivo',1,'2026-09-05 01:13:57',NULL,NULL,NULL,NULL,NULL,0.5933,'',NULL,NULL,'2026-09-05 01:13:28','2026-09-05 01:14:01','2026-09-05 01:15:45',NULL,NULL,NULL,NULL,-23.410697,-57.441676),(43,1,1,2,2,'UNC - FCEA','Av. Mariscal Lopez 2233, Asuncion (-25.26370, -57.57590)','f',15.000,12.00,12.00,12.00,407.582,1053886.25,'efectivo',0,NULL,NULL,NULL,NULL,NULL,NULL,73.3647,'',NULL,NULL,'2026-09-05 01:17:26','2026-09-05 01:18:01',NULL,NULL,NULL,'Maria Rodriguez','+595 981 552244',-25.263700,-57.575900);
/*!40000 ALTER TABLE `pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recuperacion_contraseñas`
--

DROP TABLE IF EXISTS `recuperacion_contraseñas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recuperacion_contraseñas` (
  `id_recuperacion` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `usado_en` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_recuperacion`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_recuperacion_usuario` (`id_usuario`,`usado`,`expira_en`),
  CONSTRAINT `fk_recuperacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recuperacion_contraseñas`
--

LOCK TABLES `recuperacion_contraseñas` WRITE;
/*!40000 ALTER TABLE `recuperacion_contraseñas` DISABLE KEYS */;
INSERT INTO `recuperacion_contraseñas` VALUES (1,6,'e8d719159611773df577d5db7969ad3ae0d9291c17358bafa926c0950365984a','2026-08-27 00:55:12',0,NULL,'2026-08-27 00:25:12'),(2,6,'56722748af82260c3b5c8b5934353d7c9d8f9874b701f39d1d765043a5ca9d91','2026-08-27 00:55:14',0,NULL,'2026-08-27 00:25:14'),(3,6,'75aa5c55058b48a14337ee6dde53c91defb8d1ea859169e3f108d66707c0e077','2026-08-27 00:55:28',0,NULL,'2026-08-27 00:25:28'),(4,6,'49e8a1b1870e695b303086e243e303d16653108d01df6ff30e9386ba463683c5','2026-08-27 00:55:30',0,NULL,'2026-08-27 00:25:30'),(5,6,'f724d9128f6302f8b9e953df0afb4b2a34430a352429116f724d2609e2b0a0e0','2026-08-27 00:55:32',0,NULL,'2026-08-27 00:25:32'),(6,6,'f079da014a1ba27617d6312556abdf964ee18bf7527c9b3f50370a3e224a8fa7','2026-08-27 00:55:35',0,NULL,'2026-08-27 00:25:35'),(7,6,'9e39983f8cb8f59c0af28b67ca228d21fe786b3073081e54d7116a0fbd069da3','2026-08-27 00:58:00',1,'2026-08-27 00:28:12','2026-08-27 00:28:00'),(8,6,'c77e7ec35c95b0dc2b5ebb447dd11b96daa90100ac2ad5d2357f35a0d07523e2','2026-08-27 00:58:18',1,'2026-08-27 00:28:27','2026-08-27 00:28:18'),(9,6,'ae1a6551fe9c2defefb669315558cf9dfc19b8b3e6c21d2a6b237be3d5566cef','2026-08-27 01:02:38',0,NULL,'2026-08-27 00:32:38'),(10,6,'fca30f94120ca62d0bc8c42d06a081bc80c2e74e4c2db88075a5cf67935d6f58','2026-08-27 01:02:40',0,NULL,'2026-08-27 00:32:40'),(11,6,'d7355231a54d184530233f2682b80f2b8fa46887d6451a357694ed2587df8e58','2026-08-27 01:02:41',0,NULL,'2026-08-27 00:32:41'),(12,6,'0871b52e1c0aebbab1a13c3e7729c7a582670f36be4d80edf404776bccf3c40a','2026-08-27 01:02:41',0,NULL,'2026-08-27 00:32:41'),(13,6,'a147ae662dff7188610c7dd9d92de0d84e7bfce1d9d60318e29946f76031df1c','2026-08-27 01:03:06',0,NULL,'2026-08-27 00:33:06'),(17,1,'f6c46498490df559ee4551a430e22b37af4515ad5fd30781fb1ab0b174eb6db8','2026-08-27 01:10:48',0,NULL,'2026-08-27 00:40:48'),(27,1,'67408132398f500c6485602b7d333af6e1ca947bb35346fec803355d6d8051cd','2026-08-27 01:33:49',0,NULL,'2026-08-27 01:03:49'),(28,6,'c57ba0bea8b56890244de5d4f92417aab7d2fb60f9932a40c5c8cdae437a7861','2026-08-27 01:37:20',0,NULL,'2026-08-27 01:07:20'),(29,1,'3ea7ddac6a86dccf66001ae47c000f015664b4e7b1cb84b5f531ad2df9df931c','2026-08-27 07:03:28',0,NULL,'2026-08-27 06:33:28'),(30,1,'cae2c19b1cf55a93e53e9cf87537cc06ebce0f4496749f035ad81630f0eaf6f6','2026-08-27 07:05:17',0,NULL,'2026-08-27 06:35:17'),(31,1,'fa258795b7ba5c3fdd07fb06a9547b5aef95d810ca0476c4c16e4d96f6eb07b8','2026-08-27 19:21:19',0,NULL,'2026-08-27 18:51:19');
/*!40000 ALTER TABLE `recuperacion_contraseñas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repartidores`
--

DROP TABLE IF EXISTS `repartidores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repartidores` (
  `id_repartidor` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `tipo_vehiculo` varchar(30) NOT NULL,
  `matricula` varchar(50) DEFAULT NULL,
  `huella_carbono_estimada` decimal(10,4) DEFAULT 0.0000,
  `disponible` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_repartidor`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  KEY `idx_repartidores_disponibles` (`disponible`,`tipo_vehiculo`),
  CONSTRAINT `fk_repartidor_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `chk_tipo_vehiculo_permitido` CHECK (`tipo_vehiculo` in ('Bicicleta','Vehículo Eléctrico')),
  CONSTRAINT `chk_carbono_positivo` CHECK (`huella_carbono_estimada` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repartidores`
--

LOCK TABLES `repartidores` WRITE;
/*!40000 ALTER TABLE `repartidores` DISABLE KEYS */;
INSERT INTO `repartidores` VALUES (1,1,'Bicicleta','',0.0000,1,'2026-08-27 00:11:19'),(2,7,'Vehiculo Electrico',NULL,0.0000,1,'2026-09-03 17:32:25'),(4,17,'Vehículo Eléctrico','mathiasparedes202@gmail.com',0.0000,0,'2026-09-05 01:10:54');
/*!40000 ALTER TABLE `repartidores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol`
--

DROP TABLE IF EXISTS `rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `permisos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permisos_json`)),
  `rol_del_sistema` tinyint(1) DEFAULT 0,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol`
--

LOCK TABLES `rol` WRITE;
/*!40000 ALTER TABLE `rol` DISABLE KEYS */;
INSERT INTO `rol` VALUES (1,'Administrador','Administrador del sistema','{\"permisos\":[\"admin.*\"]}',1,1,'2026-08-29 23:41:01'),(2,'Vendedor','Usuario de ventas','{\"permisos\":[\"ventas.registrar\",\"ventas.cobrar\",\"clientes.ver\",\"caja.apertura\",\"caja.cierre\"]}',0,1,'2026-08-29 23:41:01');
/*!40000 ALTER TABLE `rol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id_rol` smallint(6) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Comerciante','Usuario que publica pedidos de entrega','2026-08-26 23:42:31'),(2,'Repartidor','Encargado de realizar las entregas','2026-08-26 23:42:31'),(3,'Administrador','Gestión del sistema y métricas','2026-08-26 23:42:31');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turnos`
--

DROP TABLE IF EXISTS `turnos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `turnos` (
  `id_turno` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_repartidor` int(11) NOT NULL,
  `estado` varchar(12) NOT NULL DEFAULT 'activo' COMMENT 'activo | pausado | finalizado',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `fecha_pausa` datetime DEFAULT NULL,
  `tiempo_pausado_seg` int(10) unsigned NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_turno`),
  KEY `idx_turnos_repartidor` (`id_repartidor`,`fecha_inicio`),
  CONSTRAINT `fk_turno_repartidor` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turnos`
--

LOCK TABLES `turnos` WRITE;
/*!40000 ALTER TABLE `turnos` DISABLE KEYS */;
INSERT INTO `turnos` VALUES (5,1,'finalizado','2026-09-04 23:51:06','2026-09-05 00:11:23',NULL,0,'2026-09-05 02:51:06'),(6,2,'finalizado','2026-09-05 00:28:08','2026-09-05 00:28:14',NULL,18005,'2026-09-05 03:28:08'),(7,2,'activo','2026-09-05 00:28:14',NULL,NULL,0,'2026-09-05 03:28:14'),(8,1,'finalizado','2026-09-05 00:31:27','2026-09-05 00:55:47',NULL,0,'2026-09-05 03:31:27'),(9,1,'activo','2026-09-05 00:56:12',NULL,NULL,0,'2026-09-05 03:56:12'),(10,4,'finalizado','2026-09-05 01:12:07','2026-09-05 01:59:08',NULL,0,'2026-09-05 04:12:07');
/*!40000 ALTER TABLE `turnos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `id_rol` smallint(6) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `contraseña_hash` varchar(255) NOT NULL,
  `documento_identidad` varchar(20) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_ultima_modificacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_ultimo_acceso` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `debe_cambiar_contraseña` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `correo` (`correo`),
  UNIQUE KEY `documento_identidad` (`documento_identidad`),
  KEY `fk_usuario_rol` (`id_rol`),
  KEY `idx_usuarios_correo` (`correo`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`),
  CONSTRAINT `chk_correo_valido` CHECK (`correo` regexp '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'),
  CONSTRAINT `chk_nombre_completo` CHECK (octet_length(trim(`nombre_completo`)) >= 3),
  CONSTRAINT `chk_telefono_valido` CHECK (`telefono` regexp '^[+]?[0-9\\s-]{7,20}$' or `telefono` is null)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,2,'mathiasparedes202@gmail.com','$2y$10$zoRAWxMsyVqupbAo7P/gdeUeZEKfLTeImQb7ANt6U/rGtT.eezFBa','7075506','Mathias Paredes',NULL,'2026-08-26 23:58:37','2026-09-05 00:56:04','2026-09-05 00:56:04',1,0),(6,3,'paredesn104@gmail.com','$2y$10$hOJr/deUMDkYP9YdpYa2O.rcG9JeDBH99I8IroUPAiz/tDk2kfmOi','0999999999','Nelson Mathias Paredes Martinez','0984 044209','2026-08-27 00:11:19','2026-08-27 00:33:08','2026-08-27 00:33:08',1,0),(7,2,'carlos.mendoza@ecoruta.com','$2y$10$zoRAWxMsyVqupbAo7P/gdeUeZEKfLTeImQb7ANt6U/rGtT.eezFBa','0912345678','Carlos Mendoza','+593 98 420 7781','2026-08-27 00:11:19','2026-09-05 00:55:55','2026-09-05 00:55:55',1,1),(9,3,'admin@ecoruta.com','$2y$10$glp3yG.n9MCh86qwPQXaO.fz.p4Kx6hdI9HYG00qh0gU.xtXlD/7e','7075501','Nelson Paredes','0975378521','2026-08-27 05:46:14','2026-08-29 22:08:50',NULL,1,1),(10,3,'agaga@eco.com','$2y$10$zoRAWxMsyVqupbAo7P/gdeUeZEKfLTeImQb7ANt6U/rGtT.eezFBa','5858589','mathias',NULL,'2026-08-27 19:49:58','2026-09-05 01:49:18','2026-09-05 01:49:18',1,0),(11,1,'comercio.central@ecoruta.com','$2y$10$aKK4epEzTyPKwugL5IOVLOhK2v0I.n6TAt1fCR3His1Gqmv2IVfky','80012345-1','Gustavo Benitez',NULL,'2026-08-29 20:04:16','2026-09-05 02:12:02','2026-09-05 02:12:02',1,0),(17,2,'mati@ecoruta.com','$2y$10$xYczPkW96rh7RKqHYLOAded9K5zvrXY3G3fIMW3WtRiWilqbtOyLi','7075507','Nelson Paredes',NULL,'2026-09-05 01:10:54','2026-09-05 01:45:16','2026-09-05 01:45:16',1,0);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'ecoruta_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-05  2:57:08
