-- MySQL dump 10.13  Distrib 9.7.1, for macos26.4 (arm64)
--
-- Host: tokaido.proxy.rlwy.net    Database: railway
-- ------------------------------------------------------
-- Server version	9.7.2

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

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `teacher_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (3,'2 BAC SM 1',8,'2026-05-15 12:13:26'),(4,'1 BAC PC BIOF',8,'2026-05-15 17:44:11');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_answers`
--

DROP TABLE IF EXISTS `quiz_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quiz_id` int NOT NULL,
  `student_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `selected_option` enum('a','b','c','d') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `score` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`),
  CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `tp_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_answers`
--

LOCK TABLES `quiz_answers` WRITE;
/*!40000 ALTER TABLE `quiz_answers` DISABLE KEYS */;
INSERT INTO `quiz_answers` VALUES (35,95,'Hasna ELBAHRAOUI','a',1,'2026-05-15 17:41:57'),(36,96,'Hasna ELBAHRAOUI','b',0,'2026-05-15 17:41:57'),(37,97,'Hasna ELBAHRAOUI','b',0,'2026-05-15 17:41:57'),(38,98,'Hasna ELBAHRAOUI','b',0,'2026-05-15 17:41:57'),(39,99,'Hasna ELBAHRAOUI','b',0,'2026-05-15 17:41:57'),(40,100,'Hasna ELBAHRAOUI','d',0,'2026-05-15 17:41:57');
/*!40000 ALTER TABLE `quiz_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_attempts`
--

DROP TABLE IF EXISTS `quiz_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `tp_id` int NOT NULL,
  `total_points` int NOT NULL,
  `max_points` int NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attempt_student_tp` (`student_id`,`tp_id`),
  KEY `idx_attempt_tp` (`tp_id`),
  KEY `idx_attempt_student` (`student_id`),
  CONSTRAINT `fk_attempt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attempt_tp` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_attempts`
--

LOCK TABLES `quiz_attempts` WRITE;
/*!40000 ALTER TABLE `quiz_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_question_scores`
--

DROP TABLE IF EXISTS `quiz_question_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_question_scores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `tp_id` int NOT NULL,
  `question_id` int NOT NULL,
  `selected_option` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `score` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_question` (`student_id`,`question_id`),
  KEY `idx_qqs_tp` (`tp_id`),
  KEY `idx_qqs_student` (`student_id`),
  KEY `fk_qqs_question` (`question_id`),
  CONSTRAINT `fk_qqs_question` FOREIGN KEY (`question_id`) REFERENCES `tp_quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qqs_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qqs_tp` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_question_scores`
--

LOCK TABLES `quiz_question_scores` WRITE;
/*!40000 ALTER TABLE `quiz_question_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_question_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_settings` (
  `setting_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES ('contact_address',NULL),('contact_email','lab-office@tpplanner.edu'),('contact_phone',NULL);
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_tp_progress`
--

DROP TABLE IF EXISTS `student_tp_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_tp_progress` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `tp_id` int NOT NULL,
  `status` enum('not_started','in_progress','done') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'not_started',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_tp` (`student_id`,`tp_id`),
  KEY `idx_progress_tp` (`tp_id`),
  CONSTRAINT `fk_progress_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progress_tp` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_tp_progress`
--

LOCK TABLES `student_tp_progress` WRITE;
/*!40000 ALTER TABLE `student_tp_progress` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_tp_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_tp_scores`
--

DROP TABLE IF EXISTS `student_tp_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_tp_scores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `tp_id` int NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_score_student_tp` (`student_id`,`tp_id`),
  KEY `idx_scores_tp` (`tp_id`),
  CONSTRAINT `fk_scores_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scores_tp` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_tp_scores`
--

LOCK TABLES `student_tp_scores` WRITE;
/*!40000 ALTER TABLE `student_tp_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_tp_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `class_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_students_class` (`class_id`),
  CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (5,'Hasna ELBAHRAOUI','hasnaeelbahraoui@gmail.com','hasna123',3,'2026-05-15 17:40:12');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tp_checklists`
--

DROP TABLE IF EXISTS `tp_checklists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tp_checklists` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tp_id` int NOT NULL,
  `phase` enum('before','during','after') COLLATE utf8mb4_general_ci NOT NULL,
  `item` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `is_done` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `tp_id` (`tp_id`),
  CONSTRAINT `tp_checklists_ibfk_1` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tp_checklists`
--

LOCK TABLES `tp_checklists` WRITE;
/*!40000 ALTER TABLE `tp_checklists` DISABLE KEYS */;
INSERT INTO `tp_checklists` VALUES (94,7,'before','Matériel préparé',0),(95,7,'during','Réaction observée',0),(96,7,'during','Gaz identifié',0),(97,7,'during','Équation équilibrée',0),(98,7,'after','Conclusion rédigée',0),(104,8,'before','Matériel préparé',0),(105,8,'during','Réaction observée',0),(106,8,'during','Gaz identifié',0),(107,8,'after','Équation chimique écrite',0),(108,8,'after','Conclusion rédigée',0);
/*!40000 ALTER TABLE `tp_checklists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tp_materials`
--

DROP TABLE IF EXISTS `tp_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tp_materials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tp_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `type` enum('reagent','equipment') COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tp_id` (`tp_id`),
  CONSTRAINT `tp_materials_ibfk_1` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tp_materials`
--

LOCK TABLES `tp_materials` WRITE;
/*!40000 ALTER TABLE `tp_materials` DISABLE KEYS */;
INSERT INTO `tp_materials` VALUES (134,7,'Eau distillée','reagent'),(135,7,'Solution d’acide chlorhydrique (HCl)','reagent'),(136,7,'Zinc(Zn)','reagent'),(137,7,'Allumette','equipment'),(138,7,'Bécher','equipment'),(139,7,'Support','equipment'),(140,7,'Tube à essai','equipment'),(148,8,'Acide chlorhydrique (HCl)','reagent'),(149,8,'Carbonate de calcium (CaCO₃)','reagent'),(150,8,'Allumette','equipment'),(151,8,'Bécher','equipment'),(152,8,'Pipette','equipment'),(153,8,'Support de tube','equipment'),(154,8,'Tube à essai','equipment');
/*!40000 ALTER TABLE `tp_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tp_quizzes`
--

DROP TABLE IF EXISTS `tp_quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tp_quizzes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tp_id` int NOT NULL,
  `question` text COLLATE utf8mb4_general_ci NOT NULL,
  `option_a` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_b` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_c` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_d` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correct_option` enum('a','b','c','d') COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tp_id` (`tp_id`),
  CONSTRAINT `tp_quizzes_ibfk_1` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tp_quizzes`
--

LOCK TABLES `tp_quizzes` WRITE;
/*!40000 ALTER TABLE `tp_quizzes` DISABLE KEYS */;
INSERT INTO `tp_quizzes` VALUES (95,7,'Quel est le gaz produit lors de la réaction entre le zinc et l’acide chlorhydrique ?','Dioxygène O 2 	​','Dihydrogène H 2','Dioxyde de carbone CO2 	​','Chlore Cl2 	​','a'),(96,7,'Quelle est l’équation chimique correcte de la réaction ?','Zn+HCl→ZnHCl','Zn+HCl→ZnCl+H','Zn+2HCl→ZnCl2​+H2','ZnCl2​+H2​→Zn+2HCl','a'),(97,7,'Quel phénomène observe-t-on pendant la réaction ?','Formation d’un précipité bleu','Apparition d’une effervescence','Disparition totale du liquide','Formation de fumée noire','a'),(98,7,'Quel est le rôle du zinc dans cette réaction ?','Oxydant','Réducteur','Catalyseur','Solvant','a'),(99,7,'Quelle demi-équation correspond à l’oxydation du zinc ?','Zn2+ + 2e− →Zn','2H+ +2e− →H2​','Zn→Zn2+ + 2e−','H2​→ 2H+ +2e−','a'),(100,7,'Pourquoi approche-t-on une allumette enflammée du tube à essai ?','Pour chauffer la solution','Pour mesurer le pH','Pour identifier le dihydrogène produit','Pour accélérer la réaction','a'),(106,8,'Quel gaz est produit lors de la réaction entre le carbonate de calcium et l’acide chlorhydrique ?','Dioxygène O2 	​','Dihydrogène H2 	​','Dioxyde de carbone CO2','Azote N2 	​','a'),(107,8,'Quel est le signe principal de cette réaction chimique ?','Formation d’un précipité','Effervescence (bulles de gaz)','Changement de couleur en bleu','Solidification du mélange','a'),(108,8,'Quelle est l’équation correcte de la réaction ?','CaCO3 +HCl→CaCl2 +CO2 	​','CaCO3+2HCl→CaCl2 +H2O+CO2 	​','𝐶𝑎𝐶a3 → 𝐶𝑎 + 𝐶𝑂3CaCO3​→Ca+CO3','Ca+HCl→CaCl+H2​','a'),(109,8,'Quel test permet d’identifier le CO₂ ?','Test à la flamme','Eau de chaux qui devient trouble','Papier pH','Aimant','a'),(110,8,'Ce type de réaction est appelé :','Réaction de combustion','Réaction de neutralisation simple','Réaction acide–carbonate avec dégagement gazeux','Réaction de fusion','a');
/*!40000 ALTER TABLE `tp_quizzes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tp_sessions`
--

DROP TABLE IF EXISTS `tp_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tp_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `class_id` int NOT NULL,
  `objectives` text COLLATE utf8mb4_general_ci,
  `skills` text COLLATE utf8mb4_general_ci,
  `duration` int DEFAULT '60',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fiche_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Ex: Fiche technique 4',
  `unit` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Ex: Chimie, Physique…',
  `results` text COLLATE utf8mb4_general_ci COMMENT 'Bilan des réactions (HTML riche)',
  `safety` text COLLATE utf8mb4_general_ci COMMENT 'Consignes de sécurité (HTML riche)',
  `schema_image` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Chemin vers le schéma expérimental uploadé',
  `imported_file` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `imported_content` mediumtext COLLATE utf8mb4_general_ci,
  `imported_type` enum('none','word','pdf') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `tp_sessions_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tp_sessions`
--

LOCK TABLES `tp_sessions` WRITE;
/*!40000 ALTER TABLE `tp_sessions` DISABLE KEYS */;
INSERT INTO `tp_sessions` VALUES (7,'Étude de la réaction entre le zinc et l’acide chlorhydrique',3,'<ol><li data-list=\"bullet\">Mettre en évidence une réaction chimique entre un métal et un acide et identifier le gaz produit.</li></ol>','<ol><li data-list=\"bullet\">Écrire une équation chimique équilibrée et interpréter les observations expérimentales.</li><li data-list=\"bullet\">Observation expérimentale</li><li data-list=\"bullet\">Utilisation du matériel de laboratoire</li></ol>',120,'2026-05-15 16:07:40','TP-CH-004','Réactions chimiques',NULL,'<ol><li data-list=\"bullet\">Porter une blouse, des gants et des lunettes de protection</li><li data-list=\"bullet\">Manipuler l’acide chlorhydrique avec précaution</li><li data-list=\"bullet\">Éviter l’inhalation du gaz produit pendant la réaction</li><li data-list=\"bullet\">Ne jamais approcher une flamme sans autorisation</li><li data-list=\"bullet\">Nettoyer immédiatement toute projection chimique</li></ol>','uploads/tp_schemas/schema_new_1778861260.jpg','uploads/tp_documents/tpdoc_1778866077_c1dee6a4.docx','<p><strong>Étapes</strong></p>\n<p>Introduire quelques morceaux de zinc dans un tube à essai propre.</p>\n<p>Ajouter progressivement environ 10 mL d’acide chlorhydrique dans le tube.</p>\n<p>Observer attentivement les changements qui se produisent pendant la réaction.</p>\n<p>Approcher une allumette enflammée à l’ouverture du tube afin d’identifier le gaz dégagé.</p>\n<p>Noter toutes les observations expérimentales dans le tableau des résultats.</p>\n<p>Écrire l’équation chimique correspondant à la réaction observée.</p>\n<p>Vérifier l’équilibrage de l’équation chimique.</p>\n<p><strong>Résultats</strong></p>\n<p>Une effervescence apparaît immédiatement après l’ajout de l’acide.</p>\n<p>Le zinc disparaît progressivement au cours de la réaction.</p>\n<p>Un gaz inflammable est produit.</p>\n<p>Une petite détonation (“pop”) est entendue lors du test à la flamme.</p>\n<p>Le gaz identifié est le dihydrogène \\(H_{2}\\). \\(H_{2}\\)</p>\n<p><strong>Équation chimique</strong></p>\n<p>\\[Zn+2HCl→ZnCl_{2}+H_{2}\\]</p>\n<p><strong>Demi-équations</strong></p>\n<p>Oxydation :</p>\n<p>\\[Zn→Zn^{2+}+2e^{-}\\]</p>\n<p>Réduction :</p>\n<p>\\[2H^{+}+2e^{-}→H_{2}\\]</p>\n<p><strong>Conclusion</strong></p>\n<p>Le zinc réagit avec l’acide chlorhydrique pour former du chlorure de zinc et du dihydrogène. Cette transformation chimique est une réaction d’oxydoréduction.</p>\n','word'),(8,'Réaction entre le carbonate de calcium et l’acide chlorhydrique',4,'<p><strong>Mettre en évidence une réaction entre un acide et un carbonate et identifier le gaz produit</strong></p>','<ol><li data-list=\"bullet\">Reconnaître une réaction chimique et écrire son équation équilibrée.</li><li data-list=\"bullet\">Observation expérimentale</li><li data-list=\"bullet\">Identification des gaz</li><li data-list=\"bullet\">Écriture des équations chimiques</li><li data-list=\"bullet\">Analyse des réactions</li></ol>',120,'2026-05-15 17:58:38','TP-CH-005','Réactions acide-base et dégagement gazeux',NULL,'<ol><li data-list=\"bullet\">Porter une blouse, des gants et des lunettes de protection</li><li data-list=\"bullet\">Manipuler l’acide chlorhydrique avec précaution</li><li data-list=\"bullet\">Éviter l’inhalation du gaz dégagé</li><li data-list=\"bullet\">Ne pas toucher les produits chimiques directement</li><li data-list=\"bullet\">Nettoyer le poste de travail après manipulation</li></ol><p><br></p>','uploads/tp_schemas/schema_new_1778867918.jpg','uploads/tp_documents/tpdoc_1778867918_1f5336e1.docx','<p><strong>Étapes</strong></p>\n<p>Placer une petite quantité de carbonate de calcium dans un tube à essai propre.</p>\n<p>Ajouter progressivement environ 10 mL d’acide chlorhydrique.</p>\n<p>Observer attentivement les réactions chimiques qui se produisent.</p>\n<p>Recueillir le gaz dégagé si possible.</p>\n<p>Tester le gaz avec une allumette enflammée ou un test approprié.</p>\n<p>Noter toutes les observations dans un tableau.</p>\n<p>Écrire l’équation chimique de la réaction.</p>\n<p>Vérifier l’équilibrage de l’équation.</p>\n<p><strong>Résultats</strong></p>\n<p>Apparition immédiate d’une effervescence intense</p>\n<p>Dissolution progressive du carbonate de calcium</p>\n<p>Dégagement d’un gaz incolore</p>\n<p>Le gaz produit trouble l’eau de chaux</p>\n<p>Le gaz identifié est le dioxyde de carbone (CO₂)</p>\n<p><strong>Équation chimique</strong></p>\n<p>\\[CaCO_{3}+2HCl→CaCl_{2}+H_{2}O+CO_{2}\\]</p>\n<p><strong>Conclusion</strong></p>\n<p>La réaction entre le carbonate de calcium et l’acide chlorhydrique produit du chlorure de calcium, de l’eau et du dioxyde de carbone. C’est une réaction caractéristique des carbonates avec les acides.</p>\n','word');
/*!40000 ALTER TABLE `tp_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tp_steps`
--

DROP TABLE IF EXISTS `tp_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tp_steps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tp_id` int NOT NULL,
  `step_number` int NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tp_id` (`tp_id`),
  CONSTRAINT `tp_steps_ibfk_1` FOREIGN KEY (`tp_id`) REFERENCES `tp_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tp_steps`
--

LOCK TABLES `tp_steps` WRITE;
/*!40000 ALTER TABLE `tp_steps` DISABLE KEYS */;
/*!40000 ALTER TABLE `tp_steps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (8,'Azzeddine ATTIBI','admin@gmail.com','admin123','admin','2026-05-15 12:07:34');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'railway'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-25 15:07:01
