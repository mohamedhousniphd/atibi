-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2026 at 08:26 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tp_planner`
--

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `name`, `teacher_id`, `created_at`) VALUES
(3, '2 BAC SM 1', 8, '2026-05-15 12:13:26'),
(4, '1 BAC PC BIOF', 8, '2026-05-15 17:44:11');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answers`
--

CREATE TABLE `quiz_answers` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `selected_option` enum('a','b','c','d') DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_answers`
--

INSERT INTO `quiz_answers` (`id`, `quiz_id`, `student_name`, `selected_option`, `score`, `created_at`) VALUES
(35, 95, 'Hasna ELBAHRAOUI', 'a', 1, '2026-05-15 17:41:57'),
(36, 96, 'Hasna ELBAHRAOUI', 'b', 0, '2026-05-15 17:41:57'),
(37, 97, 'Hasna ELBAHRAOUI', 'b', 0, '2026-05-15 17:41:57'),
(38, 98, 'Hasna ELBAHRAOUI', 'b', 0, '2026-05-15 17:41:57'),
(39, 99, 'Hasna ELBAHRAOUI', 'b', 0, '2026-05-15 17:41:57'),
(40, 100, 'Hasna ELBAHRAOUI', 'd', 0, '2026-05-15 17:41:57');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `total_points` int(11) NOT NULL,
  `max_points` int(11) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_question_scores`
--

CREATE TABLE `quiz_question_scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` char(1) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('contact_address', NULL),
('contact_email', 'lab-office@tpplanner.edu'),
('contact_phone', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `email`, `password`, `class_id`, `created_at`) VALUES
(5, 'Hasna ELBAHRAOUI', 'hasnaeelbahraoui@gmail.com', 'hasna123', 3, '2026-05-15 17:40:12');

-- --------------------------------------------------------

--
-- Table structure for table `student_tp_progress`
--

CREATE TABLE `student_tp_progress` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `status` enum('not_started','in_progress','done') NOT NULL DEFAULT 'not_started',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_tp_scores`
--

CREATE TABLE `student_tp_scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tp_checklists`
--

CREATE TABLE `tp_checklists` (
  `id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `phase` enum('before','during','after') NOT NULL,
  `item` varchar(255) NOT NULL,
  `is_done` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tp_checklists`
--

INSERT INTO `tp_checklists` (`id`, `tp_id`, `phase`, `item`, `is_done`) VALUES
(94, 7, 'before', 'Matériel préparé', 0),
(95, 7, 'during', 'Réaction observée', 0),
(96, 7, 'during', 'Gaz identifié', 0),
(97, 7, 'during', 'Équation équilibrée', 0),
(98, 7, 'after', 'Conclusion rédigée', 0),
(104, 8, 'before', 'Matériel préparé', 0),
(105, 8, 'during', 'Réaction observée', 0),
(106, 8, 'during', 'Gaz identifié', 0),
(107, 8, 'after', 'Équation chimique écrite', 0),
(108, 8, 'after', 'Conclusion rédigée', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tp_materials`
--

CREATE TABLE `tp_materials` (
  `id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('reagent','equipment') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tp_materials`
--

INSERT INTO `tp_materials` (`id`, `tp_id`, `name`, `type`) VALUES
(134, 7, 'Eau distillée', 'reagent'),
(135, 7, 'Solution d’acide chlorhydrique (HCl)', 'reagent'),
(136, 7, 'Zinc(Zn)', 'reagent'),
(137, 7, 'Allumette', 'equipment'),
(138, 7, 'Bécher', 'equipment'),
(139, 7, 'Support', 'equipment'),
(140, 7, 'Tube à essai', 'equipment'),
(148, 8, 'Acide chlorhydrique (HCl)', 'reagent'),
(149, 8, 'Carbonate de calcium (CaCO₃)', 'reagent'),
(150, 8, 'Allumette', 'equipment'),
(151, 8, 'Bécher', 'equipment'),
(152, 8, 'Pipette', 'equipment'),
(153, 8, 'Support de tube', 'equipment'),
(154, 8, 'Tube à essai', 'equipment');

-- --------------------------------------------------------

--
-- Table structure for table `tp_quizzes`
--

CREATE TABLE `tp_quizzes` (
  `id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `option_a` varchar(255) DEFAULT NULL,
  `option_b` varchar(255) DEFAULT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_option` enum('a','b','c','d') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tp_quizzes`
--

INSERT INTO `tp_quizzes` (`id`, `tp_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`) VALUES
(95, 7, 'Quel est le gaz produit lors de la réaction entre le zinc et l’acide chlorhydrique ?', 'Dioxygène O 2 	​', 'Dihydrogène H 2', 'Dioxyde de carbone CO2 	​', 'Chlore Cl2 	​', 'a'),
(96, 7, 'Quelle est l’équation chimique correcte de la réaction ?', 'Zn+HCl→ZnHCl', 'Zn+HCl→ZnCl+H', 'Zn+2HCl→ZnCl2​+H2', 'ZnCl2​+H2​→Zn+2HCl', 'a'),
(97, 7, 'Quel phénomène observe-t-on pendant la réaction ?', 'Formation d’un précipité bleu', 'Apparition d’une effervescence', 'Disparition totale du liquide', 'Formation de fumée noire', 'a'),
(98, 7, 'Quel est le rôle du zinc dans cette réaction ?', 'Oxydant', 'Réducteur', 'Catalyseur', 'Solvant', 'a'),
(99, 7, 'Quelle demi-équation correspond à l’oxydation du zinc ?', 'Zn2+ + 2e− →Zn', '2H+ +2e− →H2​', 'Zn→Zn2+ + 2e−', 'H2​→ 2H+ +2e−', 'a'),
(100, 7, 'Pourquoi approche-t-on une allumette enflammée du tube à essai ?', 'Pour chauffer la solution', 'Pour mesurer le pH', 'Pour identifier le dihydrogène produit', 'Pour accélérer la réaction', 'a'),
(106, 8, 'Quel gaz est produit lors de la réaction entre le carbonate de calcium et l’acide chlorhydrique ?', 'Dioxygène O2 	​', 'Dihydrogène H2 	​', 'Dioxyde de carbone CO2', 'Azote N2 	​', 'a'),
(107, 8, 'Quel est le signe principal de cette réaction chimique ?', 'Formation d’un précipité', 'Effervescence (bulles de gaz)', 'Changement de couleur en bleu', 'Solidification du mélange', 'a'),
(108, 8, 'Quelle est l’équation correcte de la réaction ?', 'CaCO3 +HCl→CaCl2 +CO2 	​', 'CaCO3+2HCl→CaCl2 +H2O+CO2 	​', '𝐶𝑎𝐶a3 → 𝐶𝑎 + 𝐶𝑂3CaCO3​→Ca+CO3', 'Ca+HCl→CaCl+H2​', 'a'),
(109, 8, 'Quel test permet d’identifier le CO₂ ?', 'Test à la flamme', 'Eau de chaux qui devient trouble', 'Papier pH', 'Aimant', 'a'),
(110, 8, 'Ce type de réaction est appelé :', 'Réaction de combustion', 'Réaction de neutralisation simple', 'Réaction acide–carbonate avec dégagement gazeux', 'Réaction de fusion', 'a');

-- --------------------------------------------------------

--
-- Table structure for table `tp_sessions`
--

CREATE TABLE `tp_sessions` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `class_id` int(11) NOT NULL,
  `objectives` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `duration` int(11) DEFAULT 60,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fiche_number` varchar(50) DEFAULT NULL COMMENT 'Ex: Fiche technique 4',
  `unit` varchar(100) DEFAULT NULL COMMENT 'Ex: Chimie, Physique…',
  `results` text DEFAULT NULL COMMENT 'Bilan des réactions (HTML riche)',
  `safety` text DEFAULT NULL COMMENT 'Consignes de sécurité (HTML riche)',
  `schema_image` varchar(500) DEFAULT NULL COMMENT 'Chemin vers le schéma expérimental uploadé',
  `imported_file` varchar(500) DEFAULT NULL,
  `imported_content` mediumtext DEFAULT NULL,
  `imported_type` enum('none','word','pdf') NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tp_sessions`
--

INSERT INTO `tp_sessions` (`id`, `title`, `class_id`, `objectives`, `skills`, `duration`, `created_at`, `fiche_number`, `unit`, `results`, `safety`, `schema_image`, `imported_file`, `imported_content`, `imported_type`) VALUES
(7, 'Étude de la réaction entre le zinc et l’acide chlorhydrique', 3, '<ol><li data-list=\"bullet\">Mettre en évidence une réaction chimique entre un métal et un acide et identifier le gaz produit.</li></ol>', '<ol><li data-list=\"bullet\">Écrire une équation chimique équilibrée et interpréter les observations expérimentales.</li><li data-list=\"bullet\">Observation expérimentale</li><li data-list=\"bullet\">Utilisation du matériel de laboratoire</li></ol>', 120, '2026-05-15 16:07:40', 'TP-CH-004', 'Réactions chimiques', NULL, '<ol><li data-list=\"bullet\">Porter une blouse, des gants et des lunettes de protection</li><li data-list=\"bullet\">Manipuler l’acide chlorhydrique avec précaution</li><li data-list=\"bullet\">Éviter l’inhalation du gaz produit pendant la réaction</li><li data-list=\"bullet\">Ne jamais approcher une flamme sans autorisation</li><li data-list=\"bullet\">Nettoyer immédiatement toute projection chimique</li></ol>', 'uploads/tp_schemas/schema_new_1778861260.jpg', 'uploads/tp_documents/tpdoc_1778866077_c1dee6a4.docx', '<p><strong>Étapes</strong></p>\n<p>Introduire quelques morceaux de zinc dans un tube à essai propre.</p>\n<p>Ajouter progressivement environ 10 mL d’acide chlorhydrique dans le tube.</p>\n<p>Observer attentivement les changements qui se produisent pendant la réaction.</p>\n<p>Approcher une allumette enflammée à l’ouverture du tube afin d’identifier le gaz dégagé.</p>\n<p>Noter toutes les observations expérimentales dans le tableau des résultats.</p>\n<p>Écrire l’équation chimique correspondant à la réaction observée.</p>\n<p>Vérifier l’équilibrage de l’équation chimique.</p>\n<p><strong>Résultats</strong></p>\n<p>Une effervescence apparaît immédiatement après l’ajout de l’acide.</p>\n<p>Le zinc disparaît progressivement au cours de la réaction.</p>\n<p>Un gaz inflammable est produit.</p>\n<p>Une petite détonation (“pop”) est entendue lors du test à la flamme.</p>\n<p>Le gaz identifié est le dihydrogène \\(H_{2}\\). \\(H_{2}\\)</p>\n<p><strong>Équation chimique</strong></p>\n<p>\\[Zn+2HCl→ZnCl_{2}+H_{2}\\]</p>\n<p><strong>Demi-équations</strong></p>\n<p>Oxydation :</p>\n<p>\\[Zn→Zn^{2+}+2e^{-}\\]</p>\n<p>Réduction :</p>\n<p>\\[2H^{+}+2e^{-}→H_{2}\\]</p>\n<p><strong>Conclusion</strong></p>\n<p>Le zinc réagit avec l’acide chlorhydrique pour former du chlorure de zinc et du dihydrogène. Cette transformation chimique est une réaction d’oxydoréduction.</p>\n', 'word'),
(8, 'Réaction entre le carbonate de calcium et l’acide chlorhydrique', 4, '<p><strong>Mettre en évidence une réaction entre un acide et un carbonate et identifier le gaz produit</strong></p>', '<ol><li data-list=\"bullet\">Reconnaître une réaction chimique et écrire son équation équilibrée.</li><li data-list=\"bullet\">Observation expérimentale</li><li data-list=\"bullet\">Identification des gaz</li><li data-list=\"bullet\">Écriture des équations chimiques</li><li data-list=\"bullet\">Analyse des réactions</li></ol>', 120, '2026-05-15 17:58:38', 'TP-CH-005', 'Réactions acide-base et dégagement gazeux', NULL, '<ol><li data-list=\"bullet\">Porter une blouse, des gants et des lunettes de protection</li><li data-list=\"bullet\">Manipuler l’acide chlorhydrique avec précaution</li><li data-list=\"bullet\">Éviter l’inhalation du gaz dégagé</li><li data-list=\"bullet\">Ne pas toucher les produits chimiques directement</li><li data-list=\"bullet\">Nettoyer le poste de travail après manipulation</li></ol><p><br></p>', 'uploads/tp_schemas/schema_new_1778867918.jpg', 'uploads/tp_documents/tpdoc_1778867918_1f5336e1.docx', '<p><strong>Étapes</strong></p>\n<p>Placer une petite quantité de carbonate de calcium dans un tube à essai propre.</p>\n<p>Ajouter progressivement environ 10 mL d’acide chlorhydrique.</p>\n<p>Observer attentivement les réactions chimiques qui se produisent.</p>\n<p>Recueillir le gaz dégagé si possible.</p>\n<p>Tester le gaz avec une allumette enflammée ou un test approprié.</p>\n<p>Noter toutes les observations dans un tableau.</p>\n<p>Écrire l’équation chimique de la réaction.</p>\n<p>Vérifier l’équilibrage de l’équation.</p>\n<p><strong>Résultats</strong></p>\n<p>Apparition immédiate d’une effervescence intense</p>\n<p>Dissolution progressive du carbonate de calcium</p>\n<p>Dégagement d’un gaz incolore</p>\n<p>Le gaz produit trouble l’eau de chaux</p>\n<p>Le gaz identifié est le dioxyde de carbone (CO₂)</p>\n<p><strong>Équation chimique</strong></p>\n<p>\\[CaCO_{3}+2HCl→CaCl_{2}+H_{2}O+CO_{2}\\]</p>\n<p><strong>Conclusion</strong></p>\n<p>La réaction entre le carbonate de calcium et l’acide chlorhydrique produit du chlorure de calcium, de l’eau et du dioxyde de carbone. C’est une réaction caractéristique des carbonates avec les acides.</p>\n', 'word');

-- --------------------------------------------------------

--
-- Table structure for table `tp_steps`
--

CREATE TABLE `tp_steps` (
  `id` int(11) NOT NULL,
  `tp_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(8, 'Azzeddine ATTIBI', 'admin@gmail.com', 'admin123', 'admin', '2026-05-15 12:07:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attempt_student_tp` (`student_id`,`tp_id`),
  ADD KEY `idx_attempt_tp` (`tp_id`),
  ADD KEY `idx_attempt_student` (`student_id`);

--
-- Indexes for table `quiz_question_scores`
--
ALTER TABLE `quiz_question_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_question` (`student_id`,`question_id`),
  ADD KEY `idx_qqs_tp` (`tp_id`),
  ADD KEY `idx_qqs_student` (`student_id`),
  ADD KEY `fk_qqs_question` (`question_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_students_class` (`class_id`);

--
-- Indexes for table `student_tp_progress`
--
ALTER TABLE `student_tp_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_tp` (`student_id`,`tp_id`),
  ADD KEY `idx_progress_tp` (`tp_id`);

--
-- Indexes for table `student_tp_scores`
--
ALTER TABLE `student_tp_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_score_student_tp` (`student_id`,`tp_id`),
  ADD KEY `idx_scores_tp` (`tp_id`);

--
-- Indexes for table `tp_checklists`
--
ALTER TABLE `tp_checklists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tp_id` (`tp_id`);

--
-- Indexes for table `tp_materials`
--
ALTER TABLE `tp_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tp_id` (`tp_id`);

--
-- Indexes for table `tp_quizzes`
--
ALTER TABLE `tp_quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tp_id` (`tp_id`);

--
-- Indexes for table `tp_sessions`
--
ALTER TABLE `tp_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `tp_steps`
--
ALTER TABLE `tp_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tp_id` (`tp_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `quiz_question_scores`
--
ALTER TABLE `quiz_question_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `student_tp_progress`
--
ALTER TABLE `student_tp_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `student_tp_scores`
--
ALTER TABLE `student_tp_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tp_checklists`
--
ALTER TABLE `tp_checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `tp_materials`
--
ALTER TABLE `tp_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT for table `tp_quizzes`
--
ALTER TABLE `tp_quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `tp_sessions`
--
ALTER TABLE `tp_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tp_steps`
--
ALTER TABLE `tp_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `tp_quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `fk_attempt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attempt_tp` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_question_scores`
--
ALTER TABLE `quiz_question_scores`
  ADD CONSTRAINT `fk_qqs_question` FOREIGN KEY (`question_id`) REFERENCES `tp_quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qqs_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qqs_tp` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`);

--
-- Constraints for table `student_tp_progress`
--
ALTER TABLE `student_tp_progress`
  ADD CONSTRAINT `fk_progress_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_progress_tp` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_tp_scores`
--
ALTER TABLE `student_tp_scores`
  ADD CONSTRAINT `fk_scores_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scores_tp` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tp_checklists`
--
ALTER TABLE `tp_checklists`
  ADD CONSTRAINT `tp_checklists_ibfk_1` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tp_materials`
--
ALTER TABLE `tp_materials`
  ADD CONSTRAINT `tp_materials_ibfk_1` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tp_quizzes`
--
ALTER TABLE `tp_quizzes`
  ADD CONSTRAINT `tp_quizzes_ibfk_1` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tp_sessions`
--
ALTER TABLE `tp_sessions`
  ADD CONSTRAINT `tp_sessions_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tp_steps`
--
ALTER TABLE `tp_steps`
  ADD CONSTRAINT `tp_steps_ibfk_1` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
