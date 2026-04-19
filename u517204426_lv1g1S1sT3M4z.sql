-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 19-04-2026 a las 00:48:27
-- Versión del servidor: 11.8.6-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u517204426_lv1g1S1sT3M4z`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lsis_bloqueos_login`
--

CREATE TABLE `lsis_bloqueos_login` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario` varchar(20) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `intentos_fallidos` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_intento_at` datetime DEFAULT NULL,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL,
  `actualizado_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lsis_configuracion_seguridad`
--

CREATE TABLE `lsis_configuracion_seguridad` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `control_sesiones_activo` tinyint(1) NOT NULL DEFAULT 0,
  `max_dispositivos_activo` tinyint(1) NOT NULL DEFAULT 0,
  `max_dispositivos` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `timeout_inactividad_activo` tinyint(1) NOT NULL DEFAULT 0,
  `timeout_inactividad_minutos` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `limitador_login_activo` tinyint(1) NOT NULL DEFAULT 0,
  `max_intentos_fallidos` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `ventana_intentos_minutos` int(10) UNSIGNED NOT NULL DEFAULT 15,
  `bloqueo_temporal_activo` tinyint(1) NOT NULL DEFAULT 0,
  `bloqueo_temporal_minutos` int(10) UNSIGNED NOT NULL DEFAULT 15,
  `control_abuso_setup_activo` tinyint(1) NOT NULL DEFAULT 0,
  `max_intentos_setup` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `ventana_setup_minutos` int(10) UNSIGNED NOT NULL DEFAULT 15,
  `bloqueo_setup_minutos` int(10) UNSIGNED NOT NULL DEFAULT 15,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lsis_configuracion_seguridad`
--

INSERT INTO `lsis_configuracion_seguridad` (`id`, `control_sesiones_activo`, `max_dispositivos_activo`, `max_dispositivos`, `timeout_inactividad_activo`, `timeout_inactividad_minutos`, `limitador_login_activo`, `max_intentos_fallidos`, `ventana_intentos_minutos`, `bloqueo_temporal_activo`, `bloqueo_temporal_minutos`, `control_abuso_setup_activo`, `max_intentos_setup`, `ventana_setup_minutos`, `bloqueo_setup_minutos`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 1, 1, 1, 1, 1, 3, 10, 1, 5, 1, 5, 15, 15, '2026-04-18 20:17:46', '2026-04-18 22:59:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lsis_configuracion_sistema`
--

CREATE TABLE `lsis_configuracion_sistema` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `sistema_inicializado` tinyint(1) NOT NULL DEFAULT 0,
  `id_usuario_inicial` int(11) DEFAULT NULL,
  `fecha_inicializacion` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lsis_configuracion_sistema`
--

INSERT INTO `lsis_configuracion_sistema` (`id`, `sistema_inicializado`, `id_usuario_inicial`, `fecha_inicializacion`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 4, '2026-04-18 19:57:35', '2026-04-18 19:57:35', '2026-04-18 19:57:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lsis_intentos_acceso`
--

CREATE TABLE `lsis_intentos_acceso` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `endpoint` varchar(50) NOT NULL,
  `usuario` varchar(20) DEFAULT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `motivo` varchar(50) DEFAULT NULL,
  `intento_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lsis_intentos_acceso`
--

INSERT INTO `lsis_intentos_acceso` (`id`, `endpoint`, `usuario`, `ip`, `user_agent`, `exito`, `motivo`, `intento_at`) VALUES
(1, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 17:21:27'),
(2, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:21:51'),
(3, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:21:54'),
(4, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:21:56'),
(5, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:21:59'),
(6, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:22:02'),
(7, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:22:05'),
(8, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:22:07'),
(9, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:22:09'),
(10, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:22:23'),
(11, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:22:26'),
(12, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'bloqueado', '2026-04-18 17:22:50'),
(13, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'bloqueado', '2026-04-18 17:23:05'),
(14, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 17:23:27'),
(15, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:23:33'),
(16, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:23:35'),
(17, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:23:38'),
(18, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:23:40'),
(19, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:23:42'),
(20, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:23:44'),
(21, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:25:08'),
(22, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'csrf', '2026-04-18 17:25:11'),
(23, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 17:42:40'),
(24, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 17:48:24'),
(25, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 17:54:59'),
(26, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 17:55:07'),
(27, 'login', '70379785', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:56:19'),
(28, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:56:48'),
(29, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'usuario_inactivo', '2026-04-18 17:57:19'),
(30, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 17:58:30'),
(31, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:58:38'),
(32, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:58:40'),
(33, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:58:42'),
(34, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:58:45'),
(35, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:58:48'),
(36, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'credenciales_invalidas', '2026-04-18 17:58:50'),
(37, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 17:59:00'),
(38, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'bloqueado', '2026-04-18 17:59:53'),
(39, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'bloqueado', '2026-04-18 18:00:00'),
(40, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'bloqueado', '2026-04-18 18:00:27'),
(41, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'bloqueado', '2026-04-18 18:01:09'),
(42, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 0, 'bloqueado', '2026-04-18 18:02:46'),
(43, 'login', '70379752', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 'ok', '2026-04-18 18:12:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lsis_roles`
--

CREATE TABLE `lsis_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lsis_roles`
--

INSERT INTO `lsis_roles` (`id`, `nombre`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 'Superadmin', 1, '2026-04-18 18:23:45', '2026-04-18 18:23:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lsis_sesiones`
--

CREATE TABLE `lsis_sesiones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `session_id_hash` char(64) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_at` datetime NOT NULL,
  `ultima_actividad_at` datetime NOT NULL,
  `logout_at` datetime DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `motivo_cierre` varchar(50) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lsis_sesiones`
--

INSERT INTO `lsis_sesiones` (`id`, `id_usuario`, `session_id_hash`, `ip`, `user_agent`, `login_at`, `ultima_actividad_at`, `logout_at`, `estado`, `motivo_cierre`, `creado_en`, `actualizado_en`) VALUES
(1, 4, 'fef368c0b409b297e9c24414f21f48414fa5aff446883a1be7c878848ceca4ed', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:19:13', '2026-04-18 20:19:13', '2026-04-18 20:19:15', 0, 'logout', '2026-04-18 20:19:13', '2026-04-18 20:19:15'),
(2, 4, 'e16349a3d9a1808a33bb0a4a9a7bb06ed9baa726b8f09d7833d394b96ef333fe', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:19:19', '2026-04-18 20:19:19', '2026-04-18 20:19:22', 0, 'logout', '2026-04-18 20:19:19', '2026-04-18 20:19:22'),
(3, 4, '0563c61016b4592428626545b746d4d4469795fa5fde1853283185989ab73bfe', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:19:26', '2026-04-18 20:19:26', '2026-04-18 20:19:28', 0, 'logout', '2026-04-18 20:19:26', '2026-04-18 20:19:28'),
(4, 4, '4cf73785f69769f3266b1e61d21d30b0e3b5d51832a0e8cd650c67ed2845961e', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:21:36', '2026-04-18 20:21:36', '2026-04-18 20:21:58', 0, 'logout', '2026-04-18 20:21:36', '2026-04-18 20:21:58'),
(5, 4, '8f8fb87c08c70ad2408a80e3acef5c634bb3a79a34419d93b0f202f7d11c7303', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:22:15', '2026-04-18 20:22:15', '2026-04-18 20:22:19', 0, 'logout', '2026-04-18 20:22:15', '2026-04-18 20:22:19'),
(6, 4, '8bd8db02b98de4713999116a7c94074219a35319f288c6374fb5a613e003e018', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:22:42', '2026-04-18 20:22:42', '2026-04-18 20:25:41', 0, 'logout', '2026-04-18 20:22:42', '2026-04-18 20:25:41'),
(7, 4, 'b6099d467dd2b4036839492710350fe4adbb1256a18e24c101f2f2603a951aa5', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:22:59', '2026-04-18 20:22:59', '2026-04-18 20:26:00', 0, 'reemplazada', '2026-04-18 20:22:59', '2026-04-18 20:26:00'),
(8, 4, '8bb2c16f4ed50aa576622aa61414fbda12981b0d098d42eb38a7461437df6874', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:23:43', '2026-04-18 20:23:43', '2026-04-18 20:25:30', 0, 'logout', '2026-04-18 20:23:43', '2026-04-18 20:25:30'),
(9, 4, 'e35cc51faaf4cefab6421584bb4322762b30d1b5c04ca16e25cd91a204c84164', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:26:00', '2026-04-18 20:26:01', '2026-04-18 20:26:24', 0, 'reemplazada', '2026-04-18 20:26:00', '2026-04-18 20:26:24'),
(10, 4, '682c4aead21df3a1588f1ad01aded1628b9e230b113bd07551c67c8cb465ea73', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:26:24', '2026-04-18 20:26:31', '2026-04-18 20:26:34', 0, 'logout', '2026-04-18 20:26:24', '2026-04-18 20:26:34'),
(11, 4, '4ee06030b26dd3d2265c184ebe23ac872fe9b8e9bc78f98d3d341a9ebc79628d', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:26:49', '2026-04-18 20:28:13', '2026-04-18 20:28:27', 0, 'reemplazada', '2026-04-18 20:26:49', '2026-04-18 20:28:27'),
(12, 4, '990bdbd63ccd3190a6c267863082abb7d0570ffaa69edddc45cfea3e87dcaba9', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:28:27', '2026-04-18 20:41:54', '2026-04-18 20:42:00', 0, 'logout', '2026-04-18 20:28:27', '2026-04-18 20:42:00'),
(13, 4, '89101e43517e3a8fccfc87c4234c2952f3ac2f510b7331e2b8f3f5b4627bc66b', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 20:42:03', '2026-04-18 20:53:22', '2026-04-18 15:58:24', 0, 'logout', '2026-04-18 20:42:03', '2026-04-18 20:58:24'),
(14, 4, 'a2b1d91f9fbf54911d49a1146b4f441aac6f6e7e4f43a73dee663c27d51333ca', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 16:00:35', '2026-04-18 16:00:39', '2026-04-18 16:00:55', 0, 'reemplazada', '2026-04-18 21:00:35', '2026-04-18 21:00:55'),
(15, 4, '27fc457dd88b28e4c18d6440c20f4c1ac6dbc078bc02f7f441f0dce3274126db', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 16:00:55', '2026-04-18 16:01:04', '2026-04-18 16:01:13', 0, 'reemplazada', '2026-04-18 21:00:55', '2026-04-18 21:01:13'),
(16, 4, '47d6c2f07213a67ce3d15b24390b4c18db7bd3e6db64dec71861e5587874dfb0', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 16:01:13', '2026-04-18 16:01:19', '2026-04-18 16:06:11', 0, 'timeout', '2026-04-18 21:01:13', '2026-04-18 21:06:11'),
(17, 4, 'ebde902654aa1d2077fcd2e8736f11bd9ec60305c6adf076532c2612cf74bcd0', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 16:06:23', '2026-04-18 16:06:34', '2026-04-18 16:10:25', 0, 'timeout', '2026-04-18 21:06:23', '2026-04-18 21:10:25'),
(18, 4, '9f1cb80db91b560f9f6f97c440e93d937754f1004ff2c42cc24b60445505a3ac', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:18:40', '2026-04-18 17:18:44', '2026-04-18 17:18:45', 0, 'logout', '2026-04-18 22:18:40', '2026-04-18 22:18:45'),
(19, 4, 'eaee90aef2f4699d4fca26173d795341c3ea18e5b3d9f5ec8288ded8557f8dc9', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:21:27', '2026-04-18 17:21:41', '2026-04-18 17:21:46', 0, 'logout', '2026-04-18 22:21:27', '2026-04-18 22:21:46'),
(20, 4, '371ce31f686526c8716446349c75be2bd1468794de841bf2ac03099ca6427da0', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:23:27', '2026-04-18 17:23:27', '2026-04-18 17:23:30', 0, 'logout', '2026-04-18 22:23:27', '2026-04-18 22:23:30'),
(21, 4, '7135deaad38bbc8fe1f06a53446facc37b599da2c98dc63136e0a3adce6d5e69', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:42:40', '2026-04-18 17:42:43', '2026-04-18 17:45:38', 0, 'timeout', '2026-04-18 22:42:40', '2026-04-18 22:45:38'),
(22, 4, '1fac238d2da0c31f12057c498c4a69c973e19f69e6ca70a039776421961a7de3', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:48:24', '2026-04-18 17:48:26', '2026-04-18 17:48:28', 0, 'logout', '2026-04-18 22:48:24', '2026-04-18 22:48:28'),
(23, 4, '7718fc62c766aaf361af70ece13e0799937bf3b8217413a4e830b3943e32eba1', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:54:59', '2026-04-18 17:55:00', '2026-04-18 17:55:03', 0, 'logout', '2026-04-18 22:54:59', '2026-04-18 22:55:03'),
(24, 4, 'e0c5f1ce39e3a3fff334fd33927d13849afc15227c97787c87265898db909fbf', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:55:07', '2026-04-18 17:55:08', '2026-04-18 17:56:02', 0, 'logout', '2026-04-18 22:55:07', '2026-04-18 22:56:02'),
(25, 4, '9943fe4938a1e682df473c8991227d0b27f07b7766a833f107e17af3a81c281b', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:58:30', '2026-04-18 17:58:31', '2026-04-18 17:58:35', 0, 'logout', '2026-04-18 22:58:30', '2026-04-18 22:58:35'),
(26, 4, 'ffceb4c845d6f1808174f6eab584203da6d28f955c66604d5091d4167f7574f5', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 17:59:00', '2026-04-18 17:59:00', '2026-04-18 17:59:16', 0, 'logout', '2026-04-18 22:59:00', '2026-04-18 22:59:16'),
(27, 4, 'f7d4fd91129a1a9e1573d22dc3ac7c32177fea7a18092683a72309e4df4cbdc8', '179.6.167.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-18 18:12:41', '2026-04-18 18:12:41', '2026-04-18 19:13:17', 0, 'timeout', '2026-04-18 23:12:41', '2026-04-19 00:13:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lsis_usuarios`
--

CREATE TABLE `lsis_usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario` varchar(20) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_login_at` datetime DEFAULT NULL,
  `ultimo_login_ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lsis_usuarios`
--

INSERT INTO `lsis_usuarios` (`id`, `usuario`, `clave`, `nombres`, `apellidos`, `estado`, `creado_en`, `actualizado_en`, `ultimo_login_at`, `ultimo_login_ip`) VALUES
(4, '70379752', '$2y$10$X495bvgGb8u7/M9nsW4HXevskzx.MwDziFVSBsBgfXHtspBc583Ga', 'LUIGI ISRAEL', 'VILLANUEVA PEREZ', 1, '2026-04-18 19:57:35', '2026-04-18 18:12:41', '2026-04-18 18:12:41', '179.6.167.103');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lsis_usuario_roles`
--

CREATE TABLE `lsis_usuario_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_rol` int(10) UNSIGNED NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lsis_usuario_roles`
--

INSERT INTO `lsis_usuario_roles` (`id`, `id_usuario`, `id_rol`, `estado`, `creado_en`, `actualizado_en`) VALUES
(4, 4, 1, 1, '2026-04-18 19:57:35', '2026-04-18 19:57:35');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `lsis_bloqueos_login`
--
ALTER TABLE `lsis_bloqueos_login`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lsis_bloqueos_login_usuario_ip` (`usuario`,`ip`),
  ADD KEY `idx_lsis_bloqueos_login_bloqueado_hasta` (`bloqueado_hasta`),
  ADD KEY `idx_lsis_bloqueos_login_ultimo_intento` (`ultimo_intento_at`);

--
-- Indices de la tabla `lsis_configuracion_seguridad`
--
ALTER TABLE `lsis_configuracion_seguridad`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `lsis_configuracion_sistema`
--
ALTER TABLE `lsis_configuracion_sistema`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `lsis_intentos_acceso`
--
ALTER TABLE `lsis_intentos_acceso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lsis_intentos_endpoint_time` (`endpoint`,`intento_at`),
  ADD KEY `idx_lsis_intentos_login_user_ip_time` (`endpoint`,`usuario`,`ip`,`intento_at`),
  ADD KEY `idx_lsis_intentos_setup_ip_time` (`endpoint`,`ip`,`intento_at`),
  ADD KEY `idx_lsis_intentos_exito_time` (`endpoint`,`exito`,`intento_at`);

--
-- Indices de la tabla `lsis_roles`
--
ALTER TABLE `lsis_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_lsis_roles_nombre` (`nombre`);

--
-- Indices de la tabla `lsis_sesiones`
--
ALTER TABLE `lsis_sesiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lsis_sesiones_session_hash` (`session_id_hash`),
  ADD KEY `idx_lsis_sesiones_usuario_estado` (`id_usuario`,`estado`),
  ADD KEY `idx_lsis_sesiones_estado_actividad` (`estado`,`ultima_actividad_at`),
  ADD KEY `idx_lsis_sesiones_usuario_actividad` (`id_usuario`,`ultima_actividad_at`);

--
-- Indices de la tabla `lsis_usuarios`
--
ALTER TABLE `lsis_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_lsis_usuarios_usuario` (`usuario`);

--
-- Indices de la tabla `lsis_usuario_roles`
--
ALTER TABLE `lsis_usuario_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_lsis_usuario_rol` (`id_usuario`,`id_rol`),
  ADD KEY `idx_lsis_ur_usuario` (`id_usuario`),
  ADD KEY `idx_lsis_ur_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `lsis_bloqueos_login`
--
ALTER TABLE `lsis_bloqueos_login`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lsis_intentos_acceso`
--
ALTER TABLE `lsis_intentos_acceso`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT de la tabla `lsis_roles`
--
ALTER TABLE `lsis_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `lsis_sesiones`
--
ALTER TABLE `lsis_sesiones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `lsis_usuarios`
--
ALTER TABLE `lsis_usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `lsis_usuario_roles`
--
ALTER TABLE `lsis_usuario_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `lsis_usuario_roles`
--
ALTER TABLE `lsis_usuario_roles`
  ADD CONSTRAINT `fk_lsis_ur_rol` FOREIGN KEY (`id_rol`) REFERENCES `lsis_roles` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lsis_ur_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `lsis_usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
