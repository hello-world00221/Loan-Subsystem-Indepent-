-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2026 at 03:20 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `loandb`
--

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` int(11) NOT NULL,
  `municipality_id` int(11) NOT NULL,
  `barangay_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `municipality_id`, `barangay_name`) VALUES
(1, 1, 'Barangay 1 – Tondo'),
(2, 1, 'Barangay Paco'),
(3, 1, 'Barangay Pandacan'),
(4, 1, 'Barangay San Andres'),
(5, 1, 'Barangay San Miguel'),
(6, 1, 'Barangay Sampaloc'),
(7, 1, 'Barangay Santa Mesa'),
(8, 1, 'Barangay Ermita'),
(9, 1, 'Barangay Intramuros'),
(10, 1, 'Barangay Malate'),
(11, 1, 'Barangay Port Area'),
(12, 1, 'Barangay San Nicolas'),
(13, 1, 'Barangay Binondo'),
(14, 1, 'Barangay Quiapo'),
(15, 1, 'Barangay Santa Cruz'),
(16, 2, 'Barangay Bagong Silang'),
(17, 2, 'Barangay Batasan Hills'),
(18, 2, 'Barangay Commonwealth'),
(19, 2, 'Barangay Culiat'),
(20, 2, 'Barangay Fairview'),
(21, 2, 'Barangay Holy Spirit'),
(22, 2, 'Barangay Kamuning'),
(23, 2, 'Barangay Ugong Norte'),
(24, 2, 'Barangay Bagumbayan'),
(25, 2, 'Barangay Tandang Sora'),
(26, 2, 'Barangay Loyola Heights'),
(27, 2, 'Barangay Diliman'),
(28, 2, 'Barangay Kristong Hari'),
(29, 2, 'Barangay Pasong Tamo'),
(30, 2, 'Barangay Project 4'),
(31, 3, 'Barangay Bel-Air'),
(32, 3, 'Barangay Forbes Park'),
(33, 3, 'Barangay Magallanes'),
(34, 3, 'Barangay Pio del Pilar'),
(35, 3, 'Barangay Poblacion'),
(36, 3, 'Barangay Rembo'),
(37, 3, 'Barangay San Lorenzo'),
(38, 3, 'Barangay Bangkal'),
(39, 3, 'Barangay Carmona'),
(40, 3, 'Barangay Comembo'),
(41, 3, 'Barangay East Rembo'),
(42, 3, 'Barangay Guadalupe Nuevo'),
(43, 3, 'Barangay Guadalupe Viejo'),
(44, 3, 'Barangay Olympia'),
(45, 3, 'Barangay Palanan'),
(46, 4, 'Barangay Bagong Ilog'),
(47, 4, 'Barangay Bagong Katipunan'),
(48, 4, 'Barangay Bambang'),
(49, 4, 'Barangay Buting'),
(50, 4, 'Barangay Caniogan'),
(51, 4, 'Barangay dela Paz'),
(52, 4, 'Barangay Kalawaan'),
(53, 4, 'Barangay Kapasigan'),
(54, 4, 'Barangay Malinao'),
(55, 4, 'Barangay Manggahan'),
(56, 4, 'Barangay Maybunga'),
(57, 4, 'Barangay Pinagbuhatan'),
(58, 4, 'Barangay Rosario'),
(59, 4, 'Barangay San Antonio'),
(60, 4, 'Barangay San Miguel'),
(61, 5, 'Barangay Bagong Tanyag'),
(62, 5, 'Barangay Central Bicutan'),
(63, 5, 'Barangay Central Signal Village'),
(64, 5, 'Barangay Fort Bonifacio'),
(65, 5, 'Barangay Hagonoy'),
(66, 5, 'Barangay Ibayo Tipas'),
(67, 5, 'Barangay Lower Bicutan'),
(68, 5, 'Barangay Napindan'),
(69, 5, 'Barangay Palingon'),
(70, 5, 'Barangay San Miguel'),
(71, 5, 'Barangay Santa Ana'),
(72, 5, 'Barangay Tuktukan'),
(73, 5, 'Barangay Upper Bicutan'),
(74, 5, 'Barangay Ususan'),
(75, 5, 'Barangay Western Bicutan'),
(76, 6, 'Barangay Barangka'),
(77, 6, 'Barangay Calumpang'),
(78, 6, 'Barangay Concepcion Uno'),
(79, 6, 'Barangay Concepcion Dos'),
(80, 6, 'Barangay Industrial Valley'),
(81, 6, 'Barangay Jesus dela Peña'),
(82, 6, 'Barangay Malanday'),
(83, 6, 'Barangay Marikina Heights'),
(84, 6, 'Barangay Nangka'),
(85, 6, 'Barangay Parang'),
(86, 6, 'Barangay San Roque'),
(87, 6, 'Barangay Santa Elena'),
(88, 6, 'Barangay Santo Niño'),
(89, 6, 'Barangay Tañong'),
(90, 6, 'Barangay Tumana'),
(91, 7, 'Barangay Baclaran'),
(92, 7, 'Barangay BF Homes'),
(93, 7, 'Barangay Don Bosco'),
(94, 7, 'Barangay Don Galo'),
(95, 7, 'Barangay La Huerta'),
(96, 7, 'Barangay Marcelo Green'),
(97, 7, 'Barangay Merville'),
(98, 7, 'Barangay Moonwalk'),
(99, 7, 'Barangay San Dionisio'),
(100, 7, 'Barangay San Isidro'),
(101, 7, 'Barangay San Martin de Porres'),
(102, 7, 'Barangay Santo Niño'),
(103, 7, 'Barangay Tambo'),
(104, 7, 'Barangay Vitalez'),
(105, 7, 'Barangay Sun Valley'),
(106, 8, 'Barangay Almanza Uno'),
(107, 8, 'Barangay Almanza Dos'),
(108, 8, 'Barangay BF Resort'),
(109, 8, 'Barangay CAA-BF International'),
(110, 8, 'Barangay Daniel Fajardo'),
(111, 8, 'Barangay Elias Aldana'),
(112, 8, 'Barangay Ilaya'),
(113, 8, 'Barangay Manuyo Uno'),
(114, 8, 'Barangay Manuyo Dos'),
(115, 8, 'Barangay Pamplona Uno'),
(116, 8, 'Barangay Pamplona Dos'),
(117, 8, 'Barangay Pamplona Tres'),
(118, 8, 'Barangay Pilar'),
(119, 8, 'Barangay Pulang Lupa Uno'),
(120, 8, 'Barangay Talon Dos'),
(121, 9, 'Barangay Addition Hills'),
(122, 9, 'Barangay Bagong Silang'),
(123, 9, 'Barangay Barangka Ilaya'),
(124, 9, 'Barangay Buayang Bato'),
(125, 9, 'Barangay Burol'),
(126, 9, 'Barangay Daang Bakal'),
(127, 9, 'Barangay Hagdang Bato Itaas'),
(128, 9, 'Barangay Hagdang Bato Libis'),
(129, 9, 'Barangay Mauway'),
(130, 9, 'Barangay Namayan'),
(131, 9, 'Barangay New Zañiga'),
(132, 9, 'Barangay Old Zañiga'),
(133, 9, 'Barangay Pag-asa'),
(134, 9, 'Barangay Plainview'),
(135, 9, 'Barangay Pleasant Hills'),
(136, 10, 'Barangay Bagong Silang'),
(137, 10, 'Barangay Camarin'),
(138, 10, 'Barangay Deparo'),
(139, 10, 'Barangay Grace Park East'),
(140, 10, 'Barangay Grace Park West'),
(141, 10, 'Barangay Katipunan I'),
(142, 10, 'Barangay Llano'),
(143, 10, 'Barangay Maypajo'),
(144, 10, 'Barangay Monumento'),
(145, 10, 'Barangay Pariancillo Villa'),
(146, 10, 'Barangay San Jose'),
(147, 10, 'Barangay San Roque'),
(148, 10, 'Barangay Sangandaan'),
(149, 10, 'Barangay Tala'),
(150, 10, 'Barangay Tonsuya'),
(151, 11, 'Barangay Adlaon'),
(152, 11, 'Barangay Agsungot'),
(153, 11, 'Barangay Apas'),
(154, 11, 'Barangay Babag'),
(155, 11, 'Barangay Banilad'),
(156, 11, 'Barangay Basak Pardo'),
(157, 11, 'Barangay Capitol Site'),
(158, 11, 'Barangay Guadalupe'),
(159, 11, 'Barangay Lahug'),
(160, 11, 'Barangay Mabolo'),
(161, 11, 'Barangay Sambag I'),
(162, 11, 'Barangay Sambag II'),
(163, 11, 'Barangay Talamban'),
(164, 11, 'Barangay Tisa'),
(165, 11, 'Barangay T. Padilla'),
(166, 12, 'Barangay Alang-Alang'),
(167, 12, 'Barangay Bakilid'),
(168, 12, 'Barangay Bankal'),
(169, 12, 'Barangay Basak'),
(170, 12, 'Barangay Cabancalan'),
(171, 12, 'Barangay Cambaro'),
(172, 12, 'Barangay Canduman'),
(173, 12, 'Barangay Casili'),
(174, 12, 'Barangay Casuntingan'),
(175, 12, 'Barangay Centro'),
(176, 12, 'Barangay Cubacub'),
(177, 12, 'Barangay Guizo'),
(178, 12, 'Barangay Ibabao-Estancia'),
(179, 12, 'Barangay Jagobiao'),
(180, 12, 'Barangay Labogon'),
(181, 13, 'Barangay Agus'),
(182, 13, 'Barangay Babag'),
(183, 13, 'Barangay Bankal'),
(184, 13, 'Barangay Basak'),
(185, 13, 'Barangay Buaya'),
(186, 13, 'Barangay Calawisan'),
(187, 13, 'Barangay Canjulao'),
(188, 13, 'Barangay Caubian'),
(189, 13, 'Barangay Caw-oy'),
(190, 13, 'Barangay Looc'),
(191, 13, 'Barangay Mactan'),
(192, 13, 'Barangay Maribago'),
(193, 13, 'Barangay Marigondon'),
(194, 13, 'Barangay Pajo'),
(195, 13, 'Barangay Poblacion'),
(196, 21, 'Barangay Agdao'),
(197, 21, 'Barangay Bajada'),
(198, 21, 'Barangay Bankerohan'),
(199, 21, 'Barangay Buhangin'),
(200, 21, 'Barangay Bunawan'),
(201, 21, 'Barangay Calinan'),
(202, 21, 'Barangay Lanang'),
(203, 21, 'Barangay Ma-a'),
(204, 21, 'Barangay Matina Aplaya'),
(205, 21, 'Barangay Mintal'),
(206, 21, 'Barangay Paquibato'),
(207, 21, 'Barangay Puan'),
(208, 21, 'Barangay Sasa'),
(209, 21, 'Barangay Talomo'),
(210, 21, 'Barangay Toril'),
(211, 22, 'Barangay Aplaya'),
(212, 22, 'Barangay Balabag'),
(213, 22, 'Barangay Binaton'),
(214, 22, 'Barangay Cogon'),
(215, 22, 'Barangay Colorado'),
(216, 22, 'Barangay Dawis'),
(217, 22, 'Barangay Dulangan'),
(218, 22, 'Barangay Goma'),
(219, 22, 'Barangay Igpit'),
(220, 22, 'Barangay Kapatagan'),
(221, 22, 'Barangay Kiagot'),
(222, 22, 'Barangay Lungag'),
(223, 22, 'Barangay Mahayahay'),
(224, 22, 'Barangay Matti'),
(225, 22, 'Barangay Ruparan'),
(226, 31, 'Barangay Aplaya'),
(227, 31, 'Barangay Balibago'),
(228, 31, 'Barangay Caingin'),
(229, 31, 'Barangay Dila'),
(230, 31, 'Barangay Dita'),
(231, 31, 'Barangay Don Jose'),
(232, 31, 'Barangay Ibaba'),
(233, 31, 'Barangay Kanluran'),
(234, 31, 'Barangay Labas'),
(235, 31, 'Barangay Macabling'),
(236, 31, 'Barangay Malitlit'),
(237, 31, 'Barangay Malusak'),
(238, 31, 'Barangay Market Area'),
(239, 31, 'Barangay Polong'),
(240, 31, 'Barangay Tagapo'),
(241, 33, 'Barangay Bagong Kalsada'),
(242, 33, 'Barangay Batino'),
(243, 33, 'Barangay Burol Main'),
(244, 33, 'Barangay Halang'),
(245, 33, 'Barangay Laguerta'),
(246, 33, 'Barangay Lalakay'),
(247, 33, 'Barangay Maahas'),
(248, 33, 'Barangay Makiling'),
(249, 33, 'Barangay Mapagmahal'),
(250, 33, 'Barangay Parian'),
(251, 33, 'Barangay Palo-Alto'),
(252, 33, 'Barangay Real'),
(253, 33, 'Barangay San Cristobal'),
(254, 33, 'Barangay Turbina'),
(255, 33, 'Barangay Uno'),
(256, 41, 'Barangay Anilao'),
(257, 41, 'Barangay Atlag'),
(258, 41, 'Barangay Babatnin'),
(259, 41, 'Barangay Bagna'),
(260, 41, 'Barangay Bagong Bayan'),
(261, 41, 'Barangay Balayong'),
(262, 41, 'Barangay Balite'),
(263, 41, 'Barangay Bangkal'),
(264, 41, 'Barangay Bulihan'),
(265, 41, 'Barangay Caingin'),
(266, 41, 'Barangay Calero'),
(267, 41, 'Barangay Caliligawan'),
(268, 41, 'Barangay Catmon'),
(269, 41, 'Barangay Cofradia'),
(270, 41, 'Barangay Dakila'),
(271, 42, 'Barangay Bagbaguin'),
(272, 42, 'Barangay Bahay Pare'),
(273, 42, 'Barangay Bangkal'),
(274, 42, 'Barangay Banga'),
(275, 42, 'Barangay Bayugo'),
(276, 42, 'Barangay Caingin'),
(277, 42, 'Barangay Calvario'),
(278, 42, 'Barangay Camalig'),
(279, 42, 'Barangay Gasak'),
(280, 42, 'Barangay Hulo'),
(281, 42, 'Barangay Iba'),
(282, 42, 'Barangay Langka'),
(283, 42, 'Barangay Lawa'),
(284, 42, 'Barangay Libtong'),
(285, 42, 'Barangay Loma de Gato'),
(286, 51, 'Barangay Anunas'),
(287, 51, 'Barangay Balibago'),
(288, 51, 'Barangay Capaya'),
(289, 51, 'Barangay Claro M. Recto'),
(290, 51, 'Barangay Cuayan'),
(291, 51, 'Barangay Cutcut'),
(292, 51, 'Barangay Lourdes Northwest'),
(293, 51, 'Barangay Lourdes Sur'),
(294, 51, 'Barangay Mining'),
(295, 51, 'Barangay Ninoy Aquino'),
(296, 51, 'Barangay Pampang'),
(297, 51, 'Barangay Pandan'),
(298, 51, 'Barangay Pulung Cacutud'),
(299, 51, 'Barangay Salapungan'),
(300, 51, 'Barangay Tabun'),
(301, 52, 'Barangay Alasas'),
(302, 52, 'Barangay Baliti'),
(303, 52, 'Barangay Bulaon'),
(304, 52, 'Barangay Calulut'),
(305, 52, 'Barangay Del Carmen'),
(306, 52, 'Barangay Del Pilar'),
(307, 52, 'Barangay Del Rosario'),
(308, 52, 'Barangay Dela Paz Norte'),
(309, 52, 'Barangay Dela Paz Sur'),
(310, 52, 'Barangay Dolores'),
(311, 52, 'Barangay Juliana'),
(312, 52, 'Barangay Lara'),
(313, 52, 'Barangay Maimpis'),
(314, 52, 'Barangay Malino'),
(315, 52, 'Barangay Maliwalu'),
(316, 61, 'Barangay Alangilan'),
(317, 61, 'Barangay Balagtas'),
(318, 61, 'Barangay Bolbok'),
(319, 61, 'Barangay Cuta'),
(320, 61, 'Barangay Dalig'),
(321, 61, 'Barangay Dela Paz Proper'),
(322, 61, 'Barangay Gulod Labac'),
(323, 61, 'Barangay Gulod Itaas'),
(324, 61, 'Barangay Kumintang Ibaba'),
(325, 61, 'Barangay Libjo'),
(326, 61, 'Barangay Malitam'),
(327, 61, 'Barangay Pagkilatan'),
(328, 61, 'Barangay San Isidro'),
(329, 61, 'Barangay Talumpok Kanluran'),
(330, 61, 'Barangay Wawa'),
(331, 62, 'Barangay Anilao'),
(332, 62, 'Barangay Antipolo del Norte'),
(333, 62, 'Barangay Antipolo del Sur'),
(334, 62, 'Barangay Bagong Pook'),
(335, 62, 'Barangay Balintawak'),
(336, 62, 'Barangay Banaybanay'),
(337, 62, 'Barangay Bañadero'),
(338, 62, 'Barangay Bulacnin'),
(339, 62, 'Barangay Bulaklak'),
(340, 62, 'Barangay Cumba'),
(341, 62, 'Barangay Dagatan'),
(342, 62, 'Barangay Duhatan'),
(343, 62, 'Barangay Halang'),
(344, 62, 'Barangay Inosloban'),
(345, 62, 'Barangay Kayumanggi'),
(346, 71, 'Barangay Bagong Nayon'),
(347, 71, 'Barangay Beverly Hills'),
(348, 71, 'Barangay Calawis'),
(349, 71, 'Barangay Cupang'),
(350, 71, 'Barangay Dalig'),
(351, 71, 'Barangay Dela Paz'),
(352, 71, 'Barangay Inarawan'),
(353, 71, 'Barangay Mambugan'),
(354, 71, 'Barangay Mayamot'),
(355, 71, 'Barangay Munting Dilaw'),
(356, 71, 'Barangay San Isidro'),
(357, 71, 'Barangay San Jose'),
(358, 71, 'Barangay San Juan'),
(359, 71, 'Barangay San Luis'),
(360, 71, 'Barangay San Roque'),
(361, 72, 'Barangay Sto. Domingo'),
(362, 72, 'Barangay San Andres'),
(363, 72, 'Barangay San Juan'),
(364, 72, 'Barangay San Roque'),
(365, 72, 'Barangay Santa Rosa'),
(366, 72, 'Barangay Tubigan'),
(367, 72, 'Barangay Batingan'),
(368, 72, 'Barangay Karangalan Village'),
(369, 72, 'Barangay Dela Paz'),
(370, 72, 'Barangay Rizal'),
(371, 81, 'Barangay Alima'),
(372, 81, 'Barangay Aniban I'),
(373, 81, 'Barangay Aniban II'),
(374, 81, 'Barangay Banalo'),
(375, 81, 'Barangay Bayanan'),
(376, 81, 'Barangay Campo Santo'),
(377, 81, 'Barangay Digman'),
(378, 81, 'Barangay Dulong Bayan'),
(379, 81, 'Barangay Habay I'),
(380, 81, 'Barangay Habay II'),
(381, 81, 'Barangay Kaingin'),
(382, 81, 'Barangay Ligas I'),
(383, 81, 'Barangay Mambog I'),
(384, 81, 'Barangay Molino I'),
(385, 81, 'Barangay Niog I'),
(386, 85, 'Barangay Alapan I-A'),
(387, 85, 'Barangay Anabu I-A'),
(388, 85, 'Barangay Bagong Nayong Pilipino'),
(389, 85, 'Barangay Bayan Luma I'),
(390, 85, 'Barangay Buenavista I'),
(391, 85, 'Barangay Carsadang Bago I'),
(392, 85, 'Barangay Estanzuela'),
(393, 85, 'Barangay Malagasang I-A'),
(394, 85, 'Barangay Medicion I-A'),
(395, 85, 'Barangay Palico I'),
(396, 85, 'Barangay Pasong Buaya I'),
(397, 85, 'Barangay Poblacion I-A'),
(398, 85, 'Barangay Tanzang Luma I'),
(399, 85, 'Barangay Toclong I-A'),
(400, 85, 'Barangay Tropang Sasahan'),
(401, 91, 'Barangay Agot'),
(402, 91, 'Barangay Aguinaldo'),
(403, 91, 'Barangay Bagumbayan Sur'),
(404, 91, 'Barangay Balabag'),
(405, 91, 'Barangay Bolilao'),
(406, 91, 'Barangay Bonifacio'),
(407, 91, 'Barangay Buntatala'),
(408, 91, 'Barangay Dungon A'),
(409, 91, 'Barangay Dungon B'),
(410, 91, 'Barangay Hipodromo'),
(411, 91, 'Barangay Lapuz Norte'),
(412, 91, 'Barangay Lapuz Sur'),
(413, 91, 'Barangay Mansaya Lapuz'),
(414, 91, 'Barangay Poblacion Molo'),
(415, 91, 'Barangay Taal'),
(416, 2, 'Alicia'),
(417, 2, 'Amihan'),
(418, 2, 'Andres Bonifacio'),
(419, 2, 'Apolonio Samson'),
(420, 2, 'Baesa'),
(421, 2, 'Bagbag'),
(422, 2, 'Bagong Lipunan ng Crame'),
(423, 2, 'Bagong Pag-asa'),
(424, 2, 'Bagong Silangan'),
(425, 2, 'Balingasa'),
(426, 2, 'Balong Bato'),
(427, 2, 'Bayanihan'),
(428, 2, 'Blue Ridge A'),
(429, 2, 'Blue Ridge B'),
(430, 2, 'Botocan'),
(431, 2, 'Bungad'),
(432, 2, 'Camp Aguinaldo'),
(433, 2, 'Capri'),
(434, 2, 'Central'),
(435, 2, 'Claro'),
(436, 2, 'Damar'),
(437, 2, 'Damayan'),
(438, 2, 'Doña Aurora'),
(439, 2, 'Doña Faustina'),
(440, 2, 'Doña Imelda'),
(441, 2, 'Doña Josefa'),
(442, 2, 'Don Manuel'),
(443, 2, 'East Kamias'),
(444, 2, 'Escopa I'),
(445, 2, 'Escopa II'),
(446, 2, 'Escopa III'),
(447, 2, 'Escopa IV'),
(448, 2, 'E. Rodriguez'),
(449, 2, 'Greater Lagro'),
(450, 2, 'Gulod'),
(451, 2, 'Horseshoe'),
(452, 2, 'Immaculate Conception'),
(453, 2, 'Kaligayahan'),
(454, 2, 'Kalusugan'),
(455, 2, 'Kamias'),
(456, 2, 'Kaunlaran'),
(457, 2, 'Krus na Ligas'),
(458, 2, 'Laging Handa'),
(459, 2, 'Libis'),
(460, 2, 'Maharlika'),
(461, 2, 'Malaya'),
(462, 2, 'Mangga'),
(463, 2, 'Mariblo'),
(464, 2, 'Marilag'),
(465, 2, 'Masambong'),
(466, 2, 'Matandang Balara'),
(467, 2, 'Milagrosa'),
(468, 2, 'Nagkaisang Nayon'),
(469, 2, 'Nayong Kanluran'),
(470, 2, 'New Era'),
(471, 2, 'North Fairview'),
(472, 2, 'Obrero'),
(473, 2, 'Old Capitol Site'),
(474, 2, 'Paligsahan'),
(475, 2, 'Paltok'),
(476, 2, 'Payatas'),
(477, 2, 'Phil-Am'),
(478, 2, 'Pinagkaisahan'),
(479, 2, 'Pinyahan'),
(480, 2, 'Project 1'),
(481, 2, 'Project 2'),
(482, 2, 'Project 3'),
(483, 2, 'Project 5'),
(484, 2, 'Project 6'),
(485, 2, 'Quirino 2-A'),
(486, 2, 'Quirino 2-B'),
(487, 2, 'Quirino 2-C'),
(488, 2, 'Quirino 3-A'),
(489, 2, 'Ramon Magsaysay'),
(490, 2, 'Roxas'),
(491, 2, 'Sacred Heart'),
(492, 2, 'Saint Ignatius'),
(493, 2, 'San Agustin'),
(494, 2, 'San Antonio'),
(495, 2, 'San Bartolome'),
(496, 2, 'San Isidro'),
(497, 2, 'San Jose'),
(498, 2, 'San Martin de Porres'),
(499, 2, 'San Roque'),
(500, 2, 'Santa Cruz'),
(501, 2, 'Santa Lucia'),
(502, 2, 'Santa Monica'),
(503, 2, 'Santa Teresita'),
(504, 2, 'Santo Cristo'),
(505, 2, 'Santo Domingo'),
(506, 2, 'Santo Niño'),
(507, 2, 'Santol'),
(508, 2, 'Sauyo'),
(509, 2, 'Sienna'),
(510, 2, 'Silangan'),
(511, 2, 'Socorro'),
(512, 2, 'South Triangle'),
(513, 2, 'Tagumpay'),
(514, 2, 'Talayan'),
(515, 2, 'Teachers Village East'),
(516, 2, 'Teachers Village West'),
(517, 2, 'UP Campus'),
(518, 2, 'UP Village'),
(519, 2, 'Valencia'),
(520, 2, 'Vasra'),
(521, 2, 'Veterans Village'),
(522, 2, 'West Kamias'),
(523, 2, 'West Triangle'),
(524, 2, 'White Plains');

-- --------------------------------------------------------

--
-- Table structure for table `loan_applications`
--

CREATE TABLE `loan_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `loan_type_id` int(11) DEFAULT NULL,
  `user_email` varchar(255) NOT NULL,
  `loan_terms` varchar(50) DEFAULT NULL,
  `loan_amount` decimal(12,2) DEFAULT NULL,
  `monthly_payment` decimal(10,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `next_payment_due` date DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_applications`
--

INSERT INTO `loan_applications` (`id`, `user_id`, `loan_type_id`, `user_email`, `loan_terms`, `loan_amount`, `monthly_payment`, `due_date`, `next_payment_due`, `purpose`, `status`, `created_at`) VALUES
(1, 1, 8, 'franciscarpeso@gmail.com', '6 Months', 5000.00, 882.61, '2026-09-15', '2026-04-15', 'test', 'Active', '2026-03-15 04:32:22'),
(2, 3, 8, 'kurtcarpeso02@gmail.com', '6 Months', 5000.00, 882.61, '2026-09-15', NULL, 'test also', 'Closed', '2026-03-15 07:02:48'),
(3, 3, 7, 'kurtcarpeso02@gmail.com', '24 Months', 700000.00, 35627.06, '2028-03-15', NULL, 'test again', 'Closed', '2026-03-15 07:09:26'),
(4, 1, 5, 'franciscarpeso@gmail.com', '6 Months', 5000.00, 882.61, '2026-09-15', '2026-04-15', 'dry test', 'Active', '2026-03-15 07:16:43'),
(5, 3, 1, 'kurtcarpeso02@gmail.com', '6 Months', 5000.00, 882.61, '2026-09-15', '2026-04-15', 'last dry run', 'Active', '2026-03-15 07:54:33'),
(6, 1, 5, 'franciscarpeso@gmail.com', '36 Months', 6000.00, 222.98, '2029-03-15', '2026-04-15', 'last dry run', 'Rejected', '2026-03-15 07:57:42'),
(7, 4, 8, 'joannamariecarpeso@gmail.com', '6 Months', 6000.00, 1059.14, '2026-10-07', NULL, 'Tuition', 'Closed', '2026-04-07 14:51:50'),
(8, 4, 8, 'joannamariecarpeso@gmail.com', '6 Months', 7000.00, 1235.66, '2026-10-09', '2026-05-09', 'Test', 'Rejected', '2026-04-09 03:18:39'),
(9, 1, 8, 'franciscarpeso@gmail.com', '6 Months', 5000.00, 882.61, '2026-10-09', '2026-05-09', 'test', 'Rejected', '2026-04-09 03:36:11'),
(10, 3, 1, 'kurtcarpeso02@gmail.com', '12 Months', 10000.00, 926.35, '2027-04-09', '2026-05-09', 'Test', 'Active', '2026-04-09 04:30:54'),
(11, 3, 8, 'kurtcarpeso02@gmail.com', '6 Months', 5000.00, 882.61, '2026-10-09', '2026-06-09', 'Test', 'Active', '2026-04-09 04:35:41'),
(12, 3, 8, 'kurtcarpeso02@gmail.com', '6 Months', 10000.00, 1765.23, '2026-10-09', '2026-05-09', 'Pre-test', 'Rejected', '2026-04-09 04:40:50'),
(13, 2, 8, 'carpeso0958432@gmail.com', '6 Months', 7000.00, 1235.66, '2026-10-19', NULL, 'Tuition', 'Closed', '2026-04-19 09:39:38'),
(14, 3, 1, 'kurtcarpeso02@gmail.com', '12 Months', 100000.00, 9263.45, '2027-04-20', '2026-05-20', 'Tuition', 'Rejected', '2026-04-20 01:17:49'),
(15, 2, 8, 'carpeso0958432@gmail.com', '6 Months', 7000.00, 1235.66, '2026-10-22', '2026-07-22', 'Tuition', 'Active', '2026-04-22 02:16:36'),
(16, 6, 5, 'ahambinoc92@gmail.com', '36 Months', 1000000.00, 37163.58, '2029-04-22', '2026-05-22', 'Car', 'Rejected', '2026-04-22 02:42:41'),
(17, 4, 4, 'joannamariecarpeso@gmail.com', '6 Months', 6000.00, 1059.14, '2026-10-22', '2026-05-22', 'tuition', 'Active', '2026-04-22 10:43:34'),
(18, 5, 7, 'beringuelajirocordial@gmail.com', '6 Months', 57000.00, 10061.80, '2026-10-22', '2026-05-22', 'Buy washing machine', 'Active', '2026-04-22 12:29:56');

-- --------------------------------------------------------

--
-- Table structure for table `loan_approvals`
--

CREATE TABLE `loan_approvals` (
  `id` int(11) NOT NULL,
  `loan_application_id` int(11) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_by_user_id` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_approvals`
--

INSERT INTO `loan_approvals` (`id`, `loan_application_id`, `approved_by`, `approved_by_user_id`, `approved_at`) VALUES
(1, 1, 'Kurt Francis Carpeso', 2, '2026-03-15 14:53:43'),
(2, 1, 'Kurt Francis Carpeso', 2, '2026-03-15 14:54:05'),
(3, 1, 'Kurt Francis Carpeso', 2, '2026-03-15 14:55:28'),
(5, 2, 'Kurt Francis Carpeso', 2, '2026-03-15 15:04:29'),
(7, 3, 'Kurt Francis Carpeso', 2, '2026-03-15 15:10:09'),
(9, 4, 'Kurt Francis Carpeso', 2, '2026-03-15 15:17:23'),
(11, 5, 'Kurt Francis Carpeso', 2, '2026-03-15 15:55:29'),
(13, 7, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-09 11:15:15'),
(15, 10, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-09 12:33:47'),
(17, 11, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-09 12:39:23'),
(19, 13, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-19 17:47:56'),
(21, 15, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-22 10:19:53'),
(23, 17, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-22 18:51:21'),
(25, 18, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-22 20:35:55');

-- --------------------------------------------------------

--
-- Table structure for table `loan_borrowers`
--

CREATE TABLE `loan_borrowers` (
  `id` int(11) NOT NULL,
  `loan_application_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `job` varchar(255) DEFAULT NULL,
  `monthly_salary` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_borrowers`
--

INSERT INTO `loan_borrowers` (`id`, `loan_application_id`, `full_name`, `account_number`, `contact_number`, `email`, `job`, `monthly_salary`) VALUES
(1, 1, 'Kurt Francis Carpeso', '12388902312', '09959228310', 'franciscarpeso@gmail.com', NULL, NULL),
(2, 2, 'Kurt', '1234455221', '09603281984', 'kurtcarpeso02@gmail.com', NULL, NULL),
(3, 3, 'Kurt', '1234455221', '09603281984', 'kurtcarpeso02@gmail.com', NULL, NULL),
(4, 4, 'Kurt Francis Carpeso', '12388902312', '09959228310', 'franciscarpeso@gmail.com', NULL, NULL),
(5, 5, 'Kurt', '1234455221', '09603281984', 'kurtcarpeso02@gmail.com', NULL, NULL),
(6, 6, 'Kurt Francis Carpeso', '12388902312', '09959228310', 'franciscarpeso@gmail.com', NULL, NULL),
(7, 7, 'JOANNA MARIE OCENAR CARPESO', 'EG847478389', '09983894613', 'joannamariecarpeso@gmail.com', NULL, NULL),
(8, 8, 'JOANNA MARIE OCENAR CARPESO', 'EG847478389', '09983894613', 'joannamariecarpeso@gmail.com', NULL, NULL),
(9, 9, 'Kurt Francis Carpeso', '12388902312', '09959228310', 'franciscarpeso@gmail.com', NULL, NULL),
(10, 10, 'Kurt', '1234455221', '09603281984', 'kurtcarpeso02@gmail.com', NULL, NULL),
(11, 11, 'Kurt', '1234455221', '09603281984', 'kurtcarpeso02@gmail.com', NULL, NULL),
(12, 12, 'Kurt', '1234455221', '09603281984', 'kurtcarpeso02@gmail.com', NULL, NULL),
(13, 13, 'Kurt Francis Carpeso', '10000', '09603281984', 'carpeso0958432@gmail.com', NULL, NULL),
(14, 14, 'Kurt', '1234455221', '09603281984', 'kurtcarpeso02@gmail.com', NULL, NULL),
(15, 15, 'Kurt Francis Carpeso', '10000', '09603281984', 'carpeso0958432@gmail.com', NULL, NULL),
(16, 16, 'Ambinoc m Ambinocbdl', 'EG220951969', '09276436759', 'ahambinoc92@gmail.com', NULL, NULL),
(17, 17, 'JOANNA MARIE OCENAR CARPESO', 'EG847478389', '09983894613', 'joannamariecarpeso@gmail.com', NULL, NULL),
(18, 18, 'Jiro Flojo Beringuela', 'EG905992297', '09603281984', 'beringuelajirocordial@gmail.com', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_documents`
--

CREATE TABLE `loan_documents` (
  `id` int(11) NOT NULL,
  `loan_application_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `proof_of_income` varchar(255) DEFAULT NULL,
  `coe_document` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_approved` varchar(255) DEFAULT NULL,
  `pdf_active` varchar(255) DEFAULT NULL,
  `pdf_rejected` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_documents`
--

INSERT INTO `loan_documents` (`id`, `loan_application_id`, `file_name`, `proof_of_income`, `coe_document`, `pdf_path`, `pdf_approved`, `pdf_active`, `pdf_rejected`) VALUES
(1, 1, 'uploads/valid_id_1773549142_69b6365665ce0.png', 'uploads/proof_income_1773549142_69b636566647c.png', 'uploads/coe_1773549142_69b6365666986.docx', NULL, NULL, NULL, NULL),
(2, 2, 'uploads/valid_id_1773558168_69b659981aa87.png', 'uploads/proof_income_1773558168_69b659981b770.png', 'uploads/coe_1773558168_69b659981be92.pdf', NULL, NULL, NULL, NULL),
(3, 3, 'uploads/valid_id_1773558566_69b65b265d6ef.png', 'uploads/proof_income_1773558566_69b65b265db0c.png', 'uploads/coe_1773558566_69b65b265e342.docx', NULL, NULL, NULL, NULL),
(4, 4, 'uploads/valid_id_1773559003_69b65cdb1ddc0.png', 'uploads/proof_income_1773559003_69b65cdb2b064.png', 'uploads/coe_1773559003_69b65cdb2b5f7.docx', NULL, NULL, NULL, NULL),
(5, 5, 'uploads/valid_id_1773561273_69b665b9870fd.png', 'uploads/proof_income_1773561273_69b665b989ad8.png', 'uploads/coe_1773561273_69b665b98a524.pdf', NULL, NULL, NULL, NULL),
(6, 6, 'uploads/valid_id_1773561462_69b666767f3b9.png', 'uploads/proof_income_1773561462_69b666767fecf.png', 'uploads/coe_1773561462_69b666768055f.pdf', NULL, NULL, NULL, NULL),
(7, 7, 'uploads/valid_id_1775573510_69d51a06705b1.png', 'uploads/proof_income_1775573510_69d51a0675b20.png', 'uploads/coe_1775573510_69d51a0676fd2.docx', NULL, NULL, NULL, NULL),
(8, 8, 'uploads/valid_id_1775704719_69d71a8f70ccb.png', 'uploads/proof_income_1775704719_69d71a8f71f57.png', 'uploads/coe_1775704719_69d71a8f7251b.docx', NULL, NULL, NULL, NULL),
(9, 9, 'valid_id_1775705771_69d71eabd6762.png', 'proof_income_1775705771_69d71eabd6bd3.png', 'coe_1775705771_69d71eabd6ff8.docx', NULL, NULL, NULL, NULL),
(10, 10, 'valid_id_1775709054_69d72b7ee5afe.png', 'proof_income_1775709054_69d72b7ee65e1.png', 'coe_1775709054_69d72b7ee6d1a.docx', NULL, NULL, NULL, NULL),
(11, 11, 'valid_id_1775709341_69d72c9d9326d.png', 'proof_income_1775709341_69d72c9d937d1.png', 'coe_1775709341_69d72c9d93d33.docx', NULL, NULL, NULL, NULL),
(12, 12, 'valid_id_1775709650_69d72dd265e07.png', 'proof_income_1775709650_69d72dd266a11.png', 'coe_1775709650_69d72dd2670fe.docx', NULL, NULL, NULL, NULL),
(13, 13, 'valid_id_1776591577_69e4a2d9eb232.png', 'proof_income_1776591577_69e4a2d9f0b95.png', 'coe_1776591578_69e4a2da0630b.docx', NULL, NULL, NULL, NULL),
(14, 14, 'valid_id_1776647868_69e57ebcf1d44.png', 'proof_income_1776647868_69e57ebcf3bcb.png', 'coe_1776647869_69e57ebd017cf.docx', NULL, NULL, NULL, NULL),
(15, 15, 'valid_id_1776824195_69e82f83e81cd.png', 'proof_income_1776824195_69e82f83ebf87.png', 'coe_1776824195_69e82f83ee675.docx', NULL, NULL, NULL, NULL),
(16, 16, 'valid_id_1776825761_69e835a100c77.jpg', 'proof_income_1776825761_69e835a10cc90.docx', 'coe_1776825761_69e835a1180db.pdf', NULL, NULL, NULL, NULL),
(17, 17, 'valid_id_1776854614_69e8a6565092b.png', 'proof_income_1776854614_69e8a65653371.png', 'coe_1776854614_69e8a65653db7.docx', NULL, NULL, NULL, NULL),
(18, 18, 'valid_id_1776860996_69e8bf44a2901.png', 'proof_income_1776860996_69e8bf44acbc6.png', 'coe_1776860996_69e8bf44b330f.docx', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `id` int(11) NOT NULL,
  `loan_application_id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `borrower_name` varchar(150) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('online','cheque') NOT NULL,
  `transaction_ref` varchar(50) NOT NULL,
  `payment_date` datetime NOT NULL,
  `status` varchar(30) DEFAULT 'Completed',
  `notes` text DEFAULT NULL,
  `processed_by` varchar(150) DEFAULT NULL,
  `processed_by_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_payments`
--

INSERT INTO `loan_payments` (`id`, `loan_application_id`, `user_email`, `borrower_name`, `account_number`, `amount`, `payment_method`, `transaction_ref`, `payment_date`, `status`, `notes`, `processed_by`, `processed_by_id`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 7, 'joannamariecarpeso@gmail.com', NULL, NULL, 1059.14, 'cheque', 'TXN-270204193044', '2026-04-13 03:34:58', 'Completed', NULL, NULL, NULL, NULL, NULL, '2026-04-13 01:34:58', '2026-04-13 12:49:03.269039'),
(2, 11, 'kurtcarpeso02@gmail.com', 'Kurt', '1234455221', 882.61, 'cheque', 'TXN-E8BAD50D4257', '2026-04-13 20:53:12', 'Completed', NULL, 'Kurt', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-13 12:53:12', '2026-04-13 12:53:12.973176'),
(3, 7, 'joannamariecarpeso@gmail.com', 'JOANNA MARIE OCENAR CARPESO', 'EG847478389', 4940.85, 'online', 'TXN-2D0048ADFCCB', '2026-04-19 12:56:28', 'Completed', NULL, 'JOANNA MARIE OCENAR CARPESO', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-19 04:56:28', '2026-04-19 04:56:28.886952'),
(4, 7, 'joannamariecarpeso@gmail.com', 'JOANNA MARIE OCENAR CARPESO', 'EG847478389', 1059.14, 'cheque', 'TXN-33863EED3223', '2026-04-19 14:07:20', 'Completed', NULL, 'JOANNA MARIE OCENAR CARPESO', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-19 06:07:20', '2026-04-19 06:07:20.415671'),
(5, 13, 'carpeso0958432@gmail.com', 'Kurt Francis Carpeso', '10000', 9000.00, 'cheque', 'TXN-91502D079F3A', '2026-04-19 17:56:18', 'Completed', NULL, 'Kurt Francis Carpeso', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-19 09:56:18', '2026-04-19 09:56:18.418022'),
(6, 2, 'kurtcarpeso02@gmail.com', 'Kurt', '1234455221', 6000.00, 'online', 'TXN-5478221A0059', '2026-04-19 18:33:07', 'Completed', NULL, 'Kurt', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-19 10:33:07', '2026-04-19 10:33:07.632917'),
(7, 3, 'kurtcarpeso02@gmail.com', 'Kurt', '1234455221', 900000.00, 'cheque', 'TXN-1E9234E248D7', '2026-04-20 09:09:58', 'Completed', NULL, 'Kurt', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:09:58', '2026-04-20 01:09:58.284228'),
(8, 15, 'carpeso0958432@gmail.com', 'Kurt Francis Carpeso', '10000', 1235.66, 'cheque', 'TXN-CA47367037DC', '2026-04-22 10:22:28', 'Completed', NULL, 'Kurt Francis Carpeso', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-22 02:22:28', '2026-04-22 02:22:28.267471'),
(9, 15, 'carpeso0958432@gmail.com', 'Kurt Francis Carpeso', '10000', 1235.66, 'online', 'TXN-FD6E76FA7A6F', '2026-04-22 10:23:13', 'Completed', NULL, 'Kurt Francis Carpeso', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-22 02:23:13', '2026-04-22 02:23:13.347071');

-- --------------------------------------------------------

--
-- Table structure for table `loan_rejections`
--

CREATE TABLE `loan_rejections` (
  `id` int(11) NOT NULL,
  `loan_application_id` int(11) DEFAULT NULL,
  `rejected_by` varchar(255) DEFAULT NULL,
  `rejected_by_user_id` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_rejections`
--

INSERT INTO `loan_rejections` (`id`, `loan_application_id`, `rejected_by`, `rejected_by_user_id`, `rejected_at`, `rejection_remarks`) VALUES
(1, 6, 'Kurt Francis Carpeso', 2, '2026-03-15 15:58:02', 'NOt Valid'),
(2, 8, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-09 11:40:07', 'Test'),
(3, 9, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-09 11:40:26', 'test again'),
(4, 12, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-14 08:28:48', 'Not Valid'),
(5, 14, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-22 18:46:13', 'Not ready'),
(6, 16, 'KURT FRANCIS OCENAR CARPESO', 3, '2026-04-22 18:46:31', 'too much money');

-- --------------------------------------------------------

--
-- Table structure for table `loan_types`
--

CREATE TABLE `loan_types` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `max_amount` decimal(18,2) DEFAULT NULL,
  `max_term_months` int(11) DEFAULT NULL,
  `interest_rate` decimal(6,4) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_types`
--

INSERT INTO `loan_types` (`id`, `code`, `name`, `max_amount`, `max_term_months`, `interest_rate`, `description`, `is_active`) VALUES
(1, 'SALARY', 'Salary Loan', 50000.00, 12, 0.0500, 'Employee salary loan', 1),
(2, 'EMERGENCY', 'Emergency Loan', 25000.00, 6, 0.0800, 'Emergency financial assistance', 1),
(3, 'HOUSING', 'Housing Loan', 500000.00, 60, 0.0600, 'Housing loan assistance', 1),
(4, 'EDUCATION', 'Education Loan', 100000.00, 24, 0.0400, 'Educational assistance loan', 1),
(5, 'VEHICLE', 'Vehicle Loan', 300000.00, 36, 0.0700, 'Vehicle purchase loan', 1),
(6, 'MEDICAL', 'Medical Loan', 15000.00, 12, 0.0300, 'Medical emergency loan', 1),
(7, 'APPLIANCE', 'Appliance Loan', 20000.00, 18, 0.0500, 'Home appliance loan', 1),
(13, 'PERSONAL', 'Personal Loan', 1000000.00, 36, 0.0500, 'Personal Loan', 1),
(14, 'MULTI', 'Multi-Purpose Loan', 1000000.00, 36, 0.0600, ' flexible, typically non-collateral, short-to-medium-term', 1);

-- --------------------------------------------------------

--
-- Table structure for table `loan_valid_id`
--

CREATE TABLE `loan_valid_id` (
  `id` int(11) NOT NULL,
  `valid_id_type` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_valid_id`
--

INSERT INTO `loan_valid_id` (`id`, `valid_id_type`) VALUES
(1, 'Driver\'s License'),
(2, 'Postal Id'),
(3, 'GSIS'),
(4, 'NBI Clearance'),
(5, 'Passport'),
(6, 'National Id'),
(7, 'UMId'),
(8, 'Voter\'s ID'),
(9, 'PRC ID'),
(10, 'Postal ID'),
(11, 'PhilHealth ID'),
(12, 'Senior Citizen ID');

-- --------------------------------------------------------

--
-- Table structure for table `loan_valid_ids`
--

CREATE TABLE `loan_valid_ids` (
  `id` int(11) NOT NULL,
  `loan_application_id` int(11) DEFAULT NULL,
  `loan_valid_id_type` int(11) DEFAULT NULL,
  `valid_id_number` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_valid_ids`
--

INSERT INTO `loan_valid_ids` (`id`, `loan_application_id`, `loan_valid_id_type`, `valid_id_number`) VALUES
(1, 1, 8, '123332112'),
(2, 2, 8, '1234554343'),
(3, 3, 8, '1206516516506'),
(4, 4, 7, '12412414141'),
(5, 5, 7, '12412414141'),
(6, 6, 11, '1241241414141'),
(7, 7, 8, '10021231231'),
(8, 8, 4, '2342342002344'),
(9, 9, 6, '343423222234'),
(10, 10, 10, '23312311123790'),
(11, 11, 9, '9901231231333'),
(12, 12, 9, '31231313444341'),
(13, 13, 8, '9933088088321'),
(14, 14, 6, '1298419742198741'),
(15, 15, 6, '9994322458'),
(16, 16, 8, '9994322458'),
(17, 17, 3, '12345678901'),
(18, 18, 8, '1234-56789-AB');

-- --------------------------------------------------------

--
-- Table structure for table `municipalities`
--

CREATE TABLE `municipalities` (
  `id` int(11) NOT NULL,
  `province_id` int(11) NOT NULL,
  `municipality_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `municipalities`
--

INSERT INTO `municipalities` (`id`, `province_id`, `municipality_name`) VALUES
(1, 1, 'City of Manila'),
(2, 1, 'Quezon City'),
(3, 1, 'Makati City'),
(4, 1, 'Pasig City'),
(5, 1, 'Taguig City'),
(6, 1, 'Marikina City'),
(7, 1, 'Parañaque City'),
(8, 1, 'Las Piñas City'),
(9, 1, 'Mandaluyong City'),
(10, 1, 'Caloocan City'),
(11, 2, 'Cebu City'),
(12, 2, 'Mandaue City'),
(13, 2, 'Lapu-Lapu City'),
(14, 2, 'Talisay City'),
(15, 2, 'Naga City'),
(16, 2, 'Danao City'),
(17, 2, 'Carcar City'),
(18, 2, 'Toledo City'),
(19, 2, 'Minglanilla'),
(20, 2, 'Consolacion'),
(21, 3, 'Davao City'),
(22, 3, 'Digos City'),
(23, 3, 'Hagonoy'),
(24, 3, 'Kiblawan'),
(25, 3, 'Magsaysay'),
(26, 3, 'Malalag'),
(27, 3, 'Matanao'),
(28, 3, 'Padada'),
(29, 3, 'Santa Cruz'),
(30, 3, 'Sulop'),
(31, 4, 'Santa Rosa City'),
(32, 4, 'San Pablo City'),
(33, 4, 'Calamba City'),
(34, 4, 'Biñan City'),
(35, 4, 'Cabuyao City'),
(36, 4, 'San Pedro City'),
(37, 4, 'Los Baños'),
(38, 4, 'Bay'),
(39, 4, 'Calauan'),
(40, 4, 'Pagsanjan'),
(41, 5, 'Malolos City'),
(42, 5, 'Meycauayan City'),
(43, 5, 'San Jose del Monte City'),
(44, 5, 'Balagtas'),
(45, 5, 'Bocaue'),
(46, 5, 'Bulakan'),
(47, 5, 'Calumpit'),
(48, 5, 'Guiguinto'),
(49, 5, 'Marilao'),
(50, 5, 'Obando'),
(51, 6, 'Angeles City'),
(52, 6, 'San Fernando City'),
(53, 6, 'Mabalacat City'),
(54, 6, 'Arayat'),
(55, 6, 'Bacolor'),
(56, 6, 'Candaba'),
(57, 6, 'Floridablanca'),
(58, 6, 'Guagua'),
(59, 6, 'Lubao'),
(60, 6, 'Mexico'),
(61, 7, 'Batangas City'),
(62, 7, 'Lipa City'),
(63, 7, 'Tanauan City'),
(64, 7, 'Santo Tomas City'),
(65, 7, 'Nasugbu'),
(66, 7, 'Sto. Tomas'),
(67, 7, 'Balayan'),
(68, 7, 'Bauan'),
(69, 7, 'Lemery'),
(70, 7, 'Taysan'),
(71, 8, 'Antipolo City'),
(72, 8, 'Cainta'),
(73, 8, 'Taytay'),
(74, 8, 'Binangonan'),
(75, 8, 'Angono'),
(76, 8, 'Cardona'),
(77, 8, 'Morong'),
(78, 8, 'Baras'),
(79, 8, 'Tanay'),
(80, 8, 'Teresa'),
(81, 9, 'Bacoor City'),
(82, 9, 'Cavite City'),
(83, 9, 'Dasmariñas City'),
(84, 9, 'General Trias City'),
(85, 9, 'Imus City'),
(86, 9, 'Tagaytay City'),
(87, 9, 'Trece Martires City'),
(88, 9, 'Alfonso'),
(89, 9, 'Amadeo'),
(90, 9, 'Carmona'),
(91, 10, 'Iloilo City'),
(92, 10, 'Passi City'),
(93, 10, 'Oton'),
(94, 10, 'Pavia'),
(95, 10, 'Santa Barbara'),
(96, 10, 'Cabatuan'),
(97, 10, 'Mina'),
(98, 10, 'Leganes'),
(99, 10, 'Zarraga'),
(100, 10, 'Alimodian');

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `id` int(11) NOT NULL,
  `employee_number` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `surname` varchar(100) NOT NULL,
  `full_name` varchar(300) NOT NULL,
  `address` text NOT NULL,
  `province_id` int(11) DEFAULT NULL,
  `province_name` varchar(100) DEFAULT NULL,
  `municipality_id` int(11) DEFAULT NULL,
  `municipality_name` varchar(100) DEFAULT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `barangay_name` varchar(100) DEFAULT NULL,
  `officer_email` varchar(150) NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `birthday` date NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Loan Officer',
  `password_hash` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`id`, `employee_number`, `first_name`, `middle_name`, `surname`, `full_name`, `address`, `province_id`, `province_name`, `municipality_id`, `municipality_name`, `barangay_id`, `barangay_name`, `officer_email`, `contact_number`, `birthday`, `role`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES
(1, 'SA-0000001', 'Super', NULL, 'Admin', 'Super Admin', 'Evergreen Head Office', NULL, NULL, NULL, NULL, NULL, NULL, 'admin@evergreentrust.com', '0000-000-0000', '1990-01-01', 'Super Admin', '$2y$10$mu8MvOAqBpBQyci9kpKCM.pCdZ2j9eNR2AhNBXMBE3fSYu5LOQRn2', 'Active', '2026-04-05 11:33:33', NULL),
(2, 'LO-0000001', 'KURT FRANCIS', 'OCENAR', 'CARPESO', 'KURT FRANCIS OCENAR CARPESO', '123 ABC STREET', 1, 'Metro Manila', 2, 'Quezon City', 508, 'Sauyo', 'franciscarpeso@gmail.com', '09959228310', '2005-06-10', 'Loan Officer', '$2y$10$kqvPxntb4CKDuRqzgkIGR.7jL5pYQNguD7bjVv2g6fWR8ckcpAYEW', 'Active', '2026-04-05 23:12:16', '2026-04-12 22:33:00'),
(3, 'LO-0000002', 'KURT FRANCIS', 'OCENAR', 'CARPESO', 'KURT FRANCIS OCENAR CARPESO', '123 ABC STREET', 1, 'Metro Manila', 2, 'Quezon City', 508, 'Sauyo', 'carpeso0958432@gmail.com', '09959228310', '2005-06-10', 'Loan Officer', '$2y$10$dDSD6kRhXLar2y86DpjjcOvi3esup9nKBNQTtVQCdTxSxPB3Q1c.K', 'Active', '2026-04-05 23:23:35', '2026-04-20 09:14:28');

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `id` int(11) NOT NULL,
  `province_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`id`, `province_name`) VALUES
(7, 'Batangas'),
(5, 'Bulacan'),
(9, 'Cavite'),
(2, 'Cebu'),
(3, 'Davao del Sur'),
(10, 'Iloilo'),
(4, 'Laguna'),
(1, 'Metro Manila'),
(6, 'Pampanga'),
(8, 'Rizal');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `surname` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `province_name` varchar(100) DEFAULT NULL,
  `municipality_id` int(11) DEFAULT NULL,
  `municipality_name` varchar(100) DEFAULT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `barangay_name` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `user_email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `surname`, `address`, `province_id`, `province_name`, `municipality_id`, `municipality_name`, `barangay_id`, `barangay_name`, `birthday`, `full_name`, `user_email`, `password_hash`, `account_number`, `contact_number`, `created_at`) VALUES
(1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Kurt Francis Carpeso', 'franciscarpeso@gmail.com', '$2y$10$fflExrlI2bmm/.5yqsAyROjPvut/3nKNeUcUZ9/86EG3/ISFfXFfG', '12388902312', '09959228310', '2026-03-09 11:01:05'),
(2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Kurt Francis Carpeso', 'carpeso0958432@gmail.com', '$2y$10$FmqeUbqcSgbluyG6DBeO3uYUqxek/S7lzU7K7QxB770RnyiBOF5SO', '10000', '09603281984', '2026-03-09 11:02:35'),
(3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Kurt', 'kurtcarpeso02@gmail.com', '$2y$10$.aiTFvCoTMlkw/45f/9IQ.fwn45lzCcHE4iaTzqfFihF7Q9zsU29W', '1234455221', '09603281984', '2026-03-11 11:22:42'),
(4, 'JOANNA MARIE', 'OCENAR', 'CARPESO', '123 ABC STREET', 1, 'Metro Manila', 2, 'Quezon City', 508, 'Sauyo', '2001-08-29', 'JOANNA MARIE OCENAR CARPESO', 'joannamariecarpeso@gmail.com', '$2y$10$t0fgcUfjVgRy47UQc3UCPuSh6XI1BM7RP0prydn7FIvEK.bNyf3sW', 'EG847478389', '09983894613', '2026-04-02 11:26:55'),
(5, 'Jiro', 'Flojo', 'Beringuela', '123 ABC STREET', 1, 'Metro Manila', 2, 'Quezon City', 432, 'Camp Aguinaldo', '2001-10-10', 'Jiro Flojo Beringuela', 'beringuelajirocordial@gmail.com', '$2y$10$q6TVZRJVc1M4KLtY4BdE3upiQMC5FrsjvWnluF2uZKVI0iUB2ABdy', 'EG905992297', '09603281984', '2026-04-20 01:41:51'),
(6, 'Ambinoc', 'm', 'Ambinocbdl', 'gulod 17 blk13', 5, 'Bulacan', 42, 'Meycauayan City', 282, 'Barangay Langka', '2005-02-11', 'Ambinoc m Ambinocbdl', 'ahambinoc92@gmail.com', '$2y$10$wp4FwDLGZlIw4VucWm8wjepLakyNuFwxO9q53jy9RQq.kx9ZeGF8q', 'EG220951969', '09276436759', '2026-04-22 02:37:09'),
(7, 'JEFFREY', 'SAGAL', 'CARPESO', '123 ABC STREET', 1, 'Metro Manila', 2, 'Quezon City', 508, 'Sauyo', '1999-04-27', 'JEFFREY SAGAL CARPESO', 'jepcarpeso027@gmail.com', '$2y$10$k95zDTiMm2gejiTAB2foPemR23uNaE5c8WCDC7m1tJp4Ace3bB3om', 'EG389595307', '09988221233', '2026-04-22 08:50:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_brgy_municipality` (`municipality_id`);

--
-- Indexes for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_loan` (`user_id`);

--
-- Indexes for table `loan_approvals`
--
ALTER TABLE `loan_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_application_id` (`loan_application_id`);

--
-- Indexes for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_application_id` (`loan_application_id`);

--
-- Indexes for table `loan_documents`
--
ALTER TABLE `loan_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_application_id` (`loan_application_id`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan_app` (`loan_application_id`),
  ADD KEY `idx_email` (`user_email`),
  ADD KEY `idx_pay_date` (`payment_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_method` (`payment_method`),
  ADD KEY `idx_officer` (`processed_by_id`);

--
-- Indexes for table `loan_rejections`
--
ALTER TABLE `loan_rejections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_application_id` (`loan_application_id`);

--
-- Indexes for table `loan_types`
--
ALTER TABLE `loan_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `code` (`code`);

--
-- Indexes for table `loan_valid_id`
--
ALTER TABLE `loan_valid_id`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loan_valid_ids`
--
ALTER TABLE `loan_valid_ids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_application_id` (`loan_application_id`);

--
-- Indexes for table `municipalities`
--
ALTER TABLE `municipalities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_muni_province` (`province_id`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employee_number` (`employee_number`),
  ADD UNIQUE KEY `uq_officer_email` (`officer_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_province_id` (`province_id`),
  ADD KEY `idx_municipality` (`municipality_id`),
  ADD KEY `idx_barangay` (`barangay_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_province_name` (`province_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`user_email`),
  ADD UNIQUE KEY `user_email` (`user_email`),
  ADD KEY `fk_user_province` (`province_id`),
  ADD KEY `fk_user_municipality` (`municipality_id`),
  ADD KEY `fk_user_barangay` (`barangay_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=525;

--
-- AUTO_INCREMENT for table `loan_applications`
--
ALTER TABLE `loan_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `loan_approvals`
--
ALTER TABLE `loan_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `loan_documents`
--
ALTER TABLE `loan_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `loan_payments`
--
ALTER TABLE `loan_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `loan_rejections`
--
ALTER TABLE `loan_rejections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `loan_types`
--
ALTER TABLE `loan_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `loan_valid_id`
--
ALTER TABLE `loan_valid_id`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `loan_valid_ids`
--
ALTER TABLE `loan_valid_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `municipalities`
--
ALTER TABLE `municipalities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barangays`
--
ALTER TABLE `barangays`
  ADD CONSTRAINT `fk_barangay_municipality` FOREIGN KEY (`municipality_id`) REFERENCES `municipalities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD CONSTRAINT `fk_user_loan` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `loan_approvals`
--
ALTER TABLE `loan_approvals`
  ADD CONSTRAINT `loan_approvals_ibfk_1` FOREIGN KEY (`loan_application_id`) REFERENCES `loan_applications` (`id`);

--
-- Constraints for table `loan_borrowers`
--
ALTER TABLE `loan_borrowers`
  ADD CONSTRAINT `loan_borrowers_ibfk_1` FOREIGN KEY (`loan_application_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_documents`
--
ALTER TABLE `loan_documents`
  ADD CONSTRAINT `loan_documents_ibfk_1` FOREIGN KEY (`loan_application_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_rejections`
--
ALTER TABLE `loan_rejections`
  ADD CONSTRAINT `loan_rejections_ibfk_1` FOREIGN KEY (`loan_application_id`) REFERENCES `loan_applications` (`id`);

--
-- Constraints for table `loan_valid_ids`
--
ALTER TABLE `loan_valid_ids`
  ADD CONSTRAINT `loan_valid_ids_ibfk_1` FOREIGN KEY (`loan_application_id`) REFERENCES `loan_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `municipalities`
--
ALTER TABLE `municipalities`
  ADD CONSTRAINT `fk_municipality_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_municipality` FOREIGN KEY (`municipality_id`) REFERENCES `municipalities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
